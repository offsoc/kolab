<template>
    <div class="container">
        <div class="card" id="step1">
            <div class="card-body">
                <h4 class="card-title">Password Reset - Step 1/3</h4>
                <p class="card-text">
                    Enter your email address to reset your password. You may need to check your spam folder or unblock noreply@kolabnow.com.
                </p>
                <form @submit.prevent="submitStep1" data-validation-prefix="reset_">
                    <div class="form-group">
                        <label for="reset_email" class="sr-only">Email Address</label>
                        <input type="text" class="form-control" id="reset_email" placeholder="Email Address" required v-model="email">
                    </div>
                    <button class="btn btn-primary" type="submit">Continue</button>
                </form>
            </div>
        </div>

        <div class="card d-none" id="step2">
            <div class="card-body">
                <h4 class="card-title">Password Reset - Step 2/3</h4>
                <p class="card-text">
                    We sent out a confirmation code to your external email address.
                    Enter the code we sent you, or click the link in the message.
                </p>
                <form @submit.prevent="submitStep2" data-validation-prefix="reset_">
                    <div class="form-group">
                        <label for="reset_short_code" class="sr-only">Confirmation Code</label>
                        <input type="text" class="form-control" id="reset_short_code" placeholder="Confirmation Code" required v-model="short_code">
                    </div>
                    <button class="btn btn-secondary" type="button" @click="stepBack">Back</button>
                    <button class="btn btn-primary" type="submit">Continue</button>
                    <input type="hidden" id="reset_code" v-model="code" />
                </form>
            </div>
        </div>

        <div class="card d-none" id="step3">
            <div class="card-body">
                <h4 class="card-title">Password Reset - Step 3/3</h4>
                <p class="card-text">
                </p>
                <form @submit.prevent="submitStep3" data-validation-prefix="reset_">
                    <div class="form-group">
                        <label for="reset_password" class="sr-only">Password</label>
                        <input type="password" class="form-control" id="reset_password" placeholder="Password" required v-model="password">
                    </div>
                    <div class="form-group">
                        <label for="reset_confirm" class="sr-only">Confirm Password</label>
                        <input type="password" class="form-control" id="reset_confirm" placeholder="Confirm Password" required v-model="password_confirmation">
                    </div>
                    <button class="btn btn-secondary" type="button" @click="stepBack">Back</button>
                    <button class="btn btn-primary" type="submit">Submit</button>
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
                code: '',
                short_code: '',
                password: '',
                password_confirmation: ''
            }
        },
        created() {
            // Verification code provided, auto-submit Step 2
            if (this.$route.params.code) {
                if (/^([A-Z0-9]+)-([a-zA-Z0-9]+)$/.test(this.$route.params.code)) {
                    this.short_code = RegExp.$1
                    this.code = RegExp.$2
                    this.submitStep2(true)
                }
                else {
                    this.$root.errorPage(404)
                }
            }
        },
        mounted() {
            // Focus the first input (autofocus does not work when using the menu/router)
            this.displayForm(1, true)
        },
        methods: {
            // Submits data to the API, validates and gets verification code
            submitStep1() {
                this.$root.clearFormValidation($('#step1 form'))

                axios.post('/api/auth/password-reset/init', {
                    email: this.email
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

                axios.post('/api/auth/password-reset/verify', {
                    code: this.code,
                    short_code: this.short_code
                }).then(response => {
                    this.displayForm(3, true)
                }).catch(error => {
                    if (bylink === true) {
                        // FIXME: display step 1, user can do nothing about it anyway
                        //        Maybe we should display 404 error page?
                        this.displayForm(1, true)
                    }
                })
            },
            // Submits the data to the API to reset the password
            submitStep3() {
                this.$root.clearFormValidation($('#step3 form'))

                axios.post('/api/auth/password-reset', {
                    code: this.code,
                    short_code: this.short_code,
                    password: this.password,
                    password_confirmation: this.password_confirmation
                }).then(response => {
                    // auto-login and goto dashboard
                    this.$root.loginUser(response.data.access_token)
                })
            },
            // Moves the user a step back in registration form
            stepBack(e) {
                var card = $(e.target).closest('.card')

                card.prev().removeClass('d-none').find('input').first().focus()
                card.addClass('d-none').find('form')[0].reset()
            },
            displayForm(step, focus) {
                [1, 2, 3].filter(value => value != step).forEach(value => {
                    $('#step' + value).addClass('d-none')
                })

                $('#step' + step).removeClass('d-none')

                if (focus) {
                    $('#step' + step).find('input').first().focus()
                }
            }
        }
    }
</script>
