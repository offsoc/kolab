<template>
    <div>
        <table class="table table-sm m-0 transactions">
            <thead>
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
                        <btn v-if="transaction.hasDetails" class="btn-lg btn-link btn-action" icon="circle-info"
                             :title="$t('form.details')"
                             @click="loadTransaction(transaction.id)"
                        ></btn>
                    </td>
                    <td class="description">{{ description(transaction) }}</td>
                    <td :class="'price ' + className(transaction)">{{ amount(transaction) }}</td>
                </tr>
            </tbody>
            <list-foot :text="$t('wallet.transactions-none')" :colspan="isAdmin ? 5 : 4"></list-foot>
        </table>
        <list-more v-if="hasMore" :on-click="loadLog"></list-more>
    </div>
</template>

<script>
    import ListTools from './ListTools'

    export default {
        mixins: [ ListTools ],
        props: {
            walletId: { type: String, default: null },
            isAdmin: { type: Boolean, default: false },
        },
        data() {
            return {
                transactions: []
            }
        },
        mounted() {
            this.loadLog({ reset: true })
        },
        methods: {
            loadLog(params) {
                if (this.walletId) {
                    this.listSearch('transactions', '/api/v4/wallets/' + this.walletId + '/transactions', params)
                }
            },
            loadTransaction(id) {
                let record = $('#log' + id)
                let cell = record.find('td.description')
                let details = $('<div class="list-details"><ul></ul><div>').appendTo(cell)

                axios.get('/api/v4/wallets/' + this.walletId + '/transactions' + '?transaction=' + id, { loader: cell })
                    .then(response => {
                        record.find('button').remove()
                        let list = details.find('ul')
                        response.data.list.forEach(elem => {
                           list.append($('<li>').text(this.description(elem)))
                        })
                    })
            },
            amount(transaction) {
                return this.$root.price(transaction.amount, transaction.currency)
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
