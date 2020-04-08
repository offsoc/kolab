<template>
    <div class="container">
        <div class="card" id="user-info">
            <div class="card-body">
                <div class="card-title">{{ user.email }}</div>
                <div class="card-text">
                    <form @submit.prevent="submit">
                        <div class="form-group row mb-0">
                            <label for="first_name" class="col-sm-4 col-form-label">Status</label>
                            <div class="col-sm-8">
                                <span :class="$root.userStatusClass(user) + ' form-control-plaintext'" id="status">{{ $root.userStatusText(user) }}</span>
                            </div>
                        </div>
                        <div class="form-group row mb-0" v-if="user.first_name">
                            <label for="first_name" class="col-sm-4 col-form-label">First name</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="first_name">{{ user.first_name }}</span>
                            </div>
                        </div>
                        <div class="form-group row mb-0" v-if="user.last_name">
                            <label for="last_name" class="col-sm-4 col-form-label">Last name</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="last_name">{{ user.last_name }}</span>
                            </div>
                        </div>
                        <div class="form-group row mb-0" v-if="user.phone">
                            <label for="phone" class="col-sm-4 col-form-label">Phone</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="phone">{{ user.phone }}</span>
                            </div>
                        </div>
                        <div class="form-group row mb-0">
                            <label for="external_email" class="col-sm-4 col-form-label">External email</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="external_email">{{ user.external_email }}</span>
                            </div>
                        </div>
                        <div class="form-group row mb-0" v-if="user.billing_address">
                            <label for="billing_address" class="col-sm-4 col-form-label">Address</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" style="white-space:pre" id="billing_address">{{ user.billing_address }}</span>
                            </div>
                        </div>
                        <div class="form-group row mb-0">
                            <label for="country" class="col-sm-4 col-form-label">Country</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="country">{{ user.country }}</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                discount: 0,
                discount_description: '',
                user: {},
                skus: []
            }
        },
        created() {
            let user_id = this.$route.params.user

            this.$root.startLoading()

            axios.get('/api/v4/users/' + user_id)
                .then(response => {
                    this.user = response.data

                    let keys = ['first_name', 'last_name', 'external_email', 'billing_address']
                    let country = this.user.settings.country

                    if (country) {
                        this.user.country = window.config.countries[country][1]
                    }

                    keys.forEach(key => { this.user[key] = this.user.settings[key] })

                    this.discount = this.user.wallet.discount
                    this.discount_description = this.user.wallet.discount_description

                    this.$root.stopLoading()
                })
                .catch(this.$root.errorHandler)
        },
        mounted() {
        },
        methods: {
            price(cost, units = 1) {
                let index = ''

                if (this.discount) {
                    cost = Math.floor(cost * ((100 - this.discount) / 100))
                    index = '\u00B9'
                }

                return this.$root.price(cost * units) + '/month' + index
            }
        }
    }
</script>
