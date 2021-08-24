'use strict'

import { Device, parseScalabilityMode } from 'mediasoup-client'
import Config from './config.js'
import { Media } from './media.js'
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
     * Start a session
     */
    this.startSession = async (token, videoSource, audioSource) => {
        socket = initSocket(token)

        const routerRtpCapabilities = await socket.getRtpCapabilities()

        routerRtpCapabilities.headerExtensions = routerRtpCapabilities.headerExtensions
            .filter(ext => ext.uri !== 'urn:3gpp:video-orientation')

        await device.load({ routerRtpCapabilities })

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
                iceTransportPolicy: undefined, // TODO: device.flag === 'firefox' && turnServers ? 'relay' : undefined,
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
                iceTransportPolicy: undefined, // TODO: device.flag === 'firefox' && turnServers ? 'relay' : undefined
        })

        recvTransport.on('connect', ({ dtlsParameters }, callback, errback) => {
            socket.sendRequest('connectWebRtcTransport', { transportId: recvTransport.id, dtlsParameters })
                .then(callback)
                .catch(errback)
        })

        // TODO: Send the "join" request, get room data, participants, etc.?

        // Start publishing webcam/mic
        if (videoSource) {
            setCamera()
        }
        if (audioSource) {
            setMic()
        }
    }

    /**
     * Close the session (disconnect)
     */
    this.closeSession = () => {
        if (socket) {
            socket.close()
        }

        // Close mediasoup Transports.
        if (sendTransport) {
            sendTransport.close()
            sendTransport = null
        }

        if (recvTransport) {
            recvTransport.close()
            recvTransport = null
        }
    }

    this.camMute = async () => {
        camProducer.pause()

        await socket.sendRequest('pauseProducer', { producerId: camProducer.id })
    }

    this.camUnmute = async () => {
        if (camProducer) {
            camProducer.resume()
        }

        await socket.sendRequest('resumeProducer', { producerId: camProducer.id })
    }

    this.micMute = async () => {
        if (micProducer) {
            micProducer.pause()
        }

        await socket.sendRequest('pauseProducer', { producerId: micProducer.id })
    }

    this.micUnmute = async () => {
        if (micProducer) {
            micProducer.resume()
        }

        await socket.sendRequest('resumeProducer', { producerId: micProducer.id })
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
                            rtpParameters,
                            appData: { ...appData, peerId }
                    })

                    // Store the consumer
                    consumers[consumer.id] = consumer

                    consumer.on('transportclose', () => { delete consumers[consumer.id] })

                    const { spatialLayers, temporalLayers } = parseScalabilityMode(
                        consumer.rtpParameters.encodings[0].scalabilityMode
                    )

                    const eventData = {
                        id: consumer.id,
                        peerId: peerId,
                        kind: kind,
                        type: type,
                        locallyPaused: false,
                        remotelyPaused: producerPaused,
                        rtpParameters: consumer.rtpParameters,
                        source: consumer.appData.source,
                        spatialLayers: spatialLayers,
                        temporalLayers: temporalLayers,
                        /*
                        preferredSpatialLayer: spatialLayers - 1,
                        preferredTemporalLayer: temporalLayers - 1,
                        */
                        priority: 1,
                        codec: consumer.rtpParameters.codecs[0].mimeType.split('/')[1],
                        track: consumer.track
                    }

                    // TODO: create the video element?

                    trigger('addConsumer', eventData, peerId)

                    // We are ready. Answer the request so the server will
                    // resume this Consumer (which was paused for now).
                    cb(null)

                    break

                default:
                    console.error('Unknow request method: ' + request.method)
            }
        })

        socket.on('notification', async (notification) => {
            switch (notification.method) {
                case 'roomReady':
                    turnServers = notification.data.turnServers
                    return

                case 'newPeer':
                    const { id, displayName, picture, roles } = notification.data;
                    // TODO
                    return

                case 'peerClosed':
                    const { peerId } = notification.data;
                    // TODO
                    return

                case 'consumerClosed': {
                    const { consumerId } = notification.data
                    const consumer = consumers[consumerId]

                    if (!consumer) {
                        break
                    }

                    consumer.close()

                    delete consumers[consumerId]

                    const { peerId } = consumer.appData

                    trigger('removeConsumer', consumerId, peerId)

                    return
                }

                default:
                    console.error('Unknow notification method: ' + notification.method)
                    return
            }

            trigger('signal', notification.method, notification.data)
        })

        return socket
    }

    const setCamera = async (deviceId) => {
        if (!device.canProduce('video')) {
            throw new Error('cannot produce video')
        }

        const { aspectRatio, frameRate, resolution } = Config.videoOptions

        const track = await media.getUserMedia({
            video: {
                deviceId: { ideal: deviceId },
                ...VIDEO_CONSTRAINS[resolution],
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

        camProducer.on('transportclose', () => {
            camProducer = null
        })

        camProducer.on('trackended', () => {
            // disableWebcam()
        })

        trigger('addProducer', {
                id: webProducer.id,
                source: 'webcam',
                track: webProducer.track
        })
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

        const track = await media.getUserMedia({
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

        micProducer.on('transportclose', () => {
            micProducer = null
        })

        micProducer.on('trackended', () => {
            // disableMic()
        })

        trigger('addProducer', {
                id: micProducer.id,
                source: 'mic',
                track: micProducer.track
        })
    }
}

export { Client }
