<template>
    <div class="container">
        <status-component v-if="user_id !== 'new'" :status="status" @status-update="statusUpdate"></status-component>

        <div class="card" id="user-info">
            <div class="card-body">
                <div class="card-title" v-if="user_id === 'new'">{{ $t('user.new') }}</div>
                <div class="card-title" v-else>{{ $t($route.name == 'settings' ? 'dashboard.myaccount' : 'user.title') }}
                    <btn v-if="isController" icon="trash-can" class="btn-outline-danger button-delete float-end" @click="$refs.deleteWarning.show()">
                        {{ $t(isSelf ? 'user.profile-delete' : 'user.delete') }}
                    </btn>
                </div>
                <div class="card-text">
                    <tabs class="mt-3" :tabs="tabs"></tabs>
                    <div class="tab-content">
                        <div class="tab-pane active" id="general" role="tabpanel" aria-labelledby="tab-general">
                            <form @submit.prevent="submit" class="card-body">
                                <div v-if="user_id !== 'new' && isController" class="row plaintext mb-3">
                                    <label for="status" class="col-sm-4 col-form-label">
                                        <span>{{ $t('form.status') }}</span>
                                        <span v-if="$route.name === 'settings'">&nbsp;({{ $t('user.custno') }})</span>
                                    </label>
                                    <div class="col-sm-8">
                                        <span class="form-control-plaintext">
                                            <span id="status" :class="$root.statusClass(user)">{{ $root.statusText(user) }}</span>
                                            <span id="userid" v-if="$route.name === 'settings'">&nbsp;({{ user_id }})</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="row mb-3" v-if="user_id === 'new'">
                                    <label for="first_name" class="col-sm-4 col-form-label">{{ $t('form.firstname') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="first_name" v-model="user.first_name">
                                    </div>
                                </div>
                                <div class="row mb-3" v-if="user_id === 'new'">
                                    <label for="last_name" class="col-sm-4 col-form-label">{{ $t('form.lastname') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="last_name" v-model="user.last_name">
                                    </div>
                                </div>
                                <div class="row mb-3" v-if="user_id === 'new'">
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
                                <div class="row mb-3" v-if="isController">
                                    <label for="aliases-input" class="col-sm-4 col-form-label">{{ $t('user.email-aliases') }}</label>
                                    <div class="col-sm-8">
                                        <list-input id="aliases" :list="user.aliases"></list-input>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="password" class="col-sm-4 col-form-label">{{ $t('form.password') }}</label>
                                    <div class="col-sm-8">
                                        <div v-if="!isSelf" class="btn-group w-100" role="group">
                                            <input type="checkbox" id="pass-mode-input" value="input" class="btn-check" @change="setPasswordMode" :checked="passwordMode == 'input'">
                                            <label class="btn btn-outline-secondary" for="pass-mode-input">{{ $t('user.pass-input') }}</label>
                                            <input type="checkbox" id="pass-mode-link" value="link" class="btn-check" @change="setPasswordMode">
                                            <label class="btn btn-outline-secondary" for="pass-mode-link">{{ $t('user.pass-link') }}</label>
                                        </div>
                                        <password-input v-if="passwordMode == 'input'" :class="isSelf ? '' : 'mt-2'" v-model="user"></password-input>
                                        <div id="password-link" v-if="isController && (passwordMode == 'link' || user.passwordLinkCode)" class="mt-2">
                                            <span>{{ $t('user.pass-link-label') }}</span>&nbsp;<code>{{ passwordLink }}</code>
                                            <span class="d-inline-block">
                                                <btn class="btn-link p-1" :icon="['far', 'clipboard']" :title="$t('btn.copy')" @click="passwordLinkCopy"></btn>
                                                <btn v-if="user.passwordLinkCode" class="btn-link text-danger p-1" icon="trash-can" :title="$t('btn.delete')" @click="passwordLinkDelete"></btn>
                                            </span>
                                            <div v-if="!user.passwordLinkCode" class="form-text m-0">{{ $t('user.pass-link-hint') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="user_id === 'new'" id="user-packages" class="row mb-3">
                                    <label class="col-sm-4 col-form-label">{{ $t('user.package') }}</label>
                                    <package-select class="col-sm-8 pt-sm-1"></package-select>
                                </div>
                                <div v-if="user_id !== 'new' && $root.hasPermission('subscriptions')" id="user-skus" class="row mb-3">
                                    <label class="col-sm-4 col-form-label">{{ $t('form.subscriptions') }}</label>
                                    <subscription-select v-if="user.id" class="col-sm-8 pt-sm-1" :object="user" ref="skus"></subscription-select>
                                </div>
                                <btn class="btn-primary" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
                            </form>
                        </div>
                        <div v-if="isController" class="tab-pane" id="settings" role="tabpanel" aria-labelledby="tab-settings">
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
                                <div v-if="$root.hasPermission('beta')" class="row checkbox mb-3">
                                    <label for="guam_enabled" class="col-sm-4 col-form-label">
                                        {{ $t('user.imapproxy') }}
                                        <sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup>
                                    </label>
                                    <div class="col-sm-8 pt-2">
                                        <input type="checkbox" id="guam_enabled" name="guam_enabled" value="1" class="form-check-input d-block mb-2" :checked="user.config.guam_enabled">
                                        <small id="guam-hint" class="text-muted">
                                            {{ $t('user.imapproxy-text') }}
                                        </small>
                                    </div>
                                </div>
                                <div v-if="$root.hasPermission('beta')" class="row mb-3">
                                    <label for="limit_geo" class="col-sm-4 col-form-label">
                                        {{ $t('user.geolimit') }}
                                        <sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup>
                                    </label>
                                    <div class="col-sm-8 pt-2">
                                        <country-select id="limit_geo" v-model="user.config.limit_geo"></country-select>
                                        <small id="geolimit-hint" class="text-muted">
                                            {{ $t('user.geolimit-text') }}
                                        </small>
                                    </div>
                                </div>
                                <btn class="btn-primary" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
                            </form>
                        </div>
                        <div class="tab-pane" id="personal" role="tabpanel" aria-labelledby="tab-personal">
                            <form @submit.prevent="submitPersonalSettings" class="card-body">
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
                                    <label for="phone" class="col-sm-4 col-form-label">{{ $t('form.phone') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="phone" v-model="user.phone">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="external_email" class="col-sm-4 col-form-label">{{ $t('user.ext-email') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="external_email" v-model="user.external_email">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="billing_address" class="col-sm-4 col-form-label">{{ $t('user.address') }}</label>
                                    <div class="col-sm-8">
                                        <textarea class="form-control" id="billing_address" rows="3" v-model="user.billing_address"></textarea>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="country" class="col-sm-4 col-form-label">{{ $t('user.country') }}</label>
                                    <div class="col-sm-8">
                                        <select class="form-select" id="country" v-model="user.country">
                                            <option value="">-</option>
                                            <option v-for="(item, code) in countries" :value="code" :key="code">{{ item[1] }}</option>
                                        </select>
                                    </div>
                                </div>
                                <btn class="btn-primary button-submit mt-2" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <modal-dialog id="delete-warning" ref="deleteWarning" :buttons="[deleteButton]" :cancel-focus="true" @click="deleteUser()"
                      :title="$t(isSelf ? 'user.profile-delete-title' : 'user.delete-email', { email: user.email })"
        >
            <div v-if="isSelf">
                <p>{{ $t('user.profile-delete-text1') }} <strong>{{ $t('user.profile-delete-warning') }}</strong>.</p>
                <p>{{ $t('user.profile-delete-text2') }}</p>
                <p v-if="supportEmail" v-html="$t('user.profile-delete-support', { href: 'mailto:' + supportEmail, email: supportEmail })"></p>
                <p>{{ $t('user.profile-delete-contact', { app: $root.appName }) }}</p>
            </div>
            <p v-else>{{ $t('user.delete-text') }}</p>
        </modal-dialog>
    </div>
</template>

<script>
    import CountrySelect from '../Widgets/CountrySelect'
    import ListInput from '../Widgets/ListInput'
    import ModalDialog from '../Widgets/ModalDialog'
    import PackageSelect from '../Widgets/PackageSelect'
    import PasswordInput from '../Widgets/PasswordInput'
    import StatusComponent from '../Widgets/Status'
    import SubscriptionSelect from '../Widgets/SubscriptionSelect'

    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-regular-svg-icons/faClipboard').definition,
    )

    export default {
        components: {
            CountrySelect,
            ListInput,
            ModalDialog,
            PackageSelect,
            PasswordInput,
            StatusComponent,
            SubscriptionSelect
        },
        data() {
            return {
                countries: window.config.countries,
                isSelf: false,
                passwordLinkCode: '',
                passwordMode: '',
                user_id: null,
                user: { aliases: [], config: [] },
                supportEmail: window.config['app.support_email'],
                status: {},
                successRoute: { name: 'users' }
            }
        },
        computed: {
            deleteButton: function () {
                return {
                    className: 'btn-danger modal-action',
                    dismiss: 'modal',
                    label: this.isSelf ? 'user.profile-delete' : 'btn.delete',
                    icon: 'trash-can'
                }
            },
            isController: function () {
                return this.$root.hasPermission('users')
            },
            passwordLink: function () {
                return this.$root.appUrl + '/password-reset/' + this.passwordLinkCode
            },
            tabs: function () {
                let tabs = ['form.general']

                if (this.user_id === 'new') {
                    return tabs
                }

                if (this.isController) {
                    tabs.push('form.settings')
                }

                tabs.push('form.personal')

                return tabs
            }
        },
        created() {
            if (this.$route.name === 'settings') {
                this.user_id = this.$root.authInfo.id
                this.successRoute = null
            } else {
                this.user_id = this.$route.params.user
            }

            this.isSelf = this.user_id == this.$root.authInfo.id

            if (this.user_id !== 'new') {
                axios.get('/api/v4/users/' + this.user_id, { loader: true })
                    .then(response => {
                        this.user = { ...response.data, ...response.data.settings }
                        this.status = response.data.statusInfo
                        this.passwordLinkCode = this.user.passwordLinkCode
                    })
                    .catch(this.$root.errorHandler)

                if (this.isSelf) {
                    this.passwordMode = 'input'
                }
            } else {
                this.passwordMode = 'input'
            }
        },
        mounted() {
            $('#first_name').focus()
        },
        methods: {
            passwordLinkCopy() {
                navigator.clipboard.writeText($('#password-link code').text());
            },
            passwordLinkDelete() {
                this.passwordMode = ''
                $('#pass-mode-link')[0].checked = false

                // Delete the code for real
                axios.delete('/api/v4/password-reset/code/' + this.passwordLinkCode)
                    .then(response => {
                        this.passwordLinkCode = ''
                        this.user.passwordLinkCode = ''
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                        }
                    })
            },
            setPasswordMode(event) {
                const mode = event.target.checked ? event.target.value : ''

                // In the "new user" mode the password mode cannot be unchecked
                if (!mode && this.user_id === 'new') {
                    event.target.checked = true
                    return
                }

                this.passwordMode = mode

                if (!event.target.checked) {
                    return
                }

                $('#pass-mode-' + (mode == 'link' ? 'input' : 'link'))[0].checked = false

                // Note: we use $nextTick() because we have to wait for the HTML elements to exist
                this.$nextTick().then(() => {
                    if (mode == 'link' && !this.passwordLinkCode) {
                        axios.post('/api/v4/password-reset/code', {}, { loader: '#password-link' })
                            .then(response => {
                                this.passwordLinkCode = response.data.short_code + '-' + response.data.code
                            })
                    } else if (mode == 'input') {
                        $('#password').focus();
                    }
                })
            },
            submit() {
                this.$root.clearFormValidation($('#general form'))

                let props = this.isController ? ['aliases'] : []
                if (this.user_id === 'new') {
                    props = props.concat(['email', 'first_name', 'last_name', 'organization'])
                }

                let method = 'post'
                let location = '/api/v4/users'
                let post = this.$root.pick(this.user, props)

                if (this.user_id !== 'new') {
                    method = 'put'
                    location += '/' + this.user_id
                    if (this.$refs.skus) {
                        post.skus = this.$refs.skus.getSkus()
                    }
                } else {
                    post.package = $('#user-packages input:checked').val()
                }

                if (this.passwordMode == 'link' && this.passwordLinkCode) {
                    post.passwordLinkCode = this.passwordLinkCode
                } else if (this.passwordMode == 'input') {
                    post.password = this.user.password
                    post.password_confirmation = this.user.password_confirmation
                }

                axios[method](location, post)
                    .then(response => {
                        if (response.data.statusInfo) {
                            this.$root.authInfo.statusInfo = response.data.statusInfo
                        }

                        this.$toast.success(response.data.message)
                        if (this.successRoute) {
                            this.$router.push(this.successRoute)
                        }
                    })
            },
            submitPersonalSettings() {
                this.$root.clearFormValidation($('#personal form'))

                let post = this.$root.pick(this.user, ['first_name', 'last_name', 'organization', 'phone', 
                    'country', 'external_email', 'billing_address'])

                axios.put('/api/v4/users' + '/' + this.user_id, post)
                    .then(response => {
                        if (response.data.statusInfo) {
                            this.$root.authInfo.statusInfo = response.data.statusInfo
                        }

                        this.$toast.success(response.data.message)
                        if (this.successRoute) {
                            this.$router.push(this.successRoute)
                        }
                    })
            },
            submitSettings() {
                this.$root.clearFormValidation($('#settings form'))

                let post = this.$root.pick(this.user.config, ['limit_geo'])

                const checklist = ['greylist_enabled', 'guam_enabled']
                checklist.forEach(name => {
                    if ($('#' + name).length) {
                        post[name] = $('#' + name).prop('checked') ? 1 : 0
                    }
                })

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

                            if (this.isSelf) {
                                this.$root.logoutUser()
                            } else {
                                this.$router.push(this.successRoute)
                            }
                        }
                    })
            }
        }
    }
</script>
