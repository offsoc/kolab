#!/usr/bin/env node

process.title = 'edumeet-server';

const config = require('./config/config');
const fs = require('fs');
const http = require('http');
const spdy = require('spdy');
const express = require('express');
const bodyParser = require('body-parser');
const cookieParser = require('cookie-parser');
const compression = require('compression');
const mediasoup = require('mediasoup');
const AwaitQueue = require('awaitqueue');
const Logger = require('./lib/Logger');
const Room = require('./lib/Room');
const Peer = require('./lib/Peer');
const helmet = require('helmet');
// auth
const redis = require('redis');
const redisClient = redis.createClient(config.redisOptions);
const expressSession = require('express-session');
const RedisStore = require('connect-redis')(expressSession);
const sharedSession = require('express-socket.io-session');
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
const tls =
{
    cert          : fs.readFileSync(config.tls.cert),
    key           : fs.readFileSync(config.tls.key),
    secureOptions : 'tlsv12',
    ciphers       :
        [
            'ECDHE-ECDSA-AES128-GCM-SHA256',
            'ECDHE-RSA-AES128-GCM-SHA256',
            'ECDHE-ECDSA-AES256-GCM-SHA384',
            'ECDHE-RSA-AES256-GCM-SHA384',
            'ECDHE-ECDSA-CHACHA20-POLY1305',
            'ECDHE-RSA-CHACHA20-POLY1305',
            'DHE-RSA-AES128-GCM-SHA256',
            'DHE-RSA-AES256-GCM-SHA384'
        ].join(':'),
    honorCipherOrder : true
};

const app = express();

app.use(helmet.hsts());
const sharedCookieParser=cookieParser();

app.use(sharedCookieParser);
app.use(bodyParser.json({ limit: '5mb' }));
app.use(bodyParser.urlencoded({ limit: '5mb', extended: true }));

const session = expressSession({
    secret            : config.cookieSecret,
    name              : config.cookieName,
    resave            : true,
    saveUninitialized : true,
    store             : new RedisStore({ client: redisClient }),
    cookie            : {
        secure   : true,
        httpOnly : true,
        maxAge   : 60 * 60 * 1000 // Expire after 1 hour since last request from user
    }
});

if (config.trustProxy)
{
    app.set('trust proxy', config.trustProxy);
}

app.use(session);

let mainListener;
let io;

async function run()
{
    try
    {
        // Open the interactive server.
        await interactiveServer(rooms, peers);

        // start Prometheus exporter
        if (config.prometheus)
        {
            await promExporter(rooms, peers, config.prometheus);
        }

        // Run a mediasoup Worker.
        await runMediasoupWorkers();

        // Run HTTPS server.
        await runHttpsServer();

        // Run WebSocketServer.
        await runWebSocketServer();

        // eslint-disable-next-line no-unused-vars
        const errorHandler = (err, req, res, next) =>
        {
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

        // eslint-disable-next-line no-unused-vars
        app.use(errorHandler);
    }
    catch (error)
    {
        logger.error('run() [error:"%o"]', error);
    }
}

function statusLog()
{
    if (statusLogger)
    {
        statusLogger.log({
            rooms : rooms,
            peers : peers
        });
    }
}

async function runHttpsServer()
{
    app.use(compression());

    app.get(`${config.pathPrefix}/api/ping`, function (req, res, /*next*/) {
        res.send('PONG')
    })

    app.get(`${config.pathPrefix}/api/sessions`, function (req, res, /*next*/) {
        //TODO json.stringify
        res.json({
                    id : "testId"
                })
    })

    //Check if the room exists
    app.get(`${config.pathPrefix}/api/sessions/:session_id`, function (req, res, /*next*/) {
        console.warn("Checking for room")
        let room = rooms.get(req.params.session_id);
        if (!room) {
            console.warn("doesn't exist")
            res.status(404).send()
        } else {
            console.warn("exist")
            res.status(200).send()
        }
    })

    // Create room and return id
    app.post(`${config.pathPrefix}/api/sessions`, async function (req, res, /*next*/) {
        console.warn("Creating new room", req.body.mediaMode, req.body.recordingMode)
        //FIXME we're truncating because of kolab4 database layout (should be fixed instead)
        const roomId = uuidv4().substring(0, 16)
        await getOrCreateRoom({ roomId });

        res.json({
                    id : roomId
                })
    })

    app.post(`${config.pathPrefix}/api/signal`, async function (req, res, /*next*/) {
        let data = req.body;
        const roomId = data.session;
        // const signalType = data.type;
        // const payload = data.data;
        const peers = data.to;


        if (peers) {
            for (const peerId of peers) {
                let peer = peers.get(peerId);
                peer.socket.emit(
                    'signal', data
                );
            }
        } else {
            io.to(roomId).emit(
                'signal', data
            );
        }

        res.json({})
    });

    // Create connection in room (just wait for websocket instead?
    // $post = [
    //     'json' => [
    //         'role' => self::OV_ROLE_PUBLISHER,
    //         'data' => json_encode(['role' => $role])
    //     ]
    // ];
    app.post(`${config.pathPrefix}/api/sessions/:session_id/connection`, function (req, res, /*next*/) {
        console.warn("Creating connection in session", req.params.session_id)
        let roomId = req.params.session_id
        let data = req.body;

        //FIXME we're truncating because of kolab4 database layout (should be fixed instnead)
        const peerId = uuidv4().substring(0, 16)
        //TODO create room already?

        let peer = new Peer({ id: peerId, roomId });
        peers.set(peerId, peer);

        peer.on('close', () => {
            peers.delete(peerId);
            statusLog();
        });

        peer.nickname = "Display Name";
        // peer.picture = picture;
        peer.email = "email@test.com";

        if ('role' in data)
            peer.setRole(data.role);

        const proto = config.publicDomain.includes('localhost') || config.publicDomain.includes('127.0.0.1') ? 'ws' : 'wss';

        res.json({
            id: peerId,
            // When the below get's passed to the socket.io client we end up with something like (depending on the socket.io path)
            // wss://${publicDomain}/meetmedia/signaling/?peerId=peer1&roomId=room1&EIO=3&transport=websocket,
            token: `${proto}://${config.publicDomain}/?peerId=${peerId}&roomId=${roomId}`
        })
    })

    if (config.httpOnly === true)
    {
        // http
        mainListener = http.createServer(app);
    }
    else
    {
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
async function runWebSocketServer()
{
    io = require('socket.io')(mainListener, {
        path: `${config.pathPrefix}/signaling`,
        cookie: false
    });

    io.use(
        sharedSession(session, sharedCookieParser, { autoSave: true })
    );

    // Handle connections from clients.
    io.on('connection', (socket) =>
    {
        logger.info("websocket connection")

        const { roomId, peerId } = socket.handshake.query;

        if (!roomId || !peerId)
        {
            logger.warn('connection request without roomId and/or peerId');

            socket.disconnect(true);

            return;
        }

        logger.info(
            'connection request [roomId:"%s", peerId:"%s"]', roomId, peerId);

        queue.push(async () =>
        {
            const room = await getOrCreateRoom({ roomId });

            let peer = peers.get(peerId);

            if (!peer) {
                logger.warn("Peer does not exist %s", peerId);
                socket.disconnect(true);
                return;
            }

            room.handlePeer({ peer });

            statusLog();
        })
            .catch((error) =>
            {
                logger.error('room creation or room joining failed [error:"%o"]', error);

                if (socket)
                    socket.disconnect(true);

                return;
            });
    });
}

/**
 * Launch as many mediasoup Workers as given in the configuration file.
 */
async function runMediasoupWorkers()
{
    const { numWorkers } = config.mediasoup;

    logger.info('running %d mediasoup Workers...', numWorkers);

    for (let i = 0; i < numWorkers; ++i)
    {
        const worker = await mediasoup.createWorker(
            {
                logLevel   : config.mediasoup.worker.logLevel,
                logTags    : config.mediasoup.worker.logTags,
                rtcMinPort : config.mediasoup.worker.rtcMinPort,
                rtcMaxPort : config.mediasoup.worker.rtcMaxPort
            });

        worker.on('died', () =>
        {
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
async function getOrCreateRoom({ roomId })
{
    let room = rooms.get(roomId);

    // If the Room does not exist create a new one.
    if (!room)
    {
        logger.info('creating a new Room [roomId:"%s"]', roomId);

        room = await Room.create({ mediasoupWorkers, roomId, peers });

        rooms.set(roomId, room);

        statusLog();

        room.on('close', () =>
        {
            rooms.delete(roomId);

            statusLog();
        });
    }

    return room;
}

run();
