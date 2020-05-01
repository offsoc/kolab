<template>
    <div class="container" dusk="meet-component" v-html="html">
    </div>
</template>

<script>
    export default {
        data() {
            return {
                html: '<openvidu-webcomponent theme="light"></openvidu-webcomponent>'
            }
        },
        mounted() {
            this.room = this.$route.params.room

            if (!this.room) {
                this.room = this.$store.state.authInfo.email.split('@')[0]
            }

            this.webComponent = this.$el.querySelector('openvidu-webcomponent')

            this.$root.startLoading()

            this.loadUI(() => {
                this.loadOpenvidu()
                this.joinSession()
                this.$root.stopLoading()
            })
        },
        destroyed() {
            this.webComponent.sessionConfig = {}
        },
        methods: {
            loadUI(callback) {
                let script = $('#openvidu-script')

                if (!script.length) {
                    script = document.createElement('script')
                    script.onload = callback

                    script.id = 'openvidu-script'
                    script.src = '/js/openvidu-webcomponent-2.12.0.js'

                    let link = document.createElement('link')
                    link.rel = 'stylesheet'
                    link.href = '/css/openvidu-webcomponent-2.12.0.css'

                    let head = document.getElementsByTagName('head')[0]

                    head.appendChild(script)
                    head.appendChild(link)
                } else {
                    callback()
                }
            },
            loadOpenvidu() {
                this.webComponent.setAttribute('openvidu-server-url', 'https://localhost:4443')

                this.webComponent.addEventListener('sessionCreated', event => {
                    var session = event.detail

                    session.on('connectionCreated', e => {
                        console.log("connectionCreated", e)
                    })

                    session.on('streamDestroyed', e => {
                        console.log("streamDestroyed", e)
                    })

                    session.on('streamCreated', e => {
                        console.log("streamCreated", e)
                    })
                })

                this.webComponent.addEventListener('publisherCreated', event => {
                    console.log("publisherCreated event", event)
                })

                this.webComponent.addEventListener('error', event => {
                    console.log('Error event', event)
                })
            },
            joinSession() {
                axios.get('/api/v4/meet/openvidu/' + this.room)
                    .then(response => {
                        // Response data contains: sessionName, user, tokens
                        this.webComponent.sessionConfig = {
                            sessionName: response.data.session,
                            user: this.$store.state.authInfo.email,
                            tokens: [response.data.token]
                        }
                    })
                    .catch(this.$root.errorHandler)
            }
        }
    }
</script>
