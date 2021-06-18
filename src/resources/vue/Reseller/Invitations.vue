<template>
    <div class="container">
        <div class="card" id="invitations">
            <div class="card-body">
                <div class="card-title">
                    Signup Invitations
                </div>
                <div class="card-text">
                    <div class="mb-2 d-flex">
                        <form @submit.prevent="searchInvitations" id="search-form" class="input-group" style="flex:1">
                            <input class="form-control" type="text" placeholder="Email address or domain" v-model="search">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary"><svg-icon icon="search"></svg-icon> Search</button>
                            </div>
                        </form>
                        <div>
                            <button class="btn btn-success create-invite ml-1" @click="inviteUserDialog">
                                <svg-icon icon="envelope-open-text"></svg-icon> Create invite(s)
                            </button>
                        </div>
                    </div>

                    <table id="invitations-list" class="table table-sm table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">External Email</th>
                                <th scope="col">Created</th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="inv in invitations" :id="'i' + inv.id" :key="inv.id">
                                <td class="email">
                                    <svg-icon icon="envelope-open-text" :class="statusClass(inv)" :title="statusText(inv)"></svg-icon>
                                    <span>{{ inv.email }}</span>
                                </td>
                                <td class="datetime">
                                    {{ inv.created }}
                                </td>
                                <td class="buttons">
                                    <button class="btn text-danger button-delete p-0 ml-1" @click="deleteInvite(inv.id)">
                                        <svg-icon icon="trash-alt"></svg-icon>
                                        <span class="btn-label">Delete</span>
                                    </button>
                                    <button class="btn button-resend p-0 ml-1" :disabled="inv.isNew || inv.isCompleted" @click="resendInvite(inv.id)">
                                        <svg-icon icon="redo"></svg-icon>
                                        <span class="btn-label">Resend</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-fake-body">
                            <tr>
                                <td colspan="3">There are no invitations in the database.</td>
                            </tr>
                        </tfoot>
                    </table>
                    <div class="text-center p-3" id="more-loader" v-if="hasMore">
                        <button class="btn btn-secondary" @click="loadInvitations(true)">Load more</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="invite-create" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Invite for a signup</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form>
                            <p>Enter an email address of the person you want to invite.</p>
                            <div>
                                <input id="email" type="text" class="form-control" name="email">
                            </div>
                            <div class="form-separator"><hr><span>or</span></div>
                            <p>
                                To send multiple invitations at once, provide a CSV (comma separated) file,
                                or alternatively a plain-text file, containing one email address per line.
                            </p>
                            <div class="custom-file">
                                <input id="file" type="file" class="custom-file-input" name="csv" @change="fileChange">
                                <label class="custom-file-label" for="file">Choose file...</label>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary modal-action" @click="inviteUser()">
                            <svg-icon icon="paper-plane"></svg-icon> Send invite(s)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { library } from '@fortawesome/fontawesome-svg-core'
    import { faEnvelopeOpenText, faPaperPlane, faRedo } from '@fortawesome/free-solid-svg-icons'

    library.add(faEnvelopeOpenText, faPaperPlane, faRedo)

    export default {
        data() {
            return {
                invitations: [],
                hasMore: false,
                page: 1,
                search: ''
            }
        },
        mounted() {
            this.$root.startLoading()
            this.loadInvitations(null, () => this.$root.stopLoading())
        },
        methods: {
            deleteInvite(id) {
                axios.delete('/api/v4/invitations/' + id)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)

                            // Remove the invitation record from the list
                            const index = this.invitations.findIndex(item => item.id == id)
                            this.invitations.splice(index, 1)
                        }
                    })
            },
            fileChange(e) {
                let label = 'Choose file...'
                let files = e.target.files

                if (files.length) {
                    label = files[0].name
                    if (files.length > 1) {
                        label += ', ...'
                    }
                }

                $(e.target).next().text(label)
            },
            inviteUser() {
                let dialog = $('#invite-create')
                let post = new FormData()
                let params = { headers: { 'Content-Type': 'multipart/form-data' } }

                post.append('email', dialog.find('#email').val())

                this.$root.clearFormValidation(dialog.find('form'))

                // Append the file to POST data
                let files = dialog.find('#file').get(0).files
                if (files.length) {
                    post.append('file', files[0])
                }

                axios.post('/api/v4/invitations', post, params)
                    .then(response => {
                        if (response.data.status == 'success') {
                            dialog.modal('hide')
                            this.$toast.success(response.data.message)
                            if (response.data.count) {
                                this.loadInvitations({ reset: true })
                            }
                        }
                    })
            },
            inviteUserDialog() {
                let dialog = $('#invite-create')
                let form = dialog.find('form')

                form.get(0).reset()
                this.fileChange({ target: form.find('#file')[0] }) // resets file input label
                this.$root.clearFormValidation(form)

                dialog.on('shown.bs.modal', () => {
                    dialog.find('input').get(0).focus()
                }).modal()
            },
            loadInvitations(params, callback) {
                let loader
                let get = {}

                if (params) {
                    if (params.reset) {
                        this.invitations = []
                        this.page = 0
                    }

                    get.page = params.page || (this.page + 1)

                    if (typeof params === 'object' && 'search' in params) {
                        get.search = params.search
                        this.currentSearch = params.search
                    } else {
                        get.search = this.currentSearch
                    }

                    loader = $(get.page > 1 ? '#more-loader' : '#invitations-list tfoot td')
                } else {
                    this.currentSearch = null
                }

                this.$root.addLoader(loader)

                axios.get('/api/v4/invitations', { params: get })
                    .then(response => {
                        this.$root.removeLoader(loader)

                        // Note: In Vue we can't just use .concat()
                        for (let i in response.data.list) {
                            this.$set(this.invitations, this.invitations.length, response.data.list[i])
                        }
                        this.hasMore = response.data.hasMore
                        this.page = response.data.page || 1

                        if (callback) {
                            callback()
                        }
                    })
                    .catch(error => {
                        this.$root.removeLoader(loader)

                        if (callback) {
                            callback()
                        }
                    })
            },
            resendInvite(id) {
                axios.post('/api/v4/invitations/' + id + '/resend')
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)

                            // Update the invitation record
                            const index = this.invitations.findIndex(item => item.id == id)
                            this.invitations.splice(index, 1)
                            this.$set(this.invitations, index, response.data.invitation)
                        }
                    })
            },
            searchInvitations() {
                this.loadInvitations({ reset: true, search: this.search })
            },
            statusClass(invitation) {
                if (invitation.isCompleted) {
                    return 'text-success'
                }

                if (invitation.isFailed) {
                    return 'text-danger'
                }

                if (invitation.isSent) {
                    return 'text-primary'
                }

                return ''
            },
            statusText(invitation) {
                if (invitation.isCompleted) {
                    return 'User signed up'
                }

                if (invitation.isFailed) {
                    return 'Sending failed'
                }

                if (invitation.isSent) {
                    return 'Sent'
                }

                return 'Not sent yet'
            }
        }
    }
</script>
