<template>
    <div v-if="!state.isReady" id="status-box" :class="'p-4 mb-3 rounded process-' + className">
        <div v-if="state.step != 'domain-confirmed'" class="d-flex align-items-start">
            <p id="status-body" class="flex-grow-1">
                <span v-if="scope == 'dashboard'">We are preparing your account.</span>
                <span v-else-if="scope == 'domain'">We are preparing the domain.</span>
                <span v-else>We are preparing the user account.</span>
                <br>
                Some features may be missing or readonly at the moment.<br>
                <span id="refresh-text" v-if="refresh">The process never ends? Press the "Refresh" button, please.</span>
            </p>
            <button v-if="refresh" id="status-refresh" href="#" class="btn btn-secondary" @click="statusRefresh">
                <svg-icon icon="sync-alt"></svg-icon> Refresh
            </button>
        </div>
        <div v-else class="d-flex align-items-start">
            <p id="status-body" class="flex-grow-1">
                <span v-if="scope == 'dashboard'">Your account is almost ready.</span>
                <span v-else-if="scope == 'domain'">The domain is almost ready.</span>
                <span v-else>The user account is almost ready.</span>
                <br>
                Verify your domain to finish the setup process.
            </p>
            <div v-if="scope == 'domain'">
                <button id="status-verify" class="btn btn-secondary text-nowrap" @click="confirmDomain">
                    <svg-icon icon="sync-alt"></svg-icon> Verify
                </button>
            </div>
            <div v-else-if="state.link && scope != 'domain'">
                <router-link id="status-link" class="btn btn-secondary" :to="{ path: state.link }">Verify domain</router-link>
            </div>
        </div>

        <div class="status-progress text-center">
            <div class="progress">
                <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <span class="progress-label">{{ state.title || 'Initializing...' }}</span>
        </div>
    </div>
</template>

<script>
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
                state: { isReady: true }
            }
        },
        watch: {
            // We use property watcher because parent component
            // might set the property with a delay and we need to parse it
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
                    if (!info.isReady) {
                        info.process.forEach((step, idx) => {
                            if (!step.state && !('percent' in info)) {
                                info.title = step.title
                                info.step = step.label
                                info.percent = Math.floor(idx / info.process.length * 100);
                                info.link = step.link
                            }
                        })
                    }

                    this.state = info || {}

                    this.$nextTick(function() {
                        $(this.$el).find('.progress-bar')
                            .css('width', info.percent + '%')
                            .attr('aria-valuenow', info.percent)
                    })

                    // Unhide the Refresh button, the process is in failure state
                    this.refresh = info.processState == 'failed'

                    if (this.refresh || info.step == 'domain-confirmed') {
                        this.className = 'failed'
                    }
                }

                // Update status process info every 10 seconds
                // FIXME: This probably should have some limit, or the interval
                //        should grow (well, until it could be done with websocket notifications)
                clearTimeout(window.infoRequest)
                if (!this.refresh && (!info || !info.isReady)) {
                    window.infoRequest = setTimeout(() => {
                        delete window.infoRequest
                        // Stop updates after user logged out
                        if (!this.$store.state.isLoggedIn) {
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
                let url

                switch (this.scope) {
                    case 'dashboard':
                        url = '/api/v4/users/' + this.$store.state.authInfo.id + '/status'
                        break
                    case 'domain':
                        url = '/api/v4/domains/' + this.$route.params.domain + '/status'
                        break
                    default:
                        url = '/api/v4/users/' + this.$route.params.user + '/status'
                }

                return url
            }
        }
    }
</script>
