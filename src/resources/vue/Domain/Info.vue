<template>
    <div class="container">
        <status-component v-if="domain_id !== 'new'" :status="status" @status-update="statusUpdate"></status-component>

        <div class="card">
            <div class="card-body">
                <div class="card-title" v-if="domain_id === 'new'">{{ $t('domain.new') }}</div>
                <div class="card-title" v-else>{{ $t('form.domain') }}
                    <btn class="btn-outline-danger button-delete float-end" @click="$refs.deleteDialog.show()" icon="trash-can">{{ $t('domain.delete') }}</btn>
                </div>
                <div class="card-text">
                    <tabs class="mt-3" :tabs="domain_id === 'new' ? ['form.general'] : ['form.general','form.settings']"></tabs>
                    <div class="tab-content">
                        <div class="tab-pane show active" id="general" role="tabpanel" aria-labelledby="tab-general">
                            <form @submit.prevent="submit" class="card-body">
                                <div v-if="domain.id" class="row plaintext mb-3">
                                    <label for="status" class="col-sm-4 col-form-label">{{ $t('form.status') }}</label>
                                    <div class="col-sm-8">
                                        <span :class="$root.statusClass(domain) + ' form-control-plaintext'" id="status">{{ $root.statusText(domain) }}</span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="name" class="col-sm-4 col-form-label">{{ $t('domain.namespace') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="namespace" v-model="domain.namespace" :disabled="domain.id">
                                    </div>
                                </div>
                                <div v-if="!domain.id" id="domain-packages" class="row">
                                    <label class="col-sm-4 col-form-label">{{ $t('user.package') }}</label>
                                    <package-select class="col-sm-8 pt-sm-1" type="domain"></package-select>
                                </div>
                                <div v-if="domain.id" id="domain-skus" class="row">
                                    <label class="col-sm-4 col-form-label">{{ $t('user.subscriptions') }}</label>
                                    <subscription-select v-if="domain.id" class="col-sm-8 pt-sm-1" type="domain" :object="domain" :readonly="true"></subscription-select>
                                </div>
                                <btn v-if="!domain.id" class="btn-primary mt-3" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
                            </form>
                            <hr class="m-0" v-if="domain.id">
                            <div v-if="domain.id && !domain.isConfirmed" class="card-body" id="domain-verify">
                                <h5 class="mb-3">{{ $t('domain.verify') }}</h5>
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
                                    <btn class="btn-primary" @click="confirm" icon="rotate">{{ $t('btn.verify') }}</btn>
                                </div>
                            </div>
                            <div v-if="domain.isConfirmed" class="card-body" id="domain-config">
                                <h5 class="mb-3">{{ $t('domain.config') }}</h5>
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
                                    <div class="row mb-3">
                                        <label for="spf_whitelist" class="col-sm-4 col-form-label">{{ $t('domain.spf-whitelist') }}</label>
                                        <div class="col-sm-8">
                                            <list-input id="spf_whitelist" name="spf_whitelist" :list="spf_whitelist"></list-input>
                                            <small id="spf-hint" class="text-muted d-block mt-2">
                                                {{ $t('domain.spf-whitelist-text') }}
                                                <span class="d-block" v-html="$t('domain.spf-whitelist-ex')"></span>
                                            </small>
                                        </div>
                                    </div>
                                    <btn class="btn-primary" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <modal-dialog id="delete-warning" ref="deleteDialog" @click="deleteDomain()" :buttons="['delete']" :cancel-focus="true"
                      :title="$t('domain.delete-domain', { domain: domain.namespace })"
        >
            <p>{{ $t('domain.delete-text') }}</p>
        </modal-dialog>
    </div>
</template>

<script>
    import ListInput from '../Widgets/ListInput'
    import ModalDialog from '../Widgets/ModalDialog'
    import PackageSelect from '../Widgets/PackageSelect'
    import StatusComponent from '../Widgets/Status'
    import SubscriptionSelect from '../Widgets/SubscriptionSelect'

    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faRotate').definition,
    )

    export default {
        components: {
            ListInput,
            ModalDialog,
            PackageSelect,
            StatusComponent,
            SubscriptionSelect
        },
        data() {
            return {
                domain_id: null,
                domain: {},
                spf_whitelist: [],
                status: {}
            }
        },
        created() {
            this.domain_id = this.$route.params.domain

            if (this.domain_id !== 'new') {
                axios.get('/api/v4/domains/' + this.domain_id, { loader: true })
                    .then(response => {
                        this.domain = response.data
                        this.spf_whitelist = this.domain.config.spf_whitelist || []

                        if (!this.domain.isConfirmed) {
                            $('#domain-verify button').focus()
                        }

                        this.status = response.data.statusInfo
                    })
                    .catch(this.$root.errorHandler)
            }
        },
        mounted() {
            $('#namespace').focus()
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
            deleteDomain() {
                // Delete the domain from the confirm dialog
                axios.delete('/api/v4/domains/' + this.domain_id)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.$router.push({ name: 'domains' })
                        }
                    })
            },
            statusUpdate(domain) {
                this.domain = Object.assign({}, this.domain, domain)
            },
            submit() {
                this.$root.clearFormValidation($('#general form'))

                let post = this.$root.pick(this.domain, ['namespace'])

                post.package = $('#domain-packages input:checked').val()

                axios.post('/api/v4/domains', post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                        this.$router.push({ name: 'domains' })
                    })
            },
            submitSettings() {
                this.$root.clearFormValidation($('#settings form'))

                const post = this.$root.pick(this, ['spf_whitelist'])

                axios.post('/api/v4/domains/' + this.domain_id + '/config', post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                    })
            }
        }
    }
</script>
