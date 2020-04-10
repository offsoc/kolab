<template>
    <div class="container">
        <div class="card" id="user-info">
            <div class="card-body">
                <div class="card-title">{{ user.email }}</div>
                <div class="card-text">
                    <form>
                        <div v-if="user.wallet.user_id != user.id" class="form-group row mb-0">
                            <label for="manager" class="col-sm-4 col-form-label">Managed by</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="manager">
                                    <router-link :to="{ path: '/user/' + user.wallet.user_id }">{{ user.wallet.user_email }}</router-link>
                                </span>
                            </div>
                        </div>
                        <div class="form-group row mb-0">
                            <label for="userid" class="col-sm-4 col-form-label">ID <span class="text-muted">(Created at)</span></label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="userid">
                                    {{ user.id }} <span class="text-muted">({{ user.created_at }})</span>
                                </span>
                            </div>
                        </div>
                        <div class="form-group row mb-0">
                            <label for="status" class="col-sm-4 col-form-label">Status</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="status">
                                    <span :class="$root.userStatusClass(user)">{{ $root.userStatusText(user) }}</span>
                                </span>
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
                                <span class="form-control-plaintext" id="external_email">
                                    <a v-if="user.external_email" :href="'mailto:' + user.external_email">{{ user.external_email }}</a>
                                    <button type="button" class="btn btn-secondary btn-sm">Edit</button>
                                </span>
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
                    <div class="card-title">Account balance <span :class="balance < 0 ? 'text-danger' : 'text-success'"><strong>{{ $root.price(balance) }}</strong></span></div>
                    <div class="card-text">
                        <form>
                            <div class="form-group row mb-0">
                                <label for="first_name" class="col-sm-2 col-form-label">Discount:</label>
                                <div class="col-sm-10">
                                    <span class="form-control-plaintext" id="discount">
                                        <span>{{ wallet_discount ? (wallet_discount + '% - ' + wallet_discount_description) : 'none' }}</span>
                                        <button type="button" class="btn btn-secondary btn-sm" @click="discountEdit">Edit</button>
                                    </span>
                                </div>
                            </div>
                        </form>
                    </div>
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
                        <table class="table table-sm table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Subscription</th>
                                    <th scope="col">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(sku, sku_id) in skus" :id="'sku' + sku_id" :key="sku_id">
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
                                <tr v-for="domain in domains" :id="'domain' + domain.id" :key="domain.id">
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
                                <tr v-for="item in users" :id="'user' + item.id" :key="item.id">
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
                            <select v-model="wallet_discount_id" class="custom-select">
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
    </div>
</template>

<script>
    export default {
        beforeRouteUpdate (to, from, next) {
            // An event called when the route that renders this component has changed,
            // but this component is reused in the new route.
            // Required to handle links from /user/XXX to /user/YYY
            next()
            this.$parent.routerReload()
        },
        data() {
            return {
                balance: 0,
                discount: 0,
                discount_description: '',
                discounts: [],
                wallet_discount: 0,
                wallet_discount_description: '',
                wallet_discount_id: '',
                domains: [],
                skus: [],
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

                    let keys = ['first_name', 'last_name', 'external_email', 'billing_address', 'phone']
                    let country = this.user.settings.country

                    if (country) {
                        this.user.country = window.config.countries[country][1]
                    }

                    keys.forEach(key => { this.user[key] = this.user.settings[key] })

                    this.discount = this.user.wallet.discount
                    this.discount_description = this.user.wallet.discount_description

                    // TODO: currencies, multi-wallets, accounts
                    this.user.wallets.forEach(wallet => {
                        this.balance += wallet.balance
                    })

                    this.wallet_discount = this.user.wallets[0].discount
                    this.wallet_discount_id = this.user.wallets[0].discount_id || ''
                    this.wallet_discount_description = this.user.wallets[0].discount_description

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
            discountEdit() {
                $('#discount-dialog')
                    .on('shown.bs.modal', (e, a) => {
                        $(e.target).find('select').focus()
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
            submitDiscount() {
                let dialog = $('#discount-dialog').modal('hide')

                axios.put('/api/v4/wallets/' + this.user.wallets[0].id, { discount: this.wallet_discount_id })
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toastr('success', response.data.message)
                            this.wallet_discount = response.data.discount
                            this.wallet_discount_id = response.data.discount_id || ''
                            this.wallet_discount_description = response.data.discount_description

                            // Update prices in Subscriptions tab
                            if (this.user.wallet.id == response.data.id) {
                                this.discount = this.wallet_discount
                                this.discount_description = this.wallet_discount_description

                                this.skus.forEach(sku => {
                                    sku.price = this.$root.priceLabel(sku.cost, sku.units, this.discount)
                                })
                            }
                        }
                    })
            },
        }
    }
</script>
