const EventEmitter = require('events').EventEmitter;
const AwaitQueue = require('awaitqueue');
const axios = require('axios');
const Logger = require('./Logger');
const { SocketTimeoutError } = require('./errors');
const Roles = require('../userRoles');

const config = require('../config/config');

const logger = new Logger('Room');

const ROUTER_SCALE_SIZE = config.routerScaleSize || 40;

class Room extends EventEmitter
{

    static getLeastLoadedRouter(mediasoupWorkers, peers, mediasoupRouters)
    {

        const routerLoads = new Map();

        const workerLoads = new Map();

        const pipedRoutersIds = new Set();

        for (const peer of peers.values())
        {
            const routerId = peer.routerId;

            if (routerId)
            {
                if (mediasoupRouters.has(routerId))
                {
                    pipedRoutersIds.add(routerId);
                }

                if (routerLoads.has(routerId))
                {
                    routerLoads.set(routerId, routerLoads.get(routerId) + 1);
                }
                else
                {
                    routerLoads.set(routerId, 1);
                }
            }
        }

        for (const worker of mediasoupWorkers)
        {
            for (const router of worker._routers)
            {
                const routerId = router._internal.routerId;

                if (workerLoads.has(worker._pid))
                {
                    workerLoads.set(worker._pid, workerLoads.get(worker._pid) +
                        (routerLoads.has(routerId)?routerLoads.get(routerId):0));
                }
                else
                {
                    workerLoads.set(worker._pid,
                        (routerLoads.has(routerId)?routerLoads.get(routerId):0));
                }
            }
        }

        const sortedWorkerLoads = new Map([ ...workerLoads.entries() ].sort(
            (a, b) => a[1] - b[1]));

        // we don't care about if router is piped, just choose the least loaded worker
        if (pipedRoutersIds.size === 0 ||
            pipedRoutersIds.size === mediasoupRouters.size)
        {
            const workerId = sortedWorkerLoads.keys().next().value;

            for (const worker of mediasoupWorkers)
            {
                if (worker._pid === workerId)
                {
                    for (const router of worker._routers)
                    {
                        const routerId = router._internal.routerId;

                        if (mediasoupRouters.has(routerId))
                        {
                            return routerId;
                        }
                    }
                }
            }
        }
        else
        {
            // find if there is a piped router that is on a worker that is below limit
            for (const [ workerId, workerLoad ] of sortedWorkerLoads.entries())
            {
                for (const worker of mediasoupWorkers)
                {
                    if (worker._pid === workerId)
                    {
                        for (const router of worker._routers)
                        {
                            const routerId = router._internal.routerId;

                            // on purpose we check if the worker load is below the limit,
                            // as in reality the worker load is imortant,
                            // not the router load
                            if (mediasoupRouters.has(routerId) &&
                                pipedRoutersIds.has(routerId) &&
                                workerLoad < ROUTER_SCALE_SIZE)
                            {
                                return routerId;
                            }
                        }
                    }
                }
            }

            // no piped router found, we need to return router from least loaded worker
            const workerId = sortedWorkerLoads.keys().next().value;

            for (const worker of mediasoupWorkers)
            {
                if (worker._pid === workerId)
                {
                    for (const router of worker._routers)
                    {
                        const routerId = router._internal.routerId;

                        if (mediasoupRouters.has(routerId))
                        {
                            return routerId;
                        }
                    }
                }
            }
        }
    }

    /**
     * Factory function that creates and returns Room instance.
     *
     * @async
     *
     * @param {mediasoup.Worker} mediasoupWorkers - The mediasoup Worker in which a new
     *   mediasoup Router must be created.
     * @param {String} roomId - Id of the Room instance.
     */
    static async create({ mediasoupWorkers, roomId, peers })
    {
        logger.info('create() [roomId:"%s"]', roomId);

        // Router media codecs.
        const mediaCodecs = config.mediasoup.router.mediaCodecs;

        const mediasoupRouters = new Map();

        for (const worker of mediasoupWorkers)
        {
            const router = await worker.createRouter({ mediaCodecs });

            mediasoupRouters.set(router.id, router);
        }

        const firstRouter = mediasoupRouters.get(Room.getLeastLoadedRouter(
            mediasoupWorkers, peers, mediasoupRouters));

        // Create a mediasoup AudioLevelObserver on first router
        const audioLevelObserver = await firstRouter.createAudioLevelObserver(
            {
                maxEntries : 1,
                threshold  : -80,
                interval   : 800
            });

        return new Room({
            roomId,
            mediasoupRouters,
            audioLevelObserver,
            mediasoupWorkers,
            peers
        });
    }

    constructor({
        roomId,
        mediasoupRouters,
        audioLevelObserver,
        mediasoupWorkers,
        peers
    })
    {
        logger.info('constructor() [roomId:"%s"]', roomId);

        super();
        this.setMaxListeners(Infinity);

        // this._uuid = uuidv4();

        this._mediasoupWorkers = mediasoupWorkers;

        this._allPeers = peers;

        // Room ID.
        this._roomId = roomId;

        // Closed flag.
        this._closed = false;

        // Joining queue
        this._queue = new AwaitQueue();

        // Locked flag.
        this._locked = false;

        this._chatHistory = [];

        this._fileHistory = [];

        this._lastN = [];

        this._peers = {};

        this._selfDestructTimeout = null;

        // Array of mediasoup Router instances.
        this._mediasoupRouters = mediasoupRouters;

        // mediasoup AudioLevelObserver.
        this._audioLevelObserver = audioLevelObserver;

        // Current active speaker.
        this._currentActiveSpeaker = null;

        this._handleAudioLevelObserver();
    }

    isLocked()
    {
        return this._locked;
    }

    close()
    {
        logger.debug('close()');

        this._closed = true;

        this._queue.close();

        this._queue = null;

        if (this._selfDestructTimeout)
            clearTimeout(this._selfDestructTimeout);

        this._selfDestructTimeout = null;

        this._chatHistory = null;

        this._fileHistory = null;

        // Close the peers.
        for (const peer in this._peers)
        {
            if (!this._peers[peer].closed)
                this._peers[peer].close();
        }

        this._peers = null;

        // Close the mediasoup Routers.
        for (const router of this._mediasoupRouters.values())
        {
            router.close();
        }

        this._allPeers = null;

        this._mediasoupWorkers = null;

        this._mediasoupRouters.clear();

        this._audioLevelObserver = null;

        // Emit 'close' event.
        this.emit('close');
    }

    handlePeer({ peer })
    {
        logger.info('handlePeer() [peer:"%s", role:%s]', peer.id, peer.role);

        // Should not happen
        if (this._peers[peer.id])
        {
            logger.warn(
                'handleConnection() | there is already a peer with same peerId [peer:"%s"]',
                peer.id);
        }

        this._peerJoining(peer);
    }

    _handleOverRoomLimit(peer)
    {
        this._notification(peer.socket, 'overRoomLimit');
    }

    _handleGuest(peer)
    {
        if (config.activateOnHostJoin && !this.checkEmpty())
            this._peerJoining(peer);
        else
        {
            this._parkPeer(peer);
            this._notification(peer.socket, 'signInRequired');
        }
    }

    _handleAudioLevelObserver()
    {
/*
        // Set audioLevelObserver events.
        this._audioLevelObserver.on('volumes', (volumes) =>
        {
            const { producer, volume } = volumes[0];

            // Notify all Peers.
            for (const peer of this.getPeers())
            {
                this._notification(
                    peer.socket,
                    'activeSpeaker',
                    {
                        peerId : producer.appData.peerId,
                        volume : volume
                    });
            }
        });
        this._audioLevelObserver.on('silence', () =>
        {
            // Notify all Peers.
            for (const peer of this.getPeers())
            {
                this._notification(
                    peer.socket,
                    'activeSpeaker',
                    { peerId: null }
                );
            }
        });
*/
    }

    logStatus()
    {
        logger.info(
            'logStatus() [room id:"%s", peers:"%s"]',
            this._roomId,
            Object.keys(this._peers).length
        );
    }

    dump()
    {
        return {
            roomId : this._roomId,
            peers  : Object.keys(this._peers).length
        };
    }

    get id()
    {
        return this._roomId;
    }

    selfDestructCountdown()
    {
        logger.debug('selfDestructCountdown() started');

        if (this._selfDestructTimeout)
            clearTimeout(this._selfDestructTimeout);

        this._selfDestructTimeout = setTimeout(() =>
        {
            if (this._closed)
                return;

            if (this.checkEmpty())
            {
                logger.info(
                    'Room deserted for some time, closing the room [roomId:"%s"]',
                    this._roomId);
                this.close();
            }
            else
                logger.debug('selfDestructCountdown() aborted; room is not empty!');
        }, 10000);
    }

    checkEmpty()
    {
        return Object.keys(this._peers).length === 0;
    }

    _peerJoining(peer)
    {
        this._queue.push(async () =>
        {
            peer.socket.join(this._roomId);

            // If we don't have this peer, add to end
            !this._lastN.includes(peer.id) && this._lastN.push(peer.id);

            this._peers[peer.id] = peer;

            // Assign routerId
            peer.routerId = await this._getRouterId();

            this._handlePeer(peer);

            let turnServers;

            if ('turnAPIURI' in config)
            {
                try
                {
                    const { data } = await axios.get(
                        config.turnAPIURI,
                        {
                            timeout : config.turnAPITimeout || 2000,
                            params  : {
                                ...config.turnAPIparams,
                                'api_key' : config.turnAPIKey,
                                'ip'      : peer.socket.request.connection.remoteAddress
                            }
                        });

                    turnServers = [ {
                        urls       : data.uris,
                        username   : data.username,
                        credential : data.password
                    } ];
                }
                catch (error)
                {
                    if ('backupTurnServers' in config)
                        turnServers = config.backupTurnServers;

                    logger.error('_peerJoining() | error on REST turn [error:"%o"]', error);
                }
            }
            else if ('backupTurnServers' in config)
            {
                turnServers = config.backupTurnServers;
            }

            this._notification(peer.socket, 'roomReady', { turnServers });
        })
            .catch((error) =>
            {
                logger.error('_peerJoining() [error:"%o"]', error);
            });
    }

    _handlePeer(peer)
    {
        logger.debug('_handlePeer() [peer:"%s"]', peer.id);

        peer.on('close', () =>
        {
            this._handlePeerClose(peer);
        });

        peer.on('nicknameChanged', () =>
        {
            // Spread to others
            this._notification(peer.socket, 'changeNickname', {
                peerId: peer.id,
                nickame: peer.nickname
            }, true);
        });

        peer.on('pictureChanged', () =>
        {
            // Spread to others
            this._notification(peer.socket, 'changePicture', {
                peerId  : peer.id,
                picture : peer.picture
            }, true);
        });

        peer.on('gotRole', ({ newRole }) =>
        {
            // Spread to others
            this._notification(peer.socket, 'gotRole', {
                peerId: peer.id,
                role: newRole
            }, true, true);
        });

        peer.socket.on('request', (request, cb) =>
        {
            logger.debug(
                'Peer "request" event [method:"%s", peerId:"%s"]',
                request.method, peer.id);

            this._handleSocketRequest(peer, request, cb)
                .catch((error) =>
                {
                    logger.error('"request" failed [error:"%o"]', error);

                    cb(error);
                });
        });

        // Peer left before we were done joining
        if (peer.closed)
            this._handlePeerClose(peer);
    }

    _handlePeerClose(peer)
    {
        logger.debug('_handlePeerClose() [peer:"%s"]', peer.id);

        if (this._closed)
            return;

        this._notification(peer.socket, 'peerClosed', { peerId: peer.id }, true);

        // Remove from lastN
        this._lastN = this._lastN.filter((id) => id !== peer.id);

        delete this._peers[peer.id];

        // If this is the last Peer in the room close the room after a while.
        if (this.checkEmpty())
            this.selfDestructCountdown();
    }

    async _handleSocketRequest(peer, request, cb)
    {
        const router =
            this._mediasoupRouters.get(peer.routerId);

console.log(request.method);

        switch (request.method)
        {
            case 'getRouterRtpCapabilities':
            {
                cb(null, router.rtpCapabilities);
                break;
            }

            case 'join':
            {
                const {
                    nickname,
                    picture,
                    rtpCapabilities
                } = request.data;

                // Store client data into the Peer data object.
                peer.nickname = nickname;
                peer.picture = picture;
                peer.rtpCapabilities = rtpCapabilities;

                // Tell the new Peer about already joined Peers.
                // And also create Consumers for existing Producers.

                const joinedPeers = this.getPeers(peer);

                const peerInfos = joinedPeers
                    .map((joinedPeer) => (joinedPeer.peerInfo));

                cb(null, {
                    id: peer.id,
                    role: peer.role,
                    peers: peerInfos,
                    //chatHistory          : this._chatHistory,
                    //fileHistory          : this._fileHistory,
                    //lastNHistory         : this._lastN,
                    //locked               : this._locked,
                });

                for (const joinedPeer of joinedPeers)
                {
                    // Create Consumers for existing Producers.
                    for (const producer of joinedPeer.producers.values())
                    {
                        this._createConsumer(
                            {
                                consumerPeer : peer,
                                producerPeer : joinedPeer,
                                producer
                            });
                    }
                }

                // Notify the new Peer to all other Peers.
                for (const otherPeer of this.getPeers(peer))
                {
                    this._notification(
                        otherPeer.socket,
                        'newPeer',
                        peer.peerInfo
                    );
                }

                logger.debug(
                    'peer joined [peer: "%s", nickname: "%s", picture: "%s"]',
                    peer.id, nickname, picture);

                break;
            }

            case 'createWebRtcTransport':
            {
                // NOTE: Don't require that the Peer is joined here, so the client can
                // initiate mediasoup Transports and be ready when he later joins.

                const { forceTcp, producing, consuming } = request.data;

                const webRtcTransportOptions =
                {
                    ...config.mediasoup.webRtcTransport,
                    appData : { producing, consuming }
                };

                webRtcTransportOptions.enableTcp = true;

                if (forceTcp)
                    webRtcTransportOptions.enableUdp = false;
                else
                {
                    webRtcTransportOptions.enableUdp = true;
                    webRtcTransportOptions.preferUdp = true;
                }

                const transport = await router.createWebRtcTransport(
                    webRtcTransportOptions
                );

                transport.on('dtlsstatechange', (dtlsState) => {
                    if (dtlsState === 'failed' || dtlsState === 'closed') {
                        logger.warn('WebRtcTransport "dtlsstatechange" event [dtlsState:%s]', dtlsState);
                    }
                });

                // Store the WebRtcTransport into the Peer data Object.
                peer.addTransport(transport.id, transport);

                cb(
                    null,
                    {
                        id             : transport.id,
                        iceParameters  : transport.iceParameters,
                        iceCandidates  : transport.iceCandidates,
                        dtlsParameters : transport.dtlsParameters
                    });

                const { maxIncomingBitrate } = config.mediasoup.webRtcTransport;

                // If set, apply max incoming bitrate limit.
                if (maxIncomingBitrate)
                {
                    try { await transport.setMaxIncomingBitrate(maxIncomingBitrate); }
                    catch (error) {
                        logger.info("Setting the incoming bitrate failed")
                    }
                }

                break;
            }

            case 'connectWebRtcTransport':
            {
                const { transportId, dtlsParameters } = request.data;
                const transport = peer.getTransport(transportId);

                if (!transport)
                    throw new Error(`transport with id "${transportId}" not found`);

                await transport.connect({ dtlsParameters });

                cb();

                break;
            }

            case 'restartIce':
            {
                const { transportId } = request.data;
                const transport = peer.getTransport(transportId);

                if (!transport)
                    throw new Error(`transport with id "${transportId}" not found`);

                const iceParameters = await transport.restartIce();

                cb(null, iceParameters);

                break;
            }

            case 'produce':
            {
                let { appData } = request.data;

                if (
                    !appData.source ||
                    ![ 'mic', 'webcam', 'screen', 'extravideo' ]
                        .includes(appData.source)
                )
                    throw new Error('invalid producer source');

                if (
                    appData.source === 'mic' &&
                    !this._hasPermission(peer, Roles.PUBLISHER)
                )
                    throw new Error('peer not authorized');

                if (
                    appData.source === 'webcam' &&
                    !this._hasPermission(peer, Roles.PUBLISHER)
                )
                    throw new Error('peer not authorized');

                if (
                    appData.source === 'screen' &&
                    !this._hasPermission(peer, Roles.PUBLISHER)
                )
                    throw new Error('peer not authorized');

                const { transportId, kind, rtpParameters } = request.data;
                const transport = peer.getTransport(transportId);

                if (!transport)
                    throw new Error(`transport with id "${transportId}" not found`);

                // Add peerId into appData to later get the associated Peer during
                // the 'loudest' event of the audioLevelObserver.
                appData = { ...appData, peerId: peer.id };

                const producer =
                    await transport.produce({ kind, rtpParameters, appData });

                const pipeRouters = this._getRoutersToPipeTo(peer.routerId);

                for (const [ routerId, destinationRouter ] of this._mediasoupRouters)
                {
                    if (pipeRouters.includes(routerId))
                    {
                        await router.pipeToRouter({
                            producerId : producer.id,
                            router     : destinationRouter
                        });
                    }
                }

                // Store the Producer into the Peer data Object.
                peer.addProducer(producer.id, producer);
/*
                // Set Producer events.
                producer.on('score', (score) =>
                {
                    this._notification(peer.socket, 'producerScore', { producerId: producer.id, score });
                });
*/
                producer.on('videoorientationchange', (videoOrientation) =>
                {
                    logger.debug(
                        'producer "videoorientationchange" event [producerId:"%s", videoOrientation:"%o"]',
                        producer.id, videoOrientation);
                });

                cb(null, { id: producer.id });

                // Optimization: Create a server-side Consumer for each Peer.
                for (const otherPeer of this.getPeers(peer))
                {
                    this._createConsumer(
                        {
                            consumerPeer : otherPeer,
                            producerPeer : peer,
                            producer
                        });
                }

                // Add into the audioLevelObserver.
                if (kind === 'audio')
                {
                    this._audioLevelObserver.addProducer({ producerId: producer.id })
                        .catch(() => {});
                }

                break;
            }

            case 'closeProducer':
            {
                const { producerId } = request.data;
                const producer = peer.getProducer(producerId);

                if (!producer)
                    throw new Error(`producer with id "${producerId}" not found`);

                producer.close();

                // Remove from its map.
                peer.removeProducer(producer.id);

                cb();

                break;
            }

            case 'pauseProducer':
            {
                const { producerId } = request.data;
                const producer = peer.getProducer(producerId);

                if (!producer)
                    throw new Error(`producer with id "${producerId}" not found`);

                await producer.pause();

                cb();

                break;
            }

            case 'resumeProducer':
            {
                const { producerId } = request.data;
                const producer = peer.getProducer(producerId);

                if (!producer)
                    throw new Error(`producer with id "${producerId}" not found`);

                await producer.resume();

                cb();

                break;
            }

            case 'pauseConsumer':
            {
                const { consumerId } = request.data;
                const consumer = peer.getConsumer(consumerId);

                if (!consumer)
                    throw new Error(`consumer with id "${consumerId}" not found`);

                await consumer.pause();

                cb();

                break;
            }

            case 'resumeConsumer':
            {
                const { consumerId } = request.data;
                const consumer = peer.getConsumer(consumerId);

                if (!consumer)
                    throw new Error(`consumer with id "${consumerId}" not found`);

                await consumer.resume();

                cb();

                break;
            }
/*
            case 'requestConsumerKeyFrame':
            {
                const { consumerId } = request.data;
                const consumer = peer.getConsumer(consumerId);

                if (!consumer)
                    throw new Error(`consumer with id "${consumerId}" not found`);

                await consumer.requestKeyFrame();

                cb();

                break;
            }

            case 'getTransportStats':
            {
                const { transportId } = request.data;
                const transport = peer.getTransport(transportId);

                if (!transport)
                    throw new Error(`transport with id "${transportId}" not found`);

                const stats = await transport.getStats();

                cb(null, stats);

                break;
            }

            case 'getProducerStats':
            {
                const { producerId } = request.data;
                const producer = peer.getProducer(producerId);

                if (!producer)
                    throw new Error(`producer with id "${producerId}" not found`);

                const stats = await producer.getStats();

                cb(null, stats);

                break;
            }

            case 'getConsumerStats':
            {
                const { consumerId } = request.data;
                const consumer = peer.getConsumer(consumerId);

                if (!consumer)
                    throw new Error(`consumer with id "${consumerId}" not found`);

                const stats = await consumer.getStats();

                cb(null, stats);

                break;
            }
*/
            case 'changeNickname':
            {
                const { nickname } = request.data;

                peer.nickname = nickname;

                // This will be spread through events from the peer object

                // Return no error
                cb();

                break;
            }

            /* case 'changePicture':
            {
                const { picture } = request.data;

                peer.picture = picture;

                // Spread to others
                this._notification(peer.socket, 'changePicture', {
                    peerId  : peer.id,
                    picture : picture
                }, true);

                // Return no error
                cb();

                break;
            } */

            case 'chatMessage':
            {
                const { chatMessage } = request.data;

                this._chatHistory.push(chatMessage);

                // Spread to others
                this._notification(peer.socket, 'chatMessage', {
                    peerId      : peer.id,
                    chatMessage : chatMessage
                }, true);

                // Return no error
                cb();

                break;
            }

            case 'moderator:setRole':
            {
                if (!this._hasPermission(peer, Roles.MODERATOR))
                    throw new Error('peer not authorized');

                const { peerId, role } = request.data;

                const giveRolePeer = this._peers[peerId];

                if (!giveRolePeer)
                    throw new Error(`peer with id "${peerId}" not found`);

                // TODO: check if role is valid value

                // This will propagate the event automatically
                giveRolePeer.setRole(role);

                // Return no error
                cb();

                break;
            }
/*
            case 'moderator:clearChat':
            {
                if (!this._hasPermission(peer, Roles.MODERATOR))
                    throw new Error('peer not authorized');

                this._chatHistory = [];

                // Spread to others
                this._notification(peer.socket, 'moderator:clearChat', null, true);

                // Return no error
                cb();

                break;
            }
*/
            case 'raisedHand':
            {
                const { raisedHand } = request.data;

                peer.raisedHand = raisedHand;

                // Spread to others
                this._notification(peer.socket, 'raisedHand', {
                    peerId              : peer.id,
                    raisedHand          : raisedHand,
                    raisedHandTimestamp : peer.raisedHandTimestamp
                }, true);

                // Return no error
                cb();

                break;
            }

            case 'moderator:closeMeeting':
            {
                if (!this._hasPermission(peer, Roles.MODERATOR))
                    throw new Error('peer not authorized');

                this._notification(peer.socket, 'moderator:kick', null,    true);

                cb();

                // Close the room
                this.close();

                break;
            }
/*
            case 'moderator:kickPeer':
            {
                if (!this._hasPermission(peer, Roles.MODERATOR))
                    throw new Error('peer not authorized');

                const { peerId } = request.data;

                const kickPeer = this._peers[peerId];

                if (!kickPeer)
                    throw new Error(`peer with id "${peerId}" not found`);

                this._notification(kickPeer.socket, 'moderator:kick');

                kickPeer.close();

                cb();

                break;
            }

            case 'moderator:lowerHand':
            {
                if (!this._hasPermission(peer, Roles.MODERATOR))
                    throw new Error('peer not authorized');

                const { peerId } = request.data;

                const lowerPeer = this._peers[peerId];

                if (!lowerPeer)
                    throw new Error(`peer with id "${peerId}" not found`);

                this._notification(lowerPeer.socket, 'moderator:lowerHand');

                cb();

                break;
            }
*/
            default:
            {
                logger.error('unknown request.method "%s"', request.method);

                cb(500, `unknown request.method "${request.method}"`);
            }
        }
    }

    /**
     * Creates a mediasoup Consumer for the given mediasoup Producer.
     *
     * @async
     */
    async _createConsumer({ consumerPeer, producerPeer, producer })
    {
        logger.debug(
            '_createConsumer() [consumerPeer:"%s", producerPeer:"%s", producer:"%s"]',
            consumerPeer.id,
            producerPeer.id,
            producer.id
        );

        const router = this._mediasoupRouters.get(producerPeer.routerId);

        // Optimization:
        // - Create the server-side Consumer. If video, do it paused.
        // - Tell its Peer about it and wait for its response.
        // - Upon receipt of the response, resume the server-side Consumer.
        // - If video, this will mean a single key frame requested by the
        //   server-side Consumer (when resuming it).

        // NOTE: Don't create the Consumer if the remote Peer cannot consume it.
        if (
            !consumerPeer.rtpCapabilities ||
            !router.canConsume(
                {
                    producerId      : producer.id,
                    rtpCapabilities : consumerPeer.rtpCapabilities
                })
        )
        {
            return;
        }

        // Must take the Transport the remote Peer is using for consuming.
        const transport = consumerPeer.getConsumerTransport();

        // This should not happen.
        if (!transport)
        {
            logger.warn('_createConsumer() | Transport for consuming not found');

            return;
        }

        // Create the Consumer in paused mode.
        let consumer;

        try
        {
            consumer = await transport.consume(
                {
                    producerId      : producer.id,
                    rtpCapabilities : consumerPeer.rtpCapabilities,
                    paused          : producer.kind === 'video'
                });

            if (producer.kind === 'audio')
                await consumer.setPriority(255);
        }
        catch (error)
        {
            logger.warn('_createConsumer() | [error:"%o"]', error);

            return;
        }

        // Store the Consumer into the consumerPeer data Object.
        consumerPeer.addConsumer(consumer.id, consumer);

        // Set Consumer events.
        consumer.on('transportclose', () =>
        {
            // Remove from its map.
            consumerPeer.removeConsumer(consumer.id);
        });

        consumer.on('producerclose', () =>
        {
            // Remove from its map.
            consumerPeer.removeConsumer(consumer.id);

            this._notification(consumerPeer.socket, 'consumerClosed', { consumerId: consumer.id });
        });

        consumer.on('producerpause', () =>
        {
            this._notification(consumerPeer.socket, 'consumerPaused', { consumerId: consumer.id });
        });

        consumer.on('producerresume', () =>
        {
            this._notification(consumerPeer.socket, 'consumerResumed', { consumerId: consumer.id });
        });

        // Send a request to the remote Peer with Consumer parameters.
        try
        {
            await this._request(
                consumerPeer.socket,
                'newConsumer',
                {
                    peerId         : producerPeer.id,
                    kind           : consumer.kind,
                    producerId     : producer.id,
                    id             : consumer.id,
                    rtpParameters  : consumer.rtpParameters,
                    type           : consumer.type,
                    appData        : producer.appData,
                    producerPaused : consumer.producerPaused
                }
            );

            // Now that we got the positive response from the remote Peer and, if
            // video, resume the Consumer to ask for an efficient key frame.
            await consumer.resume();
        }
        catch (error)
        {
            logger.warn('_createConsumer() | [error:"%o"]', error);
        }
    }

    _hasPermission(peer, role)
    {
        return !!(peer.role & role);
    }

    /**
     * Get the list of peers.
     */
    getPeers(excludePeer = undefined)
    {
        return Object.values(this._peers)
            .filter((peer) => peer !== excludePeer);
    }

    _timeoutCallback(callback)
    {
        let called = false;

        const interval = setTimeout(
            () =>
            {
                if (called)
                    return;
                called = true;
                callback(new SocketTimeoutError('Request timed out'));
            },
            config.requestTimeout || 20000
        );

        return (...args) =>
        {
            if (called)
                return;
            called = true;
            clearTimeout(interval);

            callback(...args);
        };
    }

    _sendRequest(socket, method, data = {})
    {
        return new Promise((resolve, reject) =>
        {
            socket.emit(
                'request',
                { method, data },
                this._timeoutCallback((err, response) =>
                {
                    if (err)
                    {
                        reject(err);
                    }
                    else
                    {
                        resolve(response);
                    }
                })
            );
        });
    }

    async _request(socket, method, data)
    {
        logger.debug('_request() [method:"%s", data:"%o"]', method, data);

        const {
            requestRetries = 3
        } = config;

        for (let tries = 0; tries < requestRetries; tries++)
        {
            try
            {
                return await this._sendRequest(socket, method, data);
            }
            catch (error)
            {
                if (
                    error instanceof SocketTimeoutError &&
                    tries < requestRetries
                )
                    logger.warn('_request() | timeout, retrying [attempt:"%s"]', tries);
                else
                    throw error;
            }
        }
    }

    _notification(socket, method, data = {}, broadcast = false, includeSender = false)
    {
        if (broadcast)
        {
            socket.broadcast.to(this._roomId).emit(
                'notification', { method, data }
            );

            if (includeSender)
                socket.emit('notification', { method, data });
        }
        else
        {
            socket.emit('notification', { method, data });
        }
    }

    async _pipeProducersToRouter(routerId)
    {
        const router = this._mediasoupRouters.get(routerId);

        const peersToPipe =
            Object.values(this._peers)
                .filter((peer) => peer.routerId !== routerId && peer.routerId !== null);

        for (const peer of peersToPipe)
        {
            const srcRouter = this._mediasoupRouters.get(peer.routerId);

            for (const producerId of peer.producers.keys())
            {
                if (router._producers.has(producerId))
                {
                    continue;
                }

                await srcRouter.pipeToRouter({
                    producerId : producerId,
                    router     : router
                });
            }
        }
    }

    async _getRouterId()
    {
        const routerId = Room.getLeastLoadedRouter(
            this._mediasoupWorkers, this._allPeers, this._mediasoupRouters);

        await this._pipeProducersToRouter(routerId);

        return routerId;
    }

    // Returns an array of router ids we need to pipe to
    _getRoutersToPipeTo(originRouterId)
    {
        return Object.values(this._peers)
            .map((peer) => peer.routerId)
            .filter((routerId, index, self) =>
                routerId !== originRouterId && self.indexOf(routerId) === index
            );
    }

}

module.exports = Room;
