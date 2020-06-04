<template>
    <div class="container" dusk="wallet-component">
        <div id="wallet" class="card">
            <div class="card-body">
                <div class="card-title">Account balance</div>
                <div class="card-text">
                    <p>Current account balance is
                        <span :class="balance < 0 ? 'text-danger' : 'text-success'"><strong>{{ $root.price(balance) }}</strong></span>
                    </p>
                    <button type="button" class="btn btn-primary" @click="paymentDialog()">Add credit</button>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mt-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-history" href="#wallet-history" role="tab" aria-controls="wallet-history" aria-selected="true">
                    History
                </a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane show active" id="wallet-history" role="tabpanel" aria-labelledby="tab-history">
                <div class="card-body">
                    <div class="card-text">
                        <table class="table table-sm m-0">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col"></th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="price">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="transaction in transactions" :id="'log' + transaction.id" :key="transaction.id">
                                    <td class="datetime">{{ transaction.createdAt }}</td>
                                    <td class="selection">
                                        <button class="btn btn-lg btn-link btn-action" title="Details"
                                                v-if="transaction.hasDetails"
                                                @click="loadTransaction(transaction.id)"
                                        >
                                            <svg-icon icon="info-circle"></svg-icon>
                                        </button>
                                    </td>
                                    <td class="description">{{ transactionDescription(transaction) }}</td>
                                    <td :class="'price ' + transactionClass(transaction)">{{ transactionAmount(transaction) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-fake-body">
                                <tr>
                                    <td colspan="4">There are no transactions for this account.</td>
                                </tr>
                            </tfoot>
                        </table>
                        <div class="text-center p-3" id="transactions-loader">
                            <button class="btn btn-secondary" v-if="transactions_more" @click="loadTransactions(true)">Load more</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="payment-dialog" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ paymentDialogTitle }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div id="payment" v-if="paymentForm == 'init'">
                            <p>Choose the amount by which you want to top up your wallet.</p>
                            <form id="payment-form" @submit.prevent="payment">
                                <div class="form-group">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="amount" v-model="amount" required>
                                        <span class="input-group-append">
                                            <span class="input-group-text">{{ wallet_currency }}</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="w-100 text-center">
                                    <button type="submit" class="btn btn-primary">
                                        <svg-icon :icon="['far', 'credit-card']"></svg-icon> Continue
                                    </button>
                                </div>
                            </form>
                            <div class="form-separator"><hr><span>or</span></div>
                            <div id="mandate-form" v-if="!mandate.id">
                                <p>Add auto-payment, so you never run out.</p>
                                <div class="w-100 text-center">
                                    <button type="button" class="btn btn-primary" @click="autoPaymentForm">Set up auto-payment</button>
                                </div>
                            </div>
                            <div id="mandate-info" v-if="mandate.id">
                                <p>Auto-payment is set to fill up your account by <b>{{ mandate.amount }} CHF</b>
                                    every time your account balance gets under <b>{{ mandate.balance }} CHF</b>.
                                    You will be charged via {{ mandate.method }}.
                                </p>
                                <p v-if="mandate.isDisabled" class="disabled-mandate text-danger">
                                    The configured auto-payment has been disabled. Top up your wallet or
                                    raise the auto-payment amount.
                                </p>
                                <p>You can cancel or change the auto-payment at any time.</p>
                                <div class="form-group d-flex justify-content-around">
                                    <button type="button" class="btn btn-danger" @click="autoPaymentDelete">Cancel auto-payment</button>
                                    <button type="button" class="btn btn-primary" @click="autoPaymentChange">Change auto-payment</button>
                                </div>
                            </div>
                        </div>
                        <div id="auto-payment" v-if="paymentForm == 'auto'">
                            <form data-validation-prefix="mandate_">
                                <p>Here is how it works: Every time your account runs low,
                                    we will charge your preferred payment method for an amount you choose.
                                    You can cancel or change the auto-payment option at any time.
                                </p>
                                <div class="form-group row">
                                    <label for="mandate_amount" class="col-sm-6 col-form-label">Fill up by</label>
                                    <div class="input-group col-sm-6">
                                        <input type="text" class="form-control" id="mandate_amount" v-model="mandate.amount" required>
                                        <span class="input-group-append">
                                            <span class="input-group-text">{{ wallet_currency }}</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="mandate_balance" class="col-sm-6 col-form-label">when account balance is below</label>
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="mandate_balance" v-model="mandate.balance" required>
                                            <span class="input-group-append">
                                                <span class="input-group-text">{{ wallet_currency }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="!mandate.id">
                                    Next, you will be redirected to the checkout page, where you can provide
                                    your credit card details.
                                </p>
                                <p v-if="mandate.isDisabled" class="disabled-mandate text-danger">
                                    The auto-payment is disabled. Immediately after you submit new settings we'll
                                    attempt to top up your wallet.
                                </p>
                            </form>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary modal-action" v-if="paymentForm == 'auto' && mandate.id" @click="autoPayment">
                            <svg-icon icon="check"></svg-icon> Submit
                        </button>
                        <button type="button" class="btn btn-primary modal-action" v-if="paymentForm == 'auto' && !mandate.id" @click="autoPayment">
                            <svg-icon :icon="['far', 'credit-card']"></svg-icon> Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                amount: '',
                balance: 0,
                mandate: { amount: 10, balance: 0 },
                paymentDialogTitle: null,
                paymentForm: 'init',
                provider: window.config.paymentProvider,
                stripe: null,
                transactions: [],
                transactions_more: false,
                transactions_page: 1,
                wallet_currency: 'CHF'
            }
        },
        mounted() {
            this.balance = 0
            // TODO: currencies, multi-wallets, accounts
            this.$store.state.authInfo.wallets.forEach(wallet => {
                this.balance += wallet.balance
                this.provider = wallet.provider
            })

            this.loadTransactions()

            if (this.provider == 'stripe') {
                this.stripeInit()
            }
        },
        methods: {
            loadTransactions(more) {
                let loader = $('#wallet-history')
                let walletId = this.$store.state.authInfo.wallets[0].id
                let param = ''

                if (more) {
                    param = '?page=' + (this.transactions_page + 1)
                    loader = $('#transactions-loader')
                }

                this.$root.addLoader(loader)
                axios.get('/api/v4/wallets/' + walletId + '/transactions' + param)
                    .then(response => {
                        this.$root.removeLoader(loader)
                        // Note: In Vue we can't just use .concat()
                        for (let i in response.data.list) {
                            this.$set(this.transactions, this.transactions.length, response.data.list[i])
                        }
                        this.transactions_more = response.data.hasMore
                        this.transactions_page = response.data.page || 1
                    })
                    .catch(error => {
                        this.$root.removeLoader(loader)
                    })
            },
            loadTransaction(id) {
                let walletId = this.$store.state.authInfo.wallets[0].id
                let record = $('#log' + id)
                let cell = record.find('td.description')
                let details = $('<div class="list-details"><ul></ul><div>').appendTo(cell)

                this.$root.addLoader(cell)
                axios.get('/api/v4/wallets/' + walletId + '/transactions' + '?transaction=' + id)
                    .then(response => {
                        this.$root.removeLoader(cell)
                        record.find('button').remove()
                        let list = details.find('ul')
                        response.data.list.forEach(elem => {
                           list.append($('<li>').text(this.transactionDescription(elem)))
                        })
                    })
                    .catch(error => {
                        this.$root.removeLoader(cell)
                    })
            },
            paymentDialog() {
                const dialog = $('#payment-dialog')
                const mandate_form = $('#mandate-form')

                this.$root.removeLoader(mandate_form)

                if (!this.mandate.id) {
                    this.$root.addLoader(mandate_form)
                    axios.get('/api/v4/payments/mandate')
                        .then(response => {
                            this.$root.removeLoader(mandate_form)
                            this.mandate = response.data
                        })
                        .catch(error => {
                            this.$root.removeLoader(mandate_form)
                        })
                }

                this.paymentForm = 'init'
                this.paymentDialogTitle = 'Top up your wallet'

                this.dialog = dialog.on('shown.bs.modal', () => {
                        dialog.find('#amount').focus()
                    }).modal()
            },
            payment() {
                this.$root.clearFormValidation($('#payment-form'))

                axios.post('/api/v4/payments', {amount: this.amount})
                    .then(response => {
                        if (response.data.redirectUrl) {
                            location.href = response.data.redirectUrl
                        } else {
                            this.stripeCheckout(response.data)
                        }
                    })
            },
            autoPayment() {
                const method = this.mandate.id ? 'put' : 'post'
                const post = {
                    amount: this.mandate.amount,
                    balance: this.mandate.balance
                }

                this.$root.clearFormValidation($('#auto-payment form'))

                axios[method]('/api/v4/payments/mandate', post)
                    .then(response => {
                        if (method == 'post') {
                            // a new mandate, redirect to the chackout page
                            if (response.data.redirectUrl) {
                                location.href = response.data.redirectUrl
                            } else if (response.data.id) {
                                this.stripeCheckout(response.data)
                            }
                        } else {
                            // an update
                            if (response.data.status == 'success') {
                                this.dialog.modal('hide');
                                this.mandate = response.data
                                this.$toast.success(response.data.message)
                            }
                        }
                    })
            },
            autoPaymentChange(event) {
                this.autoPaymentForm(event, 'Update auto-payment')
            },
            autoPaymentDelete() {
                axios.delete('/api/v4/payments/mandate')
                    .then(response => {
                        this.mandate = { amount: 10, balance: 0 }
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                        }
                    })
            },
            autoPaymentForm(event, title) {
                this.paymentForm = 'auto'
                this.paymentDialogTitle = title || 'Add auto-payment'
                setTimeout(() => { this.dialog.find('#mandate_amount').focus()}, 10)
            },
            stripeInit() {
                let script = $('#stripe-script')

                if (!script.length) {
                    script = document.createElement('script')

                    script.onload = () => {
                        this.stripe = Stripe(window.config.stripePK)
                    }

                    script.id = 'stripe-script'
                    script.src = 'https://js.stripe.com/v3/'

                    document.getElementsByTagName('head')[0].appendChild(script)
                } else {
                    this.stripe = Stripe(window.config.stripePK)
                }
            },
            stripeCheckout(data) {
                if (!this.stripe) {
                    return
                }

                this.stripe.redirectToCheckout({
                    sessionId: data.id
                }).then(result => {
                    // If it fails due to a browser or network error,
                    // display the localized error message to the user
                    if (result.error) {
                        this.$toast.error(result.error.message)
                    }
                })
            },
            transactionAmount(transaction) {
                return this.$root.price(transaction.amount)
            },
            transactionClass(transaction) {
                return transaction.amount < 0 ? 'text-danger' : 'text-success';
            },
            transactionDescription(transaction) {
                let desc = transaction.description
                if (/^(billed|created|deleted)$/.test(transaction.type)) {
                    desc += ' (' + this.$root.price(transaction.amount) + ')'
                }
                return desc
            }
        }
    }
</script>
