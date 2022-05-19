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

        <ul class="nav nav-tabs mt-2" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-qrcode" href="#companion-qrcode" role="tab" aria-controls="companion-qrcode" aria-selected="true" @click="$root.tab">
                    {{ $t('companion.pair-new') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-list" href="#companion-list" role="tab" aria-controls="companion-list" aria-selected="false" @click="$root.tab">
                    {{ $t('companion.paired') }}
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane active" id="companion-qrcode" role="tabpanel" aria-labelledby="tab-qrcode">
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
            <div class="tab-pane" id="companion-list" role="tabpanel" aria-labelledby="tab-list">
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
