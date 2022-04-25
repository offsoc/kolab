<template>
    <div class="container d-flex flex-column align-items-center justify-content-center">
        <div id="logon-form" class="card col-sm-8 col-lg-6">
            <div class="card-body p-4">
                <h1 class="card-title text-center mb-3">{{ $t('login.header') }}</h1>
                <div class="card-text m-2 mb-0">
                    <form class="form-signin" @submit.prevent="submitLogin">
                        <div class="row mb-3">
                            <label for="inputEmail" class="visually-hidden">{{ $t('form.email') }}</label>
                            <div class="input-group">
                                <span class="input-group-text"><svg-icon icon="user"></svg-icon></span>
                                <input type="email" id="inputEmail" class="form-control" :placeholder="$t('form.email')" required autofocus v-model="email">
                            </div>
                        </div>
                        <div class="row mb-4">
                            <label for="inputPassword" class="visually-hidden">{{ $t('form.password') }}</label>
                            <div class="input-group">
                                <span class="input-group-text"><svg-icon icon="lock"></svg-icon></span>
                                <input type="password" id="inputPassword" class="form-control" :placeholder="$t('form.password')" required v-model="password">
                            </div>
                        </div>
                        <div class="row mb-3" v-if="$root.isUser">
                            <label for="secondfactor" class="visually-hidden">{{ $t('login.2fa') }}</label>
                            <div class="input-group">
                                <span class="input-group-text"><svg-icon icon="key"></svg-icon></span>
                                <input type="text" id="secondfactor" class="form-control rounded-end" :placeholder="$t('login.2fa')" v-model="secondfactor">
                            </div>
                            <small class="text-muted mt-2">{{ $t('login.2fa_desc') }}</small>
                        </div>
                        <div class="text-center">
                            <btn class="btn-primary" type="submit" icon="sign-in-alt">{{ $t('login.sign_in') }}</btn>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div id="logon-form-footer" class="mt-1">
            <router-link v-if="$root.isUser && $root.hasRoute('password-reset')" :to="{ name: 'password-reset' }" id="forgot-password">{{ $t('login.forgot_password') }}</router-link>
            <a v-if="webmailURL && $root.isUser" :href="webmailURL" id="webmail">{{ $t('login.webmail') }}</a>
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
                secondfactor: '',
                webmailURL: window.config['app.webmail_url']
            }
        },
        methods: {
            submitLogin() {
                this.$root.clearFormValidation($('form.form-signin'))

                const post = this.$root.pick(this, ['email', 'password', 'secondfactor'])

                axios.post('/api/auth/login', post)
                    .then(response => {
                        // login user and redirect to dashboard
                        this.$root.loginUser(response.data, this.dashboard)
                        this.$emit('success')
                    })
                    .catch(e => {})
            }
        }
    }
</script>
