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
                                {{ $t('domain.verify') }}
                            </a>
                        </li>
                        <li class="nav-item" v-if="domain.isConfirmed">
                            <a class="nav-link active" id="tab-general" href="#general" role="tab" aria-controls="general" aria-selected="true" @click="$root.tab">
                                {{ $t('domain.config') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-settings" href="#settings" role="tab" aria-controls="settings" aria-selected="false" @click="$root.tab">
                                {{ $t('form.settings') }}
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane show active" id="general" role="tabpanel" aria-labelledby="tab-general">
                            <div v-if="!domain.isConfirmed" class="card-body" id="domain-verify">
                                <div class="card-text">
                                    <p>{{ $t('domain.verify-intro') }}</p>
                                    <p>
                                        <span v-html="$t('domain.verify-dns')"></span>
                                        <ul>
                                            <li>{{ $t('domain.verify-dns-txt') }} <code>{{ domain.hash_text }}</code></li>
                                            <li>{{ $t('domain.verify-dns-cname') }} <code>{{ domain.hash_cname }}.{{ domain.namespace }}. IN CNAME {{ domain.hash_code }}.{{ domain.namespace }}.</code></li>
                                        </ul>
                                        <span>{{ $t('domain.verify-outro') }}</span>
                                    </p>
                                    <p>{{ $t('domain.verify-sample') }} <pre>{{ domain.dns.join("\n") }}</pre></p>
                                    <button class="btn btn-primary" type="button" @click="confirm"><svg-icon icon="sync-alt"></svg-icon> {{ $t('btn.verify') }}</button>
                                </div>
                            </div>
                            <div v-if="domain.isConfirmed" class="card-body" id="domain-config">
                                <div class="card-text">
                                    <p>{{ $t('domain.config-intro', { app: $root.appName }) }}</p>
                                    <p>{{ $t('domain.config-sample') }} <pre>{{ domain.mx.join("\n") }}</pre></p>
                                    <p>{{ $t('domain.config-hint') }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane" id="settings" role="tabpanel" aria-labelledby="tab-settings">
                            <div class="card-body">
                                <form @submit.prevent="submitSettings">
                                    <div class="form-group row">
                                        <label for="spf_whitelist" class="col-sm-4 col-form-label">{{ $t('domain.spf-whitelist') }}</label>
                                        <div class="col-sm-8">
                                            <list-input id="spf_whitelist" name="spf_whitelist" :list="spf_whitelist"></list-input>
                                            <small id="spf-hint" class="form-text text-muted">
                                                {{ $t('domain.spf-whitelist-text') }}
                                                <span class="d-block" v-html="$t('domain.spf-whitelist-ex')"></span>
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
