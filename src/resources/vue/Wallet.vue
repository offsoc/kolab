<template>
    <div class="container" dusk="wallet-component">
        <div id="wallet" class="card">
            <div class="card-body">
                <div class="card-title">Account balance</div>
                <div class="card-text">
                    <p>Current account balance is
                        <span :class="balance < 0 ? 'text-danger' : 'text-success'"><strong>{{ $root.price(balance) }}</strong></span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                balance: 0,
            }
        },
        mounted() {
            this.balance = 0
            // TODO: currencies, multi-wallets, accounts
            this.$store.state.authInfo.wallets.forEach(wallet => {
                this.balance += wallet.balance
            })
        },
        methods: {
        }
    }
</script>
