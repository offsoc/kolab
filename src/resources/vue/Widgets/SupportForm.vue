<template>
    <div class="modal" id="support-dialog" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form class="modal-content" @submit.prevent="submit">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $t('support.title') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" :aria-label="$t('btn.close')">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form">
                        <div class="form-group">
                            <label for="support-user">{{ $t('support.id') }}</label>
                            <input id="support-user" type="text" class="form-control" :placeholder="$t('support.id-pl')" v-model="user" />
                            <small class="form-text text-muted">{{ $t('support.id-hint') }}</small>
                        </div>
                        <div class="form-group">
                            <label for="support-name">{{ $t('support.name') }}</label>
                            <input id="support-name" type="text" class="form-control" :placeholder="$t('support.name-pl')" v-model="name" />
                        </div>
                        <div class="form-group">
                            <label for="support-email">{{ $t('support.email') }}</label>
                            <input id="support-email" type="email" class="form-control" :placeholder="$t('support.email-pl')" v-model="email" required />
                        </div>
                        <div class="form-group">
                            <label for="support-summary">{{ $t('support.summary') }}</label>
                            <input id="support-summary" type="text" class="form-control" :placeholder="$t('support.summary-pl')" v-model="summary" required />
                        </div>
                        <div class="form-group">
                            <label for="support-body">{{ $t('support.expl') }}</label>
                            <textarea id="support-body" class="form-control" rows="5" v-model="body" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel" data-dismiss="modal">{{ $t('btn.cancel') }}</button>
                    <button type="submit" class="btn btn-primary modal-action"><svg-icon icon="check"></svg-icon> {{ $t('btn.submit') }}</button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
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
            this.dialog = $('#support-dialog')
                .on('hide.bs.modal', () => {
                    this.lockForm(false)
                    if (this.cancelToken) {
                        this.cancelToken()
                        this.cancelToken = null
                    }
                })
                .on('show.bs.modal', () => {
                    this.cancelToken = null
                })
        },
        methods: {
            lockForm(lock) {
                this.dialog.find('input,textarea,.modal-action').prop('disabled', lock)
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
                        this.dialog.modal('hide')
                        this.$toast.success(response.data.message)
                    })
                    .catch(error => {
                        this.lockForm(false)
                    })
            }
        }
    }
</script>
