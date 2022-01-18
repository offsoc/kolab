#!/usr/bin/env node

process.env.DEBUG = '*'

process.env['NODE_TLS_REJECT_UNAUTHORIZED'] = 0;

let request = require('superagent');
const io = require("socket.io-client");
const child_process = require("child_process");

const Roles = require('../lib/userRoles');

let processes = [];

//e.g. http://kolab1.mkpf.ch:12443
let serverUrl = process.argv[2]
// Welcome2KolabSystems
let webhookToken = process.argv[3]
//This is the mediasoup internal id, not what we have in kolab4
let roomId = process.argv[4]
let numStreams = 1
let filename = "input.mp4";


const codecIndex = 1

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
            rtcpFeedback: []
        },
        {
            kind       : 'video',
            mimeType   : 'video/VP8',
            payloadType: 96,
            clockRate  : 90000,
            parameters :
            {
                'profile-id'             : 2,
                'x-google-start-bitrate' : 1000
            },
            rtcpFeedback: []
        },
        {
            kind       : 'audio',
            channels: 2,
            clockRate: 48000,
            mimeType: "audio/opus",
            parameters: {
                "maxplaybackrate": 48000,
                "ptime": "20",
                "sprop-stereo": 0,
                "stereo": 1,
                "usedtx": 1,
                "useinbandfec": 1
            },
            payloadType: 109,
            rtcpFeedback: []
        }
    ],
}


function startGStream(peers, ssrc, audioSsrc) {
    const cmdProgram = "gst-launch-1.0";

    const payloadType = rtpParameters.codecs[codecIndex]['payloadType']
    const audioPayloadType = 109

    //FIXME currently only handles a single peer
    const peer = peers[0];

    const cmdArgStr = [
        "-v",
        "rtpbin name=rtpbin rtp-profile=avpf",

        //The source named "dec"
        `filesrc location="${filename}" ! decodebin name=dec`,
        //The video stream
        `dec. ! queue ! videoconvert ! videoscale ! videorate ! video/x-raw,width=1280,height=720,framerate=25/1 ! vp8enc deadline=1 cpu-used=-5 ! rtpvp8pay pt=${payloadType} ssrc=${ssrc} picture-id-mode=2 ! rtprtxqueue max-size-time=2000 max-size-packets=0 ! rtpbin.send_rtp_sink_0`,
        //The audio stream from the same source
        `dec. ! queue ! audioconvert ! audioresample ! opusenc ! rtpopuspay pt=${audioPayloadType} ssrc=${audioSsrc} ! rtprtxqueue ! rtpbin.send_rtp_sink_1`,

        //Send video over udp
        `rtpbin.send_rtp_src_0 ! udpsink host=${peer.senderTransportInfo.ip} port=${peer.senderTransportInfo.port} sync=true async=false`,
        `rtpbin.send_rtcp_src_0 ! udpsink host=${peer.senderTransportInfo.ip} port=${peer.senderTransportInfo.rtcpPort} sync=false async=false`,
        //Send audio over udp
        `rtpbin.send_rtp_src_1 ! udpsink host=${peer.senderAudioTransportInfo.ip} port=${peer.senderAudioTransportInfo.port} sync=true async=false`,
        `rtpbin.send_rtcp_src_1 ! udpsink host=${peer.senderAudioTransportInfo.ip} port=${peer.senderAudioTransportInfo.rtcpPort} sync=false async=false`
    ].join(" ").trim();


    console.log(`Run command: ${cmdProgram} ${cmdArgStr}`);

    let recProcess = child_process.spawn(cmdProgram, cmdArgStr.split(/\s+/));

    recProcess.on("error", (err) => {
        console.error("gstreamer process error:", err);
    });

    recProcess.on("exit", (code, signal) => {
        console.log("gstreamer process exit, code: %d, signal: %s", code, signal);

        recProcess = null;
        process.exit()
    });

    recProcess.stdout.on("data", (chunk) => {
        chunk
            .toString()
            .split(/\r?\n/g)
            .filter(Boolean) // Filter out empty strings
            .forEach((line) => {
                console.log(line);
            });
    });

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

//ffmpeg kind of sucks https://mediasoup.discourse.group/t/very-high-packet-loss-with-ffmpeg-broadcasting/322
function startFFMPEGStream(peers, ssrc, audioSsrc) {
    const cmdProgram = "ffmpeg";

    const payloadType = rtpParameters.codecs[codecIndex]['payloadType']
    const audioPayloadType = 109

    //Build a video stream per producer
    const streams = peers.map((peer) => `[select=a:f=rtp:ssrc=${audioSsrc}:payload_type=${audioPayloadType}]rtp://${peer.senderAudioTransportInfo.ip}:${peer.senderAudioTransportInfo.port}?rtcpport=${peer.senderAudioTransportInfo.rtcpPort}|[select=v:f=rtp:ssrc=${ssrc}:payload_type=${payloadType}]rtp://${peer.senderTransportInfo.ip}:${peer.senderTransportInfo.port}?rtcpport=${peer.senderTransportInfo.rtcpPort}`);

    const cmdArgStr = [
        //The source
        `-stream_loop -1 -re -i ${filename}`, //Loop a videofile (-re for original speed)
        // "-i /dev/video0", //Stream from the webcam

        '-map 0:a:0',
        '-c:a libopus -ab 128k -ac 2 -ar 48000 -application lowdelay -cutoff 12000',

        '-vf scale=640:480',

        //The vp8 codec
        '-c:v libvpx -crf 10 -b:v 1000k',

        //The vp9 codec
        // '-strict experimental',
        // '-c:v libvpx-vp9 -crf 30 -b:v 0',
        //
        //The h264 codec
        // '-c:v h264 -b:v 500k',
        // '',
        // '-c:v libx264 -tune zerolatency -preset ultrafast -threads 0 -crf 23 -minrate 5M -maxrate 5M -bufsize 10M',

        //Frame rate?
        // '-r 25',

        "-map 0:v:0",
        "-f tee", //This option allows us to read the source once, encode once, and then output multiple streams
        streams.join('|').trim()
    ].join(" ").trim();

    console.log(`Run command: ${cmdProgram} ${cmdArgStr}`);

    let recProcess = child_process.spawn(cmdProgram, cmdArgStr.split(/\s+/));

    recProcess.on("error", (err) => {
        console.error("ffmpeg process error:", err);
    });

    recProcess.on("exit", (code, signal) => {
        console.log("ffmpeg process exit, code: %d, signal: %s", code, signal);

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
                resolve(response)
            }
        )
    })
}


async function createPeer(index, roomId/*, request, receiverPort, receiverRtcpPort*/) {
    console.warn("Creating peer")
    let signalingSocket
    await request
        .post(`${serverUrl}/meetmedia/api/sessions/${roomId}/connection`)
        .send({role: Roles.PUBLISHER | Roles.SUBSCRIBER | Roles.MODERATOR})
        .set('X-Auth-Token', webhookToken)
        .then(async (res) => {
            let data = res.body;
            console.warn(data)
            const signalingUrl = data['token'];

            signalingSocket = io(signalingUrl, { path: '/meetmedia/signaling', transports: ["websocket"], rejectUnauthorized: false });
            console.warn("Waiting for room ready")
            let roomReady = new Promise((resolve, /*reject*/) => {
                console.warn("waiting for notification")
                //For some reason this notification is never emitted
                signalingSocket.once('notification', (reason) => {
                    console.warn("Received notification", reason)
                    if (reason['method'] == 'roomReady') {
                        resolve();
                    }
                });
            })

            signalingSocket.on("connect", () => {  console.warn("connected"); });
            signalingSocket.on("disconnect", () => {  console.warn("disconnect"); });

            signalingSocket.on('notification', (reason) => {
                console.warn("1Received notification", reason)
            });

            signalingSocket.connect();
            signalingSocket.on('notification', (reason) => {
                console.warn("1Received notification", reason)
            });

            console.warn("Connecting")

            //FIXME this does not currently seem to work as it should.

            await roomReady
            console.warn("Connected")
        })
        .catch(err => {
            console.warn(err); throw err 
        })

    console.warn("Created connection")

    //Necessary later for the server to resume the consumer,
    //once we join with another peer
    // signalingSocket.on('request', async (reason, cb) => {
    //     // console.warn("Received request", reason)
    //     if (reason['method'] == 'newConsumer') {
    //         cb();
    //     }
    // });

    //Join
    await sendRequest(signalingSocket, 'join', {
        nickname: `videoproducer ${index}`,
        rtpCapabilities: {
            codecs: [rtpParameters.codecs[codecIndex]],
        }
    })
    console.warn("Joined")

    //Create sending transport
    const senderTransportInfo = await sendRequest(signalingSocket, 'createPlainTransport', {
        producing: true,
        consuming: false,
    })
    console.warn("Created transport", senderTransportInfo)

    const senderAudioTransportInfo = await sendRequest(signalingSocket, 'createPlainTransport', {
        producing: true,
        consuming: false,
    })

    //Create consuming transport
    // const consumerTransportInfo = await sendRequest(signalingSocket, 'createPlainTransport', {
    //     producing: false,
    //     consuming: true,
    // })

    // await sendRequest(signalingSocket, 'connectPlainTransport', {
    //     transportId: consumerTransportInfo.id,
    //     ip: '127.0.0.1',
    //     port: receiverPort,
    //     rtcpPort: receiverRtcpPort,
    // })

    //Create sending producer
    await sendRequest(signalingSocket, 'produce', {
        transportId: senderTransportInfo.id,
        kind: 'video',

        rtpParameters: {
            codecs: [rtpParameters.codecs[codecIndex]],
            encodings: [{ ssrc: 2222 }]
        },
        appData: {
            source: 'webcam'
        }
    })

    await sendRequest(signalingSocket, 'produce', {
        transportId: senderAudioTransportInfo.id,
        kind: 'audio',

        rtpParameters: {
            codecs: [
                {
                    "channels": 2,
                    "clockRate": 48000,
                    "mimeType": "audio/opus",
                    "parameters": {
                        "maxplaybackrate": 48000,
                        "ptime": "20",
                        "sprop-stereo": 0,
                        "stereo": 1,
                        "usedtx": 1,
                        "useinbandfec": 1
                    },
                    "payloadType": 109,
                    "rtcpFeedback": []
                }
            ],
            encodings: [{ ssrc: 2223 }]
        },
        appData: {
            source: 'mic'
        }
    })

    console.warn("Produced")

    // return {senderTransportInfo, consumerTransportInfo, signalingSocket};
    return {senderTransportInfo, senderAudioTransportInfo, signalingSocket};
}

async function run() {
    // let roomId;
    let peers = [];

    // await request
    //     .post(`${serverUrl}/meetmedia/api/sessions`)
    //     .set('X-Auth-Token', webhookToken)
    //     .then(async (res) => {
    //         roomId = res.body['id'];
    //     })
    //     .catch(err => {
    //         console.warn(err); throw err
    //     })

    for (var i = 0; i < numStreams; i++) {
        peers.push(await createPeer(i, roomId))
    }

    if (true) {
        processes.push(startGStream(peers, 2222, 2223))
    } else {
        processes.push(startFFMPEGStream(peers, 2222, 2223))
    }

    const promise = new Promise((res, _rej) => {});
    await promise;
}

run()
    .then(() => {
        for (const process of processes) {
            process.kill()
        }
        process.exit();
    });

