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
