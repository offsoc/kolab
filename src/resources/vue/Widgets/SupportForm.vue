<template>
    <div class="modal" id="support-dialog" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form class="modal-content" @submit.prevent="submit">
                <div class="modal-header">
                    <h5 class="modal-title">Contact Support</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form">
                        <div class="form-group">
                            <label>Customer number or email address you have with us</label>
                            <input id="support-user" type="text" class="form-control" placeholder="e.g. 12345678 or john@kolab.org" v-model="user" />
                            <small class="form-text text-muted">Leave blank if you are not a customer yet</small>
                        </div>
                        <div class="form-group">
                            <label>Name</label>
                            <input id="support-name" type="text" class="form-control" placeholder="how we should call you in our reply" v-model="name" />
                        </div>
                        <div class="form-group">
                            <label>Working email address</label>
                            <input id="support-email" type="email" class="form-control" placeholder="make sure we can reach you at this address" v-model="email" required />
                        </div>
                        <div class="form-group">
                            <label>Issue Summary</label>
                            <input id="support-summary" type="text" class="form-control" placeholder="one sentence that summarizes your issue" v-model="summary" required />
                        </div>
                        <div class="form-group">
                            <label>Issue Explanation</label>
                            <textarea id="support-body" class="form-control" rows="5" v-model="body" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary modal-action"><svg-icon icon="check"></svg-icon> Submit</button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
    export default {
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
