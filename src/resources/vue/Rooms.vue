<template>
    <div class="container" dusk="rooms-component">
        <div id="meet-rooms" class="card">
            <div class="card-body">
                <div class="card-title">Video chat</div>
                <div class="card-text">
                    <p>Short description of the functionality.</p>
                    <a v-if="href" :href="href">{{ href }}</a>
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
