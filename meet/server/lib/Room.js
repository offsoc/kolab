const EventEmitter = require('events').EventEmitter;
const AwaitQueue = require('awaitqueue');
const crypto = require('crypto');
const Logger = require('./Logger');
const { SocketTimeoutError } = require('./errors');
const Roles = require('./userRoles');
const { v4: uuidv4 } = require('uuid');

const config = require('../config/config');

const logger = new Logger('Room');

const ROUTER_SCALE_SIZE = config.routerScaleSize || 40;

class Room extends EventEmitter {

    static calculateLoads(mediasoupWorkers, peers, mediasoupRouters) {
        const routerLoads = new Map();
        const workerLoads = new Map();
        const pipedRoutersIds = new Set();

        // Calculate router loads by adding up peers per router, and collected piped routers
        Object.values(peers).forEach(peer => {
            const routerId = peer.routerId;

            if (routerId) {
                if (mediasoupRouters.has(routerId)) {
                    pipedRoutersIds.add(routerId);
                }

                if (routerLoads.has(routerId)) {
                    routerLoads.set(routerId, routerLoads.get(routerId) + 1);
                } else {
                    routerLoads.set(routerId, 1);
                }
            }
        });

        // Calculate worker loads by adding up router loads per worker
        for (const worker of mediasoupWorkers) {
            for (const router of worker._routers) {
                const routerId = router._internal.routerId;

                if (workerLoads.has(worker._pid)) {
                    workerLoads.set(worker._pid, workerLoads.get(worker._pid) +
                        (routerLoads.has(routerId)?routerLoads.get(routerId):0));
                } else {
                    workerLoads.set(worker._pid,
                        (routerLoads.has(routerId)?routerLoads.get(routerId):0));
                }
            }
        }
        return {routerLoads, workerLoads, pipedRoutersIds};
    }

    /*
     * Find a router that is on a worker that is least loaded.
     *
     * A worker with a router that we are already piping to is preferred.
     */
    static getLeastLoadedRouter(mediasoupWorkers, peers, mediasoupRouters) {
        const {workerLoads, pipedRoutersIds} = Room.calculateLoads(mediasoupWorkers, peers, mediasoupRouters);

        const sortedWorkerLoads = new Map([ ...workerLoads.entries() ].sort(
            (a, b) => a[1] - b[1]));

        // we don't care about if router is piped, just choose the least loaded worker
        if (pipedRoutersIds.size === 0 ||
            pipedRoutersIds.size === mediasoupRouters.size) {
            const workerId = sortedWorkerLoads.keys().next().value;

            for (const worker of mediasoupWorkers) {
                if (worker._pid === workerId) {
                    for (const router of worker._routers) {
                        const routerId = router._internal.routerId;

                        if (mediasoupRouters.has(routerId)) {
                            return routerId;
                        }
                    }
                }
            }
        } else {
            // find if there is a piped router that is on a worker that is below limit
            for (const [ workerId, workerLoad ] of sortedWorkerLoads.entries()) {
                for (const worker of mediasoupWorkers) {
                    if (worker._pid === workerId) {
                        for (const router of worker._routers) {
                            const routerId = router._internal.routerId;

                            // on purpose we check if the worker load is below the limit,
                            // as in reality the worker load is imortant,
                            // not the router load
                            if (mediasoupRouters.has(routerId) &&
                                pipedRoutersIds.has(routerId) &&
                                workerLoad < ROUTER_SCALE_SIZE) {
                                return routerId;
                            }
                        }
                    }
                }
            }

            // no piped router found, we need to return router from least loaded worker
            const workerId = sortedWorkerLoads.keys().next().value;

            for (const worker of mediasoupWorkers) {
                if (worker._pid === workerId) {
                    for (const router of worker._routers) {
                        const routerId = router._internal.routerId;

                        if (mediasoupRouters.has(routerId)) {
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
     * @param {axios} webhook - An axios instance for webhook (http) requests
     */
    static async create({ mediasoupWorkers, peers, webhook }) {
        const roomId = uuidv4().substring(0, 16); // TODO: Use full uuid

        logger.info('create() [roomId:"%s"]', roomId);

        // Router media codecs.
        const mediaCodecs = config.mediasoup.router.mediaCodecs;

        const mediasoupRouters = new Map();

        for (const worker of mediasoupWorkers) {
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
            peers,
            webhook
        });
    }

    constructor({
        roomId,
        mediasoupRouters,
        audioLevelObserver,
        mediasoupWorkers,
        peers,
        webhook
    }) {
        logger.info('constructor() [roomId:"%s"]', roomId);

        super();
        this.setMaxListeners(Infinity);

        // this._uuid = uuidv4();

        this._mediasoupWorkers = mediasoupWorkers;

        // Room ID.
        this._roomId = roomId;

        // Closed flag.
        this._closed = false;

        // Joining queue
        this._queue = new AwaitQueue();

        this._peers = peers;

        this._selfDestructTimeout = null;

        // Array of mediasoup Router instances.
        this._mediasoupRouters = mediasoupRouters;

        this._audioLevelObserver = audioLevelObserver;

        this._webhook = webhook;
    }


    dumpStats() {
        const peers = this.getPeers();
        const {routerLoads, workerLoads, pipedRoutersIds} = Room.calculateLoads(this._mediasoupWorkers, peers, this._mediasoupRouters);
        let stats = {
            numberOfWorkers: this._mediasoupWorkers.length,
            numberOfRouters: this._mediasoupRouters.size,
            numberOfPeers: peers.length,
            routerLoads: routerLoads,
            workerLoads: workerLoads,
            pipedRoutersIds: pipedRoutersIds,
        };
        console.log(stats);
    }

    close() {
        logger.debug('close()');

        this._closed = true;

        this._queue.close();

        this._queue = null;

        if (this._selfDestructTimeout)
            clearTimeout(this._selfDestructTimeout);

        this._selfDestructTimeout = null;

        // Close the peers.
        Object.values(this._peers).forEach(peer => {
            if (!peer.closed)
                peer.close();
        });

        this._peers = {};

        // Close the mediasoup Routers.
        for (const router of this._mediasoupRouters.values()) {
            router.close();
        }

        this._mediasoupRouters.clear();

        this._mediasoupWorkers = null;

        this._audioLevelObserver = null;

        // Emit 'close' event.
        this.emit('close');
    }

    handlePeer({ peer }) {
        logger.info('handlePeer() [peer:"%s", role:%s]', peer.id, peer.role);

        // Should not happen
        if (this._peers[peer.id]) {
            logger.warn(
                'handleConnection() | there is already a peer with same peerId [peer:"%s"]',
                peer.id);
        }

        this._peerJoining(peer);
    }

    logStatus() {
        logger.info(
            'logStatus() [room id:"%s", peers:"%s"]',
            this._roomId,
            Object.keys(this._peers).length
        );
    }

    dump() {
        return {
            roomId : this._roomId,
            peers  : Object.keys(this._peers).length
        };
    }

    get id() {
        return this._roomId;
    }

    selfDestructCountdown() {
        logger.debug('selfDestructCountdown() started');

        clearTimeout(this._selfDestructTimeout);

        this._selfDestructTimeout = setTimeout(() => {
            if (this._closed)
                return;

            if (this.checkEmpty()) {
                logger.info(
                    'Room deserted for some time, closing the room [roomId:"%s"]',
                    this._roomId);
                this.close();
            } else
                logger.debug('selfDestructCountdown() aborted; room is not empty!');
        }, 10000);
    }

    checkEmpty() {
        return Object.keys(this._peers).length === 0;
    }

    _getTURNCredentials(name, secret) {
        const unixTimeStamp = parseInt(Date.now()/1000) + 24*3600; // this credential would be valid for the next 24 hours
        // If there is no name, the timestamp alone can also be used.
        const username = name ? `${unixTimeStamp}:${name}` : `${unixTimeStamp}`;
        const hmac = crypto.createHmac('sha1', secret);
        hmac.setEncoding('base64');
        hmac.write(username);
        hmac.end();
        const password = hmac.read();
        return {
            username,
            password
        };
    }

    _peerJoining(peer) {
        this._queue.push(async () => {
            peer.socket.join(this._roomId);

            this._peers[peer.id] = peer;

            // Assign routerId
            peer.routerId = await this._getRouterId();

            this._handlePeer(peer);

            let iceServers;

            if (config.turn) {
                // Generate time-limited credentials. The name is only relevant for the logs.
                const {username, password} = this._getTURNCredentials(peer.id, config.turn.staticSecret);

                iceServers = [ {
                    urls       : config.turn.urls,
                    username   : username,
                    credential : password
                } ];
            }

            this._notification(peer.socket, 'roomReady', { iceServers });
        })
            .catch((error) => {
                logger.error('_peerJoining() [error:"%o"]', error);
            });
    }

    _handlePeer(peer) {
        logger.debug('_handlePeer() [peer:"%s"]', peer.id);

        peer.on('close', () => {
            this._handlePeerClose(peer);
        });

        peer.on('nicknameChanged', () => {
            // Spread to others (and self)
            const data = { peerId: peer.id, nickname: peer.nickname };
            this._notification(peer.socket, 'changeNickname', data, true, true);
        });

        peer.on('languageChanged', () => {
            // Spread to others (and self)
            const data = { peerId: peer.id, language: peer.language };
            this._notification(peer.socket, 'changeLanguage', data, true, true);
        });

        peer.on('roleChanged', () => {
            // Spread to others (and self)
            const data = { peerId: peer.id, role: peer.role };
            this._notification(peer.socket, 'changeRole', data, true, true);
        });

        peer.on('raisedHandChanged', () => {
            // Spread to others (and self)
            const data = { peerId: peer.id, raisedHand: peer.raisedHand };
            this._notification(peer.socket, 'changeRaisedHand', data, true, true);
        });

        peer.socket.on('request', (request, cb) => {
            logger.debug(
                'Peer "request" event [method:"%s", peerId:"%s"]',
                request.method, peer.id);

            this._handleSocketRequest(peer, request, cb)
                .catch((error) => {
                    logger.error('"request" failed [error:"%o"]', error);

                    cb(error);
                });
        });

        // Peer left before we were done joining
        if (peer.closed)
            this._handlePeerClose(peer);
    }

    _handlePeerClose(peer) {
        logger.debug('_handlePeerClose() [peer:"%s"]', peer.id);

        if (this._closed)
            return;

        this._notification(peer.socket, 'peerClosed', { peerId: peer.id }, true);

        delete this._peers[peer.id];

        // If this is the last Peer in the room close the room after a while.
        if (this.checkEmpty())
            this.selfDestructCountdown();
    }

    async _handleSocketRequest(peer, request, cb) {
        const router = this._mediasoupRouters.get(peer.routerId);

        switch (request.method) {
        case 'getRouterRtpCapabilities':
        {
            cb(null, router.rtpCapabilities);
            break;
        }

        case 'dumpStats':
        {
            this.dumpStats()

            cb(null);
            break;
        }

        case 'join':
        {
            const {
                nickname,
                rtpCapabilities
            } = request.data;

            // Store client data into the Peer data object.
            peer.nickname = nickname;
            peer.rtpCapabilities = rtpCapabilities;

            // Tell the new Peer about already joined Peers.
            const otherPeers = this.getPeers(peer);

            const peerInfos = otherPeers.map(otherPeer => otherPeer.peerInfo);

            cb(null, { peers: peerInfos, ...peer.peerInfo });

            // Create Consumers for existing Producers.
            for (const otherPeer of otherPeers) {
                for (const producer of otherPeer.producers.values()) {
                    this._createConsumer({
                        consumerPeer: peer,
                        producerPeer: otherPeer,
                        producer
                    });
                }
            }

            // Notify the new Peer to all other Peers.
            this._notification(peer.socket, 'newPeer', peer.peerInfo, true);

            logger.debug(
                'peer joined [peer: "%s", nickname: "%s"]',
                peer.id, nickname);

            break;
        }

        case 'createPlainTransport':
        {
            const { producing, consuming } = request.data;

            const transport = await router.createPlainTransport(
                {
                    //When consuming we manually connect using connectPlainTransport,
                    //otherwise we let the port autodetection work.
                    comedia: producing,
                    // FFmpeg and GStreamer don't support RTP/RTCP multiplexing ("a=rtcp-mux" in SDP)
                    rtcpMux: false,
                    listenIp: { ip: "127.0.0.1", announcedIp: null },
                    appData : { producing, consuming }
                }
            );
                // await transport.enableTraceEvent([ "probation", "bwe" ]);
                // transport.on("trace", (trace) => {
                //     console.log(trace);
                // });

            peer.addTransport(transport.id, transport);

            cb(
                null,
                {
                    id       : transport.id,
                    ip       : transport.tuple.localIp,
                    port     : transport.tuple.localPort,
                    rtcpPort : transport.rtcpTuple ? transport.rtcpTuple.localPort : undefined
                });

            break;
        }

        case 'connectPlainTransport':
        {
            const { transportId, ip, port, rtcpPort } = request.data;
            const transport = peer.getTransport(transportId);

            if (!transport)
                throw new Error(`transport with id "${transportId}" not found`);

            await transport.connect({
                ip: ip,
                port: port,
                rtcpPort: rtcpPort,
            });

            cb();

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
            else {
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
            if (maxIncomingBitrate) {
                try {
                    await transport.setMaxIncomingBitrate(maxIncomingBitrate); 
                } catch (error) {
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
        /*
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
        */
        case 'produce':
        {
            let { appData } = request.data;

            if (!appData.source || ![ 'mic', 'webcam', 'screen' ].includes(appData.source))
                throw new Error('invalid producer source');

            if (appData.source === 'mic' && !peer.hasRole(Roles.PUBLISHER))
                throw new Error('peer not authorized');

            if (appData.source === 'webcam' && !peer.hasRole(Roles.PUBLISHER))
                throw new Error('peer not authorized');

            if (appData.source === 'screen' && !peer.hasRole(Roles.PUBLISHER))
                throw new Error('peer not authorized');

            const { transportId, kind, rtpParameters } = request.data;
            const transport = peer.getTransport(transportId);

            if (!transport)
                throw new Error(`transport with id "${transportId}" not found`);

            // Add peerId into appData to later get the associated Peer during
            // the 'loudest' event of the audioLevelObserver.
            appData = { ...appData, peerId: peer.id };

            const producer = await transport.produce({ kind, rtpParameters, appData });

            const pipeRouters = this._getRoutersToPipeTo(peer.routerId);

            for (const [ routerId, destinationRouter ] of this._mediasoupRouters) {
                if (pipeRouters.includes(routerId)) {
                    await router.pipeToRouter({
                        producerId : producer.id,
                        router     : destinationRouter
                    });
                }
            }

            // Store the Producer into the Peer data Object.
            peer.addProducer(producer.id, producer);

            producer.on('videoorientationchange', (videoOrientation) => {
                logger.debug(
                    'producer "videoorientationchange" event [producerId:"%s", videoOrientation:"%o"]',
                    producer.id, videoOrientation);
            });

            // Trace individual packets for debugging
            // await producer.enableTraceEvent([ "rtp", "pli", "keyframe", "nack" ]);
            // producer.on("trace", (trace) => {
            //     console.log(`Trace on ${producer.id}`, trace);
            // });

            cb(null, { id: producer.id });

            // Optimization: Create a server-side Consumer for each Peer.
            for (const otherPeer of this.getPeers(peer)) {
                this._createConsumer({
                    consumerPeer: otherPeer,
                    producerPeer: peer,
                    producer
                });
            }

            // Add into the audioLevelObserver.
            if (kind === 'audio') {
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

        case 'changeNickname':
        {
            const { nickname } = request.data;

            peer.nickname = nickname;

            // This will be spread through events from the peer object

            // Return no error
            cb();

            break;
        }

        case 'chatMessage':
        {
            const { message } = request.data;

            // Spread to others
            this._notification(peer.socket, 'chatMessage', {
                peerId: peer.id,
                nickname: peer.nickname,
                message: message
            }, true, true);

            // Return no error
            cb();

            break;
        }

        case 'moderator:addRole':
        {
            if (!peer.hasRole(Roles.MODERATOR))
                throw new Error('peer not authorized');

            const { peerId, role } = request.data;

            const rolePeer = this._peers[peerId];

            if (!rolePeer)
                throw new Error(`peer with id "${peerId}" not found`);

            if (!rolePeer.isValidRole(role))
                throw new Error('invalid role');

            if (!rolePeer.hasRole(role)) {
                // The 'owner' role is not assignable
                if (role & Roles.OWNER)
                    throw new Error('the OWNER role is not assignable');

                // Promotion to publisher? Put the user hand down
                if (role & Roles.PUBLISHER && rolePeer.raisedHand)
                    rolePeer.raisedHand = false;

                // This will propagate the event automatically
                rolePeer.setRole(rolePeer.role | role);
            }

            // Return no error
            cb();

            break;
        }

        case 'moderator:removeRole':
        {
            if (!peer.hasRole(Roles.MODERATOR))
                throw new Error('peer not authorized');

            const { peerId, role } = request.data;

            const rolePeer = this._peers[peerId];

            if (!rolePeer)
                throw new Error(`peer with id "${peerId}" not found`);

            if (!rolePeer.isValidRole(role))
                throw new Error('invalid role');

            if (rolePeer.hasRole(role)) {
                if (role & Roles.OWNER)
                    throw new Error('the OWNER role is not removable');

                if (role & Roles.MODERATOR && rolePeer.role & Roles.OWNER)
                    throw new Error('the MODERATOR role cannot be removed from the OWNER');

                // Non-publisher cannot be a language interpreter
                if (role & Roles.PUBLISHER)
                    rolePeer.language = null;

                // This will propagate the event automatically
                rolePeer.setRole(rolePeer.role ^ role);
            }

            // Return no error
            cb();

            break;
        }

        case 'moderator:changeLanguage':
        {
            if (!peer.hasRole(Roles.MODERATOR))
                throw new Error('peer not authorized');

            const { peerId, language } = request.data;

            if (language && !/^[a-z]{2}$/.test(language))
                throw new Error('invalid language code');

            const langPeer = this._peers[peerId];

            if (!langPeer)
                throw new Error(`peer with id "${peerId}" not found`);

            langPeer.language = language;

            // This will be spread through events from the peer object

            // Return no error
            cb();

            break;
        }

        case 'moderator:joinRequestAccept':
        {
            if (!peer.hasRole(Roles.MODERATOR))
                throw new Error('peer not authorized');

            const { requestId } = request.data;

            // Return no error
            cb();

            if (this._webhook) {
                this._webhook.post('', { requestId, roomId: this._roomId, event: 'joinRequestAccepted' })
                    .then(function (/* response */) {
                        logger.info(`Accepted join request ${requestId}. Webhook succeeded.`);
                    })
                    .catch(function (error) {
                        logger.error(error);
                    });
            }

            break;
        }

        case 'moderator:joinRequestDeny':
        {
            if (!peer.hasRole(Roles.MODERATOR))
                throw new Error('peer not authorized');

            const { requestId } = request.data;

            // Return no error
            cb();

            if (this._webhook) {
                this._webhook.post('', { requestId, roomId: this._roomId, event: 'joinRequestDenied' })
                    .then(function (/* response */) {
                        logger.info(`Denied join request ${requestId}. Webhook succeeded.`);
                    })
                    .catch(function (error) {
                        logger.error(error);
                    });
            }

            break;
        }

        case 'moderator:closeRoom':
        {
            if (!peer.hasRole(Roles.OWNER))
                throw new Error('peer not authorized');

            this._notification(peer.socket, 'moderator:closeRoom', null, true);

            cb();

            // Close the room
            this.close();

            // TODO: remove the room?

            break;
        }

        case 'moderator:kickPeer':
        {
            if (!peer.hasRole(Roles.MODERATOR))
                throw new Error('peer not authorized');

            const { peerId } = request.data;

            const kickPeer = this._peers[peerId];

            if (!kickPeer)
                throw new Error(`peer with id "${peerId}" not found`);

            this._notification(kickPeer.socket, 'moderator:kickPeer');

            kickPeer.close();

            cb();

            break;
        }

        case 'raisedHand':
        {
            const { raisedHand } = request.data;

            peer.raisedHand = raisedHand;

            // This will be spread through events from the peer object

            // Return no error
            cb();

            break;
        }

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
    async _createConsumer({ consumerPeer, producerPeer, producer }) {
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
        ) {
            return;
        }

        // Must take the Transport the remote Peer is using for consuming.
        const transport = consumerPeer.getConsumerTransport();

        // This should not happen.
        if (!transport) {
            logger.warn('_createConsumer() | Transport for consuming not found');

            return;
        }

        // Create the Consumer in paused mode.
        let consumer;

        try {
            consumer = await transport.consume(
                {
                    producerId      : producer.id,
                    rtpCapabilities : consumerPeer.rtpCapabilities,
                    paused          : producer.kind === 'video'
                });

            if (producer.kind === 'audio')
                await consumer.setPriority(255);
        } catch (error) {
            logger.warn('_createConsumer() | [error:"%o"]', error);

            return;
        }

        // Trace individual packets for debugging
        // await consumer.enableTraceEvent([ "rtp", "pli", "fir" ]);
        // consumer.on("trace", (trace) => {
        //     console.log(`Trace on ${consumer.id}`, trace);
        // });

        // Store the Consumer into the consumerPeer data Object.
        consumerPeer.addConsumer(consumer.id, consumer);

        // Set Consumer events.
        consumer.on('transportclose', () => {
            // Remove from its map.
            consumerPeer.removeConsumer(consumer.id);
        });

        consumer.on('producerclose', () => {
            // Remove from its map.
            consumerPeer.removeConsumer(consumer.id);

            this._notification(consumerPeer.socket, 'consumerClosed', { consumerId: consumer.id });
        });

        // TODO: We don't have to send websocket signals on producerpause/producerresume
        //       The same can be achieved on the client-side using consumer.observer.on('pause')
        //       and consumer.observer.on('resume')

        consumer.on('producerpause', () => {
            this._notification(consumerPeer.socket, 'consumerPaused', { consumerId: consumer.id });
        });

        consumer.on('producerresume', () => {
            this._notification(consumerPeer.socket, 'consumerResumed', { consumerId: consumer.id });
        });

        // Send a request to the remote Peer with Consumer parameters.
        try {
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
        } catch (error) {
            logger.warn('_createConsumer() | [error:"%o"]', error);
        }
    }

    /**
     * Get the list of peers.
     */
    getPeers(excludePeer = undefined) {
        return Object.values(this._peers)
            .filter((peer) => peer !== excludePeer);
    }

    _timeoutCallback(callback) {
        let called = false;

        const interval = setTimeout(
            () => {
                if (called)
                    return;
                called = true;
                callback(new SocketTimeoutError('Request timed out'));
            },
            config.requestTimeout || 20000
        );

        return (...args) => {
            if (called)
                return;
            called = true;
            clearTimeout(interval);

            callback(...args);
        };
    }

    _sendRequest(socket, method, data = {}) {
        return new Promise((resolve, reject) => {
            socket.emit(
                'request',
                { method, data },
                this._timeoutCallback((err, response) => {
                    if (err) {
                        reject(err);
                    } else {
                        resolve(response);
                    }
                })
            );
        });
    }

    async _request(socket, method, data) {
        logger.debug('_request() [method:"%s", data:"%o"]', method, data);

        const {
            requestRetries = 3
        } = config;

        for (let tries = 0; tries < requestRetries; tries++) {
            try {
                return await this._sendRequest(socket, method, data);
            } catch (error) {
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

    _notification(socket, method, data = {}, broadcast = false, includeSender = false) {
        if (broadcast) {
            socket.broadcast.to(this._roomId).emit(
                'notification', { method, data }
            );

            if (includeSender)
                socket.emit('notification', { method, data });
        } else {
            socket.emit('notification', { method, data });
        }
    }

    /*
     * Pipe producers of peers that are running under another router to this router.
     */
    async _pipeProducersToRouter(routerId) {
        const router = this._mediasoupRouters.get(routerId);

        // All peers that have a different router
        const peersToPipe =
            Object.values(this._peers)
                .filter((peer) => peer.routerId !== routerId && peer.routerId !== null);

        for (const peer of peersToPipe) {
            const srcRouter = this._mediasoupRouters.get(peer.routerId);

            for (const producerId of peer.producers.keys()) {
                if (router._producers.has(producerId)) {
                    continue;
                }

                await srcRouter.pipeToRouter({
                    producerId : producerId,
                    router     : router
                });
            }
        }
    }

    async _getRouterId() {
        const routerId = Room.getLeastLoadedRouter(
            this._mediasoupWorkers, this._peers, this._mediasoupRouters);

        await this._pipeProducersToRouter(routerId);

        return routerId;
    }

    // Returns an array of router ids we need to pipe to:
    // The combined set of routers of all peers, exluding the router of the peer itself.
    _getRoutersToPipeTo(originRouterId) {
        return Object.values(this._peers)
            .map((peer) => peer.routerId)
            .filter((routerId, index, self) =>
                routerId !== originRouterId && self.indexOf(routerId) === index
            );
    }
}

module.exports = Room;
