<template>
    <nav :id="mode + '-menu'" class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <router-link class="navbar-brand" to="/">
                <img :src="appUrl + themeDir + '/images/logo_' + mode + '.png'" :alt="appName">
            </router-link>
            <button v-if="mode == 'header'" class="navbar-toggler" type="button"
                    data-toggle="collapse" :data-target="'#' + mode + '-menu-navbar'"
                    aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <div :id="mode + '-menu-navbar'" :class="'navbar' + (mode == 'header' ? ' collapse navbar-collapse' : '')">
                <ul class="navbar-nav">
                    <li class="nav-item" v-for="item in menu()" :key="item.index">
                        <a v-if="item.href" :class="'nav-link link-' + item.index" :href="item.href">{{ item.title }}</a>
                        <router-link v-if="item.to"
                                     :class="'nav-link link-' + item.index"
                                     active-class="active"
                                     :to="item.to"
                                     :exact="item.exact"
                        >
                            {{ item.title }}
                        </router-link>
                    </li>
                    <li class="nav-item" v-if="!loggedIn && !$root.isAdmin">
                        <router-link class="nav-link link-signup" active-class="active" :to="{name: 'signup'}">Signup</router-link>
                    </li>
                    <li class="nav-item" v-if="loggedIn">
                        <router-link class="nav-link link-dashboard" active-class="active" :to="{name: 'dashboard'}">Cockpit</router-link>
                    </li>
                    <li class="nav-item" v-if="loggedIn">
                        <router-link class="nav-link menulogin link-logout" active-class="active" :to="{name: 'logout'}">Logout</router-link>
                    </li>
                    <li class="nav-item" v-if="!loggedIn">
                        <router-link class="nav-link menulogin link-login" :to="{name: 'login'}">Login</router-link>
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
                appName: window.config['app.name'],
                appUrl: window.config['app.url'],
                themeDir: '/themes/' + window.config['app.theme']
            }
        },
        computed: {
            loggedIn() { return this.$store.state.isLoggedIn },
            route() { return this.$route.name }
        },
        mounted() {
            // On mobile close the menu when the menu item is clicked
            if (this.mode == 'header') {
                $('#header-menu .navbar').on('click', function() { $(this).removeClass('show') })
            }
        },
        methods: {
            menu() {
                let menu = []
                const loggedIn = this.loggedIn

                window.config.menu.forEach(item => {
                    if (!item.location || !item.title) {
                        console.error("Invalid menu entry", item)
                        return
                    }

                    // TODO: Different menu for different loggedIn state

                    if (window.isAdmin && !item.admin) {
                        return
                    } else if (!window.isAdmin && item.admin === 'only') {
                        return
                    }

                    if (!item.footer || this.mode == 'footer') {
                        if (item.location.match(/^https?:/)) {
                            item.href = item.location
                        } else {
                            item.to = { path: item.location }
                        }

                        item.exact = item.location == '/'
                        item.index = item.page || item.title.toLowerCase().replace(/\s+/g, '')

                        menu.push(item)
                    }
                })

                return menu
            }
        }
    }
</script>
