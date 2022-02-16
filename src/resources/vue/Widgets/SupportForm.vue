<template>
    <div class="modal" id="support-dialog" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form class="modal-content" @submit.prevent="submit">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $t('support.title') }}</h5>
                    <btn class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></btn>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="support-user" class="form-label">{{ $t('support.id') }}</label>
                        <input id="support-user" type="text" class="form-control" :placeholder="$t('support.id-pl')" v-model="user" />
                        <small class="text-muted">{{ $t('support.id-hint') }}</small>
                    </div>
                    <div class="mb-3">
                        <label for="support-name" class="form-label">{{ $t('support.name') }}</label>
                        <input id="support-name" type="text" class="form-control" :placeholder="$t('support.name-pl')" v-model="name" />
                    </div>
                    <div class="mb-3">
                        <label for="support-email" class="form-label">{{ $t('support.email') }}</label>
                        <input id="support-email" type="email" class="form-control" :placeholder="$t('support.email-pl')" v-model="email" required />
                    </div>
                    <div class="mb-3">
                        <label for="support-summary" class="form-label">{{ $t('support.summary') }}</label>
                        <input id="support-summary" type="text" class="form-control" :placeholder="$t('support.summary-pl')" v-model="summary" required />
                    </div>
                    <div>
                        <label for="support-body" class="form-label">{{ $t('support.expl') }}</label>
                        <textarea id="support-body" class="form-control" rows="5" v-model="body" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <btn class="btn-secondary modal-cancel" data-bs-dismiss="modal">{{ $t('btn.cancel') }}</btn>
                    <btn type="submit" class="btn-primary modal-action" icon="check">{{ $t('btn.submit') }}</btn>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
    import { Modal } from 'bootstrap'

    export default {
        data() {
            return {
                body: '',
                email: '',
                name: '',
                summary: '',
                user: ''
            }
        },
        mounted() {
            const dialog = this.$el

            dialog.addEventListener('hide.bs.modal', () => {
                this.lockForm(false)
                if (this.cancelToken) {
                    this.cancelToken()
                    this.cancelToken = null
                }
            })

            dialog.addEventListener('show.bs.modal', () => {
                this.cancelToken = null
            })

            dialog.addEventListener('shown.bs.modal', () => {
                $(dialog).find('input').first().focus()
            })

            this.dialog = new Modal(dialog)
        },
        methods: {
            lockForm(lock) {
                $(this.$el).find('input,textarea,.modal-action').prop('disabled', lock)
            },
            showDialog() {
                this.dialog.show()
            },
            submit() {
                this.lockForm(true)

                let params = {
                    user: this.user,
                    name: this.name,
                    email: this.email,
                    summary: this.summary,
                    body: this.body
                }

                const CancelToken = axios.CancelToken

                let args = {
                    cancelToken: new CancelToken((c) => {
                        this.cancelToken = c;
                    })
                }

                axios.post('/api/v4/support/request', params, args)
                    .then(response => {
                        this.summary = ''
                        this.body = ''
                        this.lockForm(false)
                        this.dialog.hide()
                        this.$toast.success(response.data.message)
                    })
                    .catch(error => {
                        this.lockForm(false)
                    })
            }
        }
    }
</script>
