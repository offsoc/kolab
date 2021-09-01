const EventEmitter = require('events').EventEmitter;
const Logger = require('./Logger');

const logger = new Logger('Peer');

class Peer extends EventEmitter
{
    constructor({ id, roomId })
    {
        logger.info('constructor() [id:"%s"]', id);
        super();

        this._id = id;

        this._roomId = roomId;

        this._socket = null;

        this._closed = false;

        this._role = 0;

        this._nickname = false;

        this._picture = null;

        this._email = null;

        this._routerId = null;

        this._rtpCapabilities = null;

        this._raisedHand = false;

        this._transports = new Map();

        this._producers = new Map();

        this._consumers = new Map();
    }

    close()
    {
        logger.info('close()');

        this._closed = true;

        // Iterate and close all mediasoup Transport associated to this Peer, so all
        // its Producers and Consumers will also be closed.
        for (const transport of this.transports.values())
        {
            transport.close();
        }

        if (this.socket)
            this.socket.disconnect(true);

        this.emit('close');
    }

    get id()
    {
        return this._id;
    }

    set id(id)
    {
        this._id = id;
    }

    get roomId()
    {
        return this._roomId;
    }

    set roomId(roomId)
    {
        this._roomId = roomId;
    }

    get socket()
    {
        return this._socket;
    }

    set socket(socket)
    {
        this._socket = socket;

        if (this.socket)
        {
            this.socket.on('disconnect', () =>
            {
                if (this.closed)
                    return;

                logger.debug('"disconnect" event [id:%s]', this.id);

                this.close();
            });
        }
    }

    get closed()
    {
        return this._closed;
    }

    get role()
    {
        return this._role;
    }

    get nickname()
    {
        return this._nickname;
    }

    set nickname(nickname)
    {
        if (nickname !== this._nickname)
        {
            this._nickname = nickname;

            this.emit('nicknameChanged', {});
        }
    }

    get picture()
    {
        return this._picture;
    }

    set picture(picture)
    {
        if (picture !== this._picture)
        {
            const oldPicture = this._picture;

            this._picture = picture;

            this.emit('pictureChanged', { oldPicture });
        }
    }

    get email()
    {
        return this._email;
    }

    set email(email)
    {
        this._email = email;
    }

    get routerId()
    {
        return this._routerId;
    }

    set routerId(routerId)
    {
        this._routerId = routerId;
    }

    get rtpCapabilities()
    {
        return this._rtpCapabilities;
    }

    set rtpCapabilities(rtpCapabilities)
    {
        this._rtpCapabilities = rtpCapabilities;
    }

    get raisedHand()
    {
        return this._raisedHand;
    }

    set raisedHand(raisedHand)
    {
        this._raisedHand = raisedHand;
    }

    get transports()
    {
        return this._transports;
    }

    get producers()
    {
        return this._producers;
    }

    get consumers()
    {
        return this._consumers;
    }

    setRole(newRole)
    {
        if (this._role != newRole) {
            this._role = newRole;

            this.emit('gotRole', { newRole });
        }
    }

    hasRole(role)
    {
        return !!(this._role & role);
    }

    addTransport(id, transport)
    {
        this.transports.set(id, transport);
    }

    getTransport(id)
    {
        return this.transports.get(id);
    }

    getConsumerTransport()
    {
        return Array.from(this.transports.values())
            .find((t) => t.appData.consuming);
    }

    removeTransport(id)
    {
        this.transports.delete(id);
    }

    addProducer(id, producer)
    {
        this.producers.set(id, producer);
    }

    getProducer(id)
    {
        return this.producers.get(id);
    }

    removeProducer(id)
    {
        this.producers.delete(id);
    }

    addConsumer(id, consumer)
    {
        this.consumers.set(id, consumer);
    }

    getConsumer(id)
    {
        return this.consumers.get(id);
    }

    removeConsumer(id)
    {
        this.consumers.delete(id);
    }

    get peerInfo()
    {
        const peerInfo =
        {
            id: this.id,
            nickname: this.nickname,
            // picture: this.picture,
            role: this.role,
            raisedHand: this.raisedHand
        };

        return peerInfo;
    }
}

module.exports = Peer;
