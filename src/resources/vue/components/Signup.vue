<template>
    <div class="container">
        <div id="step0">
            <div class="plan-selector d-flex justify-content-around align-items-stretch mb-3"></div>
            <h3>FAQs</h3>
            <ul>
                <li><a href="https://kolabnow.com/tos">What are your terms of service?</a></li>
                <li><a href="https://kb.kolabnow.com/faq/can-i-upgrade-an-individual-account-to-a-group-account">Can I upgrade an individual account to a group account?</a></li>
                <li><a href="https://kb.kolabnow.com/faq/how-much-storage-comes-with-my-account">How much storage comes with my account?</a></li>
            </ul>
        </div>

        <div class="card d-none" id="step1">
            <div class="card-body">
                <h4 class="card-title">Sign Up - Step 1/3</h4>
                <p class="card-text">
                    Sign up to start your free month.
                </p>
                <form @submit.prevent="submitStep1" data-validation-prefix="signup_">
                    <div class="form-group">
                        <label for="signup_name" class="sr-only">Your Name</label>
                        <input type="text" class="form-control" id="signup_name" placeholder="Your Name" required autofocus v-model="name">
                    </div>
                    <div class="form-group">
                        <label for="signup_email" class="sr-only">Existing Email Address</label>
                        <input type="text" class="form-control" id="signup_email" placeholder="Existing Email Address" required v-model="email">
                    </div>
                    <button class="btn btn-secondary" type="button" @click="stepBack">Back</button>
                    <button class="btn btn-primary" type="submit">Continue</button>
                </form>
            </div>
        </div>

        <div class="card d-none" id="step2">
            <div class="card-body">
                <h4 class="card-title">Sign Up - Step 2/3</h4>
                <p class="card-text">
                    We sent out a confirmation code to your email address.
                    Enter the code we sent you, or click the link in the message.
                </p>
                <form @submit.prevent="submitStep2" data-validation-prefix="signup_">
                    <div class="form-group">
                        <label for="signup_short_code" class="sr-only">Confirmation Code</label>
                        <input type="text" class="form-control" id="signup_short_code" placeholder="Confirmation Code" required v-model="short_code">
                    </div>
                    <button class="btn btn-secondary" type="button" @click="stepBack">Back</button>
                    <button class="btn btn-primary" type="submit">Continue</button>
                    <input type="hidden" id="signup_code" v-model="code" />
                </form>
            </div>
        </div>

        <div class="card d-none" id="step3">
            <div class="card-body">
                <h4 class="card-title">Sign Up - Step 3/3</h4>
                <p class="card-text">
                    Create your Kolab identity (you can choose additional addresses later).
                </p>
                <form @submit.prevent="submitStep3" data-validation-prefix="signup_">
                    <div class="form-group">
                        <label for="signup_login" class="sr-only"></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="signup_login" required v-model="login" placeholder="Login">
                            <span class="input-group-append">
                                <span class="input-group-text">@</span>
                            </span>
                            <input v-if="is_domain" type="text" class="form-control rounded-right" id="signup_domain" required v-model="domain" placeholder="Domain">
                            <select v-if="!is_domain" class="custom-select rounded-right" id="signup_domain" required v-model="domain"></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="signup_password" class="sr-only">Password</label>
                        <input type="password" class="form-control" id="signup_password" placeholder="Password" required v-model="password">
                    </div>
                    <div class="form-group">
                        <label for="signup_confirm" class="sr-only">Confirm Password</label>
                        <input type="password" class="form-control" id="signup_confirm" placeholder="Confirm Password" required v-model="password_confirmation">
                    </div>
                    <button class="btn btn-secondary" type="button" @click="stepBack">Back</button>
                    <button class="btn btn-primary" type="submit">Submit</button>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
    import store from '../js/store'

    export default {
        data() {
            return {
                email: '',
                name: '',
                code: '',
                short_code: '',
                login: '',
                password: '',
                password_confirmation: '',
                domain: '',
                plan: null,
                is_domain: false,
                plans: null
            }
        },
        mounted() {
            let param = this.$route.params.param;
            if (param) {
                if (/^([A-Z0-9]+)-([a-zA-Z0-9]+)$/.test(param)) {
                    // Verification code provided, auto-submit Step 2
                    this.short_code = RegExp.$1
                    this.code = RegExp.$2
                    this.submitStep2(true)
                } else if (/^([a-zA-Z_]+)$/.test(param)) {
                    // Plan title provided, save it and display Step 1
                    this.plan = param
                    this.displayForm(1, true)
                } else {
                    // TODO: Find a way to display error page without changing the URL
                    //       Maybe https://github.com/raniesantos/vue-error-page
                    this.$router.push({name: '404'})
                }
            } else {
                this.displayForm(0)
            }
        },
        methods: {
            // Composes plan selection page
            step0() {
                if (!this.plans) {
                    axios.get('/api/auth/signup/plans', {}).then(response => {
                        let boxes = []

                        this.plans = response.data.plans

                        this.plans.forEach(plan => {
                            boxes.push($(
                                `<div class="p-3 m-1 text-center bg-light flex-fill plan-box plan-${plan.title}">
                                    <button class="btn btn-primary" data-title="${plan.title}">${plan.title}</button>
                                    <div class="plan-description text-left mt-3">${plan.description}</div>
                                </div>`
                            ))
                        })

                        $('#step0').find('.plan-selector')
                            .append(boxes)
                            .find('button').on('click', event => {
                                let plan = $(event.target).data('title')
                                this.$router.push({path: '/signup/' + plan})
                                this.plan = plan
                                this.displayForm(1, true)
                            })
                    })
                }
            },
            // Submits data to the API, validates and gets verification code
            submitStep1() {
                this.$root.$emit('clearFormValidation', $('#step1 form'))

                axios.post('/api/auth/signup/init', {
                    email: this.email,
                    name: this.name,
                    plan: this.plan
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

                this.$root.$emit('clearFormValidation', $('#step2 form'))

                axios.post('/api/auth/signup/verify', {
                    code: this.code,
                    short_code: this.short_code
                }).then(response => {
                    this.displayForm(3, true)
                    // Reset user name/email/plan, we don't have them if user used a verification link
                    this.name = response.data.name
                    this.email = response.data.email
                    this.is_domain = response.data.is_domain

                    // Fill the domain selector with available domains
                    if (!this.is_domain) {
                        let options = []
                        $('select#signup_domain').html('')
                        $.each(response.data.domains, (i, v) => {
                            options.push($('<option>').text(v).attr('value', v))
                        })
                        $('select#signup_domain').append(options)
                        this.domain = window.config['app.domain']
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
                this.$root.$emit('clearFormValidation', $('#step3 form'))

                axios.post('/api/auth/signup', {
                    code: this.code,
                    short_code: this.short_code,
                    login: this.login,
                    domain: this.domain,
                    password: this.password,
                    password_confirmation: this.password_confirmation
                }).then(response => {
                    // auto-login and goto dashboard
                    store.commit('loginUser')
                    localStorage.setItem('token', response.data.access_token)
                    this.$router.push({name: 'dashboard'})
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
            }
        }
    }
</script>
