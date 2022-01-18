'use strict'

import io from "socket.io-client"
import Config from './config.js'

function Socket(url, options)
{
    let eventHandlers = {}

    const socket = io(url, {
        path: '/meetmedia/signaling/',
        transports: [ 'websocket' ]
    })

    socket.io.on("reconnect_attempt", () => {
        console.log(`WebSocket re-connect attempt`)
    });

    socket.io.on("reconnect_error", () => {
        console.log(`WebSocket re-connect error`)
    });

    socket.io.on("reconnect", () => {
        console.log(`WebSocket re-connect`)
    });

    socket.io.on('reconnect_failed', () => {
        console.log('WebSocket re-connect failed')

        this.trigger('reconnectFailed')
    })

    socket.on('connect', () => {
        console.log('WebSocket connect: ' + socket.id)
    })

    socket.on('connect_error', () => {
        console.log('WebSocket connect_error: ' + socket.id)
    })

    socket.on('disconnect', reason => {
        console.log('WebSocket disconnect: ' + reason)

        this.trigger('disconnect', reason)
    })


    socket.on('request', async (request, cb) => {
        console.log('Recv: ' + request.method, request.data)

        this.trigger('request', request, cb)
    })

    socket.on('notification', async notification => {
        console.log('Recv: ' + notification.method, notification.data)

        this.trigger('notification', notification)
    })

    this.close = () => {
        socket.close()
    }

    this.getRtpCapabilities = async () => {
        return await this.sendRequest('getRouterRtpCapabilities')
    }

    /**
     * Register event handlers
     */
    this.on = (eventName, callback) => {
        eventHandlers[eventName] = callback
    }

    /**
     * Execute an event handler
     */
    this.trigger = (...args) => {
        const eventName = args.shift()

        if (eventName in eventHandlers) {
            eventHandlers[eventName].apply(null, args)
        }
    }

    this.sendRequest = (method, data) => {
        return new Promise((resolve, reject) => {
            console.log('Send: ' + method, data)

            socket.emit(
                'request',
                { method, data },
                withTimeout((error, response) => {
                    if (error) {
                        console.error('Error on: ' + method, error)
                        reject(error)
                    } else {
                        console.log('Recv: ' + method, response)
                        resolve(response)
                    }
                })
            )
        })
    }

    const withTimeout = (callback) => {
        let called = false

        const timer = setTimeout(
            () => {
                if (called) return
                called = true
                callback(new Error('Request timed out'))
            },
            Config.requestTimeout
        )

        return (...args) => {
            if (called) return
            called = true
            clearTimeout(timer)
            callback(...args)
        }
    }
}

export { Socket }
