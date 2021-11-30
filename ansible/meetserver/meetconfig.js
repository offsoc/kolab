const os = require('os');

module.exports =
{
    // Authentication token for API (not websocket) requests
    authToken: '{{ auth_token }}',
    // Turn server configuration
    turn: {
        urls: [
            'turn:{{ public_domain }}:3478',
            // 'turns:{{ public_ip }}:443',
        ],
        staticSecret: '{{ turn_static_secret }}',
    },
    // Webhook URL
    webhookURL: 'xtian.dev.kolab.io/api/webhooks/meet',
    // Webhook authentication token
    webhookToken: 'Welcome2KolabSystems',
    // if you use encrypted private key the set the passphrase
    tls: {
        cert: '/etc/letsencrypt/live/stun-dev.kolab.io/fullchain.pem',
        key: '/etc/letsencrypt/live/stun-dev.kolab.io/privkey.pem',
    },
    // listening Host or IP
    // Use "0.0.0.0" or "::") to listen on every IP.
    listeningHost: "0.0.0.0",
    // Listening port for https server.
    listeningPort: 12443,
    // Used to establish the websocket connection from the client.
    publicDomain: '{{ public_domain }}:12443',
    // API path prefix
    pathPrefix: '/meetmedia',
    // Room size before spreading to new router
    routerScaleSize: 16,
    // Socket timeout value
    requestTimeout: 20000,
    // Socket retries when timeout
    requestRetries: 3,
    // Mediasoup settings
    mediasoup: {
        numWorkers: Object.keys(os.cpus()).length,
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
                { ip: '{{ public_ip }}', announcedIp: null }
            ],
            initialAvailableOutgoingBitrate: 1000000,
            minimumAvailableOutgoingBitrate: 600000,
            // Additional options that are not part of WebRtcTransportOptions.
            maxIncomingBitrate: 1500000
        }
    }
};
