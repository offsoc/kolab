<template>
    <div class="container">
        <p v-if="$root.authInfo.isLocked" id="lock-alert" class="alert alert-warning">
            {{ $t('wallet.locked-text') }}
        </p>
        <div class="card">
            <div class="card-body">
                <div class="card-text" v-html="payment.statusMessage"></div>
                <div class="mt-4">
                    <btn v-if="payment.tryagain" @click="tryAgain" class="btn-primary">{{ $t('btn.tryagain') }}</btn>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                payment: {}
            }
        },
        mounted() {
            this.paymentStatus(true)
        },
        beforeDestroy() {
            clearTimeout(this.timeout)
        },
        methods: {
            paymentStatus(loader) {
                axios.get('/api/v4/payments/status', { loader })
                    .then(response => {
                        this.payment = response.data
                        this.payment.tryagain = this.payment.type == 'mandate' && this.payment.status != 'paid'

                        if (this.payment.status == 'paid' && this.$root.authInfo.isLocked) {
                            // unlock, and redirect to the Dashboard
                            this.timeout = setTimeout(() => this.$root.unlock(), 5000)
                        } else if (['open', 'pending', 'authorized'].includes(this.payment.status)) {
                            // wait some time and check again
                            this.timeout = setTimeout(() => this.paymentStatus(false), 5000)
                        }
                    })
                    .catch(error => {
                        this.$root.errorHandler(error)
                    })
            },
            tryAgain() {
                // Create the first payment and goto to the checkout page, again
                axios.post('/api/v4/payments/mandate/reset')
                    .then(response => {
                        clearTimeout(this.timeout)
                        // TODO: We have this code in a few places now, de-duplicate!
                        if (response.data.redirectUrl) {
                            location.href = response.data.redirectUrl
                        } else if (response.data.id) {
                            // TODO: this.stripeCheckout(response.data)
                        }
                    })
            }
        }
    }
</script>
