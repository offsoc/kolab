#!/usr/bin/env node

process.title = 'kolabmeet-server';

const config = require('./config/config');
const fs = require('fs');
const http = require('http');
const spdy = require('spdy');
const express = require('express');
const bodyParser = require('body-parser');
const compression = require('compression');
const mediasoup = require('mediasoup');
const Logger = require('./lib/Logger');
const Room = require('./lib/Room');
const Peer = require('./lib/Peer');
const helmet = require('helmet');
const axios = require('axios');
const interactiveServer = require('./lib/interactiveServer');
const { v4: uuidv4 } = require('uuid');

/* eslint-disable no-console */
console.log('- process.env.DEBUG:', process.env.DEBUG);
console.log('- config.mediasoup.worker.logLevel:', config.mediasoup.worker.logLevel);
console.log('- config.mediasoup.worker.logTags:', config.mediasoup.worker.logTags);
/* eslint-enable no-console */


if (!config.mediasoup.webRtcTransport.listenIps[0].ip) {
    console.error('A webrtc listen ip is reuquired');
    process.exit(3)
}

const logger = new Logger();

// mediasoup Workers.
// @type {Array<mediasoup.Worker>}
const mediasoupWorkers = [];

// Map of Room instances indexed by roomId.
const rooms = new Map();

// Map of Peer instances indexed by peerId.
const peers = new Map();

// HTTP client instance for webhook "pushes"
let webhook = null;
if (config.webhookURL) {
    webhook = axios.create({
        baseURL: config.webhookURL,
        headers: { 'X-Auth-Token': config.webhookToken },
        timeout: 5000
    });
}

const app = express();

app.use(helmet.hsts());

app.use((req, res, next) => {
    if (req.get('X-Auth-Token') !== config.authToken) {
        logger.debug("X-Auth-Token mismatch")
        res.status(403).send();
    } else {
        next();
    }
});

app.use(bodyParser.json({ limit: '5mb' }));
app.use(bodyParser.urlencoded({ limit: '5mb', extended: true }));

let mainListener;
let io;

async function run() {
    try {
        await interactiveServer(rooms, peers);
        await runMediasoupWorkers();
        await runHttpsServer();
        await runWebSocketServer();

        // eslint-disable-next-line no-unused-vars
        const errorHandler = (err, req, res, next) => {
            const trackingId = uuidv4();

            res.status(500).send(
                `<h1>Internal Server Error</h1>
                <p>If you report this error, please also report this
                <i>tracking ID</i> which makes it possible to locate your session
                in the logs which are available to the system administrator:
                <b>${trackingId}</b></p>`
            );
            logger.error(
                'Express error handler dump with tracking ID: %s, error dump: %o',
                trackingId, err);
        };

        app.use(errorHandler);
    } catch (error) {
        logger.error('run() [error:"%o"]', error);
    }

    app.emit('ready');
}

async function runHttpsServer() {
    app.use(compression());

    app.get(`${config.pathPrefix}/api/stats`, async function (req, res) {
        let stats = {};
        for (const room of rooms) {
            let roomStats;
            for (const peer of Object.values(room._peers)) {
                let peerStats = {
                    id: peer.id,
                    nickname: peer._nickname,
                    consumers: [],
                    producers: [],
                    transports: [],
                };
                for (const entry of peer._consumers.values()) {
                    peerStats.consumers.push(await entry.getStats())
                }
                for (const entry of peer._producers.values()) {
                    peerStats.producers.push(await entry.getStats())
                }
                for (const entry of peer._transports.values()) {
                    peerStats.transports.push(await entry.getStats())
                }
                roomStats[peer.id] = peerStats;
            }
            stats[room.id] = roomStats;
        }
        res.send(stats);
    });

    app.get(`${config.pathPrefix}/api/health`, function (req, res) {
        res.send({ success: true, message: "Healthy" });
    });

    app.get(`${config.pathPrefix}/api/ping`, function (req, res) {
        res.send('PONG');
    })

    app.get(`${config.pathPrefix}/api/sessions`, function (req, res) {
        let list = [];
        rooms.forEach(room => {
            list.push({
                roomId: room.id,
                createdAt: room.createdAt
            })
        })

        res.json(list)
    })

    // Check if the room exists
    app.get(`${config.pathPrefix}/api/sessions/:session_id`, function (req, res) {
        const room = rooms.get(req.params.session_id);

        if (!room) {
            res.status(404).send();
        } else {
            res.status(200).send();
        }
    })

    // Create room and return id
    app.post(`${config.pathPrefix}/api/sessions`, async function (req, res) {
        console.log("Creating new room");

        const room = await createRoom();

        res.json({
            id : room.id
        })
    })

    // Send a websocket notification signals to the room participants
    app.post(`${config.pathPrefix}/api/signal`, async function (req, res) {
        const data = req.body;
        const roomId = data.roomId;
        const emit = (socket) => {
            socket.emit('notification', {
                method: `signal:${data.type}`,
                data: data.data
            })
        };

        if ('role' in data) {
            peers.forEach(peer => {
                if (peer.socket && peer.roomId == roomId && peer.hasRole(data.role)) {
                    emit(peer.socket);
                }
            })
        } else {
            emit(io.to(roomId));
        }

        res.json({});
    });

    // Create connection in room (just wait for websocket instead?
    // $post = [
    //     'json' => [
    //         'role' => self::OV_ROLE_PUBLISHER,
    //         'data' => json_encode(['role' => $role])
    //     ]
    // ];
    app.post(`${config.pathPrefix}/api/sessions/:session_id/connection`, function (req, res) {
        logger.info('Creating peer connection [roomId:"%s"]', req.params.session_id);

        const roomId = req.params.session_id;
        const room = rooms.get(roomId);

        if (!room) {
            res.status(404).send();
            return;
        }

        const peer = new Peer({ roomId });

        peers.set(peer.id, peer);

        peer.on('close', () => {
            peers.delete(peer.id);
        });

        const data = req.body;

        if ('role' in data)
            peer.setRole(data.role);

        const proto = config.tls || config.forceWSS ? 'wss' : 'ws';

        res.json({
            id: peer.id,
            // Note: socket.io client will end up using (hardcoded) /meetmedia/signaling path
            token: `${proto}://${config.publicDomain}?peerId=${peer.id}&roomId=${roomId}&authToken=${peer.authToken}`
        });
    })

    if (config.tls) {
        // TLS server configuration.
        const tls = {
            cert: fs.readFileSync(config.tls.cert),
            key: fs.readFileSync(config.tls.key),
            secureOptions: 'tlsv12',
            ciphers: [
                'ECDHE-ECDSA-AES128-GCM-SHA256',
                'ECDHE-RSA-AES128-GCM-SHA256',
                'ECDHE-ECDSA-AES256-GCM-SHA384',
                'ECDHE-RSA-AES256-GCM-SHA384',
                'ECDHE-ECDSA-CHACHA20-POLY1305',
                'ECDHE-RSA-CHACHA20-POLY1305',
                'DHE-RSA-AES128-GCM-SHA256',
                'DHE-RSA-AES256-GCM-SHA384'
            ].join(':'),
            honorCipherOrder: true
        };

        mainListener = spdy.createServer(tls, app);
    } else {
        mainListener = http.createServer(app);
    }
    console.info(`Listening on ${config.listeningPort} ${config.listeningHost}`)
    mainListener.listen(config.listeningPort, config.listeningHost);
}

/**
 * Create a WebSocketServer to allow WebSocket connections from browsers.
 */
async function runWebSocketServer() {
    io = require('socket.io')(mainListener, {
        path: `${config.pathPrefix}/signaling`,
        cookie: false
    });

    // Handle connections from clients.
    io.on('connection', async (socket) => {
        const { roomId, peerId, authToken } = socket.handshake.query;

        if (!roomId || !peerId || !authToken) {
            logger.warn('connection request without roomId and/or peerId');
            socket.disconnect(true);
            return;
        }

        logger.info('connection request [roomId:"%s", peerId:"%s"]', roomId, peerId);

        try {
            const room = rooms.get(roomId);

            if (!room) {
                logger.warn("Room does not exist %s", roomId);
                socket.disconnect(true);
                return;
            }

            const peer = peers.get(peerId);

            if (!peer || peer.roomId != roomId || peer.authToken != authToken) {
                logger.warn("Peer does not exist %s", peerId);
                socket.disconnect(true);
                return;
            }

            await room.joinRoom({ peer, socket });
        } catch (error) {
            logger.error('room creation or room joining failed [error:"%o"]', error);

            if (socket)
                socket.disconnect(true);
        }
    });
}

/**
 * Launch as many mediasoup Workers as given in the configuration file.
 */
async function runMediasoupWorkers() {
    const { numWorkers } = config.mediasoup;

    logger.info('running %d mediasoup Workers...', numWorkers);

    for (let i = 0; i < numWorkers; ++i) {
        const worker = await mediasoup.createWorker(
            {
                logLevel   : config.mediasoup.worker.logLevel,
                logTags    : config.mediasoup.worker.logTags,
                rtcMinPort : config.mediasoup.worker.rtcMinPort,
                rtcMaxPort : config.mediasoup.worker.rtcMaxPort
            });

        worker.on('died', () => {
            logger.error(
                'mediasoup Worker died, exiting  in 2 seconds... [pid:%d]', worker.pid);

            setTimeout(() => process.exit(1), 2000);
        });

        mediasoupWorkers.push(worker);

        // Create a WebRtcServer in this Worker.
        // Each mediasoup Worker will run its own WebRtcServer, so those cannot
        // share the same listening ports. Hence we increase the value in config.js
        // for each Worker.
        const webRtcServerOptions = JSON.parse(JSON.stringify(config.mediasoup.webRtcServerOptions));
        const portIncrement = mediasoupWorkers.length - 1;

        for (const listenInfo of webRtcServerOptions.listenInfos) {
            listenInfo.port += portIncrement;
        }

        const webRtcServer = await worker.createWebRtcServer(webRtcServerOptions);
        worker.appData.webRtcServer = webRtcServer;

    }
}

async function getLeastLoadedWorker() {
    let workerLoads = new Map();
    for (const worker of mediasoupWorkers) {
        workerLoads.set(worker.pid, 0);
    }

    for (const peer of peers.values()) {
        if (peer.workerId) {
            const workerId = peer.workerId;
            workerLoads.set(workerId, workerLoads.get(workerId) + 1);
        }
    }

    const sortedWorkerLoads = new Map([ ...workerLoads.entries() ].sort(
        (a, b) => a[1] - b[1]));

    const workerId = sortedWorkerLoads.keys().next().value;
    return mediasoupWorkers.find((worker) => worker.pid == workerId)
}

/**
 * Get a Room instance (or create one if it does not exist).
 */
async function createRoom() {
    logger.info('creating a new Room');

    // Create the room
    const room = await Room.create({ getLeastLoadedWorker });

    room.on('close', () => {
        logger.info('closing a Room [roomId:"%s"]', room.id);

        rooms.delete(room.id);

        if (webhook) {
            webhook.post('', { roomId: room.id, event: 'roomClosed' })
                .then(function (/* response */) {
                    logger.info(`Room ${room.id} closed. Webhook succeeded.`);
                })
                .catch(function (error) {
                    logger.error(error);
                });
        }
    });

    room.on('joinRequestAccepted', (requestId) => {
        if (webhook) {
            webhook.post('', { requestId, roomId: room.id, event: 'joinRequestAccepted' })
                .then(function (/* response */) {
                    logger.info(`Accepted join request ${requestId}. Webhook succeeded.`);
                })
                .catch(function (error) {
                    logger.error(error);
                });
        }
    });

    room.on('joinRequestDenied', (requestId) => {
        if (webhook) {
            webhook.post('', { requestId, roomId: room.id, event: 'joinRequestDenied' })
                .then(function (/* response */) {
                    logger.info(`Denied join request ${requestId}. Webhook succeeded.`);
                })
                .catch(function (error) {
                    logger.error(error);
                });
        }
    });

    rooms.set(room.id, room);

    return room;
}

run();

module.exports = app; // export for testing
