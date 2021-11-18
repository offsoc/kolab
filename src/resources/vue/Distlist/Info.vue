<template>
    <div class="container">
        <status-component v-if="list_id !== 'new'" :status="status" @status-update="statusUpdate"></status-component>

        <div class="card" id="distlist-info">
            <div class="card-body">
                <div class="card-title" v-if="list_id !== 'new'">
                    {{ $tc('distlist.list-title', 1) }}
                    <button class="btn btn-outline-danger button-delete float-end" @click="deleteList()" tag="button">
                        <svg-icon icon="trash-alt"></svg-icon> {{ $t('distlist.delete') }}
                    </button>
                </div>
                <div class="card-title" v-if="list_id === 'new'">{{ $t('distlist.new') }}</div>
                <div class="card-text">
                    <ul class="nav nav-tabs mt-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-general" href="#general" role="tab" aria-controls="general" aria-selected="true" @click="$root.tab">
                                {{ $t('form.general') }}
                            </a>
                        </li>
                        <li v-if="list_id !== 'new'" class="nav-item">
                            <a class="nav-link" id="tab-settings" href="#settings" role="tab" aria-controls="settings" aria-selected="false" @click="$root.tab">
                                {{ $t('form.settings') }}
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane show active" id="general" role="tabpanel" aria-labelledby="tab-general">
                            <form @submit.prevent="submit" class="card-body">
                                <div v-if="list_id !== 'new'" class="row plaintext mb-3">
                                    <label for="status" class="col-sm-4 col-form-label">{{ $t('form.status') }}</label>
                                    <div class="col-sm-8">
                                        <span :class="$root.distlistStatusClass(list) + ' form-control-plaintext'" id="status">{{ $root.distlistStatusText(list) }}</span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="name" class="col-sm-4 col-form-label">{{ $t('distlist.name') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="name" required v-model="list.name">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="email" class="col-sm-4 col-form-label">{{ $t('form.email') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="email" :disabled="list_id !== 'new'" required v-model="list.email">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="members-input" class="col-sm-4 col-form-label">{{ $t('distlist.recipients') }}</label>
                                    <div class="col-sm-8">
                                        <list-input id="members" :list="list.members"></list-input>
                                    </div>
                                </div>
                                <button class="btn btn-primary" type="submit"><svg-icon icon="check"></svg-icon> {{ $t('btn.submit') }}</button>
                            </form>
                        </div>
                        <div class="tab-pane" id="settings" role="tabpanel" aria-labelledby="tab-settings">
                            <form @submit.prevent="submitSettings" class="card-body">
                                <div class="row mb-3">
                                    <label for="sender-policy-input" class="col-sm-4 col-form-label">{{ $t('distlist.sender-policy') }}</label>
                                    <div class="col-sm-8 pt-2">
                                        <list-input id="sender-policy" :list="list.config.sender_policy" class="mb-1"></list-input>
                                        <small id="sender-policy-hint" class="text-muted">
                                            {{ $t('distlist.sender-policy-text') }}
                                        </small>
                                    </div>
                                </div>
                                <button class="btn btn-primary" type="submit"><svg-icon icon="check"></svg-icon> {{ $t('btn.submit') }}</button>
                            </form>
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
                list_id: null,
                list: { members: [], config: {} },
                status: {}
            }
        },
        created() {
            this.list_id = this.$route.params.list

            if (this.list_id != 'new') {
                this.$root.startLoading()

                axios.get('/api/v4/groups/' + this.list_id)
                    .then(response => {
                        this.$root.stopLoading()
                        this.list = response.data
                        this.status = response.data.statusInfo
                    })
                    .catch(this.$root.errorHandler)
            }
        },
        mounted() {
            $('#name').focus()
        },
        methods: {
            deleteList() {
                axios.delete('/api/v4/groups/' + this.list_id)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.$router.push({ name: 'distlists' })
                        }
                    })
            },
            statusUpdate(list) {
                this.list = Object.assign({}, this.list, list)
            },
            submit() {
                this.$root.clearFormValidation($('#list-info form'))

                let method = 'post'
                let location = '/api/v4/groups'

                if (this.list_id !== 'new') {
                    method = 'put'
                    location += '/' + this.list_id
                }

                axios[method](location, this.list)
                    .then(response => {
                        this.$toast.success(response.data.message)
                        this.$router.push({ name: 'distlists' })
                    })
            },
            submitSettings() {
                this.$root.clearFormValidation($('#settings form'))
                let post = this.list.config

                axios.post('/api/v4/groups/' + this.list_id + '/config', post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                    })
            }
        }
    }
</script>
