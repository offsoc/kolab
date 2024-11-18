<template>
    <div id="referral-programs">
        <ul v-if="programs.length" class="list-group list-group-flush">
            <li v-for="program in programs" :id="'ref' + program.id" :key="program.id" class="list-group-item ps-0 pe-0 d-flex">
                <div class="me-3">
                    <img :src="program.qrCode" :title="program.url" style="width: 100px" />
                </div>
                <div>
                    <p class="fw-bold mb-2 name">{{ program.name }}</p>
                    <p v-if="program.description" class="mb-1 description">{{ program.description }}</p>
                    <p class="m-0 text-secondary lh-1 info">
                        <small class="text-nowrap">
                            {{ $t('wallet.refprogram-url') }}: {{ program.url }}
                            <btn class="btn-link p-1" :icon="['far', 'clipboard']" :title="$t('btn.copy')" @click="copyUrl(program.url)"></btn>
                        </small>
                        <small class="d-block">
                            {{ $t('wallet.refprogram-refcount') }}: {{ program.refcount }}
                        </small>
                    </p>
                </div>
            </li>
        </ul>
        <div v-else class="empty-list-body">{{ $t('wallet.refprograms-none') }}</div>
    </div>
</template>

<script>
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-regular-svg-icons/faClipboard').definition,
    )

    export default {
        props: {
            walletId: { type: String, default: null }
        },
        data() {
            return {
                programs: []
            }
        },
        mounted() {
            if (!this.walletId) {
                return
            }

            const loader = $('#referral-programs')

            axios.get('/api/v4/wallets/' + this.walletId + '/referral-programs', { loader })
                .then(response => {
                    this.programs = response.data.list
                })
        },
        methods: {
            copyUrl(url) {
                navigator.clipboard.writeText(url);
            }
        }
    }
</script>
