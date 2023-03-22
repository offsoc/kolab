<template>
    <nav :id="mode + '-menu'" :class="'navbar navbar-light navbar-expand-' + (mode == 'header' ? 'lg' : 'sm')">
        <div class="container p-0">
            <router-link class="navbar-brand" to="/" v-html="$root.logo(mode)"></router-link>
            <button v-if="mode == 'header'" class="navbar-toggler" type="button"
                    data-bs-toggle="collapse" data-bs-target="#header-menu-navbar"
                    aria-controls="header-menu-navbar" aria-expanded="false" :aria-label="$t('menu.toggle')"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <div :id="mode + '-menu-navbar'" :class="mode == 'header' ? 'collapse navbar-collapse justify-content-end' : ''">
                <ul class="navbar-nav justify-content-end">
                    <li class="nav-item" v-for="item in menu" :key="item.label">
                        <a v-if="item.href" :class="'nav-link link-' + item.label" :href="item.href">{{ menuItemTitle(item) }}</a>
                        <router-link v-if="item.to"
                                     :class="'nav-link link-' + item.label"
                                     active-class="active"
                                     :to="item.to"
                                     :exact="item.exact"
                        >
                            {{ menuItemTitle(item) }}
                        </router-link>
                    </li>
                    <li class="nav-item" v-if="signupEnabled && !loggedIn && $root.isUser && !hasMenuItem('signup')">
                        <router-link class="nav-link link-signup" active-class="active" :to="{name: 'signup'}">{{ $t('menu.signup') }}</router-link>
                    </li>
                    <li class="nav-item" v-if="loggedIn && !hasMenuItem('dashboard')">
                        <router-link class="nav-link link-dashboard" active-class="active" :to="{name: 'dashboard'}">{{ $t('menu.cockpit') }}</router-link>
                    </li>
                    <li class="nav-item" v-if="loggedIn">
                        <router-link class="nav-link menulogin link-logout" active-class="active" :to="{name: 'logout'}">{{ $t('menu.logout') }}</router-link>
                    </li>
                    <li class="nav-item" v-if="!loggedIn">
                        <router-link class="nav-link menulogin link-login" :to="{name: 'login'}">{{ $t('menu.login') }}</router-link>
                    </li>
                    <li v-if="languages.length > 1 && mode == 'header'" id="language-selector" class="nav-item dropdown">
                        <a href="#" class="nav-link link-lang dropdown-toggle" role="button" data-bs-toggle="dropdown">{{ getLang().toUpperCase() }}</a>
                        <div class="dropdown-menu dropdown-menu-end mb-2">
                            <a class="dropdown-item" href="#" v-for="lang in languages" :key="lang" @click="setLang(lang)">
                                {{ lang.toUpperCase() }} - {{ $t('lang.' + lang) }}
                            </a>
                        </div>
                    </li>
                </ul>
                <div v-if="mode == 'footer'" class="footer">
                    <div id="footer-copyright">&copy; {{ copyright }}, {{ buildYear }}</div>
                    <div v-if="footer" id="footer-company">{{ footer }}</div>
                </div>
            </div>
        </div>
    </nav>
</template>

<script>
    import buildDate from '../../build/js/ts'
    import { setLang, getLang } from '../../js/locale'

    export default {
        props: {
            mode: { type: String, default: 'header' },
            footer: { type: String, default: '' }
        },
        data() {
            return {
                buildYear: buildDate.getFullYear(),
                copyright: window.config['app.company.copyright'] || '',
                languages: window.config['languages'] || [],
                signupEnabled: window.config['app.with_signup'] || false,
                menuList: []
            }
        },
        computed: {
            loggedIn() { return !!this.$root.authInfo },
            menu() {
                // Filter menu by its position on the page, and user authentication state
                return this.menuList.filter(item => {
                    return (!item.footer || this.mode == 'footer')
                        && (!('authenticated' in item) || this.loggedIn === item.authenticated)
                })
            },
            route() { return this.$route.name }
        },
        mounted() {
            this.menuList = this.loadMenu()
        },
        methods: {
            loadMenu() {
                let menu = []
                const loggedIn = this.loggedIn

                window.config.menu.forEach(item => {
                    if (!item.location || !item.label) {
                        console.error("Invalid menu entry", item)
                        return
                    }

                    if (item.location.match(/^https?:/)) {
                        item.href = item.location
                    } else {
                        item.to = { path: item.location }
                    }

                    item.exact = item.location == '/'

                    menu.push(item)
                })

                return menu
            },
            hasMenuItem(label) {
                return this.menuList.find(item => item.label == label)
            },
            menuItemTitle(item) {
                const lang = this.getLang()
                return item['title-' + lang] || item['title-en'] || item.title || this.$t('menu.' + item.label)
            },
            getLang() {
                return getLang()
            },
            setLang(language) {
                setLang(language)
            }
        }
    }
</script>
