<template>
    <div class="container" dusk="companionapp-component">
        <div class="card">
            <div class="card-body">
                <div class="card-title">
                    {{ $t('companion.title') }}
                    <small><sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup></small>
                </div>
                <div class="card-text">
                    <p>
                        {{ $t('companion.description') }}
                    </p>
                </div>
            </div>
        </div>
        <tabs class="mt-3" :tabs="['companion.pair-new','companion.paired']"></tabs>
        <div class="tab-content">
            <div class="tab-pane active" id="new" role="tabpanel" aria-labelledby="tab-new">
                <div class="card-body">
                    <div class="card-text">
                        <p>
                            {{ $t('companion.pairing-instructions') }}
                        </p>
                        <p>
                            <img :src="qrcode" />
                        </p>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="paired" role="tabpanel" aria-labelledby="tab-paired">
                <div class="card-body">
                    <companionapp-list class="card-text"></companionapp-list>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import CompanionappList from './Widgets/CompanionappList'

    export default {
        components: {
            CompanionappList
        },
        data() {
            return {
                qrcode: ""
            }
        },
        mounted() {
            axios.get('/api/v4/companion/pairing', { loading: true })
                .then(response => {
                    this.qrcode = response.data.qrcode
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
