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
                        <btn class="btn-success create-invite ms-1" @click="$refs.createDialog.show()" icon="envelope-open-text">{{ $t('invitation.create') }}</btn>
                    </div>

                    <list-table id="invitations-list" :list="invitations" :setup="setup">
                        <template #email="{ item }">
                            <svg-icon icon="envelope-open-text" :class="statusClass(item)" :title="$t('invitation.status-' + statusLabel(item))"></svg-icon>
                            &nbsp;<span>{{ item.email }}</span>
                        </template>
                        <template #buttons="{ item }">
                            <btn class="text-danger button-delete p-0 ms-1" @click="deleteInvite(item.id)" icon="trash-can">
                                <span class="btn-label">{{ $t('btn.delete') }}</span>
                            </btn>
                            <btn class="button-resend p-0 ms-1" :disabled="item.isNew || item.isCompleted" @click="resendInvite(item.id)" icon="rotate-left">
                                <span class="btn-label">{{ $t('btn.resend') }}</span>
                            </btn>
                        </template>
                    </list-table>
                    <list-more v-if="hasMore" :on-click="loadInvitations"></list-more>
                </div>
            </div>
        </div>

        <modal-dialog id="invite-create" ref="createDialog" :title="$t('invitation.create-title')" @click="inviteUser()"
                      :buttons="[{ className: 'btn-primary modal-action', icon: 'paper-plane', label: 'invitation.send' }]"
        >
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
        </modal-dialog>
    </div>
</template>

<script>
    import ListTools from '../Widgets/ListTools'
    import ModalDialog from '../Widgets/ModalDialog'

    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faEnvelopeOpenText').definition,
        require('@fortawesome/free-solid-svg-icons/faPaperPlane').definition,
        require('@fortawesome/free-solid-svg-icons/faRotateLeft').definition,
    )

    export default {
        components: {
            ModalDialog
        },
        mixins: [ ListTools ],
        data() {
            return {
                invitations: [],
                setup: {
                    buttons: true,
                    model: 'invitation',
                    columns: [
                        {
                            prop: 'email',
                            label: 'user.ext-email',
                            className: 'email',
                            contentSlot: 'email'
                        },
                        {
                            prop: 'created',
                            className: 'datetime'
                        }
                    ]
                }
            }
        },
        mounted() {
            this.loadInvitations({ init: true })

            this.$refs.createDialog.events({
                show: (event) => {
                    const form = $(event.target).find('form')
                    form.get(0).reset()
                    this.fileChange({ target: form.find('#file')[0] }) // resets file input label
                }
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
                            this.$refs.createDialog.hide()
                            this.$toast.success(response.data.message)
                            if (response.data.count) {
                                this.loadInvitations({ reset: true })
                            }
                        }
                    })
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
