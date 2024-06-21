const os = require('os');
const path = require('path');
const repl = require('repl');
const readline = require('readline');
const net = require('net');
const fs = require('fs');
const mediasoup = require('mediasoup');
const colors = require('colors/safe');
const pidusage = require('pidusage');

const SOCKET_PATH_UNIX = '/tmp/kolabmeet-server.sock';
const SOCKET_PATH_WIN = path.join('\\\\?\\pipe', process.cwd(), 'kolabmeet-server');
const SOCKET_PATH = os.platform() === 'win32' ? SOCKET_PATH_WIN : SOCKET_PATH_UNIX;

// Maps to store all mediasoup objects.
const workers = new Map();
const routers = new Map();
const transports = new Map();
const producers = new Map();
const consumers = new Map();
const dataProducers = new Map();
const dataConsumers = new Map();

class Interactive {
    constructor(socket) {
        this._socket = socket;

        this._isTerminalOpen = false;
    }

    async dump(name, params, entries) {
        const list = params[0] ? [entries.get(params[0])] : entries.values();

        for (const entry of list) {
            if (!entry) {
                this.error(`${name} not found`);
                break;
            }

            try {
                const dump = await entry.dump();
                this.log(`${name}.dump():\n${JSON.stringify(dump, null, '  ')}`);
            } catch (error) {
                this.error(`${name}.dump() failed: ${error}`);
            }
        }
    }

    async dumpStats(name, params, entries) {
        const list = params[0] ? [entries.get(params[0])] : entries.values();

        for (const entry of list) {
            if (!entry) {
                this.error(`${name} not found`);
                break;
            }

            try {
                const stats = await entry.getStats();
                this.log(`${name}.getStats():\n${JSON.stringify(stats, null, '  ')}`);
            } catch (error) {
                this.error(`${name}.getStats() failed: ${error}`);
            }
        }
    }

    async processCommand(command, params) {
        switch (command) {
        case '':
        {
            break;
        }

        case 'h':
        case 'help':
        {
            this.log('');
            this.log('available commands:');
            this.log('- h,  help                    : show this message');
            this.log('- usage                       : show CPU and memory usage of the Node.js and mediasoup-worker processes');
            this.log('- logLevel level              : changes logLevel in all mediasoup Workers');
            this.log('- logTags [tag] [tag]         : changes logTags in all mediasoup Workers (values separated by space)');
            this.log('- dumpRooms                   : dump all rooms');
            this.log('- dumpPeers                   : dump all peers');
            this.log('- dw, dumpWorkers             : dump mediasoup Workers');
            this.log('- dr, dumpRouter [id]         : dump mediasoup Router with given id (or the latest created one)');
            this.log('- dt, dumpTransport [id]      : dump mediasoup Transport with given id (or the latest created one)');
            this.log('- dp, dumpProducer [id]       : dump mediasoup Producer with given id (or the latest created one)');
            this.log('- dc, dumpConsumer [id]       : dump mediasoup Consumer with given id (or the latest created one)');
            this.log('- sr, statsRoom [id]          : get stats for the room with id');
            this.log('- st, statsTransport [id]     : get stats for mediasoup Transport with given id (or all)');
            this.log('- sp, statsProducer [id]      : get stats for mediasoup Producer with given id (or all)');
            this.log('- sc, statsConsumer [id]      : get stats for mediasoup Consumer with given id (or all)');
            this.log('- ddp, dumpDataProducer [id]  : dump mediasoup DataProducer with given id (or the latest created one)');
            this.log('- ddc, dumpDataConsumer [id]  : dump mediasoup DataConsumer with given id (or the latest created one)');
            this.log('- sdp, statsDataProducer [id] : get stats for mediasoup DataProducer with given id (or the latest created one)');
            this.log('- sdc, statsDataConsumer [id] : get stats for mediasoup DataConsumer with given id (or the latest created one)');
            this.log('- t,  terminal                : open Node REPL Terminal');
            this.log('');

            break;
        }

        case 'u':
        case 'usage':
        {
            let usage = await pidusage(process.pid);

            this.log(`Node.js process [pid:${process.pid}]:\n${JSON.stringify(usage, null, '  ')}`);

            for (const worker of workers.values()) {
                usage = await pidusage(worker.pid);

                this.log(`mediasoup-worker process [pid:${worker.pid}]:\n${JSON.stringify(usage, null, '  ')}`);
            }

            break;
        }

        case 'logLevel':
        {
            const level = params[0];
            const promises = [];

            for (const worker of workers.values()) {
                promises.push(worker.updateSettings({ logLevel: level }));
            }

            try {
                await Promise.all(promises);

                this.log('done');
            } catch (error) {
                this.error(String(error));
            }

            break;
        }

        case 'logTags':
        {
            const tags = params;
            const promises = [];

            for (const worker of workers.values()) {
                promises.push(worker.updateSettings({ logTags: tags }));
            }

            try {
                await Promise.all(promises);

                this.log('done');
            } catch (error) {
                this.error(String(error));
            }

            break;
        }

        case 'stats':
        {
            this.log(`rooms:${global.rooms.size}\npeers:${global.peers.size}`);

            break;
        }

        case 'sr':
        case 'statsRoom': {
            const room = global.rooms.get(params[0]);
            this.log(`Room ${room._roomId}`);
            this.log(`Stats \n${JSON.stringify(room.stats(), null, '  ')}`);
            for (const peer of Object.values(room._peers)) {
                this.log(`Peer ${peer._nickname}`);
                for (const entry of peer._consumers.values()) {
                    const stats = await entry.getStats();
                    this.log(`Consumer:\n${JSON.stringify(stats, null, '  ')}`);
                }
                for (const entry of peer._producers.values()) {
                    const stats = await entry.getStats();
                    this.log(`Producer:\n${JSON.stringify(stats, null, '  ')}`);
                }
                for (const entry of peer._transports.values()) {
                    const stats = await entry.getStats();
                    this.log(`Transport:\n${JSON.stringify(stats, null, '  ')}`);
                }
            }
            break;
        }

        case 'dumpRooms':
        {
            for (const room of global.rooms.values()) {
                try {
                    const dump = await room.dump();

                    this.log(`room.dump():\n${JSON.stringify(dump, null, '  ')}`);
                } catch (error) {
                    this.error(`room.dump() failed: ${error}`);
                }
            }

            break;
        }

        case 'dumpPeers':
        {
            for (const peer of global.peers.values()) {
                try {
                    const dump = await peer.peerInfo;

                    this.log(`peer.peerInfo():\n${JSON.stringify(dump, null, '  ')}`);
                } catch (error) {
                    this.error(`peer.peerInfo() failed: ${error}`);
                }
            }

            break;
        }

        case 'dw':
        case 'dumpWorkers':
        {
            for (const worker of workers.values()) {
                try {
                    const dump = await worker.dump();

                    this.log(`worker.dump():\n${JSON.stringify(dump, null, '  ')}`);
                } catch (error) {
                    this.error(`worker.dump() failed: ${error}`);
                }
            }

            break;
        }

        case 'dr':
        case 'dumpRouter':
        {
            await this.dump('router', params, routers);
            break;
        }

        case 'dt':
        case 'dumpTransport':
        {
            await this.dump('transport', params, transports);
            break;
        }

        case 'dp':
        case 'dumpProducer':
        {
            await this.dump('producer', params, producers);
            break;
        }

        case 'dc':
        case 'dumpConsumer':
        {
            await this.dump('consumer', params, consumers);
            break;
        }

        case 'ddp':
        case 'dumpDataProducer':
        {
            await this.dump('dataProducer', params, dataProducers);
            break;
        }

        case 'ddc':
        case 'dumpDataConsumer':
        {
            await this.dump('dataConsumer', params, dataConsumers);
            break;
        }

        case 'st':
        case 'statsTransport':
        {
            await this.dumpStats('transport', params, transports);
            break;
        }

        case 'sp':
        case 'statsProducer':
        {
            await this.dumpStats('producer', params, producers);
            break;
        }

        case 'sc':
        case 'statsConsumer':
        {
            await this.dumpStats('consumer', params, consumers);
            break;
        }

        case 'sdp':
        case 'statsDataProducer':
        {
            await this.dumpStats('dataProducer', params, dataProducers);
            break;
        }

        case 'sdc':
        case 'statsDataConsumer':
        {
            await this.dumpStats('dataConsumer', params, dataConsumers);
            break;
        }

        case 't':
        case 'terminal':
        {
            return false;
        }

        default:
        {
            this.error(`unknown command '${command}'`);
            this.log('press \'h\' or \'help\' to get the list of available commands');
        }
        }
        return true;
    }

    openCommandConsole() {
        const cmd = readline.createInterface(
            {
                input    : this._socket,
                output   : this._socket,
                terminal : true
            });

        cmd.on('close', () => {
            if (this._isTerminalOpen)
                return;

            this.log('\nexiting...');

            this._socket.end();
        });

        const readStdin = () => {
            cmd.question('cmd> ', async (input) => {
                const params = input.split(/[\s\t]+/);
                const command = params.shift();
                try {
                    const ret = await this.processCommand(command, params);
                    if (!ret) {
                        this._isTerminalOpen = true;
                        cmd.close();
                        this.openTerminal();
                        return;
                    }
                } catch (error) {
                    this.error(`Processing of command ${command} ${params} failed: ${error}`);
                }

                readStdin();
            });
        };

        readStdin();
    }

    openTerminal() {
        this.log('\n[opening Node REPL Terminal...]');
        this.log('here you have access to workers, routers, transports, producers, consumers, dataProducers and dataConsumers ES6 maps');

        const terminal = repl.start(
            {
                input           : this._socket,
                output          : this._socket,
                terminal        : true,
                prompt          : 'terminal> ',
                useColors       : true,
                useGlobal       : true,
                ignoreUndefined : false
            });

        this._isTerminalOpen = true;

        terminal.on('exit', () => {
            this.log('\n[exiting Node REPL Terminal...]');

            this._isTerminalOpen = false;

            this.openCommandConsole();
        });
    }

    log(msg) {
        try {
            this._socket.write(`${colors.green(msg)}\n`);
        } catch (error) {
            //Do nothing
        }
    }

    error(msg) {
        try {
            this._socket.write(`${colors.red.bold('ERROR: ')}${colors.red(msg)}\n`);
        } catch (error) {
            //Do nothing
        }
    }
}

function runMediasoupObserver() {
    mediasoup.observer.on('newworker', (worker) => {
        // Store the latest worker in a global variable.
        global.worker = worker;

        workers.set(worker.pid, worker);
        worker.observer.on('close', () => workers.delete(worker.pid));

        worker.observer.on('newrouter', (router) => {
            // Store the latest router in a global variable.
            global.router = router;

            routers.set(router.id, router);
            router.observer.on('close', () => routers.delete(router.id));

            router.observer.on('newtransport', (transport) => {
                // Store the latest transport in a global variable.
                global.transport = transport;

                transports.set(transport.id, transport);
                transport.observer.on('close', () => transports.delete(transport.id));

                transport.observer.on('newproducer', (producer) => {
                    // Store the latest producer in a global variable.
                    global.producer = producer;

                    producers.set(producer.id, producer);
                    producer.observer.on('close', () => producers.delete(producer.id));
                });

                transport.observer.on('newconsumer', (consumer) => {
                    // Store the latest consumer in a global variable.
                    global.consumer = consumer;

                    consumers.set(consumer.id, consumer);
                    consumer.observer.on('close', () => consumers.delete(consumer.id));
                });

                transport.observer.on('newdataproducer', (dataProducer) => {
                    // Store the latest dataProducer in a global variable.
                    global.dataProducer = dataProducer;

                    dataProducers.set(dataProducer.id, dataProducer);
                    dataProducer.observer.on('close', () => dataProducers.delete(dataProducer.id));
                });

                transport.observer.on('newdataconsumer', (dataConsumer) => {
                    // Store the latest dataConsumer in a global variable.
                    global.dataConsumer = dataConsumer;

                    dataConsumers.set(dataConsumer.id, dataConsumer);
                    dataConsumer.observer.on('close', () => dataConsumers.delete(dataConsumer.id));
                });
            });
        });
    });
}

module.exports = async function(rooms, peers) {
    try {
        // Run the mediasoup observer API.
        runMediasoupObserver();

        // Make maps global so they can be used during the REPL terminal.
        global.rooms = rooms;
        global.peers = peers;
        global.workers = workers;
        global.routers = routers;
        global.transports = transports;
        global.producers = producers;
        global.consumers = consumers;
        global.dataProducers = dataProducers;
        global.dataConsumers = dataConsumers;

        const server = net.createServer((socket) => {
            const interactive = new Interactive(socket);

            interactive.openCommandConsole();
        });

        await new Promise((resolve) => {
            try {
                fs.unlinkSync(SOCKET_PATH); 
            } catch (error) {
                //Do nothing
            }

            server.listen(SOCKET_PATH, resolve);
        });
    } catch (error) {
        //Do nothing
    }
};
