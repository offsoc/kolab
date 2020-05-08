<template>
    <div class="container" dusk="meet-component">
        <div id="openvidu">
            <div id="publisher"></div>
            <div id="subscriber"></div>
        </div>
    </div>
</template>

<script>
    let OV
    let session

    export default {
        data() {
            return {
                html: ''
            }
        },
        mounted() {
            this.room = this.$route.params.room

            if (!this.room) {
                this.room = this.$store.state.authInfo.email.split('@')[0]
            }

            this.$root.startLoading()

            this.loadUI(() => {
//                this.loadOpenvidu()
                this.joinSession()
                this.$root.stopLoading()
            })
        },
        destroyed() {

        },
        methods: {
            loadUI(callback) {
                let script = $('#openvidu-script')

                if (!script.length) {
                    script = document.createElement('script')
                    script.onload = callback

                    script.id = 'openvidu-script'
                    script.src = '/js/openvidu-browser-2.13.0.js'

                    let head = document.getElementsByTagName('head')[0]

                    head.appendChild(script)
                } else {
                    callback()
                }
            },
/*
            loadOpenvidu() {
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
*/
            joinSession() {
                axios.get('/api/v4/meet/openvidu/' + this.room)
                    .then(response => {
                        // Response data contains: sessionName, user, tokens
                        this.startSession(response.data)
                    })
                    .catch(this.$root.errorHandler)
            },
            startSession(data) {
                OV = new OpenVidu()
                OV.setAdvancedConfiguration(
                    {
                        iceServers: [
                            {
                                urls: "stun:kanarip.internet-box.ch:3478"
                            },
                            {
                                urls: [ "turn:kanarip.internet-box.ch:3478?transport=tcp" ],
                                username: "openvidu",
                                credential: "openvidu"
                            }
                        ]
                    }
                )

                session = OV.initSession()

                session.on("streamCreated", function (event) {
                    session.subscribe(event.stream, "subscriber")
                })

                session.connect(data.token)
                    .then(() => {
                        var publisher = OV.initPublisher("publisher");
                        session.publish(publisher);
                    })
                    .catch(error => {
                        console.log("There was an error connecting to the session:", error.code, error.message);
                    })
            }
        }
    }
</script>
