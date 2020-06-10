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
                <a class="nav-link active" id="tab-receipts" href="#wallet-receipts" role="tab" aria-controls="wallet-receipts" aria-selected="true">
                    Receipts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-history" href="#wallet-history" role="tab" aria-controls="wallet-history" aria-selected="false">
                    History
                </a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane show active" id="wallet-receipts" role="tabpanel" aria-labelledby="tab-receipts">
                <div class="card-body">
                    <div class="card-text">
                        <p v-if="receipts.length">
                            Here you can download receipts (in PDF format) for payments in specified period.
                            Select the period and press the Download button.
                        </p>
                        <div v-if="receipts.length" class="input-group">
                            <select id="receipt-id" class="form-control">
                                <option v-for="(receipt, index) in receipts" :key="index" :value="receipt">{{ receipt }}</option>
                            </select>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-secondary" @click="receiptDownload">
                                    <svg-icon icon="download"></svg-icon> Download
                                </button>
                            </div>
                        </div>
                        <p v-if="!receipts.length">
                            There are no receipts for payments in this account. Please, note that you can download
                            receipts after the month ends.
                        </p>
                    </div>
                </div>
            </div>
            <div class="tab-pane show" id="wallet-history" role="tabpanel" aria-labelledby="tab-history">
                <div class="card-body">
                    <transaction-log v-if="walletId && loadTransactions" class="card-text" :wallet-id="walletId"></transaction-log>
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
    import TransactionLog from './Widgets/TransactionLog'

    export default {
        components: {
            TransactionLog
        },
        data() {
            return {
                amount: '',
                balance: 0,
                mandate: { amount: 10, balance: 0 },
                paymentDialogTitle: null,
                paymentForm: 'init',
                provider: window.config.paymentProvider,
                receipts: [],
                stripe: null,
                loadTransactions: false,
                walletId: null,
                wallet_currency: 'CHF'
            }
        },
        mounted() {
            $('#wallet button').focus()

            this.balance = 0
            // TODO: currencies, multi-wallets, accounts
            this.$store.state.authInfo.wallets.forEach(wallet => {
                this.balance += wallet.balance
                this.provider = wallet.provider
            })

            this.walletId = this.$store.state.authInfo.wallets[0].id

            const receiptsTab = $('#wallet-receipts')

            this.$root.addLoader(receiptsTab)
            axios.get('/api/v4/wallets/' + this.walletId + '/receipts')
                .then(response => {
                    this.$root.removeLoader(receiptsTab)
                    this.receipts = response.data.list
                })
                .catch(error => {
                    this.$root.removeLoader(receiptsTab)
                })

            $(this.$el).find('ul.nav-tabs a').on('click', e => {
                e.preventDefault()
                $(e.target).tab('show')
                if ($(e.target).is('#tab-history')) {
                    this.loadTransactions = true
                }
            })

            if (this.provider == 'stripe') {
                this.stripeInit()
            }
        },
        methods: {
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
            receiptDownload() {
                const receipt = $('#receipt-id').val()
                this.$root.downloadFile('/api/v4/wallets/' + this.walletId + '/receipts/' + receipt)
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
            }
        }
    }
</script>
