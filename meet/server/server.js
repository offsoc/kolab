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
const AwaitQueue = require('awaitqueue');
const Logger = require('./lib/Logger');
const Room = require('./lib/Room');
const Peer = require('./lib/Peer');
const helmet = require('helmet');
const axios = require('axios');
const interactiveServer = require('./lib/interactiveServer');
const promExporter = require('./lib/promExporter');
const { v4: uuidv4 } = require('uuid');

/* eslint-disable no-console */
console.log('- process.env.DEBUG:', process.env.DEBUG);
console.log('- config.mediasoup.worker.logLevel:', config.mediasoup.worker.logLevel);
console.log('- config.mediasoup.worker.logTags:', config.mediasoup.worker.logTags);
/* eslint-enable no-console */

const logger = new Logger();

const queue = new AwaitQueue();

let statusLogger = null;

if ('StatusLogger' in config)
    statusLogger = new config.StatusLogger();

// mediasoup Workers.
// @type {Array<mediasoup.Worker>}
const mediasoupWorkers = [];

// Map of Room instances indexed by roomId.
const rooms = new Map();

// Map of Peer instances indexed by peerId.
const peers = new Map();

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

// HTTP client instance for webhook "pushes"
let webhook = null;
if (config.webhookURL) {
    webhook = axios.create({
        baseURL: config.webhookURL,
        timeout: 5000
    });
}

const app = express();

app.use(helmet.hsts());

app.use(bodyParser.json({ limit: '5mb' }));
app.use(bodyParser.urlencoded({ limit: '5mb', extended: true }));

if (config.trustProxy) {
    app.set('trust proxy', config.trustProxy);
}

let mainListener;
let io;

async function run() {
    try {
        // Open the interactive server.
        await interactiveServer(rooms, peers);

        // start Prometheus exporter
        if (config.prometheus) {
            await promExporter(rooms, peers, config.prometheus);
        }

        // Run a mediasoup Worker.
        await runMediasoupWorkers();

        // Run HTTPS server.
        await runHttpsServer();

        // Run WebSocketServer.
        await runWebSocketServer();

        const errorHandler = (err, req, res /*, next */) => {
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

function statusLog() {
    if (statusLogger) {
        statusLogger.log({ rooms, peers });
    }
}

async function runHttpsServer() {
    app.use(compression());

    app.get(`${config.pathPrefix}/api/ping`, function (req, res /*, next*/) {
        res.send('PONG');
    })

    app.get(`${config.pathPrefix}/api/sessions`, function (req, res /*, next*/) {
        //TODO json.stringify
        res.json({
            id : "testId"
        })
    })

    // Check if the room exists
    app.get(`${config.pathPrefix}/api/sessions/:session_id`, function (req, res /*, next*/) {
        console.log("Checking for room");

        const room = rooms.get(req.params.session_id);

        if (!room) {
            console.log("doesn't exist");
            res.status(404).send();
        } else {
            console.log("exist");
            res.status(200).send();
        }
    })

    // Create room and return id
    app.post(`${config.pathPrefix}/api/sessions`, async function (req, res /*, next*/) {
        console.log("Creating new room");

        const room = await createRoom();

        res.json({
            id : room.id
        })
    })

    // Seend websocket notification signals to room participants
    app.post(`${config.pathPrefix}/api/signal`, async function (req, res /*, next*/) {
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
    app.post(`${config.pathPrefix}/api/sessions/:session_id/connection`, function (req, res /*, next*/) {
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
            statusLog();
        });

        const data = req.body;

        if ('role' in data)
            peer.setRole(data.role);

        const proto = config.publicDomain.includes('localhost') || config.publicDomain.includes('127.0.0.1') ? 'ws' : 'wss';

        res.json({
            id: peer.id,
            // Note: socket.io client will end up using (hardcoded) /meetmedia/signaling path
            token: `${proto}://${config.publicDomain}?peerId=${peer.id}&roomId=${roomId}&authToken=${peer.authToken}`
        });
    })

    if (config.httpOnly === true) {
        // http
        mainListener = http.createServer(app);
    } else {
        // https
        mainListener = spdy.createServer(tls, app);

        // http
        const redirectListener = http.createServer(app);

        if (config.listeningHost)
            redirectListener.listen(config.listeningRedirectPort, config.listeningHost);
        else
            redirectListener.listen(config.listeningRedirectPort);
    }

    console.info(`Listening on ${config.listeningPort} ${config.listeningHost}`)
    // https or http
    if (config.listeningHost)
        mainListener.listen(config.listeningPort, config.listeningHost);
    else
        mainListener.listen(config.listeningPort);
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
    io.on('connection', (socket) => {
        logger.info("websocket connection")

        const { roomId, peerId, authToken } = socket.handshake.query;

        if (!roomId || !peerId || !authToken) {
            logger.warn('connection request without roomId and/or peerId');
            socket.disconnect(true);
            return;
        }

        logger.info('connection request [roomId:"%s", peerId:"%s"]', roomId, peerId);

        queue.push(async () => {
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

            peer.socket = socket;

            room.handlePeer({ peer });

            statusLog();
        })
            .catch((error) => {
                logger.error('room creation or room joining failed [error:"%o"]', error);

                if (socket)
                    socket.disconnect(true);
            });
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
    }
}

/**
 * Get a Room instance (or create one if it does not exist).
 */
async function createRoom() {
    logger.info('creating a new Room');

    // Create the room
    const room = await Room.create({ mediasoupWorkers, peers: {}, webhook });

    room.on('close', () => {
        logger.info('closing a Room [roomId:"%s"]', room.id);

        rooms.delete(room.id);
        statusLog();

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

    rooms.set(room.id, room);

    statusLog();

    return room;
}

run();

module.exports = app; // export for testing
