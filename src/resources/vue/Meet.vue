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
            this.webComponent = this.$el.querySelector('openvidu-webcomponent')
            this.loadUI(() => {
                this.loadOpenvidu()
                this.joinSession()
            })
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
                this.webComponent.setAttribute('openvidu-server-url', 'todo')
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
                axios.get('/api/v4/meet/openvidu', {room: this.$route.params.room})
                    .then(response => {
                        // Response data contains: sessionName, user, tokens
                        this.webComponent.sessionConfig = response.data
                    })
            }
        }
    }
</script>
