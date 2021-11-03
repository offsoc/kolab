<template>
    <div>
        <table class="table table-sm m-0 payments">
            <thead>
                <tr>
                    <th scope="col">{{ $t('form.date') }}</th>
                    <th scope="col">{{ $t('form.description') }}</th>
                    <th scope="col"></th>
                    <th scope="col" class="price">{{ $t('form.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="payment in payments" :id="'log' + payment.id" :key="payment.id">
                    <td class="datetime">{{ payment.createdAt }}</td>
                    <td class="description">{{ payment.description }}</td>
                    <td><a v-if="payment.checkoutUrl" :href="payment.checkoutUrl">{{ $t('form.details') }}</a></td>
                    <td class="price text-success">{{ amount(payment) }}</td>
                </tr>
            </tbody>
            <list-foot :text="$t('wallet.pending-payments-none')" colspan="4"></list-foot>
        </table>
        <list-more v-if="hasMore" :on-click="loadLog"></list-more>
    </div>
</template>

<script>
    import ListTools from './ListTools'

    export default {
        mixins: [ ListTools ],
        data() {
            return {
                payments: []
            }
        },
        mounted() {
            this.loadLog({ reset: true })
        },
        methods: {
            loadLog(params) {
                this.listSearch('payments', '/api/v4/payments/pending', params)
            },
            amount(payment) {
                return this.$root.price(payment.amount, payment.currency)
            }
        }
    }
</script>
