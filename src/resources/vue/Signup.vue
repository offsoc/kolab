<template>
    <div class="container">
        <div id="step0" v-if="!invitation">
            <div class="plan-selector row row-cols-sm-2 g-3">
                <div v-for="item in plans" :key="item.id">
                    <div :class="'card bg-light plan-' + item.title">
                        <div class="card-header plan-header">
                            <div class="plan-ico text-center">
                                <svg-icon :icon="plan_icons[item.title]"></svg-icon>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <button class="btn btn-primary" :data-title="item.title" @click="selectPlan(item.title)" v-html="item.button"></button>
                            <div class="plan-description text-start mt-3" v-html="item.description"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card d-none" id="step1" v-if="!invitation">
            <div class="card-body">
                <h4 class="card-title">{{ $t('signup.title') }} - {{ $t('nav.step', { i: 1, n: 3 }) }}</h4>
                <p class="card-text">
                    {{ $t('signup.step1') }}
                </p>
                <form @submit.prevent="submitStep1" data-validation-prefix="signup_">
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" id="signup_first_name" :placeholder="$t('form.firstname')" autofocus v-model="first_name">
                            <input type="text" class="form-control rounded-end" id="signup_last_name" :placeholder="$t('form.surname')" v-model="last_name">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="signup_email" class="visually-hidden">{{ $t('signup.email') }}</label>
                        <input type="text" class="form-control" id="signup_email" :placeholder="$t('signup.email')" required v-model="email">
                    </div>
                    <button class="btn btn-secondary" type="button" @click="stepBack">{{ $t('btn.back') }}</button>
                    <button class="btn btn-primary" type="submit"><svg-icon icon="check"></svg-icon> {{ $t('btn.continue') }}</button>
                </form>
            </div>
        </div>

        <div class="card d-none" id="step2" v-if="!invitation">
            <div class="card-body">
                <h4 class="card-title">{{ $t('signup.title') }} - {{ $t('nav.step', { i: 2, n: 3 }) }}</h4>
                <p class="card-text">
                    {{ $t('signup.step2') }}
                </p>
                <form @submit.prevent="submitStep2" data-validation-prefix="signup_">
                    <div class="mb-3">
                        <label for="signup_short_code" class="visually-hidden">{{ $t('form.code') }}</label>
                        <input type="text" class="form-control" id="signup_short_code" :placeholder="$t('form.code')" required v-model="short_code">
                    </div>
                    <button class="btn btn-secondary" type="button" @click="stepBack">{{ $t('btn.back') }}</button>
                    <button class="btn btn-primary" type="submit"><svg-icon icon="check"></svg-icon> {{ $t('btn.continue') }}</button>
                    <input type="hidden" id="signup_code" v-model="code" />
                </form>
            </div>
        </div>

        <div class="card d-none" id="step3">
            <div class="card-body">
                <h4 v-if="!invitation" class="card-title">{{ $t('signup.title') }} - {{ $t('nav.step', { i: 3, n: 3 }) }}</h4>
                <p class="card-text">
                    {{ $t('signup.step3') }}
                </p>
                <form @submit.prevent="submitStep3" data-validation-prefix="signup_">
                    <div class="mb-3" v-if="invitation">
                        <div class="input-group">
                            <input type="text" class="form-control" id="signup_first_name" :placeholder="$t('form.firstname')" autofocus v-model="first_name">
                            <input type="text" class="form-control rounded-end" id="signup_last_name" :placeholder="$t('form.surname')" v-model="last_name">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="signup_login" class="visually-hidden"></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="signup_login" required v-model="login" :placeholder="$t('signup.login')">
                            <span class="input-group-text">@</span>
                            <input v-if="is_domain" type="text" class="form-control rounded-end" id="signup_domain" required v-model="domain" :placeholder="$t('form.domain')">
                            <select v-else class="form-select rounded-end" id="signup_domain" required v-model="domain">
                                <option v-for="d in domains" :key="d" :value="d">{{ domain }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="signup_password" class="visually-hidden">{{ $t('form.password') }}</label>
                        <input type="password" class="form-control" id="signup_password" :placeholder="$t('form.password')" required v-model="password">
                    </div>
                    <div class="mb-3">
                        <label for="signup_confirm" class="visually-hidden">{{ $t('form.password-confirm') }}</label>
                        <input type="password" class="form-control" id="signup_confirm" :placeholder="$t('form.password-confirm')" required v-model="password_confirmation">
                    </div>
                    <div class="mb-3 pt-2">
                        <label for="signup_voucher" class="visually-hidden">{{ $t('signup.voucher') }}</label>
                        <input type="text" class="form-control" id="signup_voucher" :placeholder="$t('signup.voucher')" v-model="voucher">
                    </div>
                    <button v-if="!invitation" class="btn btn-secondary" type="button" @click="stepBack">{{ $t('btn.back') }}</button>
                    <button class="btn btn-primary" type="submit">
                        <svg-icon icon="check"></svg-icon> <span v-if="invitation">{{ $t('btn.signup') }}</span><span v-else>{{ $t('btn.submit') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                email: '',
                first_name: '',
                last_name: '',
                code: '',
                short_code: '',
                login: '',
                password: '',
                password_confirmation: '',
                domain: '',
                domains: [],
                invitation: null,
                is_domain: false,
                plan: null,
                plan_icons: {
                    individual: 'user',
                    group: 'users'
                },
                plans: [],
                voucher: ''
            }
        },
        mounted() {
            let param = this.$route.params.param;

            if (this.$route.name == 'signup-invite') {
                this.$root.startLoading()
                axios.get('/api/auth/signup/invitations/' + param)
                    .then(response => {
                        this.invitation = response.data
                        this.login = response.data.login
                        this.voucher = response.data.voucher
                        this.first_name = response.data.first_name
                        this.last_name = response.data.last_name
                        this.plan = response.data.plan
                        this.is_domain = response.data.is_domain
                        this.setDomain(response.data)
                        this.$root.stopLoading()
                        this.displayForm(3, true)
                    })
                    .catch(error => {
                        this.$root.errorHandler(error)
                    })
            } else if (param) {
                if (this.$route.path.indexOf('/signup/voucher/') === 0) {
                    // Voucher (discount) code
                    this.voucher = param
                    this.displayForm(0)
                } else if (/^([A-Z0-9]+)-([a-zA-Z0-9]+)$/.test(param)) {
                    // Verification code provided, auto-submit Step 2
                    this.short_code = RegExp.$1
                    this.code = RegExp.$2
                    this.submitStep2(true)
                } else if (/^([a-zA-Z_]+)$/.test(param)) {
                    // Plan title provided, save it and display Step 1
                    this.plan = param
                    this.displayForm(1, true)
                } else {
                    this.$root.errorPage(404)
                }
            } else {
                this.displayForm(0)
            }
        },
        methods: {
            selectPlan(plan) {
                this.$router.push({path: '/signup/' + plan})
                this.plan = plan
                this.displayForm(1, true)
            },
            // Composes plan selection page
            step0() {
                if (!this.plans.length) {
                    this.$root.startLoading()
                    axios.get('/api/auth/signup/plans').then(response => {
                        this.$root.stopLoading()
                        this.plans = response.data.plans
                    })
                    .catch(error => {
                        this.$root.errorHandler(error)
                    })
                }
            },
            // Submits data to the API, validates and gets verification code
            submitStep1() {
                this.$root.clearFormValidation($('#step1 form'))

                axios.post('/api/auth/signup/init', {
                    email: this.email,
                    last_name: this.last_name,
                    first_name: this.first_name,
                    plan: this.plan,
                    voucher: this.voucher
                }).then(response => {
                    this.displayForm(2, true)
                    this.code = response.data.code
                })
            },
            // Submits the code to the API for verification
            submitStep2(bylink) {
                if (bylink === true) {
                    this.displayForm(2, false)
                }

                this.$root.clearFormValidation($('#step2 form'))

                axios.post('/api/auth/signup/verify', {
                    code: this.code,
                    short_code: this.short_code
                }).then(response => {
                    this.displayForm(3, true)
                    // Reset user name/email/plan, we don't have them if user used a verification link
                    this.first_name = response.data.first_name
                    this.last_name = response.data.last_name
                    this.email = response.data.email
                    this.is_domain = response.data.is_domain
                    this.voucher = response.data.voucher

                    // Fill the domain selector with available domains
                    if (!this.is_domain) {
                        this.setDomain(response.data)
                    }
                }).catch(error => {
                    if (bylink === true) {
                        // FIXME: display step 1, user can do nothing about it anyway
                        //        Maybe we should display 404 error page?
                        this.displayForm(1, true)
                    }
                })
            },
            // Submits the data to the API to create the user account
            submitStep3() {
                this.$root.clearFormValidation($('#step3 form'))

                let post = {
                    login: this.login,
                    domain: this.domain,
                    password: this.password,
                    password_confirmation: this.password_confirmation,
                    voucher: this.voucher
                }

                if (this.invitation) {
                    post.invitation = this.invitation.id
                    post.plan = this.plan
                    post.first_name = this.first_name
                    post.last_name = this.last_name
                } else {
                    post.code = this.code
                    post.short_code = this.short_code
                }

                axios.post('/api/auth/signup', post).then(response => {
                    // auto-login and goto dashboard
                    this.$root.loginUser(response.data)
                })
            },
            // Moves the user a step back in registration form
            stepBack(e) {
                var card = $(e.target).closest('.card')

                card.prev().removeClass('d-none').find('input').first().focus()
                card.addClass('d-none').find('form')[0].reset()

                if (card.attr('id') == 'step1') {
                    this.step0()
                    this.$router.replace({path: '/signup'})
                }
            },
            displayForm(step, focus) {
                [0, 1, 2, 3].filter(value => value != step).forEach(value => {
                    $('#step' + value).addClass('d-none')
                })

                if (!step) {
                    return this.step0()
                }

                $('#step' + step).removeClass('d-none')

                if (focus) {
                    $('#step' + step).find('input').first().focus()
                }
            },
            setDomain(response) {
                if (response.domains) {
                    this.domains = response.domains
                }

                this.domain = response.domain || window.config['app.domain']
            }
        }
    }
</script>
