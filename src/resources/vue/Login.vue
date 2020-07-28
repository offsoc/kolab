<template>
    <div class="container d-flex flex-column align-items-center justify-content-center">
        <div class="card col-sm-8 col-lg-6">
            <div class="card-body">
                <h1 class="card-title text-center mb-3">Please sign in</h1>
                <div class="card-text">
                    <form class="form-signin" @submit.prevent="submitLogin">
                        <div class="form-group">
                            <label for="inputEmail" class="sr-only">Email address</label>
                            <div class="input-group">
                                <span class="input-group-prepend">
                                    <span class="input-group-text"><svg-icon icon="user"></svg-icon></span>
                                </span>
                                <input type="email" id="inputEmail" class="form-control" placeholder="Email address" required autofocus v-model="email">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="inputPassword" class="sr-only">Password</label>
                            <div class="input-group">
                                <span class="input-group-prepend">
                                    <span class="input-group-text"><svg-icon icon="lock"></svg-icon></span>
                                </span>
                                <input type="password" id="inputPassword" class="form-control" placeholder="Password" required v-model="password">
                            </div>
                        </div>
                        <div class="form-group pt-3" v-if="!$root.isAdmin">
                            <label for="secondfactor" class="sr-only">2FA</label>
                            <div class="input-group">
                                <span class="input-group-prepend">
                                    <span class="input-group-text"><svg-icon icon="key"></svg-icon></span>
                                </span>
                                <input type="text" id="secondfactor" class="form-control rounded-right" placeholder="Second factor code" v-model="secondFactor">
                            </div>
                            <small class="form-text text-muted">Second factor code is optional for users with no 2-Factor Authentication setup.</small>
                        </div>
                        <div class="text-center">
                            <button class="btn btn-primary" type="submit">
                                <svg-icon icon="sign-in-alt"></svg-icon> Sign in
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="mt-1">
            <router-link v-if="!$root.isAdmin && $root.hasRoute('password-reset')" :to="{ name: 'password-reset' }" id="forgot-password">Forgot password?</router-link>
            <a v-if="!$root.isAdmin && !$root.hasRoute('password-reset')" :href="app_url + '/password-reset'" id="forgot-password">Forgot password?</a>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                app_url: window.config['app.url'],
                email: '',
                password: '',
                secondFactor: ''
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
                    this.$root.loginUser(response.data)
                })
            }
        }
    }
</script>
