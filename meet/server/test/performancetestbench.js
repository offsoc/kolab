process.env.DEBUG = '*'

const assert = require('assert');
let request = require('supertest')
const io = require("socket.io-client");
const child_process = require("child_process");
const  udp = require('dgram');

let recvUdpSocket
let recvRtcpUdpSocket
let app
let processes = [];

let rtpParameters = {
    codecs: [
        {
            mimeType: "video/H264",
            payloadType: 125,
            clockRate: 90000,
            parameters: {
                "level-asymmetry-allowed": 1,
                "packetization-mode": 1,
                "profile-level-id": "42e01f",
            },
        },
    ],
}

function startFFMPEGStream(peers, ssrc) {
    const cmdProgram = "ffmpeg";

    //Build a video stream per producer
    const streams = peers.map((peer) => `[select=v:f=rtp:ssrc=${ssrc}:payload_type=125]rtp://127.0.0.1:${peer.senderTransportInfo.port}?rtcpport=${peer.senderTransportInfo.rtcpPort}`);

    const cmdArgStr = [
        "-i /dev/video0", //We are streaming from the webcam (a looping videofile would be an alternative)
        `-c:v h264`, //The codec
        "-map 0:v:0",
        "-f tee", //This option allows us to read the source once, encode once, and then output multiple streams
        streams.join('|').trim()
    ].join(" ").trim();

    console.log(`Run command: ${cmdProgram} ${cmdArgStr}`);

    let recProcess = child_process.spawn(cmdProgram, cmdArgStr.split(/\s+/));

    recProcess.on("error", (err) => {
        console.error("Recording process error:", err);
    });

    recProcess.on("exit", (code, signal) => {
        console.log("Recording process exit, code: %d, signal: %s", code, signal);

        recProcess = null;
    });

    // FFmpeg writes its logs to stderr
    recProcess.stderr.on("data", (chunk) => {
        chunk
        .toString()
        .split(/\r?\n/g)
        .filter(Boolean) // Filter out empty strings
        .forEach((line) => {
            console.log(line);
        });
    });

    return recProcess;
}

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


async function createPeer(roomId, request, receiverPort, receiverRtcpPort) {
    let signalingSocket
    let peerId
    await request
        .post(`/meetmedia/api/sessions/${roomId}/connection`)
        .send({role: 31})
        .expect(200)
        .then(async (res) => {
            let data = res.body;
            peerId = data['id'];
            const signalingUrl = data['token'];
            assert(signalingUrl.includes(peerId))
            assert(signalingUrl.includes(roomId))
            signalingSocket = io(signalingUrl, { path: '/meetmedia/signaling', transports: ["websocket"], rejectUnauthorized: false });
            let roomReady = new Promise((resolve, /*reject*/) => {
                signalingSocket.once('notification', (reason) => {
                    console.warn("Received notification", reason)
                    if (reason['method'] == 'roomReady') {
                        resolve();
                    }
                });
            })

            signalingSocket.connect();
            await roomReady
        })
        .catch(err => { console.warn(err); throw err })


    //Necessary later for the server to resume the consumer,
    //once we join with another peer
    signalingSocket.on('request', async (reason, cb) => {
        console.warn("Received request", reason)
        if (reason['method'] == 'newConsumer') {
            cb();
        }
    });

    //Join
    const { id, role/*, peers*/ } = await sendRequest(signalingSocket, 'join', {
            nickname: "nickname",
            rtpCapabilities: rtpParameters
    })
    assert.equal(id, peerId)
    assert.equal(role, 31)

    //Create sending transport
    const senderTransportInfo = await sendRequest(signalingSocket, 'createPlainTransport', {
        producing: true,
        consuming: false,
    })

    //Create consuming transport
    const consumerTransportInfo = await sendRequest(signalingSocket, 'createPlainTransport', {
        producing: false,
        consuming: true,
    })

    await sendRequest(signalingSocket, 'connectPlainTransport', {
        transportId: consumerTransportInfo.id,
        ip: '127.0.0.1',
        port: receiverPort,
        rtcpPort: receiverRtcpPort,
    })

    //Create sending producer
    await sendRequest(signalingSocket, 'produce', {
        transportId: senderTransportInfo.id,
        kind: 'video',

        rtpParameters: {
            codecs: [
                {
                    mimeType: "video/H264",
                    payloadType: 125,
                    clockRate: 90000,
                    parameters: {
                        "level-asymmetry-allowed": 1,
                        "packetization-mode": 1,
                        "profile-level-id": "42e01f",
                    },
                },
            ],
            encodings: [{ ssrc: 2222 }]
        },
        appData: {
            source: 'webcam'
        }
    })

    return {senderTransportInfo, consumerTransportInfo, signalingSocket};
}


before(function (done) {
    process.env.SSL_CERT = "../../docker/certs/kolab.hosted.com.cert"
    process.env.SSL_KEY = "../../docker/certs/kolab.hosted.com.key"
    process.env.REDIS_IP = "none"
    process.env.MEDIASOUP_NUM_WORKERS = 1
    app = require('../server.js')
    request = request(app);


    recvUdpSocket = udp.createSocket('udp4');
    recvUdpSocket.on('message',function(msg,info){
        console.warn("Received message", msg, info)
    });

    recvRtcpUdpSocket = udp.createSocket('udp4');
    recvRtcpUdpSocket.on('message',function(msg,info){
        console.warn("Received RTCP message", msg, info)
    });

    app.on("ready", function(){
        done();
    });
});

describe('Testbench', function() {
    const roomId = "room1";
    let peers = [];

    it('prepare udp sockets', async () => {
        await new Promise(resolve => recvUdpSocket.bind(22222, '127.0.0.1', resolve));
        await new Promise(resolve => recvRtcpUdpSocket.bind(22223, '127.0.0.1', resolve));
    });

    it('create peers', async () => {
        for (var i = 0; i < 2; i++) {
            peers.push(await createPeer(roomId, request, recvUdpSocket.address().port, recvRtcpUdpSocket.address().port))
        }
    });

    it('start ffmpg stream', async () => {
        processes.push(startFFMPEGStream(peers, 2222))
    });

    it('wait forever', async () => {
        const promise = new Promise((res, _rej) => {});
        return promise;
    })
});

after(function () {
    for (const process of processes) {
        process.kill()
    }
    process.exit();
})

