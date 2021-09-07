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
    let sendTransportInfo
    let sendTransport
    let recvTransport
    let iceServers = []
    let nickname = ''
    let peers = {}
    let joinProps = {}
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
        // Store the join properties for later
        joinProps = props
        // Initialize the socket, 'roomReady' request handler will do the rest of the job
        socket = initSocket(token)
    }

    /**
     * Close the session (disconnect)
     */
    this.closeSession = async (reason) => {
        // If room owner, send the request to close the room
        if (reason === true && peers.self && peers.self.role & Roles.OWNER) {
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

    this.isJoined = () => {
        return 'self' in peers
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

    this.peerMicMute = (peerId) => {
        Object.values(consumers).forEach(consumer => {
            if (consumer.peerId == peerId && consumer.kind == 'audio' && !consumer.paused) {
                consumer.pause()
                socket.sendRequest('pauseConsumer', { consumerId: consumer.id })
            }
        })
    }

    this.peerMicUnmute = (peerId) => {
        Object.values(consumers).forEach(consumer => {
            if (consumer.peerId == peerId && consumer.kind == 'audio' && consumer.paused) {
                consumer.resume()
                socket.sendRequest('resumeConsumer', { consumerId: consumer.id })
            }
        })
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

    this.setLanguage = (peerId, language) => {
        socket.sendRequest('moderator:changeLanguage', { peerId, language })
    }

    this.addRole = (peerId, role) => {
        socket.sendRequest('moderator:addRole', { peerId, role })
    }

    this.removeRole = (peerId, role) => {
        socket.sendRequest('moderator:removeRole', { peerId, role })
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

                    addPeerTrack(peer, consumer.track)

                    trigger('updatePeer', peer)

                    break

                default:
                    console.error('Unknow request method: ' + request.method)
            }
        })

        socket.on('notification', (notification) => {
            switch (notification.method) {
                case 'roomReady':
                    iceServers = notification.data.iceServers
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

                case 'changeLanguage':
                    updatePeerProperty(notification.data, 'language')
                    return

                case 'changeNickname':
                    updatePeerProperty(notification.data, 'nickname')
                    return

                case 'changeRole': {
                    const { peerId, role } = notification.data
                    const peer = peers.self.id === peerId ? peers.self : peers[peerId]

                    if (!peer) {
                        return
                    }

                    let changes = ['role']

                    const rolePublisher = role & Roles.PUBLISHER
                    const roleModerator = role & Roles.MODERATOR
                    const isPublisher = peer.role & Roles.PUBLISHER
                    const isModerator = peer.role & Roles.MODERATOR

                    if (isPublisher && !rolePublisher) {
                        // demoted to a subscriber
                        changes.push('publisherRole')

                        if (peer.isSelf) {
                            // stop publishing any streams
                            this.setMic('', true)
                            this.setCamera('', true)
                        } else {
                            // remove the video element
                            peer.videoElement = null
                            // TODO: Do we need to remove/stop consumers?
                        }
                    } else if (!isPublisher && rolePublisher) {
                        // promoted to a publisher
                        changes.push('publisherRole')

                        // create a video element with no tracks
                        setPeerTracks(peer, [])
                    }

                    if ((!isModerator && roleModerator) || (isModerator && !roleModerator)) {
                        changes.push('moderatorRole')
                    }

                    peer.role = role

                    trigger('updatePeer', peer, changes)
                    return
                }

                case 'chatMessage':
                    notification.data.isSelf = notification.data.peerId == peers.self.id
                    trigger('chatMessage', notification.data)
                    return

                case 'moderator:closeRoom':
                    this.closeSession('session-closed')
                    return

                case 'moderator:kickPeer':
                    this.closeSession('session-closed')
                    return

                case 'raisedHand':
                    updatePeerProperty(notification.data, 'raisedHand')
                    return

                case 'signal:joinRequest':
                    trigger('joinRequest', notification.data)
                    return

                default:
                    console.error('Unknow notification method: ' + notification.method)
            }
        })

        return socket
    }

    const joinRoom = async () => {
        const routerRtpCapabilities = await socket.getRtpCapabilities()

        routerRtpCapabilities.headerExtensions = routerRtpCapabilities.headerExtensions
            .filter(ext => ext.uri !== 'urn:3gpp:video-orientation')

        await device.load({ routerRtpCapabilities })

        // Setup the consuming transport (for handling streams of other participants)
        await setRecvTransport()

        // Send the "join" request, get room data, participants, etc.
        const { peers: existing, role, id: peerId } = await socket.sendRequest('join', {
                nickname: joinProps.nickname,
                rtpCapabilities: device.rtpCapabilities
        })

        trigger('joinSuccess')

        let peer = {
            id: peerId,
            role,
            nickname: joinProps.nickname,
            isSelf: true
        }

        // Add self to the list
        peers.self = peer

        // Start publishing webcam and mic (and setup the producing transport)
        await this.setCamera(joinProps.videoSource, true)
        await this.setMic(joinProps.audioSource, true)

        updatePeerState(peer)

        trigger('addPeer', peer)

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

            peers[peer.id] = peer

            trigger('addPeer', peer)
        })
    }

    this.setCamera = async (deviceId, noUpdate) => {
        // Actually selected device, do nothing
        if (deviceId == videoSource) {
            return
        }

        // Remove current device, stop producer
        if (camProducer && !camProducer.closed) {
            camProducer.close()
            await socket.sendRequest('closeProducer', { producerId: camProducer.id })
            setPeerTracks(peers.self, [])
        }

        peers.self.videoSource = videoSource = deviceId

        if (!deviceId) {
            if (!noUpdate) {
                trigger('updatePeer', updatePeerState(peers.self), ['videoSource'])
            }
            return
        }

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

        await setSendTransport()

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
        // Create/Update the video element
        addPeerTrack(peers.self, track)
        if (!noUpdate) {
            trigger('updatePeer', peers.self, ['videoSource'])
        }
    }

    this.setMic = async (deviceId, noUpdate) => {
        // Actually selected device, do nothing
        if (deviceId == audioSource) {
            return
        }

        // Remove current device, stop producer
        if (micProducer && !micProducer.closed) {
            micProducer.close()
            await socket.sendRequest('closeProducer', { producerId: micProducer.id })
        }

        peers.self.audioSource = audioSource = deviceId

        if (!deviceId) {
            if (!noUpdate) {
                trigger('updatePeer', updatePeerState(peers.self), ['audioSource'])
            }
            return
        }

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

        await setSendTransport()

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
        // Note: We're not adding this track to the video element
        if (!noUpdate) {
            trigger('updatePeer', updatePeerState(peers.self), ['audioSource'])
        }
    }

    const setPeerTracks = (peer, tracks) => {
        if (!peer.videoElement) {
            peer.videoElement = media.createVideoElement(tracks, { mirror: peer.isSelf })
        } else {
            const stream = new MediaStream()
            tracks.forEach(track => stream.addTrack(track))
            peer.videoElement.srcObject = stream
        }

        updatePeerState(peer)
    }

    const addPeerTrack = (peer, track) => {
        if (!peer.videoElement) {
            setPeerTracks(peer, [ track ])
            return
        }

        const stream = peer.videoElement.srcObject

        if (track.kind == 'video') {
            media.removeTracksFromStream(stream, 'Video')
        } else {
            media.removeTracksFromStream(stream, 'Audio')
        }

        stream.addTrack(track)

        updatePeerState(peer)
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

    const setSendTransport = async () => {
        if (sendTransport && !sendTransport.closed) {
            return
        }

        if (!sendTransportInfo) {
            sendTransportInfo = await socket.sendRequest('createWebRtcTransport', {
                    forceTcp: false,
                    producing: true,
                    consuming: false
            })
        }

        const { id, iceParameters, iceCandidates, dtlsParameters } = sendTransportInfo

        const iceTransportPolicy = (device.handlerName.toLowerCase().includes('firefox') && iceServers) ? 'relay' : undefined

        sendTransport = device.createSendTransport({
            id,
            iceParameters,
            iceCandidates,
            dtlsParameters,
            iceServers,
            iceTransportPolicy,
            proprietaryConstraints: { optional: [{ googDscp: true }] }
        })

        sendTransport.on('connect', ({ dtlsParameters }, callback, errback) => {
            socket.sendRequest('connectWebRtcTransport', { transportId: sendTransport.id, dtlsParameters })
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

    const setRecvTransport = async () => {
        const transportInfo = await socket.sendRequest('createWebRtcTransport', {
                forceTcp: false,
                producing: false,
                consuming: true
        })

        const { id, iceParameters, iceCandidates, dtlsParameters } = transportInfo

        const iceTransportPolicy = (device.handlerName.toLowerCase().includes('firefox') && iceServers) ? 'relay' : undefined

        recvTransport = device.createRecvTransport({
                id,
                iceParameters,
                iceCandidates,
                dtlsParameters,
                iceServers,
                iceTransportPolicy
        })

        recvTransport.on('connect', ({ dtlsParameters }, callback, errback) => {
            socket.sendRequest('connectWebRtcTransport', { transportId: recvTransport.id, dtlsParameters })
                .then(callback)
                .catch(errback)
        })
    }

    const updatePeerProperty = (data, prop) => {
        const peerId = data.peerId
        const peer = peers.self.id === peerId ? peers.self : peers[peerId]

        if (!peer) {
            return
        }

        peer[prop] = data[prop]

        trigger('updatePeer', peer, [ prop ])
    }
}

export { Client }
