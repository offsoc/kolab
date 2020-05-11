<template>
    <div class="container" dusk="meet-component">
        <div id="meet-room"></div>
    </div>
</template>

<script>
    import Meet from '../../js/meet/app.js'

    export default {
        data() {
            return {
            }
        },
        mounted() {
            this.room = this.$route.path.replace(/^\//, '')

            this.joinSession()
        },
        methods: {
            joinSession() {
                axios.get('/api/v4/meet/openvidu/' + this.room)
                    .then(response => {
                        // Response data contains: sessionName, user, tokens
                        this.startSession(response.data)
                    })
                    .catch(this.$root.errorHandler)
            },
            startSession(data) {
                this.meet = new Meet('#meet-room');
                this.meet.joinRoom(data)
            }
        }
    }
</script>
