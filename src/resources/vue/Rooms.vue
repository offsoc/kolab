<template>
    <div class="container" dusk="rooms-component">
        <div id="meet-rooms" class="card">
            <div class="card-body">
                <div class="card-title">{{ $t('meet.title') }} <small><sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup></small></div>
                <div class="card-text">
                    <p>{{ $t('meet.welcome') }}</p>
                    <p>{{ $t('meet.url') }}</p>
                    <p><router-link v-if="href" :to="roomRoute">{{ href }}</router-link></p>
                    <p>{{ $t('meet.notice') }}</p>
                    <dl>
                        <dt>{{ $t('meet.sharing') }}</dt>
                        <dd>{{ $t('meet.sharing-text') }}</dd>
                        <dt>{{ $t('meet.security') }}</dt>
                        <dd>{{ $t('meet.security-text') }}</dd>
                        <dt>{{ $t('meet.qa') }}</dt>
                        <dd>{{ $t('meet.qa-text') }}</dd>
                        <dt>{{ $t('meet.moderation') }}</dt>
                        <dd>{{ $t('meet.moderation-text') }}</dd>
                        <dt>{{ $t('meet.eject') }}</dt>
                        <dd>{{ $t('meet.eject-text') }}</dd>
                        <dt>{{ $t('meet.silent') }}</dt>
                        <dd>{{ $t('meet.silent-text') }}</dd>
                        <dt>{{ $t('meet.interpreters') }}</dt>
                        <dd>{{ $t('meet.interpreters-text') }}</dd>
                    </dl>
                    <p>{{ $t('meet.beta-notice') }}</p>
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
                href: '',
                roomRoute: ''
            }
        },
        mounted() {
            if (!this.$root.hasSKU('meet')) {
                this.$root.errorPage(403)
                return
            }

            this.$root.startLoading()

            axios.get('/api/v4/openvidu/rooms')
                .then(response => {
                    this.$root.stopLoading()

                    this.rooms = response.data.list
                    if (response.data.count) {
                        this.roomRoute = '/meet/' + encodeURI(this.rooms[0].name)
                        this.href = window.config['app.url'] + this.roomRoute
                    }
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
