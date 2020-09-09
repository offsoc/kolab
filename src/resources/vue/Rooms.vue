<template>
    <div class="container" dusk="rooms-component">
        <div id="meet-rooms" class="card">
            <div class="card-body">
                <div class="card-title">Video chat <small><sup class="badge badge-primary">beta</sup></small></div>
                <div class="card-text">
                    <p>We are adding a much requested feature: Video Chat.
                        The basics are working, but it is not a polished product yet.
                    </p>
                    <p>You have one personal video chat room that only you know the location of.
                        It is still in beta, so you can not block people from entering once they know the location
                        and you can not throw them out, once they are in.
                        This functionality will come later. For now, keep that in mind when you share the location
                        of your room.
                    </p>
                    <p>You can access your room and invite people by sharing this link:</p>
                    <a v-if="href" :href="href">{{ href }}</a>
                    <p></p>
                    <p>Keep in mind that this is still in beta and might come with some issues.
                        Should you encounter any on your way, let us know by contacting support.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                rooms: [],
                href: ''
            }
        },
        mounted() {
            this.$root.startLoading()

            axios.get('/api/v4/openvidu/rooms')
                .then(response => {
                    this.$root.stopLoading()

                    this.rooms = response.data.list
                    if (response.data.count) {
                        this.href = window.config['app.url'] + '/meet/' + encodeURI(this.rooms[0].name)
                    }
                })
                .catch(this.$root.errorHandler)
        },
        methods: {
        }
    }
</script>
