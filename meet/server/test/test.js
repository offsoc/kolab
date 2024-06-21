const assert = require('assert');
let request = require('supertest')
const io = require("socket.io-client");

const Roles = require('../lib/userRoles');

const mediasoupClient = require('mediasoup-client');
const { FakeHandler } = require('mediasoup-client/lib/handlers/FakeHandler');
const fakeParameters = require('./fakeParameters');

let app
let authToken = "Welcome2KolabSystems"

before(function (done) {
    process.env.SSL_CERT = "none"
    process.env.AUTH_TOKEN = authToken
    process.env.LISTENING_PORT = 12999
    process.env.PUBLIC_DOMAIN = "127.0.0.1:12999"
    process.env.WEBRTC_LISTEN_IP = "127.0.0.1"
    process.env.DEBUG = '*'

    app = require('../server.js')
    request = request(app);

    app.on("ready", function(){
        done();
    });
});

describe('GET /ping', function() {
    it('responds', function(done) {
        request
            .get('/meetmedia/api/ping')
            .set('X-Auth-Token', authToken)
            .expect(200, done);
    });
});

describe('Join room', function() {
    let roomId
    let signalingUrl;

    let signalingSocket
    let signalingSocket2
    let peerId

    async function sendRequest(socket, method, data = null) {
        return await new Promise((resolve, /*reject*/) => {
            socket.emit(
                'request',
                {method: method,
                    data: data},
                (error, response) => {
                    assert(!error)
                    resolve(response)
                }
            )
        })
    }

    it('create room', async () => {
        return request
            .post(`/meetmedia/api/sessions`)
            .set('X-Auth-Token', authToken)
            .expect(200)
            .then(async (res) => {
                roomId = res.body['id'];
            })
            .catch(err => {
                console.warn(err); throw err 
            })
    });

    it('list rooms', async () => {
        return request
            .get(`/meetmedia/api/sessions`)
            .set('X-Auth-Token', authToken)
            .expect(200);
    })

    it('connect', async () => {
        return request
            .post(`/meetmedia/api/sessions/${roomId}/connection`)
            .set('X-Auth-Token', authToken)
            .send({role: Roles.PUBLISHER | Roles.SUBSCRIBER | Roles.MODERATOR})
            .expect(200)
            .then(async (res) => {
                let data = res.body;
                peerId = data['id'];
                signalingUrl = data['token'];
                assert(signalingUrl.includes(peerId))
                assert(signalingUrl.includes(roomId))
                // console.info(signalingUrl);

                signalingSocket = io(signalingUrl, { path: '/meetmedia/signaling', transports: ["websocket"], rejectUnauthorized: false });
                let roomReady = new Promise((resolve, /*reject*/) => {
                    signalingSocket.on('notification', (reason) => {
                        if (reason['method'] == 'roomReady') {
                            resolve();
                        }
                    });
                })

                signalingSocket.connect();
                await roomReady
            })
            .catch(err => {
                console.warn(err); throw err
            })
    });

    it('getRtpCapabilities', async () => {
        const routerRtpCapabilities = await sendRequest(signalingSocket, 'getRouterRtpCapabilities')
        assert(Object.keys(routerRtpCapabilities).length != 0)
    });


    it('join', async () => {
        const { id, role, peers } = await sendRequest(signalingSocket, 'join', {
            nickname: "nickname",
            rtpCapabilities: fakeParameters.generateNativeRtpCapabilities()
        })
        assert.equal(id, peerId)
        assert.equal(role, Roles.PUBLISHER | Roles.SUBSCRIBER | Roles.MODERATOR)
        assert.equal(peers.length, 0)
    })

    it('second peer joining', async () => {
        return request
            .post(`/meetmedia/api/sessions/${roomId}/connection`)
            .set('X-Auth-Token', authToken)
            .expect(200)
            .then(async (res) => {
                let data = res.body;
                const newId = data['id'];
                const signalingUrl = data['token'];

                signalingSocket2 = io(signalingUrl, { path: '/meetmedia/signaling', transports: ["websocket"], rejectUnauthorized: false });

                let roomReady = new Promise((resolve, /*reject*/) => {
                    signalingSocket2.on('notification', async (reason) => {
                        if (reason['method'] == 'roomReady') {
                            resolve(reason);
                        }
                    });
                })

                let newPeer = new Promise((resolve, /*reject*/) => {
                    signalingSocket.on('notification', (reason) => {
                        if (reason.method == 'newPeer') {
                            resolve(reason);
                        }
                    });
                })

                signalingSocket2.connect();


                let reason = await roomReady;
                const { peers } = await sendRequest(signalingSocket2, 'join', {
                    nickname: "nickname",
                    rtpCapabilities: fakeParameters.generateNativeRtpCapabilities()
                })
                assert.equal(peers.length, 1)
                assert.equal(peers[0].id, peerId)

                reason = await newPeer;
                assert(reason.data.id == newId);
            })
            .catch(err => {
                console.warn(err); throw err 
            })
    });

    let transportInfo;

    it('createWebRtcTransport', async () => {
        transportInfo = await sendRequest(signalingSocket, 'createWebRtcTransport', {
            forceTcp: false,
            producing: true,
            consuming: false
        })

        const { id, iceParameters, iceCandidates, dtlsParameters } = transportInfo
        assert(transportInfo != null);
    });

    it('createDevice', async () => {
        let device;
        try{
            device = new mediasoupClient.Device({ handlerFactory: FakeHandler.createFactory(fakeParameters) });

            let caps = fakeParameters.generateRouterRtpCapabilities();
            await device.load({routerRtpCapabilities: caps})
            assert(device.canProduce('video'))

            const { id, iceParameters, iceCandidates, dtlsParameters } = transportInfo
            //FIXME it doesn't look like this device can actually connect
            let sendTransport = device.createSendTransport({
                id,
                iceParameters,
                iceCandidates,
                dtlsParameters,
                // iceServers: turnServers,
                // iceTransportPolicy: iceTransportPolicy,
                proprietaryConstraints: { optional: [{ googDscp: true }] }
            })

            sendTransport.on('connect', ({ dtlsParameters }, callback, errback) => {
                console.warn("on connect");
                // done();
                // socket.sendRequest('connectWebRtcTransport',
                //     { transportId: sendTransport.id, dtlsParameters })
                //     .then(callback)
                //     .catch(errback)
            })

            //TODO we should get it to connected
            // assert.equal(sendTransport.connectionState, 'new');

        } catch (error) {
            console.warn(error)
        }
    });

    it('reconnect', async () => {
        //Connect a new socket first, simulating a dangling old socket.
        let reconnectSocket = io(signalingUrl, { path: '/meetmedia/signaling', transports: ["websocket"], rejectUnauthorized: false , forceNew: true});

        //Listen for peer closed events
        let peerClosed = false;
        signalingSocket2.on('notification', (reason) => {
            if (reason['method'] == 'peerClosed') {
                peerClosed = true;
            }
        });

        let roomBack = new Promise((resolve, /*reject*/) => {
            reconnectSocket.on('notification', (reason) => {
                if (reason['method'] == 'roomBack') {
                    resolve();
                }
            });
        })

        await reconnectSocket.connect();
        await roomBack

        //Now disconnect the old socket, it shouldn't affect the new one and shouldn't trigger a peer closure.
        await signalingSocket.disconnect();

        await new Promise(resolve => setTimeout(resolve, 1000));

        //We shouldn't receive a peer closed event
        assert(!peerClosed);
        //For further tests
        signalingSocket = reconnectSocket;
    });


    it('disconnect', async () => {
        let peerClosed = new Promise((resolve, /*reject*/) => {
            signalingSocket2.on('notification', (reason) => {
                if (reason['method'] == 'peerClosed') {
                    resolve();
                }
            });
        })

        //Disconnect and wait for the peer closed signal
        await signalingSocket.disconnect();
        await peerClosed
    });

    after(function () {
        signalingSocket.close();
        signalingSocket2.close();
    })

});
