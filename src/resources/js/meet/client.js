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

        // Close mediasoup transports
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
            if (peer.screenVideoElement) {
                $(peer.screenVideoElement).remove()
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
                    setConsumerState(consumer, false)
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
                    setConsumerState(consumer, true)
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
                    consumer.source = appData.source

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
                        setConsumerState(consumer, false, true)
                    }

                    let peer = peers[peerId]

                    if (!peer) {
                        return
                    }

                    addPeerTrack(peer, consumer.track, consumer.source)

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
                    trigger('removePeer', peerId)
                    delete peers[peerId]
                    updateChannels()
                    return

                case 'consumerClosed': {
                    const { consumerId } = notification.data
                    const consumer = consumers[consumerId]

                    if (!consumer) {
                        return
                    }

                    // Calling pause() before close() on a video consumer prevents from
                    // a "freezed" video frame left in the peer video element, even removing
                    // the track from the stream below does not fix that.
                    consumer.pause()
                    consumer.close()

                    delete consumers[consumerId]

                    let peer = peers[consumer.peerId]

                    if (peer) {
                        // Remove the track from the video element
                        // FIXME: This is not really needed if the consumer was closed
                        // if (peer.videoElement) {
                        //     media.removeTracksFromStream(peer.videoElement.srcObject, consumer.kind)
                        // }

                        // If this is a shared screen, remove the video element
                        if (consumer.source == 'screen') {
                            peer.screenVideoElement = null
                        }

                        trigger('updatePeer', updatePeerState(peer))
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
                        setConsumerState(consumer, false)
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
                        setConsumerState(consumer, true)
                    }

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

                case 'changeRaisedHand':
                    updatePeerProperty(notification.data, 'raisedHand')
                    return

                case 'changeRole': {
                    const { peerId, role } = notification.data
                    const peer = peers.self.id === peerId ? peers.self : peers[peerId]

                    if (!peer) {
                        return
                    }

                    let changes = []

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
                            this.screenUnshare()
                        } else {
                            // remove the video element
                            peer.videoElement = null
                            peer.screenVideoElement = null
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

                    updatePeerProperty(notification.data, 'role', changes)

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
        const { peers: existing, role, nickname, id: peerId } = await socket.sendRequest('join', {
                nickname: joinProps.nickname,
                rtpCapabilities: device.rtpCapabilities
        })

        trigger('joinSuccess')

        let peer = {
            id: peerId,
            role,
            nickname,
            audioActive: false,
            videoActive: false,
            screenActive: false,
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
            let screenTracks = []

            // We receive newConsumer requests before we add the peer to peers list,
            // therefore we look here for any consumers that belong to this peer and update
            // the peer. If we do not do this we have to wait about 20 seconds for repeated
            // newConsumer requests
            Object.keys(consumers).forEach(cid => {
                if (consumers[cid].peerId === peer.id) {
                    (consumers.source == 'screen' ? screenTracks : tracks).push(consumers[cid].track)
                }
            })

            if (tracks.length) {
                setPeerTracks(peer, tracks)
            }
            if (screenTracks.length) {
                setPeerTracks(peer, screenTracks, 'screen')
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
        if (!(peers.self.role & Roles.PUBLISHER)) {
            // We're checking the role here because thanks to "subscribers only" feature
            // the peer might have been "downgraded" automatically to a subscriber
            deviceId = ''
        }

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
        if (!(peers.self.role & Roles.PUBLISHER)) {
            // We're checking the role here because thanks to "subscribers only" feature
            // the peer might have been "downgraded" automatically to a subscriber
            deviceId = ''
        }

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
     * Start the current user screen sharing
     */
    this.screenShare = async () => {
        if (this.screenStatus()) {
            return true
        }

        if (!(peers.self.role & Roles.PUBLISHER)) {
            // We're checking the role here because thanks to "subscribers only" feature
            // the peer might have been "downgraded" automatically to a subscriber
            return false
        }

        const { frameRate, resolution } = Config.screenOptions

        const track = await media.getDisplayTrack({
            video: {
                ...VIDEO_CONSTRAINTS[resolution],
                frameRate
            },
            audio: false
        })

        await setSendTransport()

        screenProducer = await sendTransport.produce({
            track,
            appData: {
                source : 'screen'
            }
        })
/*
        screenProducer.on('transportclose', () => {
            screenProducer = null
        })

        screenProducer.on('trackended', () => {
        })
*/
        // Create the video element
        createScreenElement(peers.self, [ track ])

        trigger('updatePeer', peers.self)

        return this.screenStatus()
    }

    /**
     * Stop the current user screen sharing
     */
    this.screenUnshare = async () => {
        if (screenProducer && !screenProducer.closed) {
            screenProducer.close()
            await socket.sendRequest('closeProducer', { producerId: screenProducer.id })

            peers.self.screenVideoElement = null

            trigger('updatePeer', peers.self)
        }

        screenProducer = null

        return this.screenStatus()
    }

    /**
     * Get the current user shared screen status
     */
    this.screenStatus = () => {
        return !!screenProducer && !screenProducer.closed
    }

    /**
     * Set the media stream tracks for a video element of a peer
     */
    const setPeerTracks = (peer, tracks, source) => {
        if (source == 'screen' && !peer.screenVideoElement) {
            createScreenElement(peer, tracks)
        } else if (source == 'screen') {
            const stream = new MediaStream()
            tracks.forEach(track => stream.addTrack(track))
            peer.screenVideoElement.srcObject = stream
        } else if (!peer.videoElement) {
            let props = peer.isSelf ? { mirror: true, muted: true } : {}
            peer.videoElement = media.createVideoElement(tracks, props)
        } else {
            const stream = new MediaStream()
            tracks.forEach(track => stream.addTrack(track))
            peer.videoElement.srcObject = stream
        }

        updatePeerState(peer)
    }

    /**
     * Add a media stream track to a video element(s) of a peer
     */
    const addPeerTrack = (peer, track, source) => {
        let stream

        if (source == 'screen') {
            if (!peer.screenVideoElement) {
                setPeerTracks(peer, [ track ], source)
                return
            }

            stream = peer.screenVideoElement.srcObject
        } else {
            if (!peer.videoElement) {
                setPeerTracks(peer, [ track ])
                return
            }

            stream = peer.videoElement.srcObject
        }

        media.removeTracksFromStream(stream, track.kind)

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
            peer.screenActive = this.screenStatus()
        } else {
            peer.videoActive = false
            peer.audioActive = false
            peer.screenActive = false

            Object.keys(consumers).forEach(cid => {
                const consumer = consumers[cid]

                if (consumer.peerId == peer.id) {
                    const key = (consumer.source == 'screen' ? 'screen' : consumer.kind) + 'Active'
                    peer[key] = !consumer.closed && !consumer.producerPaused && !consumer.channelPaused
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

        sendTransport.on('connectionstatechange', (connectionState) => {
            console.info("sendTransport new connecton state:", connectionState)
            if (connectionState == 'connecting') {
                // TODO check with a timer that we're reaching the connected state
                console.info("The 'connected' state is expected next.")
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

        recvTransport.on('connectionstatechange', (connectionState) => {
            console.info("recvTransport new connecton state:", connectionState)
            if (connectionState == 'connecting') {
                // TODO check with a timer that we're reaching the connected state
                console.info("The 'connected' state is expected next.")
            }
        })
    }

    /**
     * A helper for a peer property update (received via websocket)
     */
    const updatePeerProperty = (data, prop, changes) => {
        const peerId = data.peerId
        const peer = peers.self && peers.self.id === peerId ? peers.self : peers[peerId]

        if (!peer) {
            return
        }

        if (!changes) {
            changes = []
        }

        changes.push(prop)

        if (prop == 'language' && peer.language != data.language) {
            changes.push('interpreterRole')
        }

        peer[prop] = data[prop]

        trigger('updatePeer', peer, changes)

        if (prop == 'language') {
            updateChannels()
        } else if (peer.isSelf) {
            trigger('updateSession', sessionData())
        }
    }

    /**
     * Update list of existing language interpretation channels and update
     * audio state of all participants according to the selected channel.
     */
    const updateChannels = (update) => {
        let list = []

        Object.values(peers).forEach(peer => {
            if (!peer.isSelf && peer.language && !list.includes(peer.language)) {
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
                consumer.channelPaused = (peer.language || '') != (channel || '')

                if (consumer.channelPaused && !consumer.paused) {
                    setConsumerState(consumer, false)
                } else if (!consumer.channelPaused && consumer.paused
                    && !consumer.consumerPaused && !consumer.producerPaused
                ) {
                    setConsumerState(consumer, true)
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
        const { audioActive, videoActive, audioSource, videoSource, screenActive, raisedHand, role } = peers.self

        return {
            channel,
            channels,
            audioActive,
            videoActive,
            audioSource,
            videoSource,
            screenActive,
            raisedHand,
            role
        }
    }

    /**
     * A helper to pause/resume a consumer and propagate the state
     * to the video element as well as the server
     */
    const setConsumerState = (consumer, state, quiet) => {
        const action = state ? 'resume' : 'pause'

        // Pause/resume the consumer
        consumer[action]()

        // Mute/unmute the video element
        // Note: We don't really have to do this, but this simplifies testing
        if (consumer.kind == 'audio') {
            const peer = peers[consumer.peerId]
            if (peer && peer.videoElement && consumer.source == 'mic') {
                peer.videoElement.muted = !state
            } else if (peer && peer.screenVideoElement && consumer.source == 'screen') {
                peer.screenVideoElement.muted = !state
            }
        }

        if (!quiet) {
            socket.sendRequest(action + 'Consumer', { consumerId: consumer.id })
        }
    }

    /**
     * Creates video element for screen sharing stream
     */
    const createScreenElement = (peer, tracks) => {
        peer.screenVideoElement = media.createVideoElement(tracks, { muted: true })

        // Track video dimensions (width) change
        // Note: the videoWidth is intially 0, we have to wait a while for the real value
        let interval = setInterval(() => {
            if (!peer || !peer.screenVideoElement) {
                clearInterval(interval)
                return
            }

            const width = peer.screenVideoElement.videoWidth

            if (width != peer.screenWidth) {
                peer.screenWidth = width
                trigger('updatePeer', peers.self, [ 'screenWidth' ])
            }
        }, 1000)
    }
}

export { Client }
