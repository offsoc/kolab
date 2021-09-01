'use strict'

import { Device, parseScalabilityMode } from 'mediasoup-client'
import Config from './config.js'
import { Media } from './media.js'
import { Roles } from './constants.js'
import { Socket } from './socket.js'


function Client()
{
    let eventHandlers = {}
    let camProducer
    let micProducer
    let screenProducer
    let consumers = {}
    let socket
    let sendTransport
    let recvTransport
    let turnServers = []
    let nickname = ''
    let peers = {}
    let videoSource
    let audioSource

    const VIDEO_CONSTRAINTS = {
        'low': {
            width: { ideal: 320 }
        },
        'medium': {
            width: { ideal: 640 }
        },
        'high': {
            width: { ideal: 1280 }
        },
        'veryhigh': {
            width: { ideal: 1920 }
        },
        'ultra': {
            width: { ideal: 3840 }
        }
    }

    // Create a device (use browser auto-detection)
    const device = new Device()

    // A helper for basic browser media operations
    const media = new Media()

    this.media = media

    navigator.mediaDevices.addEventListener('devicechange', () => {
        trigger('deviceChange')
    })


    /**
     * Start a session (join a room)
     */
    this.joinSession = (token, props) => {
        // Initialize the socket, 'roomReady' request handler will do the rest of the job
        socket = initSocket(token)

        nickname = props.nickname
        videoSource = props.videoSource
        audioSource = props.audioSource
    }

    /**
     * Close the session (disconnect)
     */
    this.closeSession = async (reason) => {
        // If room owner, send the request to close the room
        if (peers.self && peers.self.role & Roles.OWNER) {
            await socket.sendRequest('moderator:closeRoom')
        }

        trigger('closeSession', { reason: reason || 'disconnected' })

        if (socket) {
            socket.close()
        }

        media.setupStop()

        // Close mediasoup Transports.
        if (sendTransport) {
            sendTransport.close()
            sendTransport = null
        }

        if (recvTransport) {
            recvTransport.close()
            recvTransport = null
        }

        // Remove peers' video elements
        Object.keys(peers).forEach(id => {
            let peer = peers[id]
            if (peer.videoElement) {
                $(peer.videoElement).remove()
                peer.videoElement = null
                peer.tracks = null
            }
        })

        // Reset state
        eventHandlers = {}
        camProducer = null
        micProducer = null
        screenProducer = null
        consumers = {}
        peers = {}
    }

    this.camMute = async () => {
        if (camProducer) {
            camProducer.pause()
            await socket.sendRequest('pauseProducer', { producerId: camProducer.id })
            trigger('updatePeer', updatePeerState(peers.self))
        }

        return this.camStatus()
    }

    this.camUnmute = async () => {
        if (camProducer) {
            camProducer.resume()
            await socket.sendRequest('resumeProducer', { producerId: camProducer.id })
            trigger('updatePeer', updatePeerState(peers.self))
        }

        return this.camStatus()
    }

    this.camStatus = () => {
        return camProducer && !camProducer.paused && !camProducer.closed
    }

    this.micMute = async () => {
        if (micProducer) {
            micProducer.pause()
            await socket.sendRequest('pauseProducer', { producerId: micProducer.id })
            trigger('updatePeer', updatePeerState(peers.self))
        }

        return this.micStatus()
    }

    this.micUnmute = async () => {
        if (micProducer) {
            micProducer.resume()
            await socket.sendRequest('resumeProducer', { producerId: micProducer.id })
            trigger('updatePeer', updatePeerState(peers.self))
        }

        return this.micStatus()
    }

    this.micStatus = () => {
        return micProducer && !micProducer.paused && !micProducer.closed
    }

    this.kickPeer = (peerId) => {
        socket.sendRequest('moderator:kickPeer', { peerId })
    }

    this.chatMessage = (message) => {
        socket.sendRequest('chatMessage', { message })
    }

    this.raiseHand = async (status) => {
        if (peers.self.raisedHand != status) {
            peers.self.raisedHand = status
            await socket.sendRequest('raisedHand', { raisedHand: status })
            trigger('updatePeer', peers.self, ['raisedHand'])
        }

        return status
    }

    this.setNickname = (nickname) => {
        if (peers.self.nickname != nickname) {
            peers.self.nickname = nickname
            socket.sendRequest('changeNickname', { nickname })
            trigger('updatePeer', peers.self, ['nickname'])
        }
    }

    /**
     * Register event handlers
     */
    this.on = (eventName, callback) => {
        eventHandlers[eventName] = callback
    }

    /**
     * Execute an event handler
     */
    const trigger = (...args) => {
        const eventName = args.shift()

        if (eventName in eventHandlers) {
            eventHandlers[eventName].apply(null, args)
        }
    }

    const initSocket = (token) => {
        // Connect to websocket
        socket = new Socket(token)

        socket.on('disconnect', reason => {
        //    this.closeSession()
        })

        socket.on('reconnectFailed', () => {
        //    this.closeSession()
        })

        socket.on('request', async (request, cb) => {
            switch (request.method) {
                case 'newConsumer':
                    const {
                        peerId,
                        producerId,
                        id,
                        kind,
                        rtpParameters,
                        type,
                        appData,
                        producerPaused
                    } = request.data

                    const consumer = await recvTransport.consume({
                            id,
                            producerId,
                            kind,
                            rtpParameters
                    })

                    consumer.peerId = peerId

                    consumer.on('transportclose', () => {
                        // TODO: What actually else needs to be done here?
                        delete consumers[consumer.id]
                    })

                    consumers[consumer.id] = consumer

                    // We are ready. Answer the request so the server will
                    // resume this Consumer (which was paused for now).
                    cb(null)

                    let peer = peers[peerId]

                    if (!peer) {
                        return
                    }

                    let tracks = (peer.tracks || []).filter(track => track.kind != kind)

                    tracks.push(consumer.track)

                    setPeerTracks(peer, tracks)

                    trigger('updatePeer', peer)

                    break

                default:
                    console.error('Unknow request method: ' + request.method)
            }
        })

        socket.on('notification', (notification) => {
            switch (notification.method) {
                case 'roomReady':
                    turnServers = notification.data.turnServers
                    joinRoom()
                    return

                case 'newPeer':
                    peers[notification.data.id] = notification.data
                    trigger('addPeer', notification.data)
                    return

                case 'peerClosed':
                    const { peerId } = notification.data
                    delete peers[peerId]
                    trigger('removePeer', peerId)
                    return

                case 'consumerClosed': {
                    const { consumerId } = notification.data
                    const consumer = consumers[consumerId]

                    if (!consumer) {
                        return
                    }

                    consumer.close()

                    delete consumers[consumerId]

                    let peer = peers[consumer.peerId]

                    if (peer) {
                        // TODO: Update peer state, remove track
                        trigger('updatePeer', peer)
                    }

                    return
                }

                case 'consumerPaused':
                case 'consumerResumed': {
                    const { consumerId } = notification.data
                    const consumer = consumers[consumerId]

                    if (!consumer) {
                        return
                    }

                    consumer[notification.method == 'consumerPaused' ? 'pause' : 'resume']()

                    let peer = peers[consumer.peerId]

                    if (peer) {
                        trigger('updatePeer', updatePeerState(peer))
                    }

                    return
                }

                case 'changeNickname': {
                    const { peerId, nickname } = notification.data
                    const peer = peers[peerId]

                    if (!peer) {
                        return
                    }

                    peer.nickname = nickname

                    trigger('updatePeer', peer, ['nickname'])
                    return
                }

                case 'chatMessage': {
                    trigger('chatMessage', notification.data)
                    return
                }

                case 'moderator:closeRoom': {
                    this.closeSession('session-closed')
                    return
                }

                case 'moderator:kickPeer': {
                    this.closeSession('session-closed')
                    return
                }

                case 'raisedHand': {
                    const { peerId, raisedHand } = notification.data
                    const peer = peers[peerId]

                    if (!peer) {
                        return
                    }

                    peer.raisedHand = raisedHand

                    trigger('updatePeer', peer, ['raisedHand'])
                    return
                }

                default:
                    console.error('Unknow notification method: ' + notification.method)
                    return
            }
        })

        return socket
    }

    const joinRoom = async () => {
        const routerRtpCapabilities = await socket.getRtpCapabilities()

        routerRtpCapabilities.headerExtensions = routerRtpCapabilities.headerExtensions
            .filter(ext => ext.uri !== 'urn:3gpp:video-orientation')

        await device.load({ routerRtpCapabilities })

        const iceTransportPolicy = (device.handlerName.toLowerCase().includes('firefox') && turnServers) ? 'relay' : undefined;

        // Setup 'producer' transport
        if (videoSource || audioSource) {
            const transportInfo = await socket.sendRequest('createWebRtcTransport', {
                forceTcp: false,
                producing: true,
                consuming: false
            })

            const { id, iceParameters, iceCandidates, dtlsParameters } = transportInfo

            sendTransport = device.createSendTransport({
                id,
                iceParameters,
                iceCandidates,
                dtlsParameters,
                iceServers: turnServers,
                iceTransportPolicy: iceTransportPolicy,
                proprietaryConstraints: { optional: [{ googDscp: true }] }
            })

            sendTransport.on('connect', ({ dtlsParameters }, callback, errback) => {
                socket.sendRequest('connectWebRtcTransport',
                    { transportId: sendTransport.id, dtlsParameters })
                    .then(callback)
                    .catch(errback)
            })

            sendTransport.on('produce', async ({ kind, rtpParameters, appData }, callback, errback) => {
                try {
                    const { id } = await socket.sendRequest('produce', {
                        transportId: sendTransport.id,
                        kind,
                        rtpParameters,
                        appData
                    })
                    callback({ id })
                } catch (error) {
                    errback(error)
                }
            })
        }

        // Setup 'consumer' transport

        const transportInfo = await socket.sendRequest('createWebRtcTransport', {
                forceTcp: false,
                producing: false,
                consuming: true
        })

        const { id, iceParameters, iceCandidates, dtlsParameters } = transportInfo

        recvTransport = device.createRecvTransport({
                id,
                iceParameters,
                iceCandidates,
                dtlsParameters,
                iceServers: turnServers,
                iceTransportPolicy: iceTransportPolicy
        })

        recvTransport.on('connect', ({ dtlsParameters }, callback, errback) => {
            socket.sendRequest('connectWebRtcTransport', { transportId: recvTransport.id, dtlsParameters })
                .then(callback)
                .catch(errback)
        })

        // Send the "join" request, get room data, participants, etc.
        const { peers: existing, role, id: peerId } = await socket.sendRequest('join', {
                nickname: nickname,
                rtpCapabilities: device.rtpCapabilities
        })

        trigger('joinSuccess')

        let peer = {
            id: peerId,
            role,
            isSelf: true,
            nickname,
            audioActive: !!audioSource,
            videoActive: !!videoSource
        }

        // Start publishing webcam
        if (videoSource) {
            await setCamera(videoSource)
            // Create the video element
            peer.videoElement = media.createVideoElement([ camProducer.track ], { mirror: true })
        }

        // Start publishing microphone
        if (audioSource) {
            setMic(audioSource)
            // Note: We're not adding this track to the video element
        }

        trigger('addPeer', peer)

        // Add self to the list
        peers.self = peer

        console.log(existing)

        // Trigger addPeer event for all peers already in the room, maintain peers list
        existing.forEach(peer => {
            let tracks = []

            // We receive newConsumer requests before we add the peer to peers list,
            // therefore we look here for any consumers that belong to this peer and update
            // the peer. If we do not do this we have to wait about 20 seconds for repeated
            // newConsumer requests
            Object.keys(consumers).forEach(cid => {
                if (consumers[cid].peerId === peer.id) {
                    tracks.push(consumers[cid].track)
                }
            })

            if (tracks.length) {
                setPeerTracks(peer, tracks)
            }

            trigger('addPeer', peer)
            peers[peer.id] = peer
        })
    }

    const setCamera = async (deviceId) => {
        if (!device.canProduce('video')) {
            throw new Error('cannot produce video')
        }

        const { aspectRatio, frameRate, resolution } = Config.videoOptions

        const track = await media.getTrack({
            video: {
                deviceId: { ideal: deviceId },
                ...VIDEO_CONSTRAINTS[resolution],
                frameRate
            }
        })

        // TODO: Simulcast support?

        camProducer = await sendTransport.produce({
            track,
            appData: {
                source : 'webcam'
            }
        })
/*
        camProducer.on('transportclose', () => {
            camProducer = null
        })

        camProducer.on('trackended', () => {
            // disableWebcam()
        })
*/
    }

    const setMic = async (deviceId) => {
        if (!device.canProduce('audio')) {
            throw new Error('cannot produce audio')
        }

        const {
            autoGainControl,
            echoCancellation,
            noiseSuppression,
            sampleRate,
            channelCount,
            volume,
            sampleSize,
            opusStereo,
            opusDtx,
            opusFec,
            opusPtime,
            opusMaxPlaybackRate
        } = Config.audioOptions

        const track = await media.getTrack({
            audio: {
                sampleRate,
                channelCount,
                volume,
                autoGainControl,
                echoCancellation,
                noiseSuppression,
                sampleSize,
                deviceId: { ideal: deviceId }
            }
        })

        micProducer = await sendTransport.produce({
            track,
            codecOptions: {
                opusStereo,
                opusDtx,
                opusFec,
                opusPtime,
                opusMaxPlaybackRate
            },
            appData: {
                source : 'mic'
            }
        })
/*
        micProducer.on('transportclose', () => {
            micProducer = null
        })

        micProducer.on('trackended', () => {
            // disableMic()
        })
*/
    }

    const setPeerTracks = (peer, tracks) => {
        if (!peer.videoElement) {
            peer.videoElement = media.createVideoElement(tracks, {})
        } else {
            const stream = new MediaStream()
            tracks.forEach(track => stream.addTrack(track))
            peer.videoElement.srcObject = stream
        }

        updatePeerState(peer)

        peer.tracks = tracks
    }

    const updatePeerState = (peer) => {
        if (peer.isSelf) {
            peer.videoActive = this.camStatus()
            peer.audioActive = this.micStatus()
        } else {
            peer.videoActive = false
            peer.audioActive = false

            Object.keys(consumers).forEach(cid => {
                const consumer = consumers[cid]

                if (consumer.peerId == peer.id) {
                    peer[consumer.kind + 'Active'] = !consumer.paused && !consumer.closed && !consumer.producerPaused
                }
            })
        }

        return peer
    }
}

export { Client }
