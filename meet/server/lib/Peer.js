const EventEmitter = require('events').EventEmitter;
const Logger = require('./Logger');
const crypto = require('crypto');
const Roles = require('./userRoles');
const { v4: uuidv4 } = require('uuid');

const logger = new Logger('Peer');

class Peer extends EventEmitter {
    constructor({ roomId }) {
        logger.info('Peer constructor()');

        super();

        this._id = uuidv4();

        this._roomId = roomId;

        this._socket = null;

        this._closed = false;

        this._role = 0;

        this._nickname = false;

        this._language = null;

        this._routerId = null;

        this._rtpCapabilities = null;

        this._raisedHand = false;

        this._disconnected = false;

        this._transports = new Map();

        this._producers = new Map();

        this._consumers = new Map();

        this._authToken = crypto.randomBytes(16).toString('hex');
    }

    close() {
        if (this._closed)
            return;

        logger.info('close()');

        this._closed = true;

        // Iterate and close all mediasoup Transport associated to this Peer, so all
        // its Producers and Consumers will also be closed.
        for (const transport of this.transports.values()) {
            transport.close();
        }

        if (this.socket)
            this.socket.disconnect(true);


        if (this._selfDestructTimeout)
            clearTimeout(this._selfDestructTimeout);

        this._selfDestructTimeout = null;

        this.emit('close');
    }

    get authToken() {
        return this._authToken;
    }

    get id() {
        return this._id;
    }

    get roomId() {
        return this._roomId;
    }

    get workerId() {
        return this._workerId;
    }

    get socket() {
        return this._socket;
    }

    set socket(socket) {
        this._socket = socket;

        if (this._socket) {
            this._disconnectListener = (reason) => {
                this._disconnected = true

                logger.debug('"disconnect" event [id:%s, reason:%s]', this.id, reason);

                if (reason === "client namespace disconnect") {
                    this.close();
                } else {
                    //If this was a connection interruption we allow to reconnect within 10s
                    //TODO inform peers about disconnected state
                    clearTimeout(this._selfDestructTimeout);

                    this._selfDestructTimeout = setTimeout(() => {
                        logger.info(
                            'Closing peer after reconnect timeout [id:"%s"]',
                            this.id);
                        this.close();
                    }, 10000);
                }
            }
            this._socket.on('disconnect', this._disconnectListener);

            this._requestListener = (request, cb) => {
                this.emit('request', request, cb);
            }

            this._socket.on('request', this._requestListener);
        }
    }

    get closed() {
        return this._closed;
    }

    get disconnected() {
        return this._disconnected;
    }

    get role() {
        return this._role;
    }

    get nickname() {
        return this._nickname;
    }

    get language() {
        return this._language;
    }

    set nickname(nickname) {
        if (nickname !== this._nickname) {
            this._nickname = nickname;

            this.emit('nicknameChanged');
        }
    }

    set language(language) {
        if (language != this._language) {
            this._language = language;

            this.emit('languageChanged');
        }
    }

    get routerId() {
        return this._routerId;
    }

    set routerId(routerId) {
        this._routerId = routerId;
    }

    set workerId(workerId) {
        this._workerId = workerId;
    }

    get rtpCapabilities() {
        return this._rtpCapabilities;
    }

    set rtpCapabilities(rtpCapabilities) {
        this._rtpCapabilities = rtpCapabilities;
    }

    get raisedHand() {
        return this._raisedHand;
    }

    set raisedHand(raisedHand) {
        if (this._raisedHand != raisedHand) {
            this._raisedHand = raisedHand;

            this.emit('raisedHandChanged');
        }
    }

    get transports() {
        return this._transports;
    }

    get producers() {
        return this._producers;
    }

    get consumers() {
        return this._consumers;
    }

    setRole(newRole) {
        if (this._role != newRole) {
            this._role = newRole;

            this.emit('roleChanged');
        }
    }

    isValidRole(newRole) {
        Object.keys(Roles).forEach(roleId => {
            const role = Roles[roleId]
            if (newRole & role) {
                newRole = newRole ^ role;
            }
        })

        return newRole == 0;
    }

    hasRole(role) {
        return !!(this._role & role);
    }

    addTransport(id, transport) {
        this.transports.set(id, transport);
    }

    getTransport(id) {
        return this.transports.get(id);
    }

    getConsumerTransport() {
        return Array.from(this.transports.values())
            .find((t) => t.appData.consuming);
    }

    removeTransport(id) {
        this.transports.delete(id);
    }

    addProducer(id, producer) {
        this.producers.set(id, producer);
    }

    getProducer(id) {
        return this.producers.get(id);
    }

    removeProducer(id) {
        this.producers.delete(id);
    }

    addConsumer(id, consumer) {
        this.consumers.set(id, consumer);
    }

    getConsumer(id) {
        return this.consumers.get(id);
    }

    removeConsumer(id) {
        this.consumers.delete(id);
    }

    get peerInfo() {
        const peerInfo =
        {
            id: this.id,
            language: this.language,
            nickname: this.nickname,
            role: this.role,
            raisedHand: this.raisedHand
        };

        return peerInfo;
    }

    joinRoom(newSocket) {
        clearTimeout(this._selfDestructTimeout);

        if (this._socket) {
            logger.debug("Peer is reconnecting ", this.id)
            this._socket.removeListener('disconnect', this._disconnectListener);
            this._socket.removeListener('request', this._requestListener);
            this._socket.disconnect();
        }

        this.socket = newSocket;
        this.socket.join(this._roomId);
    }
}

module.exports = Peer;
