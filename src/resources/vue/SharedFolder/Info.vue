<template>
    <div class="container">
        <status-component v-if="folder_id !== 'new'" :status="status" @status-update="statusUpdate"></status-component>

        <div class="card" id="folder-info">
            <div class="card-body">
                <div class="card-title" v-if="folder_id !== 'new'">
                    {{ $tc('shf.list-title', 1) }}
                    <btn class="btn-outline-danger button-delete float-end" @click="deleteFolder()" icon="trash-can">{{ $t('shf.delete') }}</btn>
                </div>
                <div class="card-title" v-if="folder_id === 'new'">{{ $t('shf.new') }}</div>
                <div class="card-text">
                    <ul class="nav nav-tabs mt-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-general" href="#general" role="tab" aria-controls="general" aria-selected="true" @click="$root.tab">
                                {{ $t('form.general') }}
                            </a>
                        </li>
                        <li v-if="folder_id !== 'new'" class="nav-item">
                            <a class="nav-link" id="tab-settings" href="#settings" role="tab" aria-controls="settings" aria-selected="false" @click="$root.tab">
                                {{ $t('form.settings') }}
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane show active" id="general" role="tabpanel" aria-labelledby="tab-general">
                            <form @submit.prevent="submit" class="card-body">
                                <div v-if="folder_id !== 'new'" class="row plaintext mb-3">
                                    <label for="status" class="col-sm-4 col-form-label">{{ $t('form.status') }}</label>
                                    <div class="col-sm-8">
                                        <span :class="$root.statusClass(folder) + ' form-control-plaintext'" id="status">{{ $root.statusText(folder) }}</span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="name" class="col-sm-4 col-form-label">{{ $t('form.name') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="name" v-model="folder.name">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="type" class="col-sm-4 col-form-label">{{ $t('form.type') }}</label>
                                    <div class="col-sm-8">
                                        <select id="type" class="form-select" v-model="folder.type" :disabled="folder_id !== 'new'">
                                            <option v-for="type in types" :key="type" :value="type">{{ $t('shf.type-' + type) }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div v-if="domains.length" class="row mb-3">
                                    <label for="domain" class="col-sm-4 col-form-label">{{ $t('form.domain') }}</label>
                                    <div v-if="domains.length" class="col-sm-8">
                                        <select class="form-select" v-model="folder.domain">
                                            <option v-for="_domain in domains" :key="_domain.id" :value="_domain.namespace">{{ _domain.namespace }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3" v-if="folder.type == 'mail'">
                                    <label for="aliases-input" class="col-sm-4 col-form-label">{{ $t('form.emails') }}</label>
                                    <div class="col-sm-8">
                                        <list-input id="aliases" :list="folder.aliases"></list-input>
                                    </div>
                                </div>
                                <btn class="btn-primary" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
                            </form>
                        </div>
                        <div class="tab-pane" id="settings" role="tabpanel" aria-labelledby="tab-settings">
                            <form @submit.prevent="submitSettings" class="card-body">
                                <div class="row mb-3">
                                    <label for="acl-input" class="col-sm-4 col-form-label">{{ $t('form.acl') }}</label>
                                    <div class="col-sm-8">
                                        <acl-input id="acl" v-model="folder.config.acl" :list="folder.config.acl" class="mb-1"></acl-input>
                                        <small id="acl-hint" class="text-muted">
                                            {{ $t('shf.acl-text') }}
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
    import AclInput from '../Widgets/AclInput'
    import ListInput from '../Widgets/ListInput'
    import StatusComponent from '../Widgets/Status'

    export default {
        components: {
            AclInput,
            ListInput,
            StatusComponent
        },
        data() {
            return {
                domains: [],
                folder_id: null,
                folder: { type: 'mail', config: {}, aliases: [] },
                status: {},
                types: [ 'mail', 'event', 'task', 'contact', 'note', 'file' ]
            }
        },
        created() {
            this.folder_id = this.$route.params.folder

            if (this.folder_id != 'new') {
                axios.get('/api/v4/shared-folders/' + this.folder_id, { loader: true })
                    .then(response => {
                        this.folder = response.data
                        this.status = response.data.statusInfo
                    })
                    .catch(this.$root.errorHandler)
            } else {
                axios.get('/api/v4/domains', { loader: true })
                    .then(response => {
                        this.domains = response.data
                        this.folder.domain = this.domains[0].namespace
                    })
                    .catch(this.$root.errorHandler)
            }
        },
        mounted() {
            $('#name').focus()
        },
        methods: {
            deleteFolder() {
                axios.delete('/api/v4/shared-folders/' + this.folder_id)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.$router.push({ name: 'shared-folders' })
                        }
                    })
            },
            statusUpdate(folder) {
                this.folder = Object.assign({}, this.folder, folder)
            },
            submit() {
                this.$root.clearFormValidation($('#folder-info form'))

                let method = 'post'
                let location = '/api/v4/shared-folders'

                if (this.folder_id !== 'new') {
                    method = 'put'
                    location += '/' + this.folder_id
                }

                const post = this.$root.pick(this.folder, ['id', 'name', 'domain', 'type', 'aliases'])

                if (post.type != 'mail') {
                    delete post.aliases
                }

                axios[method](location, post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                        this.$router.push({ name: 'shared-folders' })
                    })
            },
            submitSettings() {
                this.$root.clearFormValidation($('#settings form'))

                let post = this.$root.pick(this.folder.config, ['acl'])

                axios.post('/api/v4/shared-folders/' + this.folder_id + '/config', post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                    })
            }
        }
    }
</script>
