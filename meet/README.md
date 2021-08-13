
# To get an interactive console
/src/meetsrc/connect.js

# To dump some stats
/src/meetsrc/connect.js --stats

# Test the websocket
npm -g install wscat
wscat --no-check -c "wss://172.20.0.2:12443/socket.io/?peerId=peer1&roomId=room1&EIO=3&transport=websocket"
