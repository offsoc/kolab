<template>
    <div class="container">
        <div class="card" id="step1">
            <div class="card-body">
                <h4 class="card-title">{{ $t('password.reset') }} - {{ $t('nav.step', { i: 1, n: 3 }) }}</h4>
                <p class="card-text">
                    {{ $t('password.reset-step1') }}
                    <span v-if="fromEmail">{{ $t('password.reset-step1-hint', { email: fromEmail }) }}</span>
                </p>
                <form @submit.prevent="submitStep1" data-validation-prefix="reset_">
                    <div class="mb-3">
                        <label for="reset_email" class="visually-hidden">{{ $t('form.email') }}</label>
                        <input type="text" class="form-control" id="reset_email" :placeholder="$t('form.email')" required v-model="email">
                    </div>
                    <button class="btn btn-primary" type="submit"><svg-icon icon="check"></svg-icon> {{ $t('btn.continue') }}</button>
                </form>
            </div>
        </div>

        <div class="card d-none" id="step2">
            <div class="card-body">
                <h4 class="card-title">{{ $t('password.reset') }} - {{ $t('nav.step', { i: 2, n: 3 }) }}</h4>
                <p class="card-text">
                    {{ $t('password.reset-step2') }}
                </p>
                <form @submit.prevent="submitStep2" data-validation-prefix="reset_">
                    <div class="mb-3">
                        <label for="reset_short_code" class="visually-hidden">{{ $t('form.code') }}</label>
                        <input type="text" class="form-control" id="reset_short_code" :placeholder="$t('form.code')" required v-model="short_code">
                    </div>
                    <button class="btn btn-secondary" type="button" @click="stepBack">{{ $t('btn.back') }}</button>
                    <button class="btn btn-primary" type="submit"><svg-icon icon="check"></svg-icon> {{ $t('btn.continue') }}</button>
                    <input type="hidden" id="reset_code" v-model="code" />
                </form>
            </div>
        </div>

        <div class="card d-none" id="step3">
            <div class="card-body">
                <h4 class="card-title">{{ $t('password.reset') }} - {{ $t('nav.step', { i: 3, n: 3 }) }}</h4>
                <p class="card-text">
                </p>
                <form @submit.prevent="submitStep3" data-validation-prefix="reset_">
                    <div class="mb-3">
                        <label for="reset_password" class="visually-hidden">{{ $t('form.password') }}</label>
                        <input type="password" class="form-control" id="reset_password" :placeholder="$t('form.password')" required v-model="password">
                    </div>
                    <div class="mb-3">
                        <label for="reset_confirm" class="visually-hidden">{{ $t('form.password-confirm') }}</label>
                        <input type="password" class="form-control" id="reset_confirm" :placeholder="$t('form.password-confirm')" required v-model="password_confirmation">
                    </div>
                    <div class="form-group pt-3">
                        <label for="secondfactor" class="sr-only">2FA</label>
                        <div class="input-group">
                            <span class="input-group-prepend">
                                <span class="input-group-text"><svg-icon icon="key"></svg-icon></span>
                            </span>
                            <input type="text" id="secondfactor" class="form-control rounded-right" placeholder="Second factor code" v-model="secondFactor">
                        </div>
                        <small class="form-text text-muted">Second factor code is optional for users with no 2-Factor Authentication setup.</small>
                    </div>
                    <button class="btn btn-secondary" type="button" @click="stepBack">{{ $t('btn.back') }}</button>
                    <button class="btn btn-primary" type="submit"><svg-icon icon="check"></svg-icon> {{ $t('btn.submit') }}</button>
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
                password_confirmation: '',
                secondFactor: '',
                fromEmail: window.config['mail.from.address']
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
                    password_confirmation: this.password_confirmation,
                    secondfactor: this.secondFactor
                }).then(response => {
                    // auto-login and goto dashboard
                    this.$root.loginUser(response.data)
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
