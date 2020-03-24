<template>
    <div class="container">
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
                            <label for="email" class="col-sm-4 col-form-label">Email</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="email" :disabled="user_id !== 'new'" required v-model="user.email">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="aliases" class="col-sm-4 col-form-label">Email aliases</label>
                            <div class="col-sm-8">
                                <textarea class="form-control listinput" id="aliases"></textarea>
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
                                <table class="table form-list">
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
                                                {{ $root.price(pkg.cost) + '/month' }}
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
                            </div>
                        </div>
                        <div v-if="user_id !== 'new'" id="user-skus" class="form-group row">
                            <label class="col-sm-4 col-form-label">Subscriptions</label>
                            <div class="col-sm-8">
                                <table class="table form-list">
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
                                                <input type="checkbox" :value="sku.id" :disabled="sku.readonly" :checked="sku.enabled">
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
                                                {{ $root.price(sku.cost) + '/month' }}
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
    export default {
        data() {
            return {
                user_id: null,
                user: {},
                packages: [],
                package_id: null,
                skus: []
            }
        },
        created() {
            this.user_id = this.$route.params.user

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
                        $('#aliases').val(response.data.aliases.join("\n"))
                        listinput('#aliases')

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
            if (this.user_id === 'new') {
                listinput('#aliases')
            }

            $('#first_name').focus()
        },
        methods: {
            submit() {
                this.$root.clearFormValidation($('#user-info form'))

                this.user.aliases = $('#aliases').val().split("\n")

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
                            this.$toastr('success', response.data.message)
                        }

                        // on new user redirect to users list
                        if (this.user_id === 'new') {
                            this.$router.push({ name: 'users' })
                        }
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
                let sku, i

                for (i = 0; i < this.skus.length; i++) {
                    if (this.skus[i].id == sku_id) {
                        sku = this.skus[i];
                    }
                }

                // Update the label
                input.prev().text(value + ' ' + sku.range.unit)

                // Update the price
                record.find('.price').text(this.$root.price(sku.cost * (value - sku.units_free)) + '/month')
            }
        }
    }

    // List widget
    // TODO: move it to a separate component file when needed
    function listinput(elem)
    {
        elem = $(elem).addClass('listinput');

        let widget = $('<div class="listinput-widget">')
        let main_row = $('<div class="input-group">')
        let wrap = $('<div class="input-group-append">')
        let input = $('<input type="text" class="form-control main-input">')
        let add_btn = $('<a href="#" class="btn btn-outline-secondary">').text('Add')

        let update = () => {
            let value = []

            widget.find('input:not(.main-input)').each((index, input) => {
                if (input.value) {
                    value.push(input.value)
                }
            })

            elem.val(value.join("\n"))
        }

        let add_func = (value) => {
            let row = $('<div class="input-group">')
            let rinput = $('<input type="text" class="form-control">').val(value)
            let rwrap = $('<div class="input-group-append">')
            let del_btn = $('<a href="#" class="btn btn-outline-secondary">')
                .text('Remove')
                .on('click', e => {
                    row.remove()
                    input.focus()
                    update()
                })

            widget.append(row.append(rinput).append(rwrap.append(del_btn)))
        }

        // Create the widget and add to DOM
        widget.append(main_row.append(input).append(wrap.append(add_btn)))
            .insertAfter(elem)

        // Add rows for every line in the original textarea
        let value = $.trim(elem.val())
        if (value.length) {
            value.split("\n").forEach(add_func)
        }

        // Click handler on the Add button
        add_btn.on('click', e => {
            let value = input.val()

            if (!value) {
                return;
            }

            input.val('').focus();
            add_func(value)
            update()
        })

        // Enter key handler on main input
        input.on('keydown', function(e) {
            if (e.which == 13 && this.value) {
                add_btn.click()
                return false
            }
        })
    }

</script>
