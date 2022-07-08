<template>
    <div class="container">
        <div id="room-info" class="card">
            <div class="card-body">
                <div class="card-title" v-if="room.id">
                    {{ $t('room.title', { name: room.name }) }}
                    <btn v-if="room.canDelete" class="btn-outline-danger button-delete float-end" @click="roomDelete" icon="trash-can">{{ $t('room.delete') }}</btn>
                </div>
                <div class="card-title" v-else>{{ $t('room.new') }}</div>
                <div class="card-text">
                    <div id="room-intro" class="pt-2">
                        <p v-if="room.id">{{ $t('room.url') }}</p>
                        <p v-if="room.id" class="text-center"><router-link :to="roomRoute">{{ href }}</router-link></p>
                        <p v-if="!room.id">{{ $t('room.new-hint') }}</p>
                    </div>
                    <tabs class="mt-3" :tabs="tabs"></tabs>
                    <div class="tab-content">
                        <div v-if="!room.id || room.isOwner" class="tab-pane show active" id="general" role="tabpanel" aria-labelledby="tab-general">
                            <form @submit.prevent="submit" class="card-body">
                                <div class="row mb-3">
                                    <label for="description" class="col-sm-4 col-form-label">{{ $t('form.description') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="description" v-model="room.description">
                                        <small class="form-text">{{ $t('room.description-hint') }}</small>
                                    </div>
                                </div>
                                <div v-if="room_id === 'new' || room.isOwner" id="room-skus" class="row mb-3">
                                    <label class="col-sm-4 col-form-label">{{ $t('form.subscriptions') }}</label>
                                    <subscription-select class="col-sm-8 pt-sm-1" ref="skus" :object="room" type="room"></subscription-select>
                                </div>
                                <btn class="btn-primary" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
                            </form>
                        </div>
                        <div v-if="room.canUpdate" :class="'tab-pane' + (!tabs.includes('form.general') ? ' show active' : '')" id="settings" role="tabpanel" aria-labelledby="tab-settings">
                            <form @submit.prevent="submitSettings" class="card-body">
                                <div class="row mb-3">
                                    <label for="password" class="col-sm-4 col-form-label">{{ $t('form.password') }}</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" v-model="room.config.password">
                                        <span class="form-text">{{ $t('meet.password-text') }}</span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="room-lock-input" class="col-sm-4 col-form-label">{{ $t('meet.lock') }}</label>
                                    <div class="col-sm-8">
                                        <input type="checkbox" id="room-lock-input" class="form-check-input d-block" v-model="room.config.locked">
                                        <small class="form-text">{{ $t('meet.lock-text') }}</small>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="room-nomedia-input" class="col-sm-4 col-form-label">{{ $t('meet.nomedia') }}</label>
                                    <div class="col-sm-8">
                                        <input type="checkbox" id="room-nomedia-input" class="form-check-input d-block" v-model="room.config.nomedia">
                                        <small class="form-text">{{ $t('meet.nomedia-text') }}</small>
                                    </div>
                                </div>
                                <div v-if="room.canShare" class="row mb-3">
                                    <label for="acl-input" class="col-sm-4 col-form-label">{{ $t('room.moderators') }}</label>
                                    <div class="col-sm-8">
                                        <acl-input id="acl" v-model="room.config.acl" :list="room.config.acl" :useronly="true" :types="['full']"></acl-input>
                                        <small class="form-text">{{ $t('room.moderators-text') }}</small>
                                    </div>
                                </div>
                                <btn class="btn-primary" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import AclInput from '../Widgets/AclInput'
    import SubscriptionSelect from '../Widgets/SubscriptionSelect'

    export default {
        components: {
            AclInput,
            SubscriptionSelect
        },
        data() {
            return {
                href: '',
                room_id: '',
                room: { config: { acl: [] } },
                roomRoute: ''
            }
        },
        computed: {
            tabs() {
                let tabs = []

                if (!this.room.id || this.room.isOwner) {
                    tabs.push('form.general')
                }

                if (this.room.canUpdate) {
                    tabs.push('form.settings')
                }

                return tabs
            },
        },
        created() {
            this.room_id = this.$route.params.room

            if (this.room_id != 'new') {
                axios.get('/api/v4/rooms/' + this.room_id, { loader: true })
                    .then(response => {
                        this.room = response.data
                        this.roomRoute = '/meet/' + encodeURI(this.room.name)
                        this.href = window.config['app.url'] + this.roomRoute
                    })
                    .catch(this.$root.errorHandler)
            }
        },
        mounted() {
            $('#description').focus()
        },
        methods: {
            roomDelete() {
                axios.delete('/api/v4/rooms/' + this.room.id)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.$router.push('/rooms')
                        }
                    })
            },
            submit() {
                this.$root.clearFormValidation($('#general form'))

                let method = 'post'
                let location = '/api/v4/rooms'
                let post = this.$root.pick(this.room, ['description'])

                if (this.room.id) {
                    method = 'put'
                    location += '/' + this.room.id
                }

                if (this.$refs.skus) {
                    post.skus = this.$refs.skus.getSkus()
                }

                axios[method](location, post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                        this.$router.push('/rooms')
                    })
            },
            submitSettings() {
                this.$root.clearFormValidation($('#settings form'))

                const post = this.$root.pick(this.room.config, [ 'password', 'acl', 'locked', 'nomedia' ])

                axios.post('/api/v4/rooms/' + this.room.id + '/config', post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                    })
            }
        }
    }
</script>
