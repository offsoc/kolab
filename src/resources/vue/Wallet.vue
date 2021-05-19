<template>
    <div class="container" dusk="wallet-component">
        <div v-if="wallet.id" id="wallet" class="card">
            <div class="card-body">
                <div class="card-title">Account balance <span :class="wallet.balance < 0 ? 'text-danger' : 'text-success'">{{ $root.price(wallet.balance, wallet.currency) }}</span></div>
                <div class="card-text">
                    <p v-if="wallet.notice" id="wallet-notice">{{ wallet.notice }}</p>

                    <div v-if="showPendingPayments" class="alert alert-warning">
                        You have payments that are still in progress. See the "Pending Payments" tab below.
                    </div>
                    <p>
                        <button type="button" class="btn btn-primary" @click="paymentMethodForm('manual')">Add credit</button>
                    </p>
                    <div id="mandate-form" v-if="!mandate.isValid && !mandate.isPending">
                        <template v-if="mandate.id && !mandate.isValid">
                            <div class="alert alert-danger">
                                The setup of automatic payments failed. Restart the process to enable automatic top-ups.
                            </div>
                            <button type="button" class="btn btn-danger" @click="autoPaymentDelete">Cancel auto-payment</button>
                        </template>
                        <button type="button" class="btn btn-primary" @click="paymentMethodForm('auto')">Set up auto-payment</button>
                    </div>
                    <div id="mandate-info" v-else>
                        <div v-if="mandate.isDisabled" class="disabled-mandate alert alert-danger">
                            The configured auto-payment has been disabled. Top up your wallet or
                            raise the auto-payment amount.
                        </div>
                        <template v-else>
                            <p>
                                Auto-payment is <b>set</b> to fill up your account by <b>{{ mandate.amount }} CHF</b>
                                every time your account balance gets under <b>{{ mandate.balance }} CHF</b>.
                            </p>
                            <p>
                                Method of payment: {{ mandate.method }}
                            </p>
                        </template>
                        <div v-if="mandate.isPending" class="alert alert-warning">
                            The setup of the automatic payment is still in progress.
                        </div>
                        <p>
                            <button type="button" class="btn btn-danger" @click="autoPaymentDelete">Cancel auto-payment</button>
                            <button type="button" class="btn btn-primary" @click="autoPaymentChange">Change auto-payment</button>
                        </p>
                    </div>
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
            <li v-if="showPendingPayments" class="nav-item">
                <a class="nav-link" id="tab-payments" href="#wallet-payments" role="tab" aria-controls="wallet-payments" aria-selected="false">
                    Pending Payments
                </a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane active" id="wallet-receipts" role="tabpanel" aria-labelledby="tab-receipts">
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
            <div class="tab-pane" id="wallet-history" role="tabpanel" aria-labelledby="tab-history">
                <div class="card-body">
                    <transaction-log v-if="walletId && loadTransactions" class="card-text" :wallet-id="walletId"></transaction-log>
                </div>
            </div>
            <div class="tab-pane" id="wallet-payments" role="tabpanel" aria-labelledby="tab-payments">
                <div class="card-body">
                    <payment-log v-if="walletId && loadPayments" class="card-text" :wallet-id="walletId"></payment-log>
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
                        <div id="payment-method" v-if="paymentForm == 'method'">
                            <form data-validation-prefix="mandate_">
                                <div id="payment-method-selection">
                                    <a :id="method.id" v-for="method in paymentMethods" :key="method.id" @click="selectPaymentMethod(method)" href="#" class="card link-profile">
                                        <svg-icon v-if="method.icon" :icon="[method.icon.prefix, method.icon.name]" />
                                        <img v-if="method.image" :src="method.image" />
                                        <span class="name">{{ method.name }}</span>
                                    </a>
                                </div>
                            </form>
                        </div>
                        <div id="manual-payment" v-if="paymentForm == 'manual'">
                            <p v-if="wallet.currency != selectedPaymentMethod.currency">
                                Here is how it works: You specify the amount by which you want to to up your wallet in {{ wallet.currency }}.
                                We will then convert this to {{ selectedPaymentMethod.currency }}, and on the next page you will be provided with the bank-details
                                to transfer the amount in {{ selectedPaymentMethod.currency }}.
                            </p>
                            <p v-if="selectedPaymentMethod.id == 'banktransfer'">
                                Please note that a bank transfer can take several days to complete.
                            </p>
                            <p>Choose the amount by which you want to top up your wallet.</p>
                            <form id="payment-form" @submit.prevent="payment">
                                <div class="form-group">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="amount" v-model="amount" required>
                                        <span class="input-group-append">
                                            <span class="input-group-text">{{ wallet.currency }}</span>
                                        </span>
                                    </div>
                                </div>
                                <div v-if="wallet.currency != selectedPaymentMethod.currency && !isNaN(amount)" class="alert alert-warning">
                                    You will be charged for {{ $root.price(amount * selectedPaymentMethod.exchangeRate * 100, selectedPaymentMethod.currency) }}
                                </div>
                            </form>
                        </div>
                        <div id="auto-payment" v-if="paymentForm == 'auto'">
                            <form data-validation-prefix="mandate_">
                                <p>
                                    Here is how it works: Every time your account runs low,
                                    we will charge your preferred payment method for an amount you choose.
                                    You can cancel or change the auto-payment option at any time.
                                </p>
                                <div class="form-group row">
                                    <label for="mandate_amount" class="col-sm-6 col-form-label">Fill up by</label>
                                    <div class="input-group col-sm-6">
                                        <input type="text" class="form-control" id="mandate_amount" v-model="mandate.amount" required>
                                        <span class="input-group-append">
                                            <span class="input-group-text">{{ wallet.currency }}</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="mandate_balance" class="col-sm-6 col-form-label">when account balance is below</label>
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="mandate_balance" v-model="mandate.balance" required>
                                            <span class="input-group-append">
                                                <span class="input-group-text">{{ wallet.currency }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="!mandate.isValid">
                                    Next, you will be redirected to the checkout page, where you can provide
                                    your credit card details.
                                </p>
                                <div v-if="mandate.isValid && mandate.isDisabled" class="disabled-mandate alert alert-danger">
                                    The auto-payment is disabled. Immediately after you submit new settings we'll
                                    enable it and attempt to top up your wallet.
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary modal-action"
                                v-if="paymentForm == 'auto' && (mandate.isValid || mandate.isPending)"
                                @click="autoPayment"
                        >
                            <svg-icon icon="check"></svg-icon> Submit
                        </button>
                        <button type="button" class="btn btn-primary modal-action"
                                v-if="paymentForm == 'auto' && !mandate.isValid && !mandate.isPending"
                                @click="autoPayment"
                        >
                            <svg-icon icon="check"></svg-icon> Continue
                        </button>
                        <button type="button" class="btn btn-primary modal-action"
                                v-if="paymentForm == 'manual'"
                                @click="payment"
                        >
                            <svg-icon icon="check"></svg-icon> Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import TransactionLog from './Widgets/TransactionLog'
    import PaymentLog from './Widgets/PaymentLog'

    export default {
        components: {
            TransactionLog,
            PaymentLog
        },
        data() {
            return {
                amount: '',
                mandate: { amount: 10, balance: 0, method: null },
                paymentDialogTitle: null,
                paymentForm: null,
                nextForm: null,
                receipts: [],
                stripe: null,
                loadTransactions: false,
                loadPayments: false,
                showPendingPayments: false,
                wallet: {},
                walletId: null,
                paymentMethods: [],
                selectedPaymentMethod: null
            }
        },
        mounted() {
            $('#wallet button').focus()

            this.walletId = this.$store.state.authInfo.wallets[0].id

            this.$root.startLoading()
            axios.get('/api/v4/wallets/' + this.walletId)
                .then(response => {
                    this.$root.stopLoading()
                    this.wallet = response.data

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

                    if (this.wallet.provider == 'stripe') {
                        this.stripeInit()
                    }
                })
                .catch(this.$root.errorHandler)

            this.loadMandate()

            axios.get('/api/v4/payments/has-pending')
                .then(response => {
                    this.showPendingPayments = response.data.hasPending
                })

        },
        updated() {
            $(this.$el).find('ul.nav-tabs a').on('click', e => {
                e.preventDefault()
                $(e.target).tab('show')
                if ($(e.target).is('#tab-history')) {
                    this.loadTransactions = true
                }
                if ($(e.target).is('#tab-payments')) {
                    this.loadPayments = true
                }
            })
        },
        methods: {
            loadMandate() {
                const mandate_form = $('#mandate-form')

                this.$root.removeLoader(mandate_form)

                if (!this.mandate.id || this.mandate.isPending) {
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
            },
            selectPaymentMethod(method) {
                this.formLock = false

                this.selectedPaymentMethod = method

                this.paymentForm = this.nextForm
                this.formLock = false

                setTimeout(() => {
                    this.dialog.find('#mandate_amount').focus()
                    this.dialog.find('#amount').focus()
                }, 10)
            },
            payment() {
                if (this.formLock) {
                    return
                }

                // Lock the form to prevent from double submission
                this.formLock = true
                let onFinish = () => { this.formLock = false }

                this.$root.clearFormValidation($('#payment-form'))

                axios.post('/api/v4/payments', {amount: this.amount, methodId: this.selectedPaymentMethod.id, currency: this.selectedPaymentMethod.currency}, { onFinish })
                    .then(response => {
                        if (response.data.redirectUrl) {
                            location.href = response.data.redirectUrl
                        } else {
                            this.stripeCheckout(response.data)
                        }
                    })
            },
            autoPayment() {
                if (this.formLock) {
                    return
                }

                // Lock the form to prevent from double submission
                this.formLock = true
                let onFinish = () => { this.formLock = false }

                const method = this.mandate.id && (this.mandate.isValid || this.mandate.isPending) ? 'put' : 'post'
                let post = {
                    amount: this.mandate.amount,
                    balance: this.mandate.balance,
                }

                // Modifications can't change the method of payment
                if (this.selectedPaymentMethod) {
                    post['methodId'] = this.selectedPaymentMethod.id;
                    post['currency'] = this.selectedPaymentMethod.currency;
                }

                this.$root.clearFormValidation($('#auto-payment form'))

                axios[method]('/api/v4/payments/mandate', post, { onFinish })
                    .then(response => {
                        if (method == 'post') {
                            this.mandate.id = null
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

            paymentMethodForm(nextForm) {
                const dialog = $('#payment-dialog')
                this.formLock = false
                this.paymentMethods = []

                this.paymentForm = 'method'
                this.nextForm = nextForm
                if (nextForm == 'auto') {
                    this.paymentDialogTitle = 'Add auto-payment'
                } else {
                    this.paymentDialogTitle = 'Top up your wallet'
                }

                const methods = $('#payment-method')
                this.$root.addLoader(methods, false)
                axios.get('/api/v4/payments/methods', {params: {type: nextForm == 'manual' ? 'oneoff' : 'recurring'}})
                    .then(response => {
                        this.$root.removeLoader(methods)
                        this.paymentMethods = response.data
                    })
                    .catch(this.$root.errorHandler)

                this.dialog = dialog.on('shown.bs.modal', () => {}).modal()
            },
            autoPaymentForm(event, title) {
                const dialog = $('#payment-dialog')

                this.paymentForm = 'auto'
                this.paymentDialogTitle = title
                this.formLock = false

                this.dialog = dialog.on('shown.bs.modal', () => {
                    dialog.find('#mandate_amount').focus()
                }).modal()

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
