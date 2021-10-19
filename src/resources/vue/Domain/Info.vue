<template>
    <div class="container">
        <status-component v-if="domain_id !== 'new'" :status="status" @status-update="statusUpdate"></status-component>

        <div class="card">
            <div class="card-body">
                <div class="card-title" v-if="domain_id === 'new'">{{ $t('domain.new') }}</div>
                <div class="card-title" v-else>{{ $t('form.domain') }}
                    <button
                        class="btn btn-outline-danger button-delete float-end"
                        @click="showDeleteConfirmation()" type="button"
                    >
                        <svg-icon icon="trash-alt"></svg-icon> {{ $t('domain.delete') }}
                    </button>
                </div>
                <div class="card-text">
                    <ul class="nav nav-tabs mt-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-general" href="#general" role="tab" aria-controls="general" aria-selected="true" @click="$root.tab">
                                {{ $t('form.general') }}
                            </a>
                        </li>
                        <li class="nav-item" v-if="domain.id">
                            <a class="nav-link" id="tab-settings" href="#settings" role="tab" aria-controls="settings" aria-selected="false" @click="$root.tab">
                                {{ $t('form.settings') }}
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane show active" id="general" role="tabpanel" aria-labelledby="tab-general">
                            <form @submit.prevent="submit" class="card-body">
                                <div v-if="domain.id" class="row plaintext mb-3">
                                    <label for="status" class="col-sm-4 col-form-label">{{ $t('form.status') }}</label>
                                    <div class="col-sm-8">
                                        <span :class="$root.domainStatusClass(domain) + ' form-control-plaintext'" id="status">{{ $root.domainStatusText(domain) }}</span>
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
                                <button v-if="!domain.id" class="btn btn-primary mt-3" type="submit">
                                    <svg-icon icon="check"></svg-icon> {{ $t('btn.submit') }}
                                </button>
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
                                    <button class="btn btn-primary" type="button" @click="confirm"><svg-icon icon="sync-alt"></svg-icon> {{ $t('btn.verify') }}</button>
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
                                    <button class="btn btn-primary" type="submit"><svg-icon icon="check"></svg-icon> Submit</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="delete-warning" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $t('domain.delete-domain', { domain: domain.namespace }) }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></button>
                    </div>
                    <div class="modal-body">
                        <p>{{ $t('domain.delete-text') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-bs-dismiss="modal">{{ $t('btn.cancel') }}</button>
                        <button type="button" class="btn btn-danger modal-action" @click="deleteDomain()">
                            <svg-icon icon="trash-alt"></svg-icon> {{ $t('btn.delete') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { Modal } from 'bootstrap'
    import ListInput from '../Widgets/ListInput'
    import PackageSelect from '../Widgets/PackageSelect'
    import StatusComponent from '../Widgets/Status'
    import SubscriptionSelect from '../Widgets/SubscriptionSelect'

    export default {
        components: {
            ListInput,
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
            }
        },
        mounted() {
            $('#namespace').focus()
            $('#delete-warning')[0].addEventListener('shown.bs.modal', event => {
                $(event.target).find('button.modal-cancel').focus()
            })
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
            showDeleteConfirmation() {
                // Display the warning
                new Modal('#delete-warning').show()
            },
            statusUpdate(domain) {
                this.domain = Object.assign({}, this.domain, domain)
            },
            submit() {
                this.$root.clearFormValidation($('#general form'))

                let method = 'post'
                let location = '/api/v4/domains'

                this.domain.package = $('#domain-packages input:checked').val()

                axios[method](location, this.domain)
                    .then(response => {
                        this.$toast.success(response.data.message)
                        this.$router.push({ name: 'domains' })
                    })
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
