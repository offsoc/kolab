<template>
    <div v-if="domain" class="container">
        <div class="card" id="domain-info">
            <div class="card-body">
                <div class="card-title">{{ domain.namespace }}</div>
                <div class="card-text">
                    <form>
                        <div class="form-group row mb-0">
                            <label for="domainid" class="col-sm-4 col-form-label">ID <span class="text-muted">(Created at)</span></label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="domainid">
                                    {{ domain.id }} <span class="text-muted">({{ domain.created_at }})</span>
                                </span>
                            </div>
                        </div>
                        <div class="form-group row mb-0">
                            <label for="first_name" class="col-sm-4 col-form-label">Status</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="status">
                                    <span :class="$root.domainStatusClass(domain)">{{ $root.domainStatusText(domain) }}</span>
                                </span>
                            </div>
                        </div>
                    </form>
                    <div class="mt-2">
                        <button v-if="!domain.isSuspended" id="button-suspend" class="btn btn-warning" type="button" @click="suspendDomain">Suspend</button>
                        <button v-if="domain.isSuspended" id="button-unsuspend" class="btn btn-warning" type="button" @click="unsuspendDomain">Unsuspend</button>
                    </div>
                </div>
            </div>
        </div>
        <ul class="nav nav-tabs mt-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-config" href="#domain-config" role="tab" aria-controls="domain-config" aria-selected="true">
                    Configuration
                </a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane show active" id="domain-config" role="tabpanel" aria-labelledby="tab-config">
                <div class="card-body">
                    <div class="card-text">
                        <p>Domain DNS verification sample:</p>
                        <p><pre id="dns-verify">{{ domain.dns.join("\n") }}</pre></p>
                        <p>Domain DNS configuration sample:</p>
                        <p><pre id="dns-config">{{ domain.config.join("\n") }}</pre></p>
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
                axios.post('/api/v4/domains/' + this.domain.id + '/suspend', {})
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.domain = Object.assign({}, this.domain, { isSuspended: true })
                        }
                    })
            },
            unsuspendDomain() {
                axios.post('/api/v4/domains/' + this.domain.id + '/unsuspend', {})
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
