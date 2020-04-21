<template>
    <div class="container">
        <status-component v-bind:status="status" @status-update="statusUpdate"></status-component>

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
    import StatusComponent from '../Widgets/Status'

    export default {
        components: {
            StatusComponent
        },
        data() {
            return {
                domain_id: null,
                domain: null,
                app_name: window.config['app.name'],
                status: {}
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
                        this.status = response.data.statusInfo
                    })
                    .catch(this.$root.errorHandler)
            } else {
                this.$root.errorPage(404)
            }
        },
        methods: {
            confirm() {
                axios.get('/api/v4/domains/' + this.domain_id + '/confirm')
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.domain.isConfirmed = true
                            this.status = response.data.statusInfo
                        }

                        if (response.data.message) {
                            this.$toast[response.data.status](response.data.message)
                        }
                    })
            },
            statusUpdate(domain) {
                this.domain = Object.assign({}, this.domain, domain)
            }
        }
    }
</script>
