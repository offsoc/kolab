<template>
    <div class="container">
        <div class="card" id="user-info">
            <div class="card-body">
                <div class="card-title" v-if="user_id !== 'new'">User account</div>
                <div class="card-title" v-if="user_id === 'new'">New user account</div>
                <div class="card-text">
                    <form @submit.prevent="submit">
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
                        <button class="btn btn-primary" type="submit">Submit</button>
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
                user: {}
            }
        },
        created() {
            this.user_id = this.$route.params.user

            if (this.user_id === 'new') {
                // do nothing (for now)
            }
            else {
                axios.get('/api/v4/users/' + this.user_id)
                    .then(response => {
                        this.user = response.data
                        this.user.first_name = response.data.settings.first_name
                        this.user.last_name = response.data.settings.last_name
                        $('#aliases').val(response.data.aliases.join("\n"))
                        listinput('#aliases')
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
                }

                axios[method](location, this.user)
                    .then(response => {
                        delete this.user.password
                        delete this.user.password_confirm

                        if (response.data.status == 'success') {
                            this.$toastr('success', response.data.message)
                        }

                        // on new user redirect to users list
                        if (this.user_id === 'new') {
                            this.$route.push({ name: 'users' })
                        }
                    })
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
