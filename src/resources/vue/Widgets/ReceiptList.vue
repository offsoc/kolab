<template>
    <div>
        <table class="table table-sm m-0 receipts">
            <thead>
                <tr>
                    <th scope="col">{{ $t('form.date') }}</th>
                    <th scope="col" class="price">{{ $t('form.amount') }}</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="receipt in receipts" :id="'log' + receipt.id" :key="receipt.id">
                    <td class="datetime">{{ receipt.period }}</td>
                    <td :class="'price ' + className(receipt)">{{ amount(receipt) }}</td>
                    <td class="selection">
                        <btn class="btn-secondary float-end" @click="receiptDownload(receipt.period)" icon="download">{{ $t('btn.download') }}</btn>
                    </td>
                </tr>
            </tbody>
            <list-foot :text="$t('wallet.receipts-none')" :colspan="3"></list-foot>
        </table>
        <list-more v-if="hasMore" :on-click="loadMore"></list-more>
    </div>
</template>


<script>
    import ListTools from './ListTools'
    import { downloadFile } from '../../js/utils'

    export default {
        mixins: [ ListTools ],
        props: {
            walletId: { type: String, default: null }
        },
        data() {
            return {
                receipts: []
            }
        },
        mounted() {
            this.loadMore({ reset: true })
        },
        methods: {
            loadMore(params) {
                if (this.walletId) {
                    this.listSearch('receipts', '/api/v4/wallets/' + this.walletId + '/receipts', params)
                }
            },
            amount(receipt) {
                return this.$root.price(receipt.amount, receipt.currency)
            },
            className(receipt) {
                return receipt.amount < 0 ? 'text-danger' : 'text-success';
            },
            receiptDownload(period) {
                downloadFile('/api/v4/wallets/' + this.walletId + '/receipts/' + period)
            }
        }
    }
</script>
