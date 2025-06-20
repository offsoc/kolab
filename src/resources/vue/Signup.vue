<template>
    <div class="container">
        <div id="step0">
            <div class="plan-selector row row-cols-sm-2 g-3">
                <div v-for="item in plans" :key="item.id" :id="'plan-' + item.title">
                    <div :class="'card bg-light plan-' + item.title">
                        <div class="card-header plan-header">
                            <div class="plan-ico text-center">
                                <svg-icon :icon="plan_icons[item.title] || 'user'"></svg-icon>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <btn class="btn-primary" :data-title="item.title" @click="selectPlan(item.title)" v-html="item.button"></btn>
                            <div class="plan-description text-start mt-3" v-html="item.description"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card d-none" id="step1" v-if="!invitation">
            <div class="card-body">
                <h4 class="card-title">{{ $t('signup.title') }} - {{ $t('nav.step', { i: 1, n: steps }) }}</h4>
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
                    <div v-if="mode == 'token'" class="mb-3">
                        <label for="signup_token" class="visually-hidden">{{ $t('signup.token') }}</label>
                        <input type="text" class="form-control" id="signup_token" :placeholder="$t('signup.token')" required v-model="token">
                    </div>
                    <div v-else class="mb-3">
                        <label for="signup_email" class="visually-hidden">{{ $t('signup.email') }}</label>
                        <input type="text" class="form-control" id="signup_email" :placeholder="$t('signup.email')" required v-model="email">
                    </div>
                    <btn class="btn-secondary" @click="stepBack" v-if="plans.length > 1">{{ $t('btn.back') }}</btn>
                    <btn class="btn-primary ms-2" type="submit" icon="check">{{ $t('btn.continue') }}</btn>
                </form>
            </div>
        </div>

        <div class="card d-none" id="step2" v-if="!invitation">
            <div class="card-body">
                <h4 class="card-title">{{ $t('signup.title') }} - {{ $t('nav.step', { i: 2, n: steps }) }}</h4>
                <p class="card-text">
                    {{ $t('signup.step2') }}
                </p>
                <form @submit.prevent="submitStep2" data-validation-prefix="signup_">
                    <div class="mb-3">
                        <label for="signup_short_code" class="visually-hidden">{{ $t('form.code') }}</label>
                        <input type="text" class="form-control" id="signup_short_code" :placeholder="$t('form.code')" required v-model="short_code">
                    </div>
                    <btn class="btn-secondary" @click="stepBack">{{ $t('btn.back') }}</btn>
                    <btn class="btn-primary ms-2" type="submit" icon="check">{{ $t('btn.continue') }}</btn>
                    <input type="hidden" id="signup_code" v-model="code" />
                </form>
            </div>
        </div>

        <div class="card d-none" id="step3">
            <div class="card-body">
                <h4 v-if="!invitation && steps > 1" class="card-title">{{ $t('signup.title') }} - {{ $t('nav.step', { i: steps, n: steps }) }}</h4>
                <p class="card-text">
                    {{ $t('signup.step3', { app: $root.appName }) }}
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
                        <div v-if="is_domain" class="form-check float-end text-secondary">
                            <input class="form-check-input" type="checkbox" value="1" id="custom_domain" @change="useCustomDomain">
                            <label class="form-check-label" for="custom_domain">{{ $t('signup.owndomain') }}</label>
                        </div>
                        <div class="input-group">
                            <input type="text" class="form-control" id="signup_login" required v-model="login" :placeholder="$t('signup.login')">
                            <span class="input-group-text">@</span>
                            <input v-if="use_custom" type="text" class="form-control rounded-end" id="signup_domain" required :placeholder="$t('form.domain')" v-model="domain">
                            <select v-else class="form-select rounded-end" id="signup_domain" required v-model="domain">
                                <option v-for="_domain in domains" :key="_domain" :value="_domain">{{ _domain }}</option>
                            </select>
                        </div>
                    </div>
                    <password-input class="mb-3" v-model="pass"></password-input>
                    <div class="mb-3">
                        <label for="signup_voucher" class="visually-hidden">{{ $t('signup.voucher') }}</label>
                        <input type="text" class="form-control" id="signup_voucher" :placeholder="$t('signup.voucher')" v-model="voucher">
                    </div>
                    <btn v-if="!invitation || plans.length > 1" class="btn-secondary me-2" @click="stepBack">{{ $t('btn.back') }}</btn>
                    <btn class="btn-primary" type="submit" icon="check">
                        <span v-if="invitation">{{ $t('btn.signup') }}</span>
                        <span v-else>{{ $t('btn.submit') }}</span>
                    </btn>
                </form>
            </div>
        </div>

        <div class="card d-none border-0" id="step4">
            <div v-if="checkout.cost" class="card-body row row-cols-lg-2 align-items-center">
                <h4 class="card-title text-center mb-4 col-lg-5">{{ $t('signup.created') }}</h4>
                <div class="card-text mb-4 col-lg-7">
                    <div class="card internal" id="summary">
                        <div class="card-body">
                            <div class="card-text">
                                <h5>{{ checkout.title }}</h5>
                                <p id="summary-content">{{ checkout.content }}</p>
                                <p class="credit-cards">
                                    <img src="/themes/default/images/visa.svg" alt="Visa" />
                                    <img src="/themes/default/images/mastercard.svg" alt="Mastercard" />
                                </p>
                                <div id="summary-summary" class="mb-4" v-if="checkout.summary" v-html="checkout.summary"></div>
                                <form>
                                    <btn class="btn-secondary me-2" @click="stepBack">{{ $t('btn.back') }}</btn>
                                    <btn class="btn-primary" @click="submitStep4">{{ $t('btn.subscribe') }}</btn>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="card-body">
                <h4 class="card-title mb-4">{{ $t('signup.created') }}</h4>
                <div class="card-text mb-4" id="summary">
                    <p id="summary-content">{{ checkout.content }}</p>
                    <form>
                        <btn class="btn-secondary me-2" @click="stepBack">{{ $t('btn.back') }}</btn>
                        <btn class="btn-primary" @click="submitStep4">{{ $t('btn.subscribe') }}</btn>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import PasswordInput from './Widgets/PasswordInput'
    import { paymentCheckout } from '../js/utils'

    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faMobileRetro').definition,
        require('@fortawesome/free-solid-svg-icons/faUsers').definition
    )

    export default {
        components: {
            PasswordInput
        },
        data() {
            return {
                checkout: {},
                email: '',
                first_name: '',
                last_name: '',
                code: '',
                short_code: '',
                login: '',
                pass: {},
                domain: '',
                domains: [],
                invitation: null,
                is_domain: false,
                mode: 'email',
                plan: null,
                plan_icons: {
                    individual: 'user',
                    group: 'users',
                    phone: 'mobile-retro'
                },
                plans: [],
                referral: '',
                selectedDomain: '',
                token: '',
                use_custom: false,
                voucher: ''
            }
        },
        computed: {
            steps() {
                switch (this.mode) {
                    case 'token':
                        return 2
                    case 'mandate':
                        return 1
                    case 'email':
                    default:
                        return 3
                }
            }
        },
        mounted() {
            let params = this.$route.path.split('/').filter(v => v.length > 0).slice(1)

            if (params.length === 2 && params[0] === 'invite') {
                // Invitation code
                axios.get('/api/auth/signup/invitations/' + params[1], { loader: true })
                    .then(response => {
                        this.invitation = response.data
                        this.displayForm(0)
                    })
                    .catch(error => {
                        this.$root.errorHandler(error)
                    })
            } else if (params.length === 2 && params[0] === 'voucher') {
                // Voucher (discount) code
                this.voucher = params[1]
                this.displayForm(0)
            } else if (params.length === 2 && params[0] === 'referral') {
                // Referral code
                this.referral = params[1]
                this.displayForm(0)
            } else if (params.length === 1 && /^([A-Z0-9]+)-([a-zA-Z0-9]+)$/.test(params[0])) {
                 // Verification code provided, auto-submit Step 2
                this.short_code = RegExp.$1
                this.code = RegExp.$2
                this.submitStep2(true)
            } else if (params.length === 1 && /^([a-zA-Z_]+)$/.test(params[0])) {
                // Plan title provided, save it and display Step 1
                this.step0(params[0])
            } else if (params.length) {
                this.$root.errorPage(404)
            } else {
                this.displayForm(0)
            }
        },
        methods: {
            selectPlan(plan) {
                this.$router.push({path: '/signup/' + plan})
                this.selectPlanByTitle(plan)
            },
            // Composes plan selection page
            selectPlanByTitle(title) {
                const plan = this.plans.filter(plan => plan.title == title)[0]
                if (plan) {
                    this.plan = title
                    this.mode = plan.mode
                    this.is_domain = plan.isDomain

                    this.displayForm(plan.mode == 'mandate' || this.invitation ? 3 : 1, true)
                }
            },
            step0(plan) {
                if (!this.plans.length) {
                    axios.get('/api/auth/signup/plans', { loader: true }).then(response => {
                        this.plans = response.data.plans
                        if (this.plans.length == 1) {
                            if (!plan || plan != this.plans[0].title) {
                                plan = this.plans[0].title
                            }
                        }

                        this.selectPlanByTitle(plan)
                    })
                    .catch(error => {
                        this.$root.errorHandler(error)
                    })
                } else {
                    this.selectPlanByTitle(plan)
                }
            },
            // Submits data to the API, validates and gets verification code
            submitStep1() {
                this.$root.clearFormValidation($('#step1 form'))

                const post = this.$root.pick(this, ['email', 'last_name', 'first_name', 'plan', 'token', 'voucher', 'referral'])

                axios.post('/api/auth/signup/init', post)
                    .then(response => {
                        this.code = response.data.code
                        this.short_code = response.data.short_code
                        this.mode = response.data.mode
                        this.is_domain = response.data.is_domain
                        this.setDomain(response.data)
                        this.displayForm(this.mode == 'token' ? 3 : 2, true)
                    })
            },
            // Submits the code to the API for verification
            submitStep2(bylink) {
                if (bylink === true) {
                    this.displayForm(2, false)
                }

                this.$root.clearFormValidation($('#step2 form'))

                const post = this.$root.pick(this, ['code', 'short_code'])

                axios.post('/api/auth/signup/verify', post)
                    .then(response => {
                        // Reset user name/email/plan, we don't have them if user used a verification link
                        this.first_name = response.data.first_name
                        this.last_name = response.data.last_name
                        this.email = response.data.email
                        this.is_domain = response.data.is_domain
                        this.voucher = response.data.voucher
                        this.displayForm(3, true)
                    })
                    .catch(error => {
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

                const post = this.lastStepPostData()

                if (this.mode == 'mandate') {
                    axios.post('/api/auth/signup/validate', post).then(response => {
                        this.checkout = response.data
                        this.displayForm(4)
                    })
                } else {
                    axios.post('/api/auth/signup', post).then(response => {
                        // auto-login and goto dashboard
                        this.$root.loginUser(response.data)
                    })
                }
            },
            submitStep4() {
                const post = this.lastStepPostData()

                axios.post('/api/auth/signup', post).then(response => {
                    let checkout = response.data.checkout

                    // auto-login and goto to the payment checkout (or Dashboard for a free account)
                    this.$root.loginUser(response.data, !paymentCheckout(this, checkout))
                })
            },
            // Moves the user a step back in registration form
            stepBack(e) {
                const card = $(e.target).closest('.card[id^="step"]')
                let step = card.attr('id').replace('step', '')

                card.addClass('d-none')

                step -= 1

                if (step == 2 && this.mode == 'token') {
                    step = 1
                }

                if ((this.invitation || this.mode == 'mandate') && step < 3) {
                    step = 0
                }

                $('#step' + step).removeClass('d-none').find('input').first().focus()

                if (!step) {
                    this.step0()
                    this.$router.replace({path: '/signup'})
                }
            },
            displayForm(step, focus) {
                [0, 1, 2, 3, 4].filter(value => value != step).forEach(value => {
                    $('#step' + value).addClass('d-none')
                })

                if (!step) {
                    return this.step0()
                }

                if (step > 2 && !this.domains.length) {
                    axios.get('/api/auth/signup/domains')
                        .then(response => {
                            this.setDomain(response.data)
                            this.displayForm(step, focus)
                        })
                    return
                }

                $('#step' + step).removeClass('d-none')

                if (focus) {
                    $('#step' + step).find('input:not([type=checkbox])').first().focus()
                }
            },
            lastStepPostData() {
                let post = {
                    ...this.$root.pick(this, ['login', 'domain', 'voucher', 'plan']),
                    ...this.pass
                }

                if (this.invitation) {
                    post.invitation = this.invitation.id
                    post.first_name = this.first_name
                    post.last_name = this.last_name
                } else {
                    post.code = this.code
                    post.short_code = this.short_code
                }

                return post
            },
            setDomain(response) {
                if (response.domains) {
                    this.domains = response.domains
                }

                this.domain = response.domain

                if (!this.domain) {
                    this.domain = window.config['app.domain']
                    if (this.domains.length && !this.domains.includes(this.domain)) {
                        this.domain = this.domains[0]
                    }
                }

                this.selectedDomain = this.domain
            },
            useCustomDomain(event) {
                this.use_custom = event.target.checked
                this.domain = this.use_custom ? '' : this.selectedDomain
            }
        }
    }
</script>
