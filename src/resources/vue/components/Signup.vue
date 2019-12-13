<template>
    <div class="container">
        <div class="card" id="step1">
            <div class="card-body">
                <h4 class="card-title">Step 1/3</h4>
                <p class="card-text">
                    Sign up to start your free month.
                </p>
                <form v-on:submit.prevent="submitStep1" data-validation-prefix="signup_">
                    <div class="form-group">
                        <label for="signup_name" class="sr-only">Your Name</label>
                        <input type="text" class="form-control" id="signup_name" placeholder="Your Name" required autofocus v-model="name">
                    </div>
                    <div class="form-group">
                        <label for="signup_email" class="sr-only">Existing Email Address</label>
                        <input type="text" class="form-control" id="signup_email" placeholder="Existing Email Address" required v-model="email">
                    </div>
                    <button class="btn btn-primary" type="submit">Continue</button>
                </form>
            </div>
        </div>

        <div class="card d-none" id="step2">
            <div class="card-body">
                <h4 class="card-title">Step 2/3</h4>
                <p class="card-text">
                    We sent out a confirmation code to your email address.
                    Enter the code we sent you, or click the link in the message.
                </p>
                <form v-on:submit.prevent="submitStep2" data-validation-prefix="signup_">
                    <div class="form-group">
                        <label for="signup_code" class="sr-only">Confirmation Code</label>
                        <input type="text" class="form-control" id="signup_code" placeholder="Confirmation Code" required v-model="short_code">
                    </div>
                    <button class="btn btn-secondary" type="button" v-on:click="stepBack">Back</button>
                    <button class="btn btn-primary" type="submit">Continue</button>
                </form>
            </div>
        </div>

        <div class="card d-none" id="step3">
            <div class="card-body">
                <h4 class="card-title">Step 3/3</h4>
                <p class="card-text">
                    Create your Kolab identity (you can choose additional addresses later).
                </p>
                <form v-on:submit.prevent="submitStep3" data-validation-prefix="signup_">
                    <div class="form-group">
                        <label for="signup_login" class="sr-only"></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="signup_login" required>
                            <span class="input-group-text border-left-0 border-right-0 rounded-0">@</span>
                            <select class="custom-select rounded-right" id="signup_domain">
                                <option value="kolabnow.com">kolabnow.com</option>
                                <option value="kolabnow.com">mykolab.com</option>
                            </select>
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
                    <button class="btn btn-secondary" type="button" v-on:click="stepBack">Back</button>
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
                password: '',
                password_confirmation: ''
            }
        },
        created() {
            // Verification code provided, jump to Step 2
            if (this.$route.params.code && /^([A-Z0-9]+)-([a-zA-Z0-9]+)$/.test(this.$route.params.code)) {
                this.short_code = RegExp.$1
                this.code = RegExp.$2
                this.submitStep2()
            }
        },
        methods: {
            // Submits data to the API, validates and gets verification code
            submitStep1() {
                this.$root.$emit('clearFormValidation', $('#step1 form'))

                axios.post('/api/auth/signup/init', {
                    email: this.email,
                    name: this.name
                }).then(response => {
                    $('#step1').addClass('d-none')
                    $('#step2').removeClass('d-none').find('input').first().focus()
                    this.code = response.data.code
                })
            },
            // Submits the code to the API for verification
            submitStep2() {
                this.$root.$emit('clearFormValidation', $('#step2 form'))

                axios.post('/api/auth/signup/verify', {
                    email: this.email,
                    name: this.name,
                    code: this.code,
                    short_code: this.short_code
                }).then(response => {
                    $('#step1,#step2').addClass('d-none')
                    $('#step3').removeClass('d-none').find('input').first().focus()

                    $('#signup_domain > option').first().prop('selected', true)

                    // Reset user name/email, we don't have them if user used a verification link
                    this.name = response.data.name
                    this.email = response.data.email
                })
            },
            // Submits the data to the API to create the user account
            submitStep3() {
                this.$root.$emit('clearFormValidation', $('#step3 form'))

                axios.post('/api/auth/signup', {
                    code: this.code,
                    short_code: this.short_code,
                    email: this.email,
                    // FIXME: For some reason if I use v-model for login and domain fields
                    //        whenever user enters something in login input the domain field
                    //        is reset to an empty value. We'll use jQuery for now
                    login: $('#signup_login').val(),
                    domain: $('#signup_domain').val(),
                    password: this.password,
                    password_confirmation: this.password_confirmation
                }).then(response => {
                    $('#step2').addClass('d-none')
                    $('#step3').removeClass('d-none').find('input').first().focus()

                    // auto-login and goto dashboard
                    store.commit('loginUser')
                    localStorage.setItem('token', response.data.access_token)
                    this.$router.push({name: 'dashboard'})
                })
            },
            // Moves the user a step back in registration form
            stepBack(e) {
                var card = $(e.target).closest('.card')

                card.prev().removeClass('d-none')
                card.addClass('d-none').find('form')[0].reset()
            }
        }
    }
</script>
