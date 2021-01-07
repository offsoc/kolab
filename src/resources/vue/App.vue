<template>
    <router-view v-if="!isLoading && !routerReloading" :key="key" @hook:mounted="childMounted"></router-view>
</template>

<script>
    export default {
        computed: {
            key() {
                // The 'key' property is used to reload the Page component
                // whenever a route changes. Normally vue does not do that.
                return this.$route.name == '404' ? this.$route.path : 'static'
            }
        },
        data() {
            return {
                isLoading: true,
                routerReloading: false
            }
        },
        mounted() {
            const token = localStorage.getItem('token')

            if (token) {
                this.$root.startLoading()
                axios.defaults.headers.common.Authorization = 'Bearer ' + token

                axios.get('/api/auth/info?refresh_token=1')
                    .then(response => {
                        this.$root.loginUser(response.data, false)
                        this.$root.stopLoading()
                        this.isLoading = false
                    })
                    .catch(error => {
                        // Release lock on the router-view, otherwise links (e.g. Logout) will not work
                        this.isLoading = false
                        this.$root.logoutUser(false)
                        this.$root.errorHandler(error)
                    })
            } else {
                this.isLoading = false
            }
        },
        methods: {
            childMounted() {
                this.$root.updateBodyClass()
                this.getFAQ()
            },
            getFAQ() {
                let page = this.$route.path

                if (page == '/' || page == '/login') {
                    return
                }

                axios.get('/content/faq' + page, { ignoreErrors: true })
                    .then(response => {
                        const result = response.data.faq
                        $('#faq').remove()
                        if (result && result.length) {
                            let faq = $('<div id="faq" class="faq mt-3"><h5>FAQ</h5><ul class="pl-4"></ul></div>')
                            let list = []

                            result.forEach(item => {
                                list.push($('<li>').append($('<a>').attr('href', item.href).text(item.title)))

                                // Handle internal links with the vue-router
                                if (item.href.charAt(0) == '/') {
                                    list[list.length-1].find('a').on('click', event => {
                                        event.preventDefault()
                                        this.$router.push(item.href)
                                    })
                                }
                            })

                            faq.find('ul').append(list)
                            $(this.$el).append(faq)
                        }
                    })
            },
            routerReload() {
                // Together with beforeRouteUpdate even on a route component
                // allows us to force reload the component. So it is possible
                // to jump from/to page that uses currently loaded component.
                this.routerReloading = true
                this.$nextTick().then(() => {
                    this.routerReloading = false
                })
            }
        }
    }
</script>
