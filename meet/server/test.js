#!/usr/bin/env node

const io = require("socket.io-client");
const axios = require('axios')

const roomId = "room1";


axios
  .post('http://127.0.0.1:12443/api/sessions/${roomId}/connection', {
    // todo: 'Buy the milk'
  })
  .then(res => {
    console.log(`statusCode: ${res.status}`)
    // console.log(res)
      //
    let data = res.data;
    console.log(data)
    const peerId = data['id'];

    const _signalingUrl = `ws://127.0.0.1:12443/?peerId=${peerId}&roomId=${roomId}`;
    console.warn(`${_signalingUrl}`);

    _signalingSocket = io(_signalingUrl, { transports: ["websocket"], rejectUnauthorized: false });

    _signalingSocket.on('connect', () =>
    {
        console.debug('signaling Peer "connect" event');
        _signalingSocket.emit("hello", { a: "b", c: [] });
    });

    _signalingSocket.on('disconnect', (reason) =>
    {
        console.warn('signaling Peer "disconnect" event [reason:"%s"]', reason);
    });

    _signalingSocket.on("error", (error) => {
        console.warn('error %s', error);
    });

    _signalingSocket.on("reconnect_failed", () => {
        console.warn('reconnect failed');
    });
    //_signalingSocket.connect();

    console.warn('done');


  })
  .catch(error => {
    console.error(error)
  })







//const delay = ms => new Promise(resolve => setTimeout(resolve, ms))
//await delay(1000) /// waiting 1 second.
//console.warn('done done');

