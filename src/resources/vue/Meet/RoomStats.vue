<template>
    <modal-dialog id="room-stats-dialog" ref="dialog" title="Statistics">
        <p>
            <span class="fw-bold">Room Id:</span>
            <pre class="text-muted m-0">{{ room }}</pre>
        </p>
        <p>
            <span class="fw-bold">Mediaserver Room Id:</span>
            <pre class="text-muted m-0">{{ stats.roomId }}</pre>
        </p>
        <p>
            <span class="fw-bold">Sender Transport:</span>
            <pre class="text-muted m-0">{{ stats.sendTransportState }}</pre>
            <pre class="text-muted m-0">{{ toText(stats.sendTransportStats) }}</pre>
        </p>
        <p>
            <span class="fw-bold">Receiver Transport:</span>
            <pre class="text-muted m-0">{{ stats.receiveTransportState }}</pre>
            <pre class="text-muted m-0">{{ toText(stats.receiveTransportStats) }}</pre>
        </p>
        <p>
            <span class="fw-bold">Consumers:</span>
            <pre class="text-muted m-0">{{ toText(stats.consumerStats) }}</pre>
        </p>
        <p>
            <span class="fw-bold">Camera Producer:</span>
            <pre class="text-muted m-0">{{ toText(stats.camProducerStats) }}</pre>
        </p>
        <p>
            <span class="fw-bold">Mic Producer:</span>
            <pre class="text-muted m-0">{{ toText(stats.micProducerStats) }}</pre>
        </p>
        <p>
            <span class="fw-bold">Screen Producer:</span>
            <pre class="text-muted m-0">{{ toText(stats.screenProducerStats) }}</pre>
        </p>
    </modal-dialog>
</template>

<script>
    import ModalDialog from '../Widgets/ModalDialog'

    export default {
        components: {
            ModalDialog
        },
        props: {
            room: { type: String, default: () => null }
        },
        data() {
            return {
                stats: {}
            }
        },
        mounted() {
            this.$refs.dialog.events({
                show: () => {
                    clearInterval(this.statsRequest)
                    this.refreshStats()
                    this.statsRequest = setInterval(() => { this.refreshStats() }, 3000)
                },
                hide: () => {
                    clearInterval(this.statsRequest)
                }
            })
        },
        methods: {
            async refreshStats() {
                let stats = await this.meet.getStats()
                this.stats = stats
            },
            toggle(meet) {
                this.meet = meet
                this.$refs.dialog[this.statsRequest ? 'hide' : 'show']()
            },
            toText(o) {
                return JSON.stringify(o, null, '  ')
            }
        }
    }
</script>
