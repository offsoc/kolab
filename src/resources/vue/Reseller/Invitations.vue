<template>
    <div class="container">
        <div class="card" id="invitations">
            <div class="card-body">
                <div class="card-title">
                    {{ $t('invitation.title') }}
                </div>
                <div class="card-text">
                    <div class="mb-2 d-flex">
                        <list-search :placeholder="$t('invitation.search')" :on-search="searchInvitations"></list-search>
                        <btn class="btn-success create-invite ms-1" @click="inviteUserDialog" icon="envelope-open-text">{{ $t('invitation.create') }}</btn>
                    </div>

                    <table id="invitations-list" class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th scope="col">{{ $t('user.ext-email') }}</th>
                                <th scope="col">{{ $t('form.created') }}</th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="inv in invitations" :id="'i' + inv.id" :key="inv.id">
                                <td class="email">
                                    <svg-icon icon="envelope-open-text" :class="statusClass(inv)" :title="$t('invitation.status-' + statusLabel(inv))"></svg-icon>
                                    <span>{{ inv.email }}</span>
                                </td>
                                <td class="datetime">
                                    {{ inv.created }}
                                </td>
                                <td class="buttons">
                                    <btn class="text-danger button-delete p-0 ms-1" @click="deleteInvite(inv.id)" icon="trash-alt">
                                        <span class="btn-label">{{ $t('btn.delete') }}</span>
                                    </btn>
                                    <btn class="button-resend p-0 ms-1" :disabled="inv.isNew || inv.isCompleted" @click="resendInvite(inv.id)" icon="redo">
                                        <span class="btn-label">{{ $t('btn.resend') }}</span>
                                    </btn>
                                </td>
                            </tr>
                        </tbody>
                        <list-foot :text="$t('invitation.empty-list')" colspan="3"></list-foot>
                    </table>
                    <list-more v-if="hasMore" :on-click="loadInvitations"></list-more>
                </div>
            </div>
        </div>

        <div id="invite-create" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $t('invitation.create-title') }}</h5>
                        <btn class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></btn>
                    </div>
                    <div class="modal-body">
                        <form>
                            <p>{{ $t('invitation.create-email') }}</p>
                            <div>
                                <input id="email" type="text" class="form-control" name="email">
                            </div>
                            <div class="form-separator"><hr><span>{{ $t('form.or') }}</span></div>
                            <p>{{ $t('invitation.create-csv') }}</p>
                            <div>
                                <input id="file" type="file" class="form-control" name="csv">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <btn class="btn-secondary modal-cancel" data-bs-dismiss="modal">{{ $t('btn.cancel') }}</btn>
                        <btn class="btn-primary modal-action" icon="paper-plane" @click="inviteUser()">{{ $t('invitation.send') }}</btn>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { Modal } from 'bootstrap'
    import { library } from '@fortawesome/fontawesome-svg-core'
    import { faEnvelopeOpenText, faPaperPlane, faRedo } from '@fortawesome/free-solid-svg-icons'
    import ListTools from '../Widgets/ListTools'

    library.add(faEnvelopeOpenText, faPaperPlane, faRedo)

    export default {
        mixins: [ ListTools ],
        data() {
            return {
                invitations: []
            }
        },
        mounted() {
            this.loadInvitations({ init: true })

            $('#invite-create')[0].addEventListener('shown.bs.modal', event => {
                $('input', event.target).first().focus()
            })
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
                let label = this.$t('btn.file')
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
                            this.dialog.hide()
                            this.$toast.success(response.data.message)
                            if (response.data.count) {
                                this.loadInvitations({ reset: true })
                            }
                        }
                    })
            },
            inviteUserDialog() {
                const dialog = $('#invite-create')[0]
                const form = $('form', dialog)

                form.get(0).reset()
                this.fileChange({ target: form.find('#file')[0] }) // resets file input label
                this.$root.clearFormValidation(form)

                this.dialog = new Modal(dialog)
                this.dialog.show()
            },
            loadInvitations(params) {
                this.listSearch('invitations', '/api/v4/invitations', params)
            },
            resendInvite(id) {
                axios.post('/api/v4/invitations/' + id + '/resend')
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)

                            // Update the invitation record
                            const index = this.invitations.findIndex(item => item.id == id)
                            if (index > -1) {
                                this.$set(this.invitations, index, response.data.invitation)
                            }
                        }
                    })
            },
            searchInvitations(search) {
                this.loadInvitations({ reset: true, search })
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
            statusLabel(invitation) {
                if (invitation.isCompleted) {
                    return 'completed'
                }

                if (invitation.isFailed) {
                    return 'failed'
                }

                if (invitation.isSent) {
                    return 'sent'
                }

                return 'new'
            }
        }
    }
</script>
