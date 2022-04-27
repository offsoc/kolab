<template>
    <div id="room-stats-dialog" class="modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content" style="max-height: 90vh">
                <div class="modal-header">
                    <h5 class="modal-title">Statistics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></button>
                </div>
                <div class="modal-body overflow-auto">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <span class="fw-bold">Room Id:</span>
                            <pre class="text-muted m-0">{{ room }}</pre>
                        </li>
                        <li class="list-group-item">
                            <span class="fw-bold">Mediaserver Room Id:</span>
                            <pre class="text-muted m-0">{{ stats.roomId }}</pre>
                        </li>
                        <li class="list-group-item">
                            <span class="fw-bold">Send Transport state:</span>
                            <pre class="text-muted m-0">{{ stats.sendTransportState }}</pre>
                            <pre class="text-muted m-0">{{ toText(stats.sendTransportStats) }}</pre>
                        </li>
                        <li class="list-group-item">
                            <span class="fw-bold">Receive Transport state:</span>
                            <pre class="text-muted m-0">{{ stats.receiveTransportState }}</pre>
                            <pre class="text-muted m-0">{{ toText(stats.receiveTransportStats) }}</pre>
                        </li>
                        <li class="list-group-item">
                            <span class="fw-bold">Consumers:</span>
                            <pre class="text-muted m-0">{{ toText(stats.consumerStats) }}</pre>
                        </li>
                        <li class="list-group-item">
                            <span class="fw-bold">Camera Producer:</span>
                            <pre class="text-muted m-0">{{ toText(stats.camProducerStats) }}</pre>
                        </li>
                        <li class="list-group-item">
                            <span class="fw-bold">Mic Producer:</span>
                            <pre class="text-muted m-0">{{ toText(stats.micProducerStats) }}</pre>
                        </li>
                        <li class="list-group-item">
                            <span class="fw-bold">Screen Producer:</span>
                            <pre class="text-muted m-0">{{ toText(stats.screenProducerStats) }}</pre>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            stats: { type: Object, default: () => null },
            room: { type: String, default: () => null }
        },
        methods: {
            toText(o) {
                return JSON.stringify(o, null, '  ')
            }
        }
    }
</script>
