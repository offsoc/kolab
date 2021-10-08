This is the kolab meet server side component.

Run it with nodejs (or use the meet container).

It should become available on on port 12433 (curl -k -v http://localhost:12443/ping)

# To get an interactive console
/src/meetsrc/connect.js

# To dump some stats
/src/meetsrc/connect.js --stats

# Test the websocket
npm -g install wscat
wscat --no-check -c "wss://172.20.0.2:12443/socket.io/?peerId=peer1&roomId=room1&EIO=3&transport=websocket"

# Update code in container
docker exec -ti kolab-meet /bin/bash -c "/bin/cp -rf /src/meet/* /src/meetsrc/"

# Quick WebRTC overview

In our setup there are the following components involved:
* Client (Browser with some javascript)
* Kolab 4 (Webserver runnnign the kolab 4 application)
* Turn server (coturn)
* Kolabmeet server (nodejs application)

Kolabmeet itself has two 3 interaction points:
* A webserver for the API
* A websocket for signaling
* Mediasoup for webrtc

To join a meeting this is roughly what happens:
* The Client asks the webserver to join a room
* The webserver contacts kolabmeet to create the room and returns a url for a signaling websocket
* The client connects to kolabmeet via the signaling websocket
* The client now asks kolabmeet via the websocket to prepare the media channels
* Mediasoup then ultimately establishes the webrtc connection, potentially routing the data via the configured turn server.

This leads to the following topolgy:
* Client <-> Kolab 4 API <-> Kolabmeet API
* Client <-> Kolabmeet Websocket (Signaling)
* Client <-> (Turn Server) <-> Kolabmeet WebRTC <-> (Turn Server) <-> Client


# Troubleshooting

* Socket.io (signaling) has a debug option that can be set in the browser local storage.
* Mediasoup has a debug flag that can be set in the config.
* Coturn has config flags to enable logging of all connection attempts.
* Firefox has about:webrtc and chrome has chrome://webrtc-internals
* wscat can be used to test websockets.
* On the kolabmeet server you can connect to the server by executing connect.js, which allows to inspect the internal state.
* The browser won't allow a ws: connection from a https:// site, but only chrome will tell you about it.
* If in question, restart your browser. Sometimes things suddenly start working again.
* Access to media (webcam and microphone) only works on https or localhost sites (secure context). Otherwise the client side will start to break.
* 127.0.0.1 as webrtc listening host may not work for a local webrtc setup (and will not give you any warnings about it either). See also firefoxes media.peerconnection.ice.* config options. Use a local interfaces ip instead.

## Connection setup

In order:
* The client first opens a room via the meet laravel controller. This should work as it's the regular Kolab4 API.
* Next the controller needs to access the meet API, which it does via MEET_SERVER_URL
* The client next opens a websocket with the meet server directly, which requires that the client has access to the meet server API.
* Finally the client needs to establish a webrtc transport with the meet server (possibly via the turn server), and then create producers and consumers for audio/video/screenshare.

Establishing the webrtc connection is the most unclear part, because there is a lot of hidden negotiation done by the browser.
Important checks are:
* Which ICE candidates does the meet server communicate to the client (typically a turn server with an IP that the client can reach directly, typically a public IP)
* These ice candidates should then be visible in firefoxe's about:webrtc view, and one should be selected to establish the transport.
* The mediasoup-client transport should reach the "connected" state. (This is also visible on the server as the "dtlsState" of the transport)
* Ultimately you should see data packets on the server when enabling the trace messages, once a client is connected.

# Scalability

The number of participants a server can handle, greatly depends on the number of streams that need to be handled.
In principle there's at least 2 streams per participant (ignoring screensharing) for audio + video incoming (upstream), and then each of those streams is sent to all participants (excluding the sender). This leads to 2n * (n - 1) streams when everyone is sending and receiving vide + audio. A single cpu core is expected to be able to handle ~500 streams, which leads to ~16 participants.

This number can of course be greatly affected by reducing the number of streams that need to be handled, e.g. listeners not sending video.

## Horizontal scaling

Currently we can scale with the number of threads on a system by using multiple workers, but not across multiple servers.

In the simplest form it would of course be possible to load balance and just distribute rooms on different nodes.

To distribute a single room across different nodes more work is required:
* A transport needs to be established between the nodes.
* For each participant on the remote node all streams need to be proxied as well as the signaling.

The benefits of this should be:
* A room can grow beyond the limits of a server (which would be very large rooms).
* If we assume peers join a geo-local server:
** Instead of having to send N streams across to all peers, we can send 1 stream to the server which then distributes to N peers, reducing the required bandwidth on the path between servers.
** If a reencoder is implemented in each server, latency for request of keyframes is reduced.
** Local peers can use a more efficient direct path between each other and thus further relieve the server interconnection.

## High availability

In the simplest form the server is simply restarted and all clients reconnect. This results in a brief interruption and some state is lost (chat history), but everyone should be back in the same room relatively quickly.

More advanced forms could potentially recover the internal state from e.g. redis, to recover quicker and relatively transparent to the user. I think the transports need to be reestablished, but webrtc should allow for this.

## Reencoder

The reencoder is a process (running on the server) that consumes a track and simply reencodes it and forwards it to the server again. The server will then have to serve that reencoded track to the end user instead of the direct track.
That way a keyframe request can be handled by the reencoder instead of the original client, which is especially useful with geolocated nodes (due to latency), but in general protects the client from constantly having to generate keyframes whenevery a client looses connection.

A reencoder can be implemented using libmediasoupclient and thus probably is a c++ endavour (or potentially rust?).

## Ideas to explore

* Automatically disable video for silent participants, and only enable for last N active speakers: https://docs.openvidu.io/en/2.19.0/openvidu-enterprise/#large-scale-sessions
* Ensure we make use of simulcast (per peer adaptive stream quality, depending on available bandwidth and processing power)
