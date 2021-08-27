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
* Client <-> Kolabmeet Websocket (Signaling
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
