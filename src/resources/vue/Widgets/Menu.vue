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
                    <li class="nav-item" v-for="item in menu" :key="item.index">
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
                    <li class="nav-item" v-if="!loggedIn && $root.isUser">
                        <router-link class="nav-link link-signup" active-class="active" :to="{name: 'signup'}">{{ $t('menu.signup') }}</router-link>
                    </li>
                    <li class="nav-item" v-if="loggedIn">
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
                        <div class="dropdown-menu dropdown-menu-right mb-2">
                            <a class="dropdown-item" href="#" v-for="lang in languages" :key="lang" @click="setLang(lang)">
                                {{ lang.toUpperCase() }} - {{ $t('lang.' + lang) }}
                            </a>
                        </div>
                    </li>
                </ul>
                <div v-if="mode == 'footer'" class="footer">
                    <div id="footer-copyright">@ Apheleia IT AG, {{ buildYear }}</div>
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
                languages: window.config['languages'] || [],
                menuList: []
            }
        },
        computed: {
            loggedIn() { return this.$store.state.isLoggedIn },
            menu() { return this.menuList.filter(item => !item.footer || this.mode == 'footer') },
            route() { return this.$route.name }
        },
        mounted() {
            this.menuList = this.loadMenu()

            // On mobile close the menu when the menu item is clicked
            if (this.mode == 'header') {
                $('#header-menu .navbar').on('click', function() { $(this).removeClass('show') })
            }
        },
        methods: {
            loadMenu() {
                let menu = []
                const lang = this.getLang()
                const loggedIn = this.loggedIn

                window.config.menu.forEach(item => {
                    item.title = item['title-' + lang] || item['title-en'] || item.title

                    if (!item.location || !item.title) {
                        console.error("Invalid menu entry", item)
                        return
                    }

                    // TODO: Different menu for different loggedIn state

                    if (item.location.match(/^https?:/)) {
                        item.href = item.location
                    } else {
                        item.to = { path: item.location }
                    }

                    item.exact = item.location == '/'
                    item.index = item.page || item.title.toLowerCase().replace(/\s+/g, '')

                    menu.push(item)
                })

                return menu
            },
            getLang() {
                return getLang()
            },
            setLang(language) {
                setLang(language)
                this.menuList = this.loadMenu()
            }
        }
    }
</script>
