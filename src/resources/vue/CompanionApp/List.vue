<template>
    <div class="container">
        <div class="card" id="companionapp-list">
            <div class="card-body">
                <div class="card-title">
                    {{ $t('companion.title') }}
                    <small><sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup></small>

                    <btn class="btn-success float-end" @click="createDevice($t('companion.new-device'))" icon="mobile-screen">
                        {{ $t('companion.create') }}
                    </btn>
                </div>
                <div class="card-text">
                    <p>
                        {{ $t('companion.description') }}
                    </p>
                    <p v-if="appDownloadLink" v-html="$t('companion.download-description', { href: appDownloadLink})"></p>
                    <p>
                        {{ $t('companion.description-detailed') }}
                    </p>
                    <div class="alert alert-warning">
                        <p>
                            {{ $t('companion.description-warning') }}
                        </p>
                        <div>
                            <btn class="btn-success" @click="createDevice($t('companion.recovery-device'))" icon="mobile-screen">
                                {{ $t('companion.create-recovery-device') }}
                            </btn>
                        </div>
                    </div>
                </div>
                <div class="card-text">
                    <list-widget :list="companions"></list-widget>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    import ListWidget from './ListWidget'
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faMobileScreen').definition,
    )

    export default {
        components: {
            ListWidget
        },
        data() {
            return {
                companions: [],
                appDownloadLink: window.config['app.companion_download_link']
            }
        },
        created() {
            axios.get('/api/v4/companions', { loader: true })
                .then(response => {
                    //TODO show "NOt paired" in device-id field
                    this.companions = response.data.list
                })
                .catch(this.$root.errorHandler)
        },
        methods: {
            createDevice(name) {
                // If we already have a device for this purpose, just show that.
                for (let device of this.companions) {
                    if (device.name == name) {
                        this.$router.push({ name: 'companion' , params: { companion: device.id }})
                        return
                    }
                }

                axios.post('/api/v4/companions', {'name': name})
                    .then(response => {
                        this.$toast.success(response.data.message)
                        this.$router.push({ name: 'companion' , params: { companion: response.data.id }})
                    })
            }
        }
    }
</script>
