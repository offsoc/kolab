const EventEmitter = require('events').EventEmitter;
const userRoles = require('../userRoles');
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

        this._joined = false;

        this._joinedTimestamp = null;

        this._authenticated = false;

        this._authenticatedTimestamp = null;

        this._roles = [ userRoles.NORMAL ];

        this._nickname = false;

        this._picture = null;

        this._email = null;

        this._routerId = null;

        this._rtpCapabilities = null;

        this._raisedHand = false;

        this._raisedHandTimestamp = null;

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

    get joined()
    {
        return this._joined;
    }

    set joined(joined)
    {
        joined ?
            this._joinedTimestamp = Date.now() :
            this._joinedTimestamp = null;

        this._joined = joined;
    }

    get joinedTimestamp()
    {
        return this._joinedTimestamp;
    }

    get authenticated()
    {
        return this._authenticated;
    }

    set authenticated(authenticated)
    {
        if (authenticated !== this._authenticated)
        {
            authenticated ?
                this._authenticatedTimestamp = Date.now() :
                this._authenticatedTimestamp = null;

            const oldAuthenticated = this._authenticated;

            this._authenticated = authenticated;

            this.emit('authenticationChanged', { oldAuthenticated });
        }
    }

    get authenticatedTimestamp()
    {
        return this._authenticatedTimestamp;
    }

    get roles()
    {
        return this._roles;
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
        raisedHand ?
            this._raisedHandTimestamp = Date.now() :
            this._raisedHandTimestamp = null;

        this._raisedHand = raisedHand;
    }

    get raisedHandTimestamp()
    {
        return this._raisedHandTimestamp;
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

    addRole(newRole)
    {
        if (
            !this._roles.some((role) => role.id === newRole.id) &&
            newRole.id !== userRoles.NORMAL.id // Can not add NORMAL
        )
        {
            this._roles.push(newRole);

            logger.info('addRole() | [newRole:"%s]"', newRole);

            this.emit('gotRole', { newRole });
        }
    }

    removeRole(oldRole)
    {
        if (
            this._roles.some((role) => role.id === oldRole.id) &&
            oldRole.id !== userRoles.NORMAL.id // Can not remove NORMAL
        )
        {
            this._roles = this._roles.filter((role) => role.id !== oldRole.id);

            logger.info('removeRole() | [oldRole:"%s]"', oldRole);

            this.emit('lostRole', { oldRole });
        }
    }

    hasRole(role)
    {
        return this._roles.some((myRole) => myRole.id === role.id);
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
            picture: this.picture,
            roles: this.roles.map((role) => role.id),
            raisedHand: this.raisedHand,
            raisedHandTimestamp: this.raisedHandTimestamp
        };

        return peerInfo;
    }
}

module.exports = Peer;
