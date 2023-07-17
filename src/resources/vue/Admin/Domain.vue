<template>
    <div class="container">
        <div class="card" id="domain-info">
            <div class="card-body">
                <div class="card-title">{{ domain.namespace }}</div>
                <div class="card-text">
                    <form class="read-only short">
                        <div class="row plaintext">
                            <label for="domainid" class="col-sm-4 col-form-label">
                                {{ $t('form.id') }} <span class="text-muted">({{ $t('form.created') }})</span>
                            </label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="domainid">
                                    {{ domain.id }} <span class="text-muted">({{ domain.created_at }})</span>
                                </span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="first_name" class="col-sm-4 col-form-label">{{ $t('form.status') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="status">
                                    <span :class="$root.statusClass(domain)">{{ $root.statusText(domain) }}</span>
                                </span>
                            </div>
                        </div>
                    </form>
                    <div class="mt-2 buttons">
                        <btn :id="`button-${suspendAction}`" class="btn-outline-primary" @click="setSuspendState">
                            {{ $t(`btn.${suspendAction}`) }}
                        </btn>
                    </div>
                </div>
            </div>
        </div>
        <tabs class="mt-3" :tabs="['form.config', 'form.settings', 'log.history']" ref="tabs"></tabs>
        <div class="tab-content">
            <div v-if="domain.id" class="tab-pane show active" id="config" role="tabpanel" aria-labelledby="tab-config">
                <div class="card-body">
                    <div class="card-text">
                        <p>{{ $t('domain.dns-confirm') }}</p>
                        <p><pre id="dns-confirm">{{ domain.dns.join("\n") }}</pre></p>
                        <p>{{ $t('domain.dns-config') }}</p>
                        <p><pre id="dns-config">{{ domain.mx.join("\n") }}</pre></p>
                    </div>
                </div>
            </div>
            <div v-if="domain.id" class="tab-pane" id="settings" role="tabpanel" aria-labelledby="tab-settings">
                <div class="card-body">
                    <div class="card-text">
                        <form class="read-only short">
                            <div class="row plaintext">
                                <label for="spf_whitelist" class="col-sm-4 col-form-label">{{ $t('domain.spf-whitelist') }}</label>
                                <div class="col-sm-8">
                                    <span class="form-control-plaintext" id="spf_whitelist">
                                        {{ domain.config && domain.config.spf_whitelist.length ? domain.config.spf_whitelist.join(', ') : $t('form.none') }}
                                    </span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="history" role="tabpanel" aria-labelledby="tab-history">
                <div class="card-body">
                    <event-log v-if="loadEventLog" :object-id="domain.id" object-type="domain" ref="eventLog" class="card-text mb-0"></event-log>
                </div>
            </div>
        </div>

        <modal-dialog id="suspend-dialog" ref="suspendDialog" :title="$t(`btn.${suspendAction}`)" @click="submitSuspend()" :buttons="['submit']">
            <textarea v-model="comment" name="comment" class="form-control" :placeholder="$t('form.comment')" rows="3"></textarea>
        </modal-dialog>
    </div>
</template>

<script>
    import EventLog from '../Widgets/EventLog'
    import ModalDialog from '../Widgets/ModalDialog'

    export default {
        components: {
            EventLog,
            ModalDialog
        },
        data() {
            return {
                comment: '',
                domain: {},
                loadEventLog: false
            }
        },
        computed: {
            suspendAction() {
                return this.domain.isSuspended ? 'unsuspend' : 'suspend'
            }
        },
        created() {
            const domain_id = this.$route.params.domain;

            axios.get('/api/v4/domains/' + domain_id)
                .then(response => {
                    this.domain = response.data
                })
                .catch(this.$root.errorHandler)
        },
        mounted() {
            this.$refs.tabs.clickHandler('history', () => { this.loadEventLog = true })
        },
        methods: {
            setSuspendState() {
                this.$root.clearFormValidation($('#suspend-dialog'))
                this.$refs.suspendDialog.show()
            },
            submitSuspend() {
                const post = { comment: this.comment }

                axios.post(`/api/v4/domains/${this.domain.id}/${this.suspendAction}`, post)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.domain = Object.assign({}, this.domain, { isSuspended: !this.domain.isSuspended })

                            this.$refs.suspendDialog.hide()
                            this.comment = ''

                            if (this.loadEventLog) {
                                this.$refs.eventLog.loadLog({ reset: true })
                            }
                        }
                    })
            }
        }
    }
</script>
