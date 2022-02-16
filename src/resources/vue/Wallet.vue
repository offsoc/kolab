<template>
    <div class="container" dusk="wallet-component">
        <div v-if="wallet.id" id="wallet" class="card">
            <div class="card-body">
                <div class="card-title">{{ $t('wallet.title') }} <span :class="wallet.balance < 0 ? 'text-danger' : 'text-success'">{{ $root.price(wallet.balance, wallet.currency) }}</span></div>
                <div class="card-text">
                    <p v-if="wallet.notice" id="wallet-notice">{{ wallet.notice }}</p>

                    <div v-if="showPendingPayments" class="alert alert-warning">
                        {{ $t('wallet.pending-payments-warning') }}
                    </div>
                    <p>
                        <btn class="btn-primary" @click="paymentMethodForm('manual')">{{ $t('wallet.add-credit') }}</btn>
                    </p>
                    <div id="mandate-form" v-if="!mandate.isValid && !mandate.isPending">
                        <template v-if="mandate.id && !mandate.isValid">
                            <div class="alert alert-danger">
                                {{ $t('wallet.auto-payment-failed') }}
                            </div>
                            <btn class="btn-danger" @click="autoPaymentDelete">{{ $t('wallet.auto-payment-cancel') }}</btn>
                        </template>
                        <btn class="btn-primary" @click="paymentMethodForm('auto')">{{ $t('wallet.auto-payment-setup') }}</btn>
                    </div>
                    <div id="mandate-info" v-else>
                        <div v-if="mandate.isDisabled" class="disabled-mandate alert alert-danger">
                            {{ $t('wallet.auto-payment-disabled') }}
                        </div>
                        <template v-else>
                            <p v-html="$t('wallet.auto-payment-info', { amount: mandate.amount + ' ' + wallet.currency, balance: mandate.balance + ' ' + wallet.currency})"></p>
                            <p>{{ $t('wallet.payment-method', { method: mandate.method }) }}</p>
                        </template>
                        <div v-if="mandate.isPending" class="alert alert-warning">
                            {{ $t('wallet.auto-payment-inprogress') }}
                        </div>
                        <p class="buttons">
                            <btn class="btn-danger" @click="autoPaymentDelete">{{ $t('wallet.auto-payment-cancel') }}</btn>
                            <btn class="btn-primary" @click="autoPaymentChange">{{ $t('wallet.auto-payment-change') }}</btn>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mt-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-receipts" href="#wallet-receipts" role="tab" aria-controls="wallet-receipts" aria-selected="true">
                    {{ $t('wallet.receipts') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-history" href="#wallet-history" role="tab" aria-controls="wallet-history" aria-selected="false">
                    {{ $t('wallet.history') }}
                </a>
            </li>
            <li v-if="showPendingPayments" class="nav-item">
                <a class="nav-link" id="tab-payments" href="#wallet-payments" role="tab" aria-controls="wallet-payments" aria-selected="false">
                    {{ $t('wallet.pending-payments') }}
                </a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane active" id="wallet-receipts" role="tabpanel" aria-labelledby="tab-receipts">
                <div class="card-body">
                    <div class="card-text">
                        <p v-if="receipts.length">
                            {{ $t('wallet.receipts-hint') }}
                        </p>
                        <div v-if="receipts.length" class="input-group">
                            <select id="receipt-id" class="form-control">
                                <option v-for="(receipt, index) in receipts" :key="index" :value="receipt">{{ receipt }}</option>
                            </select>
                            <btn class="btn-secondary" @click="receiptDownload" icon="download">{{ $t('btn.download') }}</btn>
                        </div>
                        <p v-if="!receipts.length">
                            {{ $t('wallet.receipts-none') }}
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
                        <btn class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></btn>
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
                                {{ $t('wallet.currency-conv', { wc: wallet.currency, pc: selectedPaymentMethod.currency }) }}
                            </p>
                            <p v-if="selectedPaymentMethod.id == 'banktransfer'">
                                {{ $t('wallet.banktransfer-hint') }}
                            </p>
                            <p>
                                {{ $t('wallet.payment-amount-hint') }}
                            </p>
                            <form id="payment-form" @submit.prevent="payment">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="amount" v-model="amount" required>
                                    <span class="input-group-text">{{ wallet.currency }}</span>
                                </div>
                                <div v-if="wallet.currency != selectedPaymentMethod.currency && !isNaN(amount)" class="alert alert-warning m-0 mt-3">
                                    {{ $t('wallet.payment-warning', { price: $root.price(amount * selectedPaymentMethod.exchangeRate * 100, selectedPaymentMethod.currency) }) }}
                                </div>
                            </form>
                        </div>
                        <div id="auto-payment" v-if="paymentForm == 'auto'">
                            <form data-validation-prefix="mandate_">
                                <p>
                                    {{ $t('wallet.auto-payment-hint') }}
                                </p>
                                <div class="row mb-3">
                                    <label for="mandate_amount" class="col-sm-6 col-form-label">{{ $t('wallet.fill-up') }}</label>
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="mandate_amount" v-model="mandate.amount" required>
                                            <span class="input-group-text">{{ wallet.currency }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="mandate_balance" class="col-sm-6 col-form-label">{{ $t('wallet.when-below') }}</label>
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="mandate_balance" v-model="mandate.balance" required>
                                            <span class="input-group-text">{{ wallet.currency }}</span>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="!mandate.isValid">
                                    {{ $t('wallet.auto-payment-next') }}
                                </p>
                                <div v-if="mandate.isValid && mandate.isDisabled" class="disabled-mandate alert alert-danger m-0">
                                    {{ $t('wallet.auto-payment-disabled-next') }}
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <btn class="btn-secondary modal-cancel" data-bs-dismiss="modal">{{ $t('btn.cancel') }}</btn>
                        <btn class="btn-primary modal-action" icon="check" @click="autoPayment"
                             v-if="paymentForm == 'auto' && (mandate.isValid || mandate.isPending)"
                        >
                            {{ $t('btn.submit') }}
                        </btn>
                        <btn class="btn btn-primary modal-action" icon="check" @click="autoPayment"
                             v-if="paymentForm == 'auto' && !mandate.isValid && !mandate.isPending"
                        >
                            {{ $t('btn.continue') }}
                        </btn>
                        <btn class="btn-primary modal-action" icon="check" @click="payment"
                             v-if="paymentForm == 'manual'"
                        >
                            {{ $t('btn.continue') }}
                        </btn>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { Modal } from 'bootstrap'
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
                this.$root.tab(e)

                if ($(e.target).is('#tab-history')) {
                    this.loadTransactions = true
                } else if ($(e.target).is('#tab-payments')) {
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

                setTimeout(() => { $('#payment-dialog').find('#amount,#mandate_amount').focus() }, 10)
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
                                this.dialog.hide();
                                this.mandate = response.data
                                this.$toast.success(response.data.message)
                            }
                        }
                    })
            },
            autoPaymentChange(event) {
                this.autoPaymentForm(event, this.$t('wallet.auto-payment-update'))
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
                this.formLock = false
                this.paymentMethods = []
                this.paymentForm = 'method'
                this.nextForm = nextForm
                this.paymentDialogTitle = this.$t(nextForm == 'auto' ? 'wallet.auto-payment-setup' : 'wallet.top-up')

                this.dialog = new Modal('#payment-dialog')
                this.dialog.show()

                this.$nextTick().then(() => {
                    const form = $('#payment-method')
                    this.$root.addLoader(form, false, {'min-height': '10em'})

                    axios.get('/api/v4/payments/methods', {params: {type: nextForm == 'manual' ? 'oneoff' : 'recurring'}})
                        .then(response => {
                            this.$root.removeLoader(form)
                            this.paymentMethods = response.data
                        })
                })
            },
            autoPaymentForm(event, title) {
                this.paymentForm = 'auto'
                this.paymentDialogTitle = title
                this.formLock = false

                this.dialog = new Modal('#payment-dialog')
                this.dialog.show()
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
