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
    let channel = null
    let channels = []
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
        Object.values(peers).forEach(peer => {
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
        channels = []
    }

    /**
     * Returns True if user already joined the room session
     */
    this.isJoined = () => {
        return 'self' in peers
    }

    /**
     * Accept the join request
     */
    this.joinRequestAccept = (requestId) => {
        socket.sendRequest('moderator:joinRequestAccept', { requestId })
    }

    /**
     * Deny the join request
     */
    this.joinRequestDeny = (requestId) => {
        socket.sendRequest('moderator:joinRequestDeny', { requestId })
    }

    /**
     * Disable the current user camera
     */
    this.camMute = async () => {
        if (camProducer) {
            camProducer.pause()
            await socket.sendRequest('pauseProducer', { producerId: camProducer.id })
            trigger('updatePeer', updatePeerState(peers.self))
        }

        return this.camStatus()
    }

    /**
     * Enable the current user camera
     */
    this.camUnmute = async () => {
        if (camProducer) {
            camProducer.resume()
            await socket.sendRequest('resumeProducer', { producerId: camProducer.id })
            trigger('updatePeer', updatePeerState(peers.self))
        }

        return this.camStatus()
    }

    /**
     * Get the current user camera status
     */
    this.camStatus = () => {
        return !!(camProducer && !camProducer.paused && !camProducer.closed)
    }

    /**
     * Mute the current user microphone
     */
    this.micMute = async () => {
        if (micProducer) {
            micProducer.pause()
            await socket.sendRequest('pauseProducer', { producerId: micProducer.id })
            trigger('updatePeer', updatePeerState(peers.self))
        }

        return this.micStatus()
    }

    /**
     * Unmute the current user microphone
     */
    this.micUnmute = async () => {
        if (micProducer) {
            micProducer.resume()
            await socket.sendRequest('resumeProducer', { producerId: micProducer.id })
            trigger('updatePeer', updatePeerState(peers.self))
        }

        return this.micStatus()
    }

    /**
     * Get the current user microphone status
     */
    this.micStatus = () => {
        return !!(micProducer && !micProducer.paused && !micProducer.closed)
    }

    /**
     * Kick a user out of the room
     */
    this.kickPeer = (peerId) => {
        socket.sendRequest('moderator:kickPeer', { peerId })
    }

    /**
     * Send a chat message to the server
     */
    this.chatMessage = (message) => {
        socket.sendRequest('chatMessage', { message })
    }

    /**
     * Mute microphone of another user
     */
    this.peerMicMute = (peerId) => {
        Object.values(consumers).forEach(consumer => {
            if (consumer.peerId == peerId && consumer.kind == 'audio') {
                consumer.consumerPaused = true
                if (!consumer.paused) {
                    consumer.pause()
                    socket.sendRequest('pauseConsumer', { consumerId: consumer.id })
                }
            }
        })
    }

    /**
     * Unmute microphone of another user
     */
    this.peerMicUnmute = (peerId) => {
        Object.values(consumers).forEach(consumer => {
            if (consumer.peerId == peerId && consumer.kind == 'audio') {
                consumer.consumerPaused = false
                if (consumer.paused && !consumer.producerPaused && !consumer.channelPaused) {
                    consumer.resume()
                    socket.sendRequest('resumeConsumer', { consumerId: consumer.id })
                }
            }
        })
    }

    /**
     * Set 'raisedHand' state of the current user
     */
    this.raiseHand = async (status) => {
        if (peers.self.raisedHand != status) {
            peers.self.raisedHand = status
            await socket.sendRequest('raisedHand', { raisedHand: status })
            trigger('updatePeer', peers.self, ['raisedHand'])
        }

        return status
    }

    /**
     * Set nickname of the current user
     */
    this.setNickname = (nickname) => {
        if (peers.self.nickname != nickname) {
            peers.self.nickname = nickname
            socket.sendRequest('changeNickname', { nickname })
            trigger('updatePeer', peers.self, ['nickname'])
        }
    }

    /**
     * Set language channel for the current user
     */
    this.setLanguageChannel = (language) => {
        channel = language
        updateChannels(true)
    }

    /**
     * Set language for the current user (make him an interpreter)
     */
    this.setLanguage = (peerId, language) => {
        socket.sendRequest('moderator:changeLanguage', { peerId, language })
    }

    /**
     * Add a role to a user
     */
    this.addRole = (peerId, role) => {
        socket.sendRequest('moderator:addRole', { peerId, role })
    }

    /**
     * Remove a role from a user
     */
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

        console.log(eventName, args)

        if (eventName in eventHandlers) {
            eventHandlers[eventName].apply(null, args)
        }
    }

    /**
     * Initialize websocket connection, register event handlers
     */
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

                    if (producerPaused) {
                        consumer.producerPaused = true
                        consumer.pause()
                    }

                    let peer = peers[peerId]

                    if (!peer) {
                        return
                    }

                    addPeerTrack(peer, consumer.track)

                    trigger('updatePeer', peer)
                    updateChannels()

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
                    updateChannels()
                    return

                case 'peerClosed':
                    const { peerId } = notification.data
                    delete peers[peerId]
                    trigger('removePeer', peerId)
                    updateChannels()
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

                case 'consumerPaused': {
                    const { consumerId } = notification.data
                    const consumer = consumers[consumerId]

                    if (!consumer) {
                        return
                    }

                    consumer.producerPaused = true

                    if (!consumer.paused) {
                        consumer.pause()
                        socket.sendRequest('pauseConsumer', { consumerId: consumer.id })
                    }

                    let peer = peers[consumer.peerId]

                    if (peer) {
                        trigger('updatePeer', updatePeerState(peer))
                    }

                    return
                }

                case 'consumerResumed': {
                    const { consumerId } = notification.data
                    const consumer = consumers[consumerId]

                    if (!consumer) {
                        return
                    }

                    consumer.producerPaused = false

                    if (consumer.paused && !consumer.consumerPaused && !consumer.channelPaused) {
                        consumer.resume()
                        socket.sendRequest('resumeConsumer', { consumerId: consumer.id })
                    }

                    let peer = peers[consumer.peerId]

                    if (peer) {
                        trigger('updatePeer', updatePeerState(peer))
                    }

                    return
                }

                case 'changeLanguage':
                    updatePeerProperty(notification.data, 'language')
                    updateChannels()
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

    /**
     * Join the session (room)
     */
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
            audioActive: false,
            videoActive: false,
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

        updateChannels()
    }

    /**
     * Set the camera device for the current user
     */
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

    /**
     * Set the microphone device for the current user
     */
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

    /**
     * Set the media stream tracks for a video element of a peer
     */
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

    /**
     * Add a media stream track to a video element of a peer
     */
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

    /**
     * Update peer state
     */
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
                    peer[consumer.kind + 'Active'] = !consumer.closed && !consumer.producerPaused && !consumer.channelPaused
                }
            })
        }

        return peer
    }

    /**
     * Configure transport for producer (publisher) streams
     */
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

    /**
     * Configure transport for consumer streams
     */
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

    /**
     * A helper for a peer property update (received via websocket)
     */
    const updatePeerProperty = (data, prop) => {
        const peerId = data.peerId
        const peer = peers.self.id === peerId ? peers.self : peers[peerId]

        if (!peer) {
            return
        }

        peer[prop] = data[prop]

        trigger('updatePeer', peer, [ prop ])
    }

    /**
     * Update list of existing language interpretation channels and update
     * audio state of all participants according to the selected channel.
     */
    const updateChannels = (update) => {
        let list = []

        Object.values(peers).forEach(peer => {
            if (peer.language && !list.includes(peer.language)) {
                list.push(peer.language)
            }
        })

        update = update || channels.join() != list.join()
        channels = list

        // The channel user was using has been removed (or the participant stopped being an interpreter)
        if (channel && !channels.includes(channel)) {
            channel = null
            update = true
        }

        // Mute/unmute all peers depending on the selected channel
        Object.values(consumers).forEach(consumer => {
            if (consumer.kind == 'audio' && !consumer.closed) {
                let peer = peers[consumer.peerId]

                // It can happen because consumers are being removed after the peer
                if (!peer) {
                    return
                }

                // When a channel is selected we mute everyone except the interpreter of the language.
                // When a channel is not selected we mute language interpreters only
                consumer.channelPaused = channel && peer.language != channel

                if (consumer.channelPaused && !consumer.paused) {
                    consumer.pause()
                    socket.sendRequest('pauseConsumer', { consumerId: consumer.id })
                } else if (!consumer.channelPaused && consumer.paused
                    && !consumer.consumerPaused && !consumer.producerPaused
                ) {
                    consumer.resume()
                    socket.sendRequest('resumeConsumer', { consumerId: consumer.id })
                }

                const state = !consumer.producerPaused && !consumer.channelPaused

                if (peer.audioActive != state) {
                    peer.audioActive = state
                    trigger('updatePeer', peer)
                }
            }
        })

        if (update) {
            trigger('updateSession', sessionData())
        }
    }

    /**
     * Returns all relevant information about the current session/user state
     */
    const sessionData = () => {
        return {
            channel,
            channels,
            audioActive: peers.self.audioActive,
            videoActive: peers.self.videoActive,
            audioSource: peers.self.audioSource,
            videoSource: peers.self.videoSource,
            screenActive: peers.self.screenActive,
            raisedHand: peers.self.raisedHand
        }
    }
}

export { Client }
