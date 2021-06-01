<template>
    <div class="container d-flex flex-column align-items-center justify-content-center">
        <div id="logon-form" class="card col-sm-8 col-lg-6">
            <div class="card-body">
                <h1 class="card-title text-center mb-3">{{ $t('login.header') }}</h1>
                <div class="card-text">
                    <form class="form-signin" @submit.prevent="submitLogin">
                        <div class="form-group">
                            <label for="inputEmail" class="sr-only">{{ $t('form.email') }}</label>
                            <div class="input-group">
                                <span class="input-group-prepend">
                                    <span class="input-group-text"><svg-icon icon="user"></svg-icon></span>
                                </span>
                                <input type="email" id="inputEmail" class="form-control" :placeholder="$t('form.email')" required autofocus v-model="email">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="inputPassword" class="sr-only">{{ $t('form.password') }}</label>
                            <div class="input-group">
                                <span class="input-group-prepend">
                                    <span class="input-group-text"><svg-icon icon="lock"></svg-icon></span>
                                </span>
                                <input type="password" id="inputPassword" class="form-control" :placeholder="$t('form.password')" required v-model="password">
                            </div>
                        </div>
                        <div class="form-group pt-3" v-if="!$root.isAdmin">
                            <label for="secondfactor" class="sr-only">{{ $t('login.2fa') }}</label>
                            <div class="input-group">
                                <span class="input-group-prepend">
                                    <span class="input-group-text"><svg-icon icon="key"></svg-icon></span>
                                </span>
                                <input type="text" id="secondfactor" class="form-control rounded-right" :placeholder="$t('login.2fa')" v-model="secondFactor">
                            </div>
                            <small class="form-text text-muted">{{ $t('login.2fa_desc') }}</small>
                        </div>
                        <div class="text-center">
                            <button class="btn btn-primary" type="submit">
                                <svg-icon icon="sign-in-alt"></svg-icon> {{ $t('login.sign_in') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div id="logon-form-footer" class="mt-1">
            <router-link v-if="!$root.isAdmin && $root.hasRoute('password-reset')" :to="{ name: 'password-reset' }" id="forgot-password">{{ $t('login.forgot_password') }}</router-link>
            <a v-if="webmailURL && !$root.isAdmin" :href="webmailURL" id="webmail">{{ $t('login.webmail') }}</a>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            dashboard: { type: Boolean, default: true }
        },
        data() {
            return {
                email: '',
                password: '',
                secondFactor: '',
                webmailURL: window.config['app.webmail_url']
            }
        },
        methods: {
            submitLogin() {
                this.$root.clearFormValidation($('form.form-signin'))

                axios.post('/api/auth/login', {
                    email: this.email,
                    password: this.password,
                    secondfactor: this.secondFactor
                }).then(response => {
                    // login user and redirect to dashboard
                    this.$root.loginUser(response.data, this.dashboard)
                    this.$emit('success')
                })
            }
        }
    }
</script>
