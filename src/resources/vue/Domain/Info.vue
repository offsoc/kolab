<template>
    <div class="container">
        <status-component :status="status" @status-update="statusUpdate"></status-component>

        <div v-if="domain && !domain.isConfirmed" class="card" id="domain-verify">
            <div class="card-body">
                <div class="card-title">{{ $t('domain.verify') }}</div>
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
        </div>
        <div v-if="domain && domain.isConfirmed" class="card" id="domain-config">
            <div class="card-body">
                <div class="card-title">{{ $t('domain.config') }}</div>
                <div class="card-text">
                    <p>{{ $t('domain.config-intro', { app: $root.appName }) }}</p>
                    <p>{{ $t('domain.config-sample') }} <pre>{{ domain.config.join("\n") }}</pre></p>
                    <p>{{ $t('domain.config-hint') }}</p>
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
