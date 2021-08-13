#!/usr/bin/env node

const io = require("socket.io-client");

const peerId = "peer1";
const roomId = "room1";
const _signalingUrl = `wss://172.20.0.2:12443/?peerId=${peerId}&roomId=${roomId}`;
console.warn(`${_signalingUrl}`);

_signalingSocket = io(_signalingUrl, { transports: ["websocket"], rejectUnauthorized: false });

_signalingSocket.on('connect', () =>
{
    console.debug('signaling Peer "connect" event');
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
_signalingSocket.emit("hello", { a: "b", c: [] });


//const delay = ms => new Promise(resolve => setTimeout(resolve, ms))
//await delay(1000) /// waiting 1 second.
//console.warn('done done');

