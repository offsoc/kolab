const assert = require('assert');
let request = require('supertest')
const io = require("socket.io-client");

const mediasoupClient = require('mediasoup-client');
const { FakeHandler } = require('mediasoup-client/lib/handlers/FakeHandler');
const fakeParameters = require('./fakeParameters');

let app

before(function (done) {
    process.env.SSL_CERT = "../../docker/certs/kolab.hosted.com.cert"
    process.env.SSL_KEY = "../../docker/certs/kolab.hosted.com.key"
    process.env.REDIS_IP = "none"
    // process.env.DEBUG = '*'
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
      .expect(200, done);
  });
});

describe('Join room', function() {
    const roomId = "room1";
    let signalingSocket
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
            .post(`/meetmedia/api/sessions/${roomId}/connection`)
            .send({role: 31})
            .expect(200)
            .then(async (res) => {
                let data = res.body;
                peerId = data['id'];
                const signalingUrl = data['token'];
                assert(signalingUrl.includes(peerId))
                assert(signalingUrl.includes(roomId))
                console.info(signalingUrl);

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
            .catch(err => { console.warn(err); throw err })
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
        assert.equal(role, 31)
        assert.equal(peers.length, 0)
    })

    it('second peer joining', async () => {
        return request
            .post(`/meetmedia/api/sessions/${roomId}/connection`)
            .expect(200)
            .then(async (res) => {
                let data = res.body;
                const newId = data['id'];
                const signalingUrl = data['token'];

                let signalingSocket2 = io(signalingUrl, { path: '/meetmedia/signaling', transports: ["websocket"], rejectUnauthorized: false });

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

                signalingSocket.connect();


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
            .catch(err => { console.warn(err); throw err })
    });

    let transportInfo;

    it('createWebRtcTransport', async () => {
        transportInfo = await sendRequest(signalingSocket, 'createWebRtcTransport', {
            forceTcp: false,
            producing: true,
            consuming: false
        })

        const { id, iceParameters, iceCandidates, dtlsParameters } = transportInfo
        console.warn(id);
    });

    it('createDevice', async () => {
        let device;
        try{
            device = new mediasoupClient.Device({ handlerFactory: FakeHandler.createFactory(fakeParameters) });

            let caps = fakeParameters.generateRouterRtpCapabilities();
            await device.load({routerRtpCapabilities: caps})
            assert(device.canProduce('video'))

            console.info(transportInfo)
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

    after(function () {
        signalingSocket.close();
    })

});

after(function () {
    process.exit();
})

