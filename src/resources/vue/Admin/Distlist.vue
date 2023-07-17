<template>
    <div class="container">
        <div v-if="list.id" class="card" id="distlist-info">
            <div class="card-body">
                <div class="card-title">{{ list.email }}</div>
                <div class="card-text">
                    <form class="read-only short">
                        <div class="row plaintext">
                            <label for="distlistid" class="col-sm-4 col-form-label">
                                {{ $t('form.id') }} <span class="text-muted">({{ $t('form.created') }})</span>
                            </label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="distlistid">
                                    {{ list.id }} <span class="text-muted">({{ list.created_at }})</span>
                                </span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="status" class="col-sm-4 col-form-label">{{ $t('form.status') }}</label>
                            <div class="col-sm-8">
                                <span :class="$root.statusClass(list) + ' form-control-plaintext'" id="status">{{ $root.statusText(list) }}</span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="name" class="col-sm-4 col-form-label">{{ $t('distlist.name') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="name">{{ list.name }}</span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="members" class="col-sm-4 col-form-label">{{ $t('distlist.recipients') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="members">
                                    <span v-for="member in list.members" :key="member">{{ member }}<br></span>
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
        <tabs class="mt-3" :tabs="['form.settings', 'log.history']" ref="tabs"></tabs>
        <div v-if="list.id" class="tab-content">
            <div class="tab-pane show active" id="settings" role="tabpanel" aria-labelledby="tab-settings">
                <div class="card-body">
                    <div class="card-text">
                        <form class="read-only short">
                            <div class="row plaintext">
                                <label for="sender_policy" class="col-sm-4 col-form-label">{{ $t('distlist.sender-policy') }}</label>
                                <div class="col-sm-8">
                                    <span class="form-control-plaintext" id="sender_policy">
                                        {{ list.config.sender_policy && list.config.sender_policy.length ? list.config.sender_policy.join(', ') : $t('form.none') }}
                                    </span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="history" role="tabpanel" aria-labelledby="tab-history">
                <div class="card-body">
                    <event-log v-if="loadEventLog" :object-id="list.id" object-type="group" ref="eventLog" class="card-text mb-0"></event-log>
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
                list: { members: [], config: {} },
                loadEventLog: false
            }
        },
        computed: {
            suspendAction() {
                return this.list.isSuspended ? 'unsuspend' : 'suspend'
            }
        },
        created() {
            axios.get('/api/v4/groups/' + this.$route.params.list, { loader: true })
                .then(response => {
                    this.list = response.data
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

                axios.post(`/api/v4/groups/${this.list.id}/${this.suspendAction}`, post)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.list = Object.assign({}, this.list, { isSuspended: !this.list.isSuspended })

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
