process.env.DEBUG = '*'

const assert = require('assert');
let request = require('supertest')
const io = require("socket.io-client");
const Process = require("child_process");

let app

let rtpParameters = {
    mediaCodecs: [
        {
            kind: "audio",
            mimeType: "audio/opus",
            preferredPayloadType: 111,
            clockRate: 48000,
            channels: 2,
            parameters: {
                minptime: 10,
                useinbandfec: 1,
            },
        },
        {
            kind: "video",
            mimeType: "video/H264",
            preferredPayloadType: 125,
            clockRate: 90000,
            parameters: {
                "level-asymmetry-allowed": 1,
                "packetization-mode": 1,
                "profile-level-id": "42e01f",
            },
        },
    ],
}


before(function (done) {
    process.env.SSL_CERT = "../../docker/certs/kolab.hosted.com.cert"
    process.env.SSL_KEY = "../../docker/certs/kolab.hosted.com.key"
    process.env.REDIS_IP = "none"
    app = require('../server.js')
    request = request(app);

    app.on("ready", function(){
        done();
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
                rtpCapabilities: rtpParameters
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
                    rtpCapabilities: rtpParameters
                })
                assert.equal(peers.length, 1)
                assert.equal(peers[0].id, peerId)

                reason = await newPeer;
                assert(reason.data.id == newId);
            })
            .catch(err => { console.warn(err); throw err })
    });

    let transportInfo;

    it('createPlainTransport', async () => {
        transportInfo = await sendRequest(signalingSocket, 'createPlainTransport', {
            producing: true,
            consuming: false,
        })

        const { id } = await sendRequest(signalingSocket, 'produce', {
            transportId: transportInfo.id,
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

    });

    let recProcess;

    it('startFFMPEG', async () => {
        let recResolve;
        const promise = new Promise((res, _rej) => {
            recResolve = res;
        });

        const cmdProgram = "ffmpeg";

        const cmdArgStr = [
            "-i /dev/video0",
            "-r 24",
            "-v info",
            "-video_size 320x240",
            `-c:v h264`,
            "-f",
            "rtp",
            "-payload_type 125",
            "-ssrc 2222",
            `rtp://127.0.0.1:${transportInfo.port}?rtcpport=${transportInfo.rtcpPort}`,
        ].join(" ").trim();

        console.log(`Run command: ${cmdProgram} ${cmdArgStr}`);

        recProcess = Process.spawn(cmdProgram, cmdArgStr.split(/\s+/));

        recProcess.on("error", (err) => {
            console.error("Recording process error:", err);
        });

        recProcess.on("exit", (code, signal) => {
            console.log("Recording process exit, code: %d, signal: %s", code, signal);

            recProcess = null;
            // stopMediasoupRtp();

            if (!signal || signal === "SIGINT") {
                console.log("Recording stopped");
            } else {
                console.warn(
                    "Recording process didn't exit cleanly, output file might be corrupt"
                );
            }
        });

        // FFmpeg writes its logs to stderr
        recProcess.stderr.on("data", (chunk) => {
            chunk
            .toString()
            .split(/\r?\n/g)
            .filter(Boolean) // Filter out empty strings
            .forEach((line) => {
                console.log(line);
                //Stop after 5s of streaming
                if (line.startsWith("ffmpeg version")) {
                    setTimeout(() => {
                        recResolve();
                    }, 5000);
                }
            });
        });

        return promise;
    });


    after(function () {
        signalingSocket.close();
        if (recProcess) {
            recProcess.kill()
        }
    })

});

after(function () {
    process.exit();
})

