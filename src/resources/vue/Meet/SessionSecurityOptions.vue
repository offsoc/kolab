<template>
    <div v-if="config">
        <div id="security-options-dialog" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Security options</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="security-options-password">
                            <div id="password-input" class="input-group input-group-activable">
                                <span class="input-group-text label">Password:</span>
                                <span v-if="config.password" id="password-input-text" class="input-group-text">{{ config.password }}</span>
                                <span v-else id="password-input-text" class="input-group-text text-muted">none</span>
                                <input type="text" :value="config.password" name="password" class="form-control rounded-left activable">
                                <div class="input-group-append">
                                    <button type="button" @click="passwordSave" id="password-save-btn" class="btn btn-outline-primary activable rounded-right">Save</button>
                                    <button type="button" v-if="config.password" id="password-clear-btn" @click="passwordClear" class="btn btn-outline-danger rounded">Clear password</button>
                                    <button type="button" v-else @click="passwordSet" id="password-set-btn" class="btn btn-outline-primary rounded">Set password</button>
                                </div>
                            </div>
                            <small class="form-text text-muted">
                                You can add a password to your meeting. Participants will have to provide
                                the password before they are allowed to join the meeting.
                            </small>
                        </form>
                        <hr v-if="false">
                        <form v-if="false" id="security-options-lock">
                            <div id="room-lock" class="">
                                <span class="">Locked room:</span>
                                <input type="checkbox" name="lock" value="1" :checked="config.locked" @click="lockSave">
                            </div>
                            <small class="form-text text-muted">
                                When the room is locked participants have to be approved by you
                                before they could join the meeting.
                            </small>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-action" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            config: { type: Object, default: () => null },
            room: { type: String, default: () => null }
        },
        data() {
            return {
            }
        },
        mounted() {
            $('#security-options-dialog').on('show.bs.modal', e => {
                $(e.target).find('.input-group-activable.active').removeClass('active')
            })
        },
        methods: {
            configSave(name, value, callback) {
                const post = {}
                post[name] = value

                axios.post('/api/v4/openvidu/rooms/' + this.room + '/config', post)
                    .then(response => {
                        this.config[name] = value
                        if (callback) {
                            callback(response.data)
                        }
                        this.$emit('config-update', this.config)
                        this.$toast.success(response.data.message)
                    })
            },
            lockSave(e) {
                this.configSave('locked', $(e.target).prop('checked') ? 1 : 0)
            },
            passwordClear() {
                this.configSave('password', '')
            },
            passwordSave() {
                this.configSave('password', $('#password-input input').val(), () => {
                        $('#password-input').removeClass('active')
                })
            },
            passwordSet() {
                $('#password-input').addClass('active').find('input')
                    .off('keydown.pass')
                    .on('keydown.pass', e => {
                        if (e.which == 13) {
                            // On ENTER save the password
                            this.passwordSave()
                            e.preventDefault()
                        } else if (e.which == 27) {
                            // On ESC escape from the input, but not the dialog
                            $('#password-input').removeClass('active')
                            e.stopPropagation()
                        }
                    })
                    .focus()
            }
        }
    }
</script>
