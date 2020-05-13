<template>
    <div class="container">
        <status-component v-if="user_id !== 'new'" v-bind:status="status" @status-update="statusUpdate"></status-component>

        <div class="card" id="user-info">
            <div class="card-body">
                <div class="card-title" v-if="user_id !== 'new'">User account</div>
                <div class="card-title" v-if="user_id === 'new'">New user account</div>
                <div class="card-text">
                    <form @submit.prevent="submit">
                        <div v-if="user_id !== 'new'" class="form-group row">
                            <label for="first_name" class="col-sm-4 col-form-label">Status</label>
                            <div class="col-sm-8">
                                <span :class="$root.userStatusClass(user) + ' form-control-plaintext'" id="status">{{ $root.userStatusText(user) }}</span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="first_name" class="col-sm-4 col-form-label">First name</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="first_name" v-model="user.first_name">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="last_name" class="col-sm-4 col-form-label">Last name</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="last_name" v-model="user.last_name">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="organization" class="col-sm-4 col-form-label">Organization</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="organization" v-model="user.organization">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="email" class="col-sm-4 col-form-label">Email</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="email" :disabled="user_id !== 'new'" required v-model="user.email">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="aliases-input" class="col-sm-4 col-form-label">Email aliases</label>
                            <div class="col-sm-8">
                                <list-input id="aliases" :list="user.aliases"></list-input>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="password" class="col-sm-4 col-form-label">Password</label>
                            <div class="col-sm-8">
                                <input type="password" class="form-control" id="password" v-model="user.password" :required="user_id === 'new'">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="password_confirmaton" class="col-sm-4 col-form-label">Confirm password</label>
                            <div class="col-sm-8">
                                <input type="password" class="form-control" id="password_confirmation" v-model="user.password_confirmation" :required="user_id === 'new'">
                            </div>
                        </div>
                        <div v-if="user_id === 'new'" id="user-packages" class="form-group row">
                            <label class="col-sm-4 col-form-label">Package</label>
                            <div class="col-sm-8">
                                <table class="table table-sm form-list">
                                    <thead class="thead-light sr-only">
                                        <tr>
                                            <th scope="col"></th>
                                            <th scope="col">Package</th>
                                            <th scope="col">Price</th>
                                            <th scope="col"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="pkg in packages" :id="'p' + pkg.id" :key="pkg.id">
                                            <td class="selection">
                                                <input type="checkbox" :value="pkg.id" @click="selectPackage" :checked="pkg.id == package_id">
                                            </td>
                                            <td class="name">
                                                {{ pkg.name }}
                                            </td>
                                            <td class="price text-nowrap">
                                                {{ $root.priceLabel(pkg.cost, 1, discount) }}
                                            </td>
                                            <td class="buttons">
                                                <button v-if="pkg.description" type="button" class="btn btn-link btn-lg p-0" v-tooltip.click="pkg.description">
                                                    <svg-icon icon="info-circle"></svg-icon>
                                                    <span class="sr-only">More information</span>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <small v-if="discount > 0" class="hint">
                                    <hr class="m-0">
                                    &sup1; applied discount: {{ discount }}% - {{ discount_description }}
                                </small>
                            </div>
                        </div>
                        <div v-if="user_id !== 'new'" id="user-skus" class="form-group row">
                            <label class="col-sm-4 col-form-label">Subscriptions</label>
                            <div class="col-sm-8">
                                <table class="table table-sm form-list">
                                    <thead class="thead-light sr-only">
                                        <tr>
                                            <th scope="col"></th>
                                            <th scope="col">Subscription</th>
                                            <th scope="col">Price</th>
                                            <th scope="col"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="sku in skus" :id="'s' + sku.id" :key="sku.id">
                                            <td class="selection">
                                                <input type="checkbox" @input="onInputSku"
                                                       :value="sku.id"
                                                       :disabled="sku.readonly"
                                                       :checked="sku.enabled"
                                                       :dusk="'sku-input-' + sku.title"
                                                >
                                            </td>
                                            <td class="name">
                                                <span class="name">{{ sku.name }}</span>
                                                <div v-if="sku.range" class="range-input">
                                                    <label class="text-nowrap">{{ sku.range.min }} {{ sku.range.unit }}</label>
                                                    <input
                                                        type="range" class="custom-range" @input="rangeUpdate"
                                                        :value="sku.value || sku.range.min"
                                                        :min="sku.range.min"
                                                        :max="sku.range.max"
                                                    >
                                                </div>
                                            </td>
                                            <td class="price text-nowrap">
                                                {{ $root.priceLabel(sku.cost, 1, discount) }}
                                            </td>
                                            <td class="buttons">
                                                <button v-if="sku.description" type="button" class="btn btn-link btn-lg p-0" v-tooltip.click="sku.description">
                                                    <svg-icon icon="info-circle"></svg-icon>
                                                    <span class="sr-only">More information</span>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <small v-if="discount > 0" class="hint">
                                    <hr class="m-0">
                                    &sup1; applied discount: {{ discount }}% - {{ discount_description }}
                                </small>
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit"><svg-icon icon="check"></svg-icon> Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import ListInput from '../Widgets/ListInput'
    import StatusComponent from '../Widgets/Status'

    export default {
        components: {
            ListInput,
            StatusComponent
        },
        data() {
            return {
                discount: 0,
                discount_description: '',
                user_id: null,
                user: { aliases: [] },
                packages: [],
                package_id: null,
                skus: [],
                status: {}
            }
        },
        created() {
            this.user_id = this.$route.params.user

            let wallet = this.$store.state.authInfo.accounts[0]

            if (!wallet) {
                wallet = this.$store.state.authInfo.wallets[0]
            }

            if (wallet && wallet.discount) {
                this.discount = wallet.discount
                this.discount_description = wallet.discount_description
            }

            if (this.user_id === 'new') {
                // do nothing (for now)
                axios.get('/api/v4/packages')
                    .then(response => {
                        this.packages = response.data.filter(pkg => !pkg.isDomain)
                        this.package_id = this.packages[0].id
                    })
                    .catch(this.$root.errorHandler)
            }
            else {
                axios.get('/api/v4/users/' + this.user_id)
                    .then(response => {
                        this.user = response.data
                        this.user.first_name = response.data.settings.first_name
                        this.user.last_name = response.data.settings.last_name
                        this.user.organization = response.data.settings.organization
                        this.discount = this.user.wallet.discount
                        this.discount_description = this.user.wallet.discount_description
                        this.status = response.data.statusInfo

                        axios.get('/api/v4/skus')
                            .then(response => {
                                // "merge" SKUs with user entitlement-SKUs
                                this.skus = response.data
                                    .filter(sku => sku.type == 'user')
                                    .map(sku => {
                                        if (sku.id in this.user.skus) {
                                            sku.enabled = true
                                            sku.value = this.user.skus[sku.id].count
                                        } else if (!sku.readonly) {
                                            sku.enabled = false
                                        }

                                        return sku
                                    })

                                // Update all range inputs (and price)
                                this.$nextTick(() => {
                                    $('#user-skus input[type=range]').each((idx, elem) => { this.rangeUpdate(elem) })
                                })
                            })
                            .catch(this.$root.errorHandler)
                    })
                    .catch(this.$root.errorHandler)
            }
        },
        mounted() {
            $('#first_name').focus()
        },
        methods: {
            submit() {
                this.$root.clearFormValidation($('#user-info form'))

                let method = 'post'
                let location = '/api/v4/users'

                if (this.user_id !== 'new') {
                    method = 'put'
                    location += '/' + this.user_id

                    let skus = {}
                    $('#user-skus input[type=checkbox]:checked').each((idx, input) => {
                        let id = $(input).val()
                        let range = $(input).parents('tr').first().find('input[type=range]').val()

                        skus[id] = range || 1
                    })
                    this.user.skus = skus
                } else {
                    this.user.package = this.package_id
                }

                axios[method](location, this.user)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                        }

                        // on new user redirect to users list
                        if (this.user_id === 'new') {
                            this.$router.push({ name: 'users' })
                        }
                    })
            },
            onInputSku(e) {
                let input = e.target
                let sku = this.findSku(input.value)
                let required = []

                // We use 'readonly', not 'disabled', because we might want to handle
                // input events. For example to display an error when someone clicks
                // the locked input
                if (input.readOnly) {
                    input.checked = !input.checked
                    // TODO: Display an alert explaining why it's locked
                    return
                }

                // TODO: Following code might not work if we change definition of forbidden/required
                //       or we just need more sophisticated SKU dependency rules

                if (input.checked) {
                    // Check if a required SKU is selected, alert the user if not
                    (sku.required || []).forEach(title => {
                        this.skus.forEach(item => {
                            let checkbox
                            if (item.handler == title && (checkbox = $('#s' + item.id).find('input[type=checkbox]')[0])) {
                                if (!checkbox.checked) {
                                    required.push(item.name)
                                }
                            }
                        })
                    })

                    if (required.length) {
                        input.checked = false
                        return alert(sku.name + ' requires ' + required.join(', ') + '.')
                    }
                } else {
                    // Uncheck all dependent SKUs, e.g. when unchecking Groupware we also uncheck Activesync
                    // TODO: Should we display an alert instead?
                    this.skus.forEach(item => {
                        if (item.required && item.required.indexOf(sku.handler) > -1) {
                            $('#s' + item.id).find('input[type=checkbox]').prop('checked', false)
                        }
                    })
                }

                // Uncheck+lock/unlock conflicting SKUs
                (sku.forbidden || []).forEach(title => {
                    this.skus.forEach(item => {
                        let checkbox
                        if (item.handler == title && (checkbox = $('#s' + item.id).find('input[type=checkbox]')[0])) {
                            if (input.checked) {
                                checkbox.checked = false
                                checkbox.readOnly = true
                            } else {
                                checkbox.readOnly = false
                            }
                        }
                    })
                })
            },
            selectPackage(e) {
                // Make sure there always is only one package selected
                $('#user-packages input').prop('checked', false)
                this.package_id = $(e.target).prop('checked', false).val()
            },
            rangeUpdate(e) {
                let input = $(e.target || e)
                let value = input.val()
                let record = input.parents('tr').first()
                let sku_id = record.find('input[type=checkbox]').val()
                let sku = this.findSku(sku_id)
                let cost = sku.cost

                // Update the label
                input.prev().text(value + ' ' + sku.range.unit)

                // Update the price
                record.find('.price').text(this.$root.priceLabel(cost, value - sku.units_free, this.discount))
            },
            findSku(id) {
                for (let i = 0; i < this.skus.length; i++) {
                    if (this.skus[i].id == id) {
                        return this.skus[i];
                    }
                }
            },
            statusUpdate(user) {
                this.user = Object.assign({}, this.user, user)
            }
        }
    }
</script>
