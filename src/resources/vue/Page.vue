<template>
    <div class="page-content container" @click="clickHandler" v-html="content"></div>
</template>

<script>
    export default {
        data() {
            return {
                content: ''
            }
        },
        mounted() {
            let page = this.$root.pageName()

            // Redirect / to /dashboard, if root page is not defined
            if (page == '404' && this.$route.path == '/') {
                this.$router.push({ name: 'dashboard' })
                return
            }

            this.$root.startLoading()

            axios.get('/content/page/' + page, { ignoreErrors: true })
                .then(response => {
                    this.$root.stopLoading()
                    this.content = response.data
                })
                .catch(this.$root.errorHandler)
        },
        methods: {
            clickHandler(event) {
                // ensure we use the link, in case the click has been received by a subelement
                let target = event.target

                while (target && target.tagName !== 'A') {
                    target = target.parentNode
                }

                // handle only links that do not reference external resources
                if (target && target.href && !target.getAttribute('href').match(/:\/\//)) {
                    const { altKey, ctrlKey, metaKey, shiftKey, button, defaultPrevented } = event

                    if (
                        // don't handle with control keys
                        metaKey || altKey || ctrlKey || shiftKey
                        // don't handle when preventDefault called
                        || defaultPrevented
                        // don't handle right clicks
                        || (button !== undefined && button !== 0)
                        // don't handle if `target="_blank"`
                        || /_blank/i.test(target.getAttribute('target'))
                    ) {
                        return
                    }

                    // don't handle same page links/anchors
                    const url = new URL(target.href)
                    const to = url.pathname

                    if (to == '/support/contact') {
                        event.preventDefault()
                        this.$root.supportDialog(this.$el)
                        return
                    }

                    if (window.location.pathname !== to) {
                        event.preventDefault()
                        this.$router.push(to)
                    }
                }
            }
        }
    }
</script>
