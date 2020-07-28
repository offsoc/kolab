<template>
    <nav :id="mode + '-menu'" class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <router-link class="navbar-brand" :to="{ name: 'dashboard' }">
                <img :src="'/images/logo_' + mode + '.png'" :alt="app_name">
            </router-link>
            <button v-if="mode == 'header'" class="navbar-toggler" type="button"
                    data-toggle="collapse" :data-target="'#' + mode + '-menu-navbar'"
                    aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <div :id="mode + '-menu-navbar'" :class="'navbar' + (mode == 'header' ? ' collapse navbar-collapse' : '')">
                <ul class="navbar-nav">
                    <li class="nav-item" v-if="!logged_in">
                        <router-link v-if="!$root.isAdmin && $root.hasRoute('signup')" class="nav-link link-signup" active-class="active" :to="{name: 'signup'}">Signup</router-link>
                        <a v-else class="nav-link link-signup" :href="app_url + '/signup'">Signup</a>
                    </li>
                    <li class="nav-item" v-if="!logged_in">
                        <a class="nav-link link-explore" href="https://kolabnow.com">Explore</a>
                    </li>
                    <li class="nav-item" v-if="!logged_in">
                        <a class="nav-link link-blog" href="https://blogs.kolabnow.com">Blog</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link link-support" href="https://kolabnow.com/support">Support</a>
                    </li>
                    <li class="nav-item" v-if="logged_in">
                        <a class="nav-link link-contact" href="https://kolabnow.com/contact">Contact</a>
                    </li>
                    <li class="nav-item" v-if="!logged_in && mode == 'footer'">
                        <a class="nav-link link-tos" href="https://kolabnow.com/tos">ToS</a>
                    </li>
                    <li class="nav-item" v-if="logged_in">
                        <a class="nav-link menulogin link-webmail" href="https://kolabnow.com/apps" target="_blank">Webmail</a>
                    </li>
                    <li class="nav-item" v-if="logged_in">
                        <router-link class="nav-link menulogin link-logout" active-class="active" :to="{name: 'logout'}">Logout</router-link>
                    </li>
                    <li class="nav-item" v-if="!logged_in && route == 'login'">
                        <a class="nav-link menulogin link-webmail" href="https://kolabnow.com/apps" target="_blank">Webmail</a>
                    </li>
                    <li class="nav-item" v-if="!logged_in && (!route || route == 'signup' || $route.path == '/meet')">
                        <router-link class="nav-link menulogin link-login" active-class="active" :to="{name: 'login'}">Login</router-link>
                    </li>
                </ul>
                <div v-if="mode == 'footer'" class="footer">
                    <div id="footer-copyright">@ Apheleia IT AG, 2020</div>
                    <div v-if="footer" id="footer-company">{{ footer }}</div>
                </div>
            </div>
        </div>
    </nav>
</template>

<script>
    export default {
        props: {
            mode: { type: String, default: 'header' },
            footer: { type: String, default: '' }
        },
        data() {
            return {
                app_name: window.config['app.name'],
                app_url: window.config['app.url'],
            }
        },
        computed: {
            logged_in() { return this.$store.state.isLoggedIn },
            route() { return this.$route.name }
        },
        mounted() {
            // On mobile close the menu when the menu item is clicked
            if (this.mode == 'header') {
                $('#header-menu .navbar').on('click', function() { $(this).removeClass('show') })
            }
        }
    }
</script>
