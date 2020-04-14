<template>
    <div class="container">
        <div v-if="!isReady" id="domain-status-box" class="card">
            <div class="card-body">
                <div class="card-title">Domain status: <span class="text-danger">Not ready</span></div>
                <div class="card-text">
                    <p>The process to create the domain has not been completed yet.
                        Some features may be disabled or readonly.</p>
                    <ul class="status-list">
                        <li v-for="item in statusProcess" :key="item.label">
                            <svg-icon :icon="['far', item.state ? 'check-square' : 'square']"
                                      :class="item.state ? 'text-success' : 'text-muted'"
                            ></svg-icon>
                            <span>{{ item.title }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div v-if="domain && !domain.isConfirmed" class="card" id="domain-verify">
            <div class="card-body">
                <div class="card-title">Domain verification</div>
                <div class="card-text">
                    <p>In order to confirm that you're the actual holder of the domain,
                        we need to run a verification process before finally activating it for email delivery.</p>
                    <p>The domain <b>must have one of the following entries</b> in DNS:
                        <ul>
                            <li>TXT entry with value: <code>{{ domain.hash_text }}</code></li>
                            <li>or CNAME entry: <code>{{ domain.hash_cname }}.{{ domain.namespace }}. IN CNAME {{ domain.hash_code }}.{{ domain.namespace }}.</code></li>
                        </ul>
                        When this is done press the button below to start the verification.</p>
                    <p>Here's a sample zone file for your domain: <pre>{{ domain.dns.join("\n") }}</pre></p>
                    <button class="btn btn-primary" type="button" @click="confirm"><svg-icon icon="sync-alt"></svg-icon> Verify</button>
                </div>
            </div>
        </div>
        <div v-if="domain && domain.isConfirmed" class="card" id="domain-config">
            <div class="card-body">
                <div class="card-title">Domain configuration</div>
                <div class="card-text">
                    <p>In order to let {{ app_name }} receive email traffic for your domain you need to adjust
                        the DNS settings, more precisely the MX entries, accordingly.</p>
                    <p>Edit your domain's zone file and replace existing MX
                        entries with the following values: <pre>{{ domain.config.join("\n") }}</pre></p>
                    <p>If you don't know how to set DNS entries for your domain,
                        please contact the registration service where you registered
                        the domain or your web hosting provider.</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                domain_id: null,
                domain: null,
                app_name: window.config['app.name'],
                isReady: true,
                statusProcess: []
            }
        },
        created() {
            if (this.domain_id = this.$route.params.domain) {
                axios.get('/api/v4/domains/' + this.domain_id)
                    .then(response => {
                        this.domain = response.data
                        if (!this.domain.isConfirmed) {
                            $('#domain-verify button').focus()
                        }
                        this.parseStatusInfo(response.data.statusInfo)
                    })
                    .catch(this.$root.errorHandler)
            } else {
                this.$root.errorPage(404)
            }
        },
        destroyed() {
            clearTimeout(window.domainRequest)
        },
        methods: {
            confirm() {
                axios.get('/api/v4/domains/' + this.domain_id + '/confirm')
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.domain.isConfirmed = true
                            this.parseStatusInfo(response.data.statusInfo)
                            this.$toast.success(response.data.message)
                        }
                    })
            },
            // Displays domain status information
            parseStatusInfo(info) {
                this.statusProcess = info.process
                this.isReady = info.isReady

                // Update status process info every 10 seconds
                // FIXME: This probably should have some limit, or the interval
                //        should grow (well, until it could be done with websocket notifications)
                if (!info.isReady) {
                    window.domainRequest = setTimeout(() => {
                        axios.get('/api/v4/domains/' + this.domain_id)
                            .then(response => {
                                this.domain = response.data
                                this.parseStatusInfo(this.domain.statusInfo)
                            })
                            .catch(error => {
                                this.parseStatusInfo(info)
                            })
                    }, 10000);
                }
            },
        }
    }
</script>
