<template>
    <div>
        <table class="table table-sm m-0 payments">
            <thead class="thead-light">
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
            <tfoot class="table-fake-body">
                <tr>
                    <td colspan="4">{{ $t('wallet.pending-payments-none') }}</td>
                </tr>
            </tfoot>
        </table>
        <div class="text-center p-3" id="payments-loader" v-if="hasMore">
            <button class="btn btn-secondary" @click="loadLog(true)">{{ $t('nav.more') }}</button>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
        },
        data() {
            return {
                payments: [],
                hasMore: false,
                page: 1
            }
        },
        mounted() {
            this.loadLog()
        },
        methods: {
            loadLog(more) {
                let loader = $(this.$el)
                let param = ''

                if (more) {
                    param = '?page=' + (this.page + 1)
                    loader = $('#payments-loader')
                }

                this.$root.addLoader(loader)
                axios.get('/api/v4/payments/pending' + param)
                    .then(response => {
                        this.$root.removeLoader(loader)
                        // Note: In Vue we can't just use .concat()
                        for (let i in response.data.list) {
                            this.$set(this.payments, this.payments.length, response.data.list[i])
                        }
                        this.hasMore = response.data.hasMore
                        this.page = response.data.page || 1
                    })
                    .catch(error => {
                        this.$root.removeLoader(loader)
                    })
            },
            amount(payment) {
                return this.$root.price(payment.amount)
            }
        }
    }
</script>
