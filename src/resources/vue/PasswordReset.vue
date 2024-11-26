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
                    <btn class="btn-primary" type="submit" icon="check">{{ $t('btn.continue') }}</btn>
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
                    <btn class="btn-secondary" @click="stepBack">{{ $t('btn.back') }}</btn>
                    <btn class="btn-primary ms-2" type="submit" icon="check">{{ $t('btn.continue') }}</btn>
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
                    <password-input class="mb-3" v-model="pass" :user="userId" v-if="userId" :focus="true"></password-input>
                    <div class="form-group pt-1 mb-3">
                        <label for="secondfactor" class="visually-hidden">{{ $t('login.2fa') }}</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <svg-icon icon="key"></svg-icon>
                            </span>
                            <input type="text" id="secondfactor" class="form-control rounded-end" :placeholder="$t('login.2fa')" v-model="secondfactor">
                        </div>
                        <small class="form-text text-muted">{{ $t('login.2fa_desc') }}</small>
                    </div>
                    <btn class="btn-secondary" @click="stepBack">{{ $t('btn.back') }}</btn>
                    <btn class="btn-primary ms-2" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
    import PasswordInput from './Widgets/PasswordInput'

    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faKey').definition,
    )

    export default {
        components: {
            PasswordInput
        },
        data() {
            return {
                email: '',
                code: '',
                short_code: '',
                pass: {},
                secondfactor: '',
                userId: null,
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
                } else {
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
                let post = {
                    code: this.code,
                    short_code: this.short_code
                }

                let params = {}

                if (bylink === true) {
                    params.ignoreErrors = true
                    params.loader = true
                }

                this.$root.clearFormValidation($('#step2 form'))

                axios.post('/api/auth/password-reset/verify', post, params).then(response => {
                    this.userId = response.data.userId
                    this.displayForm(3, true)
                }).catch(error => {
                    if (bylink === true) {
                        this.$root.errorPage(404, '', this.$t('password.link-invalid'))
                    }
                })
            },
            // Submits the data to the API to reset the password
            submitStep3() {
                this.$root.clearFormValidation($('#step3 form'))

                const post = {
                    ...this.$root.pick(this, ['code', 'short_code', 'secondfactor']),
                    ...this.pass
                }

                axios.post('/api/auth/password-reset', post)
                    .then(response => {
                        // auto-login and goto dashboard
                        this.$root.loginUser(response.data)
                    })
            },
            // Moves the user a step back in registration form
            stepBack(e) {
                var card = $(e.target).closest('.card')

                card.prev().removeClass('d-none').find('input').first().focus()
                card.addClass('d-none').find('form')[0].reset()

                this.userId = null
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
