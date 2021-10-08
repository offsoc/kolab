const os = require('os');

module.exports =
{
    // Authentication token for API (not websocket) requests
    authToken: process.env.AUTH_TOKEN,
    // Turn server configuration
    turn: process.env.TURN_SERVER === 'none' ? null : {
        urls: [
            // Using transport=tcp prevents the use of udp for the connection to the server, which is useful for testing,
            // but most likely not desired for production: https://datatracker.ietf.org/doc/html/rfc5766#section-2.1
            process.env.TURN_SERVER || 'turn:127.0.0.1:3478?transport=tcp'
        ],
        staticSecret: process.env.TURN_STATIC_SECRET || 'uzYguvIl9tpZFMuQOE78DpOi6Jc7VFSD0UAnvgMsg5n4e74MgIf6vQvbc6LWzZjz',
    },
    /*
    // redis server options used for session storage
    redisOptions: {
        host: process.env.REDIS_IP || '127.0.0.1',
        port: process.env.REDIS_PORT || 6379,
        db: process.env.REDIS_DB || '3',
        ...(process.env.REDIS_PASSWORD ? { password: process.env.REDIS_PASSWORD } : {})
    },
    */
    // Webhook URL
    webhookURL: process.env.WEBHOOK_URL,
    // Webhook authentication token
    webhookToken: process.env.WEBHOOK_TOKEN,
    // if you use encrypted private key the set the passphrase
    tls: {
        // passphrase: 'key_password'
        cert: process.env.SSL_CERT || `/etc/pki/tls/certs/kolab.hosted.com.cert`,
        key: process.env.SSL_KEY || `/etc/pki/tls/certs/kolab.hosted.com.key`,
    },
    // listening Host or IP 
    // If omitted listens on every IP. ("0.0.0.0" and "::")
    listeningHost: process.env.LISTENING_HOST || '0.0.0.0',
    // Listening port for https server.
    listeningPort: process.env.LISTENING_PORT || 12443,
    // Any http request is redirected to https.
    // Listening port for http server.
    listeningRedirectPort: 12080,
    // Listens only on http, only on listeningPort
    // listeningRedirectPort disabled
    // use case: loadbalancer backend
    httpOnly: true,
    publicDomain: process.env.PUBLIC_DOMAIN || '127.0.0.1:12443',
    pathPrefix: '/meetmedia',
    // WebServer/Express trust proxy config for httpOnly mode
    // You can find more info:
    //  - https://expressjs.com/en/guide/behind-proxies.html
    //  - https://www.npmjs.com/package/proxy-addr
    // use case: loadbalancer backend
    trustProxy: '',
    // When truthy, the room will be open to all users when as long as there
    // are allready users in the room
    activateOnHostJoin: true,
    // Room size before spreading to new router
    routerScaleSize: process.env.ROUTER_SCALE_SIZE || 40,
    // Socket timout value
    requestTimeout: 20000,
    // Socket retries when timeout
    requestRetries: 3,
    // Mediasoup settings
    mediasoup: {
        numWorkers: process.env.MEDIASOUP_NUM_WORKERS || Object.keys(os.cpus()).length,
        // mediasoup Worker settings.
        worker: {
            logLevel: 'warn',
            logTags: [
                'info',
                'ice',
                'dtls',
                'rtp',
                'srtp',
                'rtcp'
            ],
            rtcMinPort: 40000,
            rtcMaxPort: 49999
        },
        // mediasoup Router settings.
        router: {
            // Router media codecs.
            mediaCodecs: [
                {
                    kind      : 'audio',
                    mimeType  : 'audio/opus',
                    clockRate : 48000,
                    channels  : 2
                },
                {
                    kind       : 'video',
                    mimeType   : 'video/VP8',
                    clockRate  : 90000,
                    parameters :
                    {
                        'x-google-start-bitrate' : 1000
                    }
                },
                {
                    kind       : 'video',
                    mimeType   : 'video/VP9',
                    clockRate  : 90000,
                    parameters :
                    {
                        'profile-id'             : 2,
                        'x-google-start-bitrate' : 1000
                    }
                },
                {
                    kind       : 'video',
                    mimeType   : 'video/h264',
                    clockRate  : 90000,
                    parameters :
                    {
                        'packetization-mode'      : 1,
                        'profile-level-id'        : '4d0032',
                        'level-asymmetry-allowed' : 1,
                        'x-google-start-bitrate'  : 1000
                    }
                },
                {
                    kind       : 'video',
                    mimeType   : 'video/h264',
                    clockRate  : 90000,
                    parameters :
                    {
                        'packetization-mode'      : 1,
                        'profile-level-id'        : '42e01f',
                        'level-asymmetry-allowed' : 1,
                        'x-google-start-bitrate'  : 1000
                    }
                }
            ]
        },
        // mediasoup WebRtcTransport settings.
        webRtcTransport: {
            listenIps: [
                { ip: process.env.WEBRTC_LISTEN_IP, announcedIp: null }
            ],
            initialAvailableOutgoingBitrate: 1000000,
            minimumAvailableOutgoingBitrate: 600000,
            // Additional options that are not part of WebRtcTransportOptions.
            maxIncomingBitrate: 1500000
        }
    }

    /*
    ,
    // Prometheus exporter
    prometheus: {
        deidentify: false, // deidentify IP addresses
        // listen: 'localhost', // exporter listens on this address
        numeric: false, // show numeric IP addresses
        port: 8889, // allocated port
        quiet: false // include fewer labels
    }
    */
};
