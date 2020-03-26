<template>
    <div class="text-center form-wrapper">
        <form class="form-signin" @submit.prevent="submitLogin">
            <div v-if="!factors.length" id="login-form">
                <h1 class="h3 mb-3 font-weight-normal">Please sign in</h1>

                <label for="inputEmail" class="sr-only">Email address</label>
                <input type="email" id="inputEmail" class="form-control" placeholder="Email address" required autofocus v-model="email">

                <label for="inputPassword" class="sr-only">Password</label>
                <input type="password" id="inputPassword" class="form-control" placeholder="Password" required v-model="password">
            </div>

            <div v-if="factors.length" id="login-2fa">
                <h1 class="h3 mb-3 font-weight-normal">2-Factor Authentication</h1>

                <div v-for="(factor, index) in factors" :key="item.name">
                    <p v-if="index > 0" class="text-center">or</p>
                    <div class="form-group">
                        <label :for="'factor-' + factor.name">{{ factor.label }}</label>
                        <input type="text" class="form-control" :name="factor.name" :required="factor.required">
                    </div>
                </div>
            </div>

            <button class="btn btn-lg btn-primary btn-block" type="submit">Sign in</button>

            <br><br><router-link :to="{ name: 'password-reset' }">Forgot password?</router-link>
        </form>
    </div>
</template>


<script>
    export default {
        data() {
            return {
                email: '',
                password: '',
                loginError: false,
                factors: []
            }
        },
        methods: {
            submitLogin() {
                this.loginError = false
                axios.post('/api/auth/login', {
                    email: this.email,
                    password: this.password
                }).then(response => {
                    // login user and redirect to dashboard
                    this.$root.loginUser(response.data.access_token)
                }).catch(error => {
                    if (!error.response || !error.response.data || !error.response.data['second-factor']) {
                        this.loginError = true
                        return
                    }

                    this.factors = error.response.data['second-factor']
                });
            }
        }
    }
</script>

<style scoped>
    .form-wrapper {
        position: absolute;
        top: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
    }

    .form-signin {
        width: 100%;
        max-width: 330px;
        padding: 15px;
        margin: 0 auto;
    }

    .form-signin .form-control {
        position: relative;
        box-sizing: border-box;
        height: auto;
        padding: 10px;
        font-size: 16px;
    }

    .form-signin .form-control:focus {
        z-index: 2;
    }

    .form-signin input[type="email"] {
        margin-bottom: -1px;
        border-bottom-right-radius: 0;
        border-bottom-left-radius: 0;
    }

    .form-signin input[type="password"] {
        margin-bottom: 10px;
        border-top-left-radius: 0;
        border-top-right-radius: 0;
    }
</style>
