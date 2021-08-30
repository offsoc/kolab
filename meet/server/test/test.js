const assert = require('assert');
let request = require('supertest')
const io = require("socket.io-client");

// const mediasoupClient = require('mediasoup-client');
// const { FakeHandler } = require('../node_modules/mediasoup-client/lib/handlers/FakeHandler');
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

    it('create room', function(done) {
        request
            .post(`/meetmedia/api/sessions/${roomId}/connection`)
            .expect(200)
            .then((res) => {
                let data = res.body;
                peerId = data['id'];
                const signalingUrl = data['token'];
                assert(signalingUrl.includes(peerId))
                assert(signalingUrl.includes(roomId))
                console.info(signalingUrl);

                signalingSocket = io(signalingUrl, { path: '/meetmedia/signaling', transports: ["websocket"], rejectUnauthorized: false });
                signalingSocket.on('notification', (reason) =>
                {
                    console.warn('Received notification "%s"', reason);
                    if (reason['method'] == 'roomReady') {
                        done();
                    }
                });

                signalingSocket.connect();
            })
            .catch(err => { console.warn(err); done(err)})
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
        assert.equal(role, 0)
        assert.equal(peers.length, 0)
    })

    it('second peer joining', function(done) {
        request
            .post(`/meetmedia/api/sessions/${roomId}/connection`)
            .expect(200)
            .then((res) => {
                let data = res.body;
                const newId = data['id'];
                const signalingUrl = data['token'];

                let signalingSocket2 = io(signalingUrl, { path: '/meetmedia/signaling', transports: ["websocket"], rejectUnauthorized: false });
                signalingSocket2.on('notification', async (reason) =>
                {
                    console.warn('Received peer2 notification "%s"', reason);
                    if (reason['method'] == 'roomReady') {
                        const { peers } = await sendRequest(signalingSocket2, 'join', {
                                nickname: "nickname",
                                rtpCapabilities: fakeParameters.generateNativeRtpCapabilities()
                        })
                        assert.equals(peers.length, 1)
                        assert.equals(peers[0].id, peerId)
                    }
                });

                signalingSocket.on('notification', (reason) =>
                {
                    console.warn('Received peer1 notification "%s"', reason);
                    if (reason.method == 'newPeer') {
                        assert(reason.data.id == newId);
                        done();
                    }
                });

                signalingSocket.connect();
            })
            .catch(err => { console.warn(err); done(err)})
    });

    // it('createDevice', async () => {
    //     let device;
    //     try{
    //         device = new mediasoupClient.Device({ handlerFactory: FakeHandler.createFactory(fakeParameters) });
    //     } catch (error) {
    //         console.warn(error)
    //         if (error.name === 'UnsupportedError') {
    //             console.warn('browser not supported');
    //         }
    //     }
    //     // try {
    //     //     await device.load(routerRtpCapabilities)
    //     // } catch (err) {
    //     //     console.warn("Device loading failed", err);
    //     // }
    //     // assert(device.canProduce('video'))
    //     // console.warn("So can we produce?", device.canProduce('video'))
    //     // return true;
    // });

    after(function () {
        signalingSocket.close();
    })

});

after(function () {
    process.exit();
})

