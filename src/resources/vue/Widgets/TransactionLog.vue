<template>
    <div>
        <table class="table table-sm m-0 transactions">
            <thead class="thead-light">
                <tr>
                    <th scope="col">{{ $t('form.date') }}</th>
                    <th scope="col" v-if="isAdmin">{{ $t('form.user') }}</th>
                    <th scope="col"></th>
                    <th scope="col">{{ $t('form.description') }}</th>
                    <th scope="col" class="price">{{ $t('form.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="transaction in transactions" :id="'log' + transaction.id" :key="transaction.id">
                    <td class="datetime">{{ transaction.createdAt }}</td>
                    <td class="email" v-if="isAdmin">{{ transaction.user }}</td>
                    <td class="selection">
                        <button class="btn btn-lg btn-link btn-action" title="Details" type="button"
                                v-if="transaction.hasDetails"
                                @click="loadTransaction(transaction.id)"
                        >
                            <svg-icon icon="info-circle"></svg-icon>
                        </button>
                    </td>
                    <td class="description">{{ description(transaction) }}</td>
                    <td :class="'price ' + className(transaction)">{{ amount(transaction) }}</td>
                </tr>
            </tbody>
            <tfoot class="table-fake-body">
                <tr>
                    <td :colspan="isAdmin ? 5 : 4">{{ $t('wallet.transactions-none') }}</td>
                </tr>
            </tfoot>
        </table>
        <div class="text-center p-3" id="transactions-loader" v-if="hasMore">
            <button class="btn btn-secondary" @click="loadLog(true)">{{ $t('nav.more') }}</button>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            walletId: { type: String, default: null },
            isAdmin: { type: Boolean, default: false },
        },
        data() {
            return {
                transactions: [],
                hasMore: false,
                page: 1
            }
        },
        mounted() {
            this.loadLog()
        },
        methods: {
            loadLog(more) {
                if (!this.walletId) {
                    return
                }

                let loader = $(this.$el)
                let param = ''

                if (more) {
                    param = '?page=' + (this.page + 1)
                    loader = $('#transactions-loader')
                }

                this.$root.addLoader(loader)
                axios.get('/api/v4/wallets/' + this.walletId + '/transactions' + param)
                    .then(response => {
                        this.$root.removeLoader(loader)
                        // Note: In Vue we can't just use .concat()
                        for (let i in response.data.list) {
                            this.$set(this.transactions, this.transactions.length, response.data.list[i])
                        }
                        this.hasMore = response.data.hasMore
                        this.page = response.data.page || 1
                    })
                    .catch(error => {
                        this.$root.removeLoader(loader)
                    })
            },
            loadTransaction(id) {
                let record = $('#log' + id)
                let cell = record.find('td.description')
                let details = $('<div class="list-details"><ul></ul><div>').appendTo(cell)

                this.$root.addLoader(cell)
                axios.get('/api/v4/wallets/' + this.walletId + '/transactions' + '?transaction=' + id)
                    .then(response => {
                        this.$root.removeLoader(cell)
                        record.find('button').remove()
                        let list = details.find('ul')
                        response.data.list.forEach(elem => {
                           list.append($('<li>').text(this.description(elem)))
                        })
                    })
                    .catch(error => {
                        this.$root.removeLoader(cell)
                    })
            },
            amount(transaction) {
                return this.$root.price(transaction.amount)
            },
            className(transaction) {
                return transaction.amount < 0 ? 'text-danger' : 'text-success';
            },
            description(transaction) {
                let desc = transaction.description

                if (/^(billed|created|deleted)$/.test(transaction.type)) {
                    desc += ' (' + this.$root.price(transaction.amount) + ')'
                }

                return desc
            }
        }
    }
</script>
