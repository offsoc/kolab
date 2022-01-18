
<template>
    <div id="room-stats-dialog" class="modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Statistics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="input-group input-group-activable mb-2">
                            <span class="input-group-text label">Room Id:</span>
                            <span class="input-group-text">{{ room }}</span>
                        </div>
                        <div class="input-group input-group-activable mb-2">
                            <span class="input-group-text label">Mediaserver Room Id:</span>
                            <span class="input-group-text">{{ stats.roomId }}</span>
                        </div>
                        <div class="input-group input-group-activable mb-2">
                            <span class="input-group-text label">Send Transport state:</span>
                            <span class="input-group-text">{{ stats.sendTransportState }}</span>
                        </div>
                        <pre class="text-muted">
                            {{ JSON.stringify(stats.sendTransportStats, null, '\t') }}
                        </pre>
                        <div class="input-group input-group-activable mb-2">
                            <span class="input-group-text label">Receive Transport state:</span>
                            <span class="input-group-text">{{ stats.receiveTransportState }}</span>
                        </div>
                        <pre class="text-muted">
                            {{ JSON.stringify(stats.receiveTransportStats, null, '\t') }}
                        </pre>
                    </form>
                    <hr>
                    <form>
                        <div class="input-group input-group-activable mb-2">
                            <span class="input-group-text label">Consumers:</span>
                        </div>
                        <pre class="text-muted">
                            {{ JSON.stringify(stats.consumerStats, null, '\t') }}
                        </pre>
                    </form>
                    <hr>
                    <form>
                        <div class="input-group input-group-activable mb-2">
                            <span class="input-group-text label">Camera Producer:</span>
                        </div>
                        <pre class="text-muted">
                            {{ JSON.stringify(stats.camProducerStats, null, '\t') }}
                        </pre>
                    </form>
                    <hr>
                    <form>
                        <div class="input-group input-group-activable mb-2">
                            <span class="input-group-text label">Mic Producer:</span>
                        </div>
                        <pre class="text-muted">
                            {{ JSON.stringify(stats.micProducerStats, null, '\t') }}
                        </pre>
                    </form>
                    <hr>
                    <form>
                        <div class="input-group input-group-activable mb-2">
                            <span class="input-group-text label">Screen Producer:</span>
                        </div>
                        <pre class="text-muted">
                            {{ JSON.stringify(stats.screenProducerStats, null, '\t') }}
                        </pre>
                    </form>
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
        mounted() {
            $('#room-stats-dialog')[0].addEventListener('show.bs.modal', e => {
                $(e.target).find('.input-group-activable.active').removeClass('active')
            })
        },
        methods: {
        }
    }
</script>
