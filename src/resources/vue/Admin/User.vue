<template>
    <div class="container">
        <div class="card" id="user-info">
            <div class="card-body">
                <h1 class="card-title">{{ user.email }}</h1>
                <div class="card-text">
                    <form class="read-only short">
                        <div v-if="user.wallet.user_id != user.id" class="form-group row plaintext">
                            <label for="manager" class="col-sm-4 col-form-label">Managed by</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="manager">
                                    <router-link :to="{ path: '/user/' + user.wallet.user_id }">{{ user.wallet.user_email }}</router-link>
                                </span>
                            </div>
                        </div>
                        <div class="form-group row plaintext">
                            <label for="userid" class="col-sm-4 col-form-label">ID <span class="text-muted">(Created at)</span></label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="userid">
                                    {{ user.id }} <span class="text-muted">({{ user.created_at }})</span>
                                </span>
                            </div>
                        </div>
                        <div class="form-group row plaintext">
                            <label for="status" class="col-sm-4 col-form-label">Status</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="status">
                                    <span :class="$root.userStatusClass(user)">{{ $root.userStatusText(user) }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="form-group row plaintext" v-if="user.first_name">
                            <label for="first_name" class="col-sm-4 col-form-label">First name</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="first_name">{{ user.first_name }}</span>
                            </div>
                        </div>
                        <div class="form-group row plaintext" v-if="user.last_name">
                            <label for="last_name" class="col-sm-4 col-form-label">Last name</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="last_name">{{ user.last_name }}</span>
                            </div>
                        </div>
                        <div class="form-group row plaintext" v-if="user.organization">
                            <label for="organization" class="col-sm-4 col-form-label">Organization</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="organization">{{ user.organization }}</span>
                            </div>
                        </div>
                        <div class="form-group row plaintext" v-if="user.phone">
                            <label for="phone" class="col-sm-4 col-form-label">Phone</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="phone">{{ user.phone }}</span>
                            </div>
                        </div>
                        <div class="form-group row plaintext">
                            <label for="external_email" class="col-sm-4 col-form-label">External email</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="external_email">
                                    <a v-if="user.external_email" :href="'mailto:' + user.external_email">{{ user.external_email }}</a>
                                    <button type="button" class="btn btn-secondary btn-sm" @click="emailEdit">Edit</button>
                                </span>
                            </div>
                        </div>
                        <div class="form-group row plaintext" v-if="user.billing_address">
                            <label for="billing_address" class="col-sm-4 col-form-label">Address</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" style="white-space:pre" id="billing_address">{{ user.billing_address }}</span>
                            </div>
                        </div>
                        <div class="form-group row plaintext">
                            <label for="country" class="col-sm-4 col-form-label">Country</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="country">{{ user.country }}</span>
                            </div>
                        </div>
                    </form>
                    <div class="mt-2">
                        <button v-if="!user.isSuspended" id="button-suspend" class="btn btn-warning" type="button" @click="suspendUser">Suspend</button>
                        <button v-if="user.isSuspended" id="button-unsuspend" class="btn btn-warning" type="button" @click="unsuspendUser">Unsuspend</button>
                    </div>
                </div>
            </div>
        </div>
        <ul class="nav nav-tabs mt-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-finances" href="#user-finances" role="tab" aria-controls="user-finances" aria-selected="true">
                    Finances
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-aliases" href="#user-aliases" role="tab" aria-controls="user-aliases" aria-selected="false">
                    Aliases ({{ user.aliases.length }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-subscriptions" href="#user-subscriptions" role="tab" aria-controls="user-subscriptions" aria-selected="false">
                    Subscriptions ({{ skus.length }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-domains" href="#user-domains" role="tab" aria-controls="user-domains" aria-selected="false">
                    Domains ({{ domains.length }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-users" href="#user-users" role="tab" aria-controls="user-users" aria-selected="false">
                    Users ({{ users.length }})
                </a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane show active" id="user-finances" role="tabpanel" aria-labelledby="tab-finances">
                <div class="card-body">
                    <h2 class="card-title">Account balance <span :class="wallet.balance < 0 ? 'text-danger' : 'text-success'"><strong>{{ $root.price(wallet.balance) }}</strong></span></h2>
                    <div class="card-text">
                        <form class="read-only short">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Discount</label>
                                <div class="col-sm-8">
                                    <span class="form-control-plaintext" id="discount">
                                        <span>{{ wallet.discount ? (wallet.discount + '% - ' + wallet.discount_description) : 'none' }}</span>
                                        <button type="button" class="btn btn-secondary btn-sm" @click="discountEdit">Edit</button>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group row" v-if="wallet.mandate && wallet.mandate.id">
                                <label class="col-sm-4 col-form-label">Auto-payment</label>
                                <div class="col-sm-8">
                                    <span class="form-control-plaintext" id="autopayment">
                                        Fill up by <b>{{ wallet.mandate.amount }} CHF</b>
                                        when under <b>{{ wallet.mandate.balance }} CHF</b>
                                        using {{ wallet.mandate.method }}<span v-if="wallet.mandate.isDisabled"> (disabled)</span>.
                                    </span>
                                </div>
                            </div>
                            <div class="form-group row" v-if="wallet.providerLink">
                                <label class="col-sm-4 col-form-label">{{ capitalize(wallet.provider) }} ID</label>
                                <div class="col-sm-8">
                                    <span class="form-control-plaintext" v-html="wallet.providerLink"></span>
                                </div>
                            </div>
                        </form>
                        <div class="mt-2">
                            <button id="button-award" class="btn btn-success" type="button" @click="awardDialog">Add bonus</button>
                            <button id="button-penalty" class="btn btn-danger" type="button" @click="penalizeDialog">Add penalty</button>
                        </div>
                    </div>
                    <h2 class="card-title mt-4">Transactions</h2>
                    <transaction-log v-if="wallet.id && !walletReload" class="card-text" :wallet-id="wallet.id" :is-admin="true"></transaction-log>
                </div>
            </div>
            <div class="tab-pane" id="user-aliases" role="tabpanel" aria-labelledby="tab-aliases">
                <div class="card-body">
                    <div class="card-text">
                        <table class="table table-sm table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Email address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(alias, index) in user.aliases" :id="'alias' + index" :key="index">
                                    <td>{{ alias }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-fake-body">
                                <tr>
                                    <td>This user has no email aliases.</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="user-subscriptions" role="tabpanel" aria-labelledby="tab-subscriptions">
                <div class="card-body">
                    <div class="card-text">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Subscription</th>
                                    <th scope="col">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(sku, sku_id) in skus" :id="'sku' + sku.id" :key="sku_id">
                                    <td>{{ sku.name }}</td>
                                    <td>{{ sku.price }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-fake-body">
                                <tr>
                                    <td colspan="2">This user has no subscriptions.</td>
                                </tr>
                            </tfoot>
                        </table>
                        <small v-if="discount > 0" class="hint">
                            <hr class="m-0">
                            &sup1; applied discount: {{ discount }}% - {{ discount_description }}
                        </small>
                        <div class="mt-2">
                            <button type="button" class="btn btn-danger" id="reset2fa" v-if="has2FA" @click="reset2FADialog">Reset 2-Factor Auth</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="user-domains" role="tabpanel" aria-labelledby="tab-domains">
                <div class="card-body">
                    <div class="card-text">
                        <table class="table table-sm table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="domain in domains" :id="'domain' + domain.id" :key="domain.id" @click="$root.clickRecord">
                                    <td>
                                        <svg-icon icon="globe" :class="$root.domainStatusClass(domain)" :title="$root.domainStatusText(domain)"></svg-icon>
                                        <router-link :to="{ path: '/domain/' + domain.id }">{{ domain.namespace }}</router-link>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-fake-body">
                                <tr>
                                    <td>There are no domains in this account.</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="user-users" role="tabpanel" aria-labelledby="tab-users">
                <div class="card-body">
                    <div class="card-text">
                        <table class="table table-sm table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Primary Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in users" :id="'user' + item.id" :key="item.id" @click="$root.clickRecord">
                                    <td>
                                        <svg-icon icon="user" :class="$root.userStatusClass(item)" :title="$root.userStatusText(item)"></svg-icon>
                                        <router-link :to="{ path: '/user/' + item.id }">{{ item.email }}</router-link>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-fake-body">
                                <tr>
                                    <td>There are no users in this account.</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="discount-dialog" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Account discount</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="form-group">
                            <select v-model="wallet.discount_id" class="custom-select">
                                <option value="">- none -</option>
                                <option v-for="item in discounts" :value="item.id" :key="item.id">{{ item.label }}</option>
                            </select>
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary modal-action" @click="submitDiscount()">
                            <svg-icon icon="check"></svg-icon> Submit
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="email-dialog" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">External email</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="form-group">
                            <input v-model="external_email" name="external_email" class="form-control">
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary modal-action" @click="submitEmail()">
                            <svg-icon icon="check"></svg-icon> Submit
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="oneoff-dialog" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ oneoff_negative ? 'Add a penalty to the wallet' : 'Add a bonus to the wallet' }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form data-validation-prefix="oneoff_">
                            <div class="form-group">
                                <label for="oneoff_amount" class="col-form-label">Amount</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="oneoff_amount" v-model="oneoff_amount" required>
                                    <span class="input-group-append">
                                        <span class="input-group-text">{{ oneoff_currency }}</span>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="oneoff_description" class="col-form-label">Description</label>
                                <input class="form-control" id="oneoff_description" v-model="oneoff_description" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary modal-action" @click="submitOneOff()">
                            <svg-icon icon="check"></svg-icon> Submit
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="reset-2fa-dialog" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">2-Factor Authentication Reset</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>This will remove 2-Factor Authentication entitlement as well
                            as the user-configured factors.</p>
                        <p>Please, make sure to confirm the user identity properly.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger modal-action" @click="reset2FA()">Reset</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import TransactionLog from '../Widgets/TransactionLog'

    export default {
        beforeRouteUpdate (to, from, next) {
            // An event called when the route that renders this component has changed,
            // but this component is reused in the new route.
            // Required to handle links from /user/XXX to /user/YYY
            next()
            this.$parent.routerReload()
        },
        components: {
            TransactionLog
        },
        data() {
            return {
                oneoff_amount: '',
                oneoff_currency: 'CHF',
                oneoff_description: '',
                oneoff_negative: false,
                countries: window.config.countries,
                discount: 0,
                discount_description: '',
                discounts: [],
                external_email: '',
                has2FA: false,
                wallet: {},
                walletReload: false,
                domains: [],
                skus: [],
                sku2FA: null,
                users: [],
                user: {
                    aliases: [],
                    wallet: {},
                    skus: {},
                }
            }
        },
        created() {
            const user_id = this.$route.params.user

            this.$root.startLoading()

            axios.get('/api/v4/users/' + user_id)
                .then(response => {
                    this.$root.stopLoading()

                    this.user = response.data

                    const financesTab = '#user-finances'
                    const keys = ['first_name', 'last_name', 'external_email', 'billing_address', 'phone', 'organization']

                    let country = this.user.settings.country
                    if (country && country in window.config.countries) {
                        country = window.config.countries[country][1]
                    }

                    this.user.country = country

                    keys.forEach(key => { this.user[key] = this.user.settings[key] })

                    this.discount = this.user.wallet.discount
                    this.discount_description = this.user.wallet.discount_description

                    // TODO: currencies, multi-wallets, accounts
                    // Get more info about the wallet (e.g. payment provider related)
                    this.$root.addLoader(financesTab)
                    axios.get('/api/v4/wallets/' + this.user.wallets[0].id)
                        .then(response => {
                            this.$root.removeLoader(financesTab)
                            this.wallet = response.data
                        })
                        .catch(error => {
                            this.$root.removeLoader(financesTab)
                        })

                    // Create subscriptions list
                    axios.get('/api/v4/skus')
                        .then(response => {
                            // "merge" SKUs with user entitlement-SKUs
                            response.data.forEach(sku => {
                                if (sku.id in this.user.skus) {
                                    let count = this.user.skus[sku.id].count
                                    let item = {
                                        id: sku.id,
                                        name: sku.name,
                                        cost: sku.cost,
                                        units: count - sku.units_free,
                                        price: this.$root.priceLabel(sku.cost, count - sku.units_free, this.discount)
                                    }

                                    if (sku.range) {
                                        item.name += ' ' + count + ' ' + sku.range.unit
                                    }

                                    this.skus.push(item)

                                    if (sku.title == '2fa') {
                                        this.has2FA = true
                                        this.sku2FA = sku.id
                                    }
                                }
                            })
                        })

                    // Fetch users
                    // TODO: Multiple wallets
                    axios.get('/api/v4/users?owner=' + user_id)
                        .then(response => {
                            this.users = response.data.list.filter(user => {
                                return user.id != user_id;
                            })
                        })

                    // Fetch domains
                    axios.get('/api/v4/domains?owner=' + user_id)
                        .then(response => {
                            this.domains = response.data.list
                        })
                })
                .catch(this.$root.errorHandler)
        },
        mounted() {
            $(this.$el).find('ul.nav-tabs a').on('click', e => {
                e.preventDefault()
                $(e.target).tab('show')
            })
        },
        methods: {
            capitalize(str) {
                return str.charAt(0).toUpperCase() + str.slice(1)
            },
            awardDialog() {
                this.oneOffDialog(false)
            },
            discountEdit() {
                $('#discount-dialog')
                    .on('shown.bs.modal', e => {
                        $(e.target).find('select').focus()
                        // Note: Vue v-model is strict, convert null to a string
                        this.wallet.discount_id = this.wallet_discount_id || ''
                    })
                    .modal()

                if (!this.discounts.length) {
                    // Fetch discounts
                    axios.get('/api/v4/discounts')
                        .then(response => {
                            this.discounts = response.data.list
                        })
                }
            },
            emailEdit() {
                this.external_email = this.user.external_email
                this.$root.clearFormValidation($('#email-dialog'))

                $('#email-dialog')
                    .on('shown.bs.modal', e => {
                        $(e.target).find('input').focus()
                    })
                    .modal()
            },
            oneOffDialog(negative) {
                this.oneoff_negative = negative
                this.dialog = $('#oneoff-dialog').on('shown.bs.modal', event => {
                    this.$root.clearFormValidation(event.target)
                    $(event.target).find('#oneoff_amount').focus()
                }).modal()
            },
            penalizeDialog() {
                this.oneOffDialog(true)
            },
            reload() {
                // this is to reload transaction log
                this.walletReload = true
                this.$nextTick(() => { this.walletReload = false })
            },
            reset2FA() {
                $('#reset-2fa-dialog').modal('hide')
                axios.post('/api/v4/users/' + this.user.id + '/reset2FA')
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.skus = this.skus.filter(sku => sku.id != this.sku2FA)
                            this.has2FA = false
                        }
                    })
            },
            reset2FADialog() {
                $('#reset-2fa-dialog').modal()
            },
            submitDiscount() {
                $('#discount-dialog').modal('hide')

                axios.put('/api/v4/wallets/' + this.user.wallets[0].id, { discount: this.wallet.discount_id })
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.wallet = Object.assign({}, this.wallet, response.data)

                            // Update prices in Subscriptions tab
                            if (this.user.wallet.id == response.data.id) {
                                this.discount = this.wallet.discount
                                this.discount_description = this.wallet.discount_description

                                this.skus.forEach(sku => {
                                    sku.price = this.$root.priceLabel(sku.cost, sku.units, this.discount)
                                })
                            }
                        }
                    })
            },
            submitEmail() {
                axios.put('/api/v4/users/' + this.user.id, { external_email: this.external_email })
                    .then(response => {
                        if (response.data.status == 'success') {
                            $('#email-dialog').modal('hide')
                            this.$toast.success(response.data.message)
                            this.user.external_email = this.external_email
                            this.external_email = null // required because of Vue
                        }
                    })
            },
            submitOneOff() {
                let wallet_id = this.user.wallets[0].id
                let post = {
                    amount: this.oneoff_amount,
                    description: this.oneoff_description
                }

                if (this.oneoff_negative && /^\d+(\.?\d+)?$/.test(post.amount)) {
                    post.amount *= -1
                }

                // TODO: We maybe should use system currency not wallet currency,
                //       or have a selector so the operator does not have to calculate
                //       exchange rates

                this.$root.clearFormValidation(this.dialog)

                axios.post('/api/v4/wallets/' + wallet_id + '/one-off', post)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.dialog.modal('hide')
                            this.$toast.success(response.data.message)
                            this.wallet = Object.assign({}, this.wallet, {balance: response.data.balance})
                            this.oneoff_amount = ''
                            this.oneoff_description = ''
                            this.reload()
                        }
                    })
            },
            suspendUser() {
                axios.post('/api/v4/users/' + this.user.id + '/suspend', {})
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.user = Object.assign({}, this.user, { isSuspended: true })
                        }
                    })
            },
            unsuspendUser() {
                axios.post('/api/v4/users/' + this.user.id + '/unsuspend', {})
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.user = Object.assign({}, this.user, { isSuspended: false })
                        }
                    })
            }
        }
    }
</script>
