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
    // Webhook URL
    webhookURL: process.env.WEBHOOK_URL,
    // Webhook authentication token
    webhookToken: process.env.WEBHOOK_TOKEN,
    // if you use encrypted private key the set the passphrase
    tls: process.env.SSL_CERT === 'none' ? null : {
        // passphrase: 'key_password'
        cert: process.env.SSL_CERT || `/etc/pki/tls/certs/kolab.hosted.com.cert`,
        key: process.env.SSL_KEY || `/etc/pki/tls/certs/kolab.hosted.com.key`,
    },
    // listening Host or IP
    // Use "0.0.0.0" or "::") to listen on every IP.
    listeningHost: process.env.LISTENING_HOST || "0.0.0.0",
    // Listening port for https server.
    listeningPort: process.env.LISTENING_PORT || 12443,
    // Used to establish the websocket connection from the client.
    publicDomain: process.env.PUBLIC_DOMAIN || '127.0.0.1:12443',
    // API path prefix
    pathPrefix: '/meetmedia',
    // Room size before spreading to new router
    routerScaleSize: process.env.ROUTER_SCALE_SIZE || 16,
    // Socket timeout value
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
            // Initial bitrate estimation
            initialAvailableOutgoingBitrate: 1000000,
            // Additional options that are not part of WebRtcTransportOptions.
            maxIncomingBitrate: 1500000
        }
    }
};
