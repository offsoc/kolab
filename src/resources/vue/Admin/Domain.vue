<template>
    <div v-if="domain" class="container">
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
                        <btn v-if="!domain.isSuspended" id="button-suspend" class="btn-warning" @click="suspendDomain">
                            {{ $t('btn.suspend') }}
                        </btn>
                        <btn v-if="domain.isSuspended" id="button-unsuspend" class="btn-warning" @click="unsuspendDomain">
                            {{ $t('btn.unsuspend') }}
                        </btn>
                    </div>
                </div>
            </div>
        </div>
        <tabs class="mt-3" :tabs="['form.config', 'form.settings']"></tabs>
        <div class="tab-content">
            <div class="tab-pane show active" id="config" role="tabpanel" aria-labelledby="tab-config">
                <div class="card-body">
                    <div class="card-text">
                        <p>{{ $t('domain.dns-verify') }}</p>
                        <p><pre id="dns-verify">{{ domain.dns.join("\n") }}</pre></p>
                        <p>{{ $t('domain.dns-config') }}</p>
                        <p><pre id="dns-config">{{ domain.mx.join("\n") }}</pre></p>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="settings" role="tabpanel" aria-labelledby="tab-settings">
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
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                domain: null
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
        methods: {
            suspendDomain() {
                axios.post('/api/v4/domains/' + this.domain.id + '/suspend')
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.domain = Object.assign({}, this.domain, { isSuspended: true })
                        }
                    })
            },
            unsuspendDomain() {
                axios.post('/api/v4/domains/' + this.domain.id + '/unsuspend')
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.domain = Object.assign({}, this.domain, { isSuspended: false })
                        }
                    })
            }
        }
    }
</script>
