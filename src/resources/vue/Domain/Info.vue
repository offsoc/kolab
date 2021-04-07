<template>
    <div class="container">
        <status-component :status="status" @status-update="statusUpdate"></status-component>

        <div v-if="domain" class="card">
            <div class="card-body">
                <div class="card-title">{{ domain.namespace }}</div>
                <div class="card-text">
                    <ul class="nav nav-tabs mt-3" role="tablist">
                        <li class="nav-item" v-if="!domain.isConfirmed">
                            <a class="nav-link active" id="tab-general" href="#general" role="tab" aria-controls="general" aria-selected="true" @click="$root.tab">
                                Domain verification
                            </a>
                        </li>
                        <li class="nav-item" v-if="domain.isConfirmed">
                            <a class="nav-link active" id="tab-general" href="#general" role="tab" aria-controls="general" aria-selected="true" @click="$root.tab">
                                Domain configuration
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-settings" href="#settings" role="tab" aria-controls="settings" aria-selected="false" @click="$root.tab">
                                Settings
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane show active" id="general" role="tabpanel" aria-labelledby="tab-general">
                            <div v-if="!domain.isConfirmed" class="card-body" id="domain-verify">
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
                            <div v-if="domain.isConfirmed" class="card-body" id="domain-config">
                                <div class="card-text">
                                    <p>In order to let {{ app_name }} receive email traffic for your domain you need to adjust
                                        the DNS settings, more precisely the MX entries, accordingly.</p>
                                    <p>Edit your domain's zone file and replace existing MX
                                        entries with the following values: <pre>{{ domain.mx.join("\n") }}</pre></p>
                                    <p>If you don't know how to set DNS entries for your domain,
                                        please contact the registration service where you registered
                                        the domain or your web hosting provider.</p>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane" id="settings" role="tabpanel" aria-labelledby="tab-settings">
                            <div class="card-body">
                                <form @submit.prevent="submitSettings">
                                    <div class="form-group row">
                                        <label for="spf_whitelist" class="col-sm-4 col-form-label">SPF Whitelist</label>
                                        <div class="col-sm-8">
                                            <list-input id="spf_whitelist" name="spf_whitelist" :list="spf_whitelist"></list-input>
                                            <small id="spf-hint" class="form-text text-muted">
                                                The Sender Policy Framework allows a sender domain to disclose, through DNS,
                                                which systems are allowed to send emails with an envelope sender address within said domain.
                                                <span class="d-block">
                                                    Here you can specify a list of allowed servers, for example: <var>.ess.barracuda.com</var>.
                                                </span>
                                            </small>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary" type="submit"><svg-icon icon="check"></svg-icon> Submit</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import ListInput from '../Widgets/ListInput'
    import StatusComponent from '../Widgets/Status'

    export default {
        components: {
            ListInput,
            StatusComponent
        },
        data() {
            return {
                domain_id: null,
                domain: null,
                app_name: window.config['app.name'],
                spf_whitelist: [],
                status: {}
            }
        },
        created() {
            if (this.domain_id = this.$route.params.domain) {
                this.$root.startLoading()

                axios.get('/api/v4/domains/' + this.domain_id)
                    .then(response => {
                        this.$root.stopLoading()
                        this.domain = response.data
                        this.spf_whitelist = this.domain.config.spf_whitelist || []

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
            },
            submitSettings() {
                this.$root.clearFormValidation($('#settings form'))

                let post = { spf_whitelist: this.spf_whitelist }

                axios.post('/api/v4/domains/' + this.domain_id + '/config', post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                    })
            }
        }
    }
</script>
