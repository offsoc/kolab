<template>
    <div class="container">
        <status-component v-if="resource_id !== 'new'" :status="status" @status-update="statusUpdate"></status-component>

        <div class="card" id="resource-info">
            <div class="card-body">
                <div class="card-title" v-if="resource_id !== 'new'">
                    {{ $tc('resource.list-title', 1) }}
                    <btn class="btn-outline-danger button-delete float-end" @click="deleteResource()" icon="trash-can">{{ $t('resource.delete') }}</btn>
                </div>
                <div class="card-title" v-if="resource_id === 'new'">{{ $t('resource.new') }}</div>
                <div class="card-text">
                    <tabs class="mt-3" :tabs="resource_id === 'new' ? ['form.general'] : ['form.general','form.settings']"></tabs>
                    <div class="tab-content">
                        <div class="tab-pane show active" id="general" role="tabpanel" aria-labelledby="tab-general">
                            <form @submit.prevent="submit" class="card-body">
                                <div v-if="resource_id !== 'new'" class="row plaintext mb-3">
                                    <label for="status" class="col-sm-4 col-form-label">{{ $t('form.status') }}</label>
                                    <div class="col-sm-8">
                                        <span :class="$root.statusClass(resource) + ' form-control-plaintext'" id="status">{{ $root.statusText(resource) }}</span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="name" class="col-sm-4 col-form-label">{{ $t('form.name') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="name" v-model="resource.name">
                                    </div>
                                </div>
                                <div v-if="domains.length" class="row mb-3">
                                    <label for="domain" class="col-sm-4 col-form-label">{{ $t('form.domain') }}</label>
                                    <div class="col-sm-8">
                                        <select class="form-select" v-model="resource.domain">
                                            <option v-for="_domain in domains" :key="_domain.id" :value="_domain.namespace">{{ _domain.namespace }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div v-if="resource.email" class="row mb-3">
                                    <label for="email" class="col-sm-4 col-form-label">{{ $t('form.email') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="email" disabled v-model="resource.email">
                                    </div>
                                </div>
                                <div v-if="$root.hasPermission('subscriptions') && (resource_id === 'new' || resource.id)" id="resource-skus" class="row mb-3">
                                    <label class="col-sm-4 col-form-label">{{ $t('form.subscriptions') }}</label>
                                    <subscription-select class="col-sm-8 pt-sm-1" ref="skus" :object="resource" type="resource" :readonly="true"></subscription-select>
                                </div>
                                <btn class="btn-primary" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
                            </form>
                        </div>
                        <div class="tab-pane" id="settings" role="tabpanel" aria-labelledby="tab-settings">
                            <form @submit.prevent="submitSettings" class="card-body">
                                <div class="row mb-3">
                                    <label for="invitation_policy" class="col-sm-4 col-form-label">{{ $t('resource.invitation-policy') }}</label>
                                    <div class="col-sm-8">
                                        <div class="input-group input-group-select mb-1">
                                            <select class="form-select" id="invitation_policy" v-model="resource.config.invitation_policy" @change="policyChange">
                                                <option value="accept">{{ $t('resource.ipolicy-accept') }}</option>
                                                <option value="manual">{{ $t('resource.ipolicy-manual') }}</option>
                                                <option value="reject">{{ $t('resource.ipolicy-reject') }}</option>
                                            </select>
                                            <input type="text" class="form-control" id="owner" v-model="resource.config.owner" :placeholder="$t('form.email')">
                                        </div>
                                        <small id="invitation-policy-hint" class="text-muted">
                                            {{ $t('resource.invitation-policy-text') }}
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
</template>

<script>
    import StatusComponent from '../Widgets/Status'
    import SubscriptionSelect from '../Widgets/SubscriptionSelect'

    export default {
        components: {
            StatusComponent,
            SubscriptionSelect
        },
        data() {
            return {
                domains: [],
                resource_id: null,
                resource: { config: {} },
                status: {}
            }
        },
        created() {
            this.resource_id = this.$route.params.resource

            if (this.resource_id != 'new') {
                axios.get('/api/v4/resources/' + this.resource_id, { loader: true })
                    .then(response => {
                        this.resource = response.data
                        this.status = response.data.statusInfo

                        if (this.resource.config.invitation_policy.match(/^manual:(.+)$/)) {
                            this.resource.config.owner = RegExp.$1
                            this.resource.config.invitation_policy = 'manual'
                        }
                        this.$nextTick().then(() => { this.policyChange() })
                    })
                    .catch(this.$root.errorHandler)
            } else {
                axios.get('/api/v4/domains', { loader: true })
                    .then(response => {
                        this.domains = response.data.list
                        this.resource.domain = this.domains[0].namespace
                    })
                    .catch(this.$root.errorHandler)
            }
        },
        mounted() {
            $('#name').focus()
        },
        methods: {
            deleteResource() {
                axios.delete('/api/v4/resources/' + this.resource_id)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.$router.push({ name: 'resources' })
                        }
                    })
            },
            policyChange() {
                let select = $('#invitation_policy')
                select.parent()[select.val() == 'manual' ? 'addClass' : 'removeClass']('selected')
            },
            statusUpdate(resource) {
                this.resource = Object.assign({}, this.resource, resource)
            },
            submit() {
                this.$root.clearFormValidation($('#resource-info form'))

                let method = 'post'
                let location = '/api/v4/resources'
                let post = this.$root.pick(this.resource, ['id', 'name', 'domain'])

                if (this.resource_id !== 'new') {
                    method = 'put'
                    location += '/' + this.resource_id
                }

                // post.skus = this.$refs.skus.getSkus()

                axios[method](location, post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                        this.$router.push({ name: 'resources' })
                    })
            },
            submitSettings() {
                this.$root.clearFormValidation($('#settings form'))

                let post = this.$root.pick(this.resource.config, ['invitation_policy', 'owner'])

                if (post.invitation_policy == 'manual') {
                    post.invitation_policy += ':' + post.owner
                }

                delete post.owner

                axios.post('/api/v4/resources/' + this.resource_id + '/config', post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                    })
            }
        }
    }
</script>
