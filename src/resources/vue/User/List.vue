<template>
    <div class="container">
        <div class="card" id="user-list">
            <div class="card-body">
                <div class="card-title">User Accounts</div>
                <div class="card-text">
                    <router-link class="btn btn-primary create-user" :to="{ path: 'user/new' }" tag="button">
                        <svg-icon icon="user"></svg-icon> Create user
                    </router-link>
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">Primary Email</th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users" :id="'user' + user.id" :key="user.id">
                                <td>
                                    <router-link :to="{ path: 'user/' + user.id }">{{ user.email }}</router-link>
                                </td>
                                <td>
                                    <button v-if="$root.isController(user.wallet_id)" class="btn btn-danger button-delete" @click="deleteUser(user.id)">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="delete-warning" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Do you really want to delete this user permanently?
                            This will delete all account data and withdraw the permission to access the email account.
                            Please note that this action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger modal-action" @click="deleteUser()">
                            <svg-icon icon="trash-alt"></svg-icon> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                users: [],
                current_user: null
            }
        },
        created() {
            axios.get('/api/v4/users')
                .then(response => {
                    this.users = response.data
                })
                .catch(this.$root.errorHandler)
        },
        methods: {
            deleteUser(id) {
                let dialog = $('#delete-warning').modal('hide')

                // Delete the user from the confirm dialog
                if (!id && this.current_user) {
                    id = this.current_user.id
                    axios.delete('/api/v4/users/' + id)
                        .then(response => {
                            if (response.data.status == 'success') {
                                this.$toastr('success', response.data.message)
                                $('#user' + id).remove()
                            }
                        })

                    return
                }


                // Deleting self, redirect to /profile/delete page
                if (id == this.$store.state.authInfo.id) {
                    this.$router.push({ name: 'profile-delete' })
                    return
                }

                // Display the warning
                if (this.current_user = this.getUser(id)) {
                    dialog.find('.modal-title').text('Delete ' + this.current_user.email)
                    dialog.on('shown.bs.modal', () => {
                        dialog.find('button.modal-cancel').focus()
                    }).modal()
                }
            },
            getUser(id) {
                for (let i = 0; i < this.users.length; i++) {
                    if (this.users[i].id == id) {
                        return this.users[i]
                    }
                }
            }
        }
    }
</script>
