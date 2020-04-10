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
        }
    }
</script>
