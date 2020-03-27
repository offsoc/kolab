<template>
    <div class="container d-flex flex-column align-items-center">
        <div class="card col-sm-8">
            <div class="card-body">
                <h1 class="card-title text-center">Please sign in</h1>
                <div class="card-text">
                    <form class="form-signin" @submit.prevent="submitLogin">
                        <div class="form-group">
                            <label for="inputEmail" class="sr-only">Email address</label>
                            <input type="email" id="inputEmail" class="form-control" placeholder="Email address" required autofocus v-model="email">
                        </div>
                        <div class="form-group">
                            <label for="inputPassword" class="sr-only">Password</label>
                            <input type="password" id="inputPassword" class="form-control" placeholder="Password" required v-model="password">
                        </div>
                        <div class="form-group">
                            <label for="secondfactor" class="sr-only">2FA</label>
                            <input type="text" id="secondfactor" class="form-control" placeholder="Second factor code" v-model="secondFactor">
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
            <router-link :to="{ name: 'password-reset' }">Forgot password?</router-link>
        </div>
    </div>
</template>


<script>
    export default {
        data() {
            return {
                email: '',
                password: '',
                secondFactor: '',
                loginError: false
            }
        },
        methods: {
            submitLogin() {
                this.loginError = false
                this.$root.clearFormValidation($('form.form-signin'))

                axios.post('/api/auth/login', {
                    email: this.email,
                    password: this.password,
                    secondfactor: this.secondFactor
                }).then(response => {
                    // login user and redirect to dashboard
                    this.$root.loginUser(response.data.access_token)
                }).catch(error => {
                    this.loginError = true
                });
            }
        }
    }
</script>
