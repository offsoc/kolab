<template>
    <div v-if="!state.isDone" id="status-box" :class="'p-4 mb-3 rounded process-' + className">
        <div v-if="state.step != 'domain-confirmed'" class="d-flex align-items-start">
            <p id="status-body" class="flex-grow-1">
                <span>{{ $t('status.prepare-' + scopeLabel()) }}</span>
                <br>
                {{ $t('status.prepare-hint') }}
                <br>
                <span id="refresh-text" v-if="refresh">{{ $t('status.prepare-refresh') }}</span>
            </p>
            <btn v-if="refresh" id="status-refresh" href="#" class="btn-secondary" @click="statusRefresh" icon="rotate">
                {{ $t('btn.refresh') }}
            </btn>
        </div>
        <div v-else class="d-flex align-items-start">
            <p id="status-body" class="flex-grow-1">
                <span>{{ $t('status.ready-' + scopeLabel()) }}</span>
                <br>
                {{ $t('status.confirm') }}
            </p>
            <div v-if="scope == 'domain'">
                <btn id="status-confirm" class="btn-secondary text-nowrap" @click="confirmDomain" icon="rotate">
                    {{ $t('btn.confirm') }}
                </btn>
            </div>
            <div v-else-if="state.link && scope != 'domain'">
                <router-link id="status-link" class="btn btn-secondary" :to="{ path: state.link }">{{ $t('status.confirm-domain') }}</router-link>
            </div>
        </div>

        <div class="status-progress text-center">
            <div class="progress">
                <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <span class="progress-label">{{ state.title || $t('msg.initializing') }}</span>
        </div>
    </div>
</template>

<script>
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faRotate').definition,
    )

    export default {
        props: {
            status: { type: Object, default: () => {} }
        },
        data() {
            return {
                className: 'pending',
                refresh: false,
                delay: 5000,
                scope: 'user',
                state: { isDone: true },
                waiting: 0,
            }
        },
        watch: {
            // We use property watcher because parent component
            // might set the property with a delay and we need to parse it
            // FIXME: Problem with this and update-status event is that whenever
            //        we emit the event a watcher function is executed, causing
            //        duplicate parseStatusInfo() calls. Fortunaltely this does not
            //        cause duplicate http requests.
            status: function (val, oldVal) {
                this.parseStatusInfo(val)
            }
        },
        destroyed() {
            clearTimeout(window.infoRequest)
        },
        mounted() {
            this.scope = this.$route.name
        },
        methods: {
            // Displays account status information
            parseStatusInfo(info) {
                if (info) {
                    if (!info.isDone) {
                        let failedCount = 0
                        let allCount = info.process.length

                        info.process.forEach((step, idx) => {
                            if (!step.state) {
                                failedCount++

                                if (!info.title) {
                                    info.title = step.title
                                    info.step = step.label
                                    info.link = step.link
                                }
                            }
                        })

                        info.percent = Math.floor((allCount - failedCount) / allCount * 100);
                    }

                    this.state = info || {}

                    this.$nextTick(function() {
                        $(this.$el).find('.progress-bar')
                            .css('width', info.percent + '%')
                            .attr('aria-valuenow', info.percent)
                    })

                    // Unhide the Refresh button, the process is in failure state
                    this.refresh = info.processState == 'failed' && this.waiting == 0

                    if (this.refresh || info.step == 'domain-confirmed') {
                        this.className = 'failed'
                    }

                    // A async job has been dispatched, switch to a waiting mode where
                    // we hide the Refresh button and pull status for about a minute,
                    // after that we switch to normal mode, i.e. user can Refresh again (if still not ready)
                    if (info.processState == 'waiting') {
                        this.waiting = 10
                        this.delay = 5000
                    } else if (this.waiting > 0) {
                        this.waiting -= 1
                    }
                }

                // Update status process info every 5,6,7,8,9,... seconds
                clearTimeout(window.infoRequest)
                if ((!this.refresh || this.waiting > 0) && (!info || !info.isDone)) {
                    window.infoRequest = setTimeout(() => {
                        delete window.infoRequest
                        // Stop updates after user logged out
                        if (!this.$root.authInfo) {
                            return;
                        }

                        axios.get(this.getUrl())
                            .then(response => {
                                this.parseStatusInfo(response.data)
                                this.emitEvent(response.data)
                            })
                            .catch(error => {
                                this.parseStatusInfo(info)
                            })
                    }, this.delay);

                    this.delay += 1000;
                }
            },
            statusRefresh() {
                clearTimeout(window.infoRequest)

                axios.get(this.getUrl() + '?refresh=1')
                    .then(response => {
                        this.$toast[response.data.status](response.data.message)
                        this.parseStatusInfo(response.data)
                        this.emitEvent(response.data)
                    })
                    .catch(error => {
                        this.parseStatusInfo(this.state)
                    })
            },
            confirmDomain() {
                axios.get('/api/v4/domains/' + this.$route.params.domain + '/confirm')
                    .then(response => {
                        if (response.data.message) {
                            this.$toast[response.data.status](response.data.message)
                        }

                        if (response.data.status == 'success') {
                            this.parseStatusInfo(response.data.statusInfo)
                            response.data.isConfirmed = true
                            this.emitEvent(response.data)
                        }
                    })
            },
            emitEvent(data) {
                // Remove useless data and emit the event (to parent components)
                delete data.status
                delete data.message
                this.$emit('status-update', data)
            },
            getUrl() {
                let scope = this.scope
                let id = this.$route.params[scope]

                if (scope == 'dashboard' || scope == 'settings') {
                    id = this.$root.authInfo.id
                    scope = 'user'
                } else if (scope =='distlist') {
                    id = this.$route.params.list
                    scope = 'group'
                } else if (scope == 'shared-folder') {
                    id = this.$route.params.folder
                }

                return '/api/v4/' + scope + 's/' + id + '/status'
            },
            scopeLabel() {
                return this.scope == 'dashboard' || this.scope == 'settings' ? 'account' : this.scope
            }
        }
    }
</script>
