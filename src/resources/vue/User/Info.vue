<template>
    <div class="container">
        <status-component v-if="user_id !== 'new'" :status="status" @status-update="statusUpdate"></status-component>

        <div class="card" id="user-info">
            <div class="card-body">
                <div class="card-title" v-if="user_id !== 'new'">{{ $t('user.title') }}
                    <button
                        class="btn btn-outline-danger button-delete float-end"
                        @click="showDeleteConfirmation()" type="button"
                    >
                        <svg-icon icon="trash-alt"></svg-icon> {{ $t('user.delete') }}
                    </button>
                </div>
                <div class="card-title" v-if="user_id === 'new'">{{ $t('user.new') }}</div>
                <div class="card-text">
                    <ul class="nav nav-tabs mt-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-general" href="#general" role="tab" aria-controls="general" aria-selected="true" @click="$root.tab">
                                {{ $t('form.general') }}
                            </a>
                        </li>
                        <li v-if="user_id !== 'new'" class="nav-item">
                            <a class="nav-link" id="tab-settings" href="#settings" role="tab" aria-controls="settings" aria-selected="false" @click="$root.tab">
                                {{ $t('form.settings') }}
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane show active" id="general" role="tabpanel" aria-labelledby="tab-general">
                            <form @submit.prevent="submit" class="card-body">
                                <div v-if="user_id !== 'new'" class="row plaintext mb-3">
                                    <label for="status" class="col-sm-4 col-form-label">{{ $t('form.status') }}</label>
                                    <div class="col-sm-8">
                                        <span :class="$root.statusClass(user) + ' form-control-plaintext'" id="status">{{ $root.statusText(user) }}</span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="first_name" class="col-sm-4 col-form-label">{{ $t('form.firstname') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="first_name" v-model="user.first_name">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="last_name" class="col-sm-4 col-form-label">{{ $t('form.lastname') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="last_name" v-model="user.last_name">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="organization" class="col-sm-4 col-form-label">{{ $t('user.org') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="organization" v-model="user.organization">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="email" class="col-sm-4 col-form-label">{{ $t('form.email') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="email" :disabled="user_id !== 'new'" required v-model="user.email">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="aliases-input" class="col-sm-4 col-form-label">{{ $t('user.aliases-email') }}</label>
                                    <div class="col-sm-8">
                                        <list-input id="aliases" :list="user.aliases"></list-input>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="password" class="col-sm-4 col-form-label">{{ $t('form.password') }}</label>
                                    <div class="col-sm-8">
                                        <input type="password" class="form-control" id="password" v-model="user.password" :required="user_id === 'new'">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="password_confirmaton" class="col-sm-4 col-form-label">{{ $t('form.password-confirm') }}</label>
                                    <div class="col-sm-8">
                                        <input type="password" class="form-control" id="password_confirmation" v-model="user.password_confirmation" :required="user_id === 'new'">
                                    </div>
                                </div>
                                <div v-if="user_id === 'new'" id="user-packages" class="row mb-3">
                                    <label class="col-sm-4 col-form-label">{{ $t('user.package') }}</label>
                                    <package-select class="col-sm-8 pt-sm-1"></package-select>
                                </div>
                                <div v-if="user_id !== 'new'" id="user-skus" class="row mb-3">
                                    <label class="col-sm-4 col-form-label">{{ $t('user.subscriptions') }}</label>
                                    <subscription-select v-if="user.id" class="col-sm-8 pt-sm-1" :object="user"></subscription-select>
                                </div>
                                <button class="btn btn-primary" type="submit"><svg-icon icon="check"></svg-icon> {{ $t('btn.submit') }}</button>
                            </form>
                        </div>
                        <div class="tab-pane" id="settings" role="tabpanel" aria-labelledby="tab-settings">
                            <form @submit.prevent="submitSettings" class="card-body">
                                <div class="row checkbox mb-3">
                                    <label for="greylist_enabled" class="col-sm-4 col-form-label">{{ $t('user.greylisting') }}</label>
                                    <div class="col-sm-8 pt-2">
                                        <input type="checkbox" id="greylist_enabled" name="greylist_enabled" value="1" class="form-check-input d-block mb-2" :checked="user.config.greylist_enabled">
                                        <small id="greylisting-hint" class="text-muted">
                                            {{ $t('user.greylisting-text') }}
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
        <div id="delete-warning" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $t('user.delete-email', { email: user.email }) }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></button>
                    </div>
                    <div class="modal-body">
                        <p>{{ $t('user.delete-text') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-bs-dismiss="modal">{{ $t('btn.cancel') }}</button>
                        <button type="button" class="btn btn-danger modal-action" @click="deleteUser()">
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
                user_id: null,
                user: { aliases: [], config: [] },
                status: {}
            }
        },
        created() {
            this.user_id = this.$route.params.user

            if (this.user_id !== 'new') {
                this.$root.startLoading()

                axios.get('/api/v4/users/' + this.user_id)
                    .then(response => {
                        this.$root.stopLoading()

                        this.user = response.data
                        this.user.first_name = response.data.settings.first_name
                        this.user.last_name = response.data.settings.last_name
                        this.user.organization = response.data.settings.organization
                        this.status = response.data.statusInfo
                    })
                    .catch(this.$root.errorHandler)
            }
        },
        mounted() {
            $('#first_name').focus()
            $('#delete-warning')[0].addEventListener('shown.bs.modal', event => {
                $(event.target).find('button.modal-cancel').focus()
            })
        },
        methods: {
            submit() {
                this.$root.clearFormValidation($('#general form'))

                let method = 'post'
                let location = '/api/v4/users'

                if (this.user_id !== 'new') {
                    method = 'put'
                    location += '/' + this.user_id

                    let skus = {}
                    $('#user-skus input[type=checkbox]:checked').each((idx, input) => {
                        let id = $(input).val()
                        let range = $(input).parents('tr').first().find('input[type=range]').val()

                        skus[id] = range || 1
                    })
                    this.user.skus = skus
                } else {
                    this.user.package = $('#user-packages input:checked').val()
                }

                axios[method](location, this.user)
                    .then(response => {
                        if (response.data.statusInfo) {
                            this.$store.state.authInfo.statusInfo = response.data.statusInfo
                        }

                        this.$toast.success(response.data.message)
                        this.$router.push({ name: 'users' })
                    })
            },
            submitSettings() {
                this.$root.clearFormValidation($('#settings form'))
                let post = { greylist_enabled: $('#greylist_enabled').prop('checked') ? 1 : 0 }

                axios.post('/api/v4/users/' + this.user_id + '/config', post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                    })
            },
            statusUpdate(user) {
                this.user = Object.assign({}, this.user, user)
            },
            deleteUser() {
                // Delete the user from the confirm dialog
                axios.delete('/api/v4/users/' + this.user_id)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.$router.push({ name: 'users' })
                        }
                    })
            },
            showDeleteConfirmation() {
                if (this.user_id == this.$store.state.authInfo.id) {
                    // Deleting self, redirect to /profile/delete page
                    this.$router.push({ name: 'profile-delete' })
                } else {
                    // Display the warning
                    new Modal('#delete-warning').show()
                }
            }
        }
    }
</script>
