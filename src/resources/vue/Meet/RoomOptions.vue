<template>
    <div v-if="config">
        <div id="room-options-dialog" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $t('meet.options') }}</h5>
                        <btn class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></btn>
                    </div>
                    <div class="modal-body">
                        <form id="room-options-password">
                            <div id="password-input" class="input-group input-group-activable mb-2">
                                <span class="input-group-text label">{{ $t('meet.password') }}:</span>
                                <span v-if="config.password" id="password-input-text" class="input-group-text">{{ config.password }}</span>
                                <span v-else id="password-input-text" class="input-group-text text-muted">{{ $t('meet.password-none') }}</span>
                                <input type="text" :value="config.password" name="password" class="form-control rounded-start activable">
                                <btn @click="passwordSave" id="password-save-btn" class="btn-outline-primary activable rounded-end">{{ $t('btn.save') }}</btn>
                                <btn v-if="config.password" id="password-clear-btn" @click="passwordClear" class="btn-outline-danger rounded">{{ $t('meet.password-clear') }}</btn>
                                <btn v-else @click="passwordSet" id="password-set-btn" class="btn-outline-primary rounded">{{ $t('meet.password-set') }}</btn>
                            </div>
                            <small class="text-muted">
                                {{ $t('meet.password-text') }}
                            </small>
                        </form>
                        <hr>
                        <form id="room-options-lock">
                            <div id="room-lock" class="mb-2">
                                <label for="room-lock-input">{{ $t('meet.lock') }}:</label>
                                <input type="checkbox" id="room-lock-input" name="lock" value="1" :checked="config.locked" @click="lockSave">
                            </div>
                            <small class="text-muted">
                                {{ $t('meet.lock-text') }}
                            </small>
                        </form>
                        <hr>
                        <form id="room-options-nomedia">
                            <div id="room-nomedia" class="mb-2">
                                <label for="room-nomedia-input">{{ $t('meet.nomedia') }}:</label>
                                <input type="checkbox" id="room-nomedia-input" name="lock" value="1" :checked="config.nomedia" @click="nomediaSave">
                            </div>
                            <small class="text-muted">
                                {{ $t('meet.nomedia-text') }}
                            </small>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <btn class="btn-secondary modal-action" data-bs-dismiss="modal">{{ $t('btn.close') }}</btn>
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
        mounted() {
            $('#room-options-dialog')[0].addEventListener('show.bs.modal', e => {
                $(e.target).find('.input-group-activable.active').removeClass('active')
            })
        },
        methods: {
            configSave(name, value, callback) {
                const post = {}
                post[name] = value

                axios.post('/api/v4/openvidu/rooms/' + this.room + '/config', post)
                    .then(response => {
                        this.$set(this.config, name, value)
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
            nomediaSave(e) {
                this.configSave('nomedia', $(e.target).prop('checked') ? 1 : 0)
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
