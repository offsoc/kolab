<template>
    <nav id="primary-menu" class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a :href="app_url" class="navbar-brand" :title="app_name"><img src="/images/logo_header.png" :alt="app_name"></a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar" aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div id="navbar" class="collapse navbar-collapse justify-content-end">
                <ul class="navbar-nav">
                    <li class="nav-item" v-if="!logged_in">
                        <router-link class="nav-link" active-class="active" :to="{name: 'signup'}">Signup</router-link>
                    </li>
                    <li class="nav-item" v-if="!logged_in">
                        <a class="nav-link" href="https://kolabnow.com">Explore</a>
                    </li>
                    <li class="nav-item" v-if="!logged_in">
                        <a class="nav-link" href="https://blogs.kolabnow.com">Blog</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://kolabnow.com/support">Support</a>
                    </li>
                    <li class="nav-item" v-if="logged_in">
                        <a class="nav-link" href="https://kolabnow.com/contact">Contact</a>
                    </li>
                    <li class="nav-item" v-if="logged_in">
                        <a class="nav-link menulogin" href="https://kolabnow.com/apps" target="_blank">Webmail</a>
                    </li>
                    <li class="nav-item" v-if="logged_in">
                        <router-link class="nav-link menulogin" active-class="active" :to="{name: 'logout'}">Logout</router-link>
                    </li>
                    <li class="nav-item" v-if="!logged_in && route == 'login'">
                        <a class="nav-link menulogin" href="https://kolabnow.com/apps" target="_blank">Webmail</a>
                    </li>
                    <li class="nav-item" v-if="!logged_in && (!route || route == 'signup')">
                        <router-link class="nav-link menulogin" active-class="active" :to="{name: 'login'}">Login</router-link>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</template>

<script>
    import store from '../js/store'

    export default {
        data() {
            return {
                app_name: window.config['app.name'],
                app_url: window.config['app.url'],
            }
        },
        computed: {
            logged_in() { return store.state.isLoggedIn },
            route() { return this.$route.name }
        }
    }
</script>
