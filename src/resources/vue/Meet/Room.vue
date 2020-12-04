<template>
    <div id="meet-component">
        <div id="meet-session-toolbar" class="hidden">
            <div id="meet-session-menu">
                <button class="btn btn-link link-audio" @click="switchSound" title="Mute audio">
                    <svg-icon icon="microphone"></svg-icon>
                </button>
                <button class="btn btn-link link-video" @click="switchVideo" title="Mute video">
                    <svg-icon icon="video"></svg-icon>
                </button>
                <button class="btn btn-link link-screen text-danger" @click="switchScreen" :disabled="!canShareScreen" title="Share screen">
                    <svg-icon icon="desktop"></svg-icon>
                </button>
                <button class="btn btn-link link-chat text-danger" @click="switchChat" title="Chat">
                    <svg-icon icon="align-left"></svg-icon>
                </button>
                <button class="btn btn-link link-fullscreen closed hidden" @click="switchFullscreen" title="Full screen">
                    <svg-icon icon="expand"></svg-icon>
                </button>
                <button class="btn btn-link link-fullscreen open hidden" @click="switchFullscreen" title="Full screen">
                    <svg-icon icon="compress"></svg-icon>
                </button>
                <button class="btn btn-link link-security" v-if="session && session.owner" @click="securityOptions" title="Security options">
                    <svg-icon icon="shield-alt"></svg-icon>
                </button>
                <button class="btn btn-link link-logout" @click="logout" title="Leave session">
                    <svg-icon icon="power-off"></svg-icon>
                </button>
            </div>
        </div>

        <div id="meet-setup" class="card container mt-2 mt-md-5 mb-5">
            <div class="card-body">
                <div class="card-title">Set up your session</div>
                <div class="card-text">
                    <form class="setup-form row">
                        <div id="setup-preview" class="col-sm-6 mb-3 mb-sm-0">
                            <video class="rounded"></video>
                            <div class="volume"><div class="bar"></div></div>
                        </div>
                        <div class="col-sm-6 align-self-center">
                            <div class="input-group">
                                <label for="setup-microphone" class="input-group-prepend mb-0">
                                    <span class="input-group-text" title="Microphone"><svg-icon icon="microphone"></svg-icon></span>
                                </label>
                                <select class="custom-select" id="setup-microphone" v-model="microphone" @change="setupMicrophoneChange">
                                    <option value="">None</option>
                                    <option v-for="mic in setup.microphones" :value="mic.deviceId" :key="mic.deviceId">{{ mic.label }}</option>
                                </select>
                            </div>
                            <div class="input-group mt-2">
                                <label for="setup-camera" class="input-group-prepend mb-0">
                                    <span class="input-group-text" title="Camera"><svg-icon icon="video"></svg-icon></span>
                                </label>
                                <select class="custom-select" id="setup-camera" v-model="camera" @change="setupCameraChange">
                                    <option value="">None</option>
                                    <option v-for="cam in setup.cameras" :value="cam.deviceId" :key="cam.deviceId">{{ cam.label }}</option>
                                </select>
                            </div>
                            <div class="input-group mt-2">
                                <label for="setup-nickname" class="input-group-prepend mb-0">
                                    <span class="input-group-text" title="Nickname"><svg-icon icon="user"></svg-icon></span>
                                </label>
                                <input class="form-control" type="text" id="setup-nickname" v-model="nickname" placeholder="Your name">
                            </div>
                            <div class="input-group mt-2" v-if="session.config && session.config.requires_password">
                                <label for="setup-password" class="input-group-prepend mb-0">
                                    <span class="input-group-text" title="Password"><svg-icon icon="key"></svg-icon></span>
                                </label>
                                <input type="password" class="form-control" id="setup-password" v-model="password" placeholder="Password">
                            </div>
                            <div class="mt-3">
                                <button v-if="roomState == 'ready' || roomState == 424 || roomState == 425"
                                        type="button"
                                        @click="joinSession"
                                        :class="'btn w-100 btn-' + (roomState == 'ready' ? 'success' : 'primary')"
                                >JOIN</button>
                                <button v-if="roomState == 423"
                                        type="button"
                                        @click="joinSession"
                                        class="btn btn-primary w-100"
                                >I'm the owner</button>
                            </div>
                        </div>
                        <div class="mt-4 col-sm-12">
                            <status-message :status="roomState" :status-labels="roomStateLabels"></status-message>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="meet-session-layout" class="d-flex hidden">
            <div id="meet-session"></div>
            <div id="meet-chat">
                <div class="chat"></div>
                <div class="chat-input m-2">
                    <textarea class="form-control" rows="1"></textarea>
                </div>
            </div>
        </div>

        <logon-form id="meet-auth" class="hidden" :dashboard="false" @success="authSuccess"></logon-form>

        <div id="leave-dialog" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Room closed</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>The session has been closed by the room owner.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger modal-action" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <session-security-options v-if="session.config" :config="session.config" :room="room" @config-update="configUpdate"></session-security-options>
    </div>
</template>

<script>
    import Meet from '../../js/meet/app.js'
    import StatusMessage from '../Widgets/StatusMessage'
    import LogonForm from '../Login'
    import SessionSecurityOptions from './SessionSecurityOptions'

    export default {
        components: {
            LogonForm,
            SessionSecurityOptions,
            StatusMessage
        },
        data() {
            return {
                setup: {
                    cameras: [],
                    microphones: [],
                },
                canShareScreen: false,
                camera: '',
                meet: null,
                microphone: '',
                nickname: '',
                password: '',
                room: null,
                roomState: 'init',
                roomStateLabels: {
                    init: 'Checking the room...',
                    404: 'The room does not exist.',
                    423: 'The room is closed. Please, wait for the owner to start the session.',
                    424: 'The room is closed. It will be open for others after you join.',
                    425: 'The room is ready. Please, provide a valid password.',
                    500: 'Failed to create a session. Server error.'
                },
                session: {}
            }
        },
        mounted() {
            this.room = this.$route.params.room

            // Initialize OpenVidu and do some basic checks
            this.meet = new Meet($('#meet-session')[0]);
            this.canShareScreen = this.meet.isScreenSharingSupported()

            // Check the room and init the session
            this.initSession()

            // Setup the room UI
            this.setupSession()
        },
        beforeDestroy() {
            clearTimeout(window.roomRequest)

            if (this.meet) {
                this.meet.leaveRoom()
            }
        },
        methods: {
            authSuccess() {
                // The user (owner) authentication succeeded
                this.roomState = 'init'
                this.initSession()

                $('#meet-setup').removeClass('hidden')
                $('#meet-auth').addClass('hidden')
            },
            configUpdate(config) {
                this.session.config = Object.assign({}, this.session.config, config)
            },
            initSession(init) {
                this.post = {
                    password: this.password,
                    nickname: this.nickname,
                    screenShare: this.canShareScreen ? 1 : 0,
                    init: init ? 1 : 0
                }

                $('#setup-password').removeClass('is-invalid')

                axios.post('/api/v4/openvidu/rooms/' + this.room, this.post, { ignoreErrors: true })
                    .then(response => {
                        // Response data contains: session, token and shareToken
                        this.roomState = 'ready'
                        this.session = response.data

                        if (init) {
                            this.joinSession()
                        }
                    })
                    .catch(error => {
                        this.roomState = String(error.response.status)

                        if (error.response.data && error.response.data.config) {
                            this.session.config = error.response.data.config
                        }

                        switch (this.roomState) {
                            case '423':
                                // Waiting for the owner to open the room...
                                // Update room state every 10 seconds
                                window.roomRequest = setTimeout(() => { this.initSession() }, 10000)
                                break;

                            case '425':
                                // Missing/invalid password
                                if (init) {
                                    $('#setup-password').addClass('is-invalid').focus()
                                }
                                break;
                        }
                    })

                if (document.fullscreenEnabled) {
                    $('#meet-session-menu').find('.link-fullscreen.closed').removeClass('hidden')
                }
            },
            joinSession() {
                if (this.roomState == 423) {
                    $('#meet-setup').addClass('hidden')
                    $('#meet-auth').removeClass('hidden')
                    return
                }

                if (this.roomState == 424 || this.roomState == 425) {
                    this.initSession(true)
                    return
                }

                clearTimeout(window.roomRequest)

                $('#app').addClass('meet')
                $('#meet-setup').addClass('hidden')
                $('#meet-session-toolbar,#meet-session-layout').removeClass('hidden')

                if (!this.canShareScreen) {
                    this.setMenuItem('screen', false, true)
                }

                this.session.nickname = this.nickname
                this.session.menuElement = $('#meet-session-menu')[0]
                this.session.chatElement = $('#meet-chat')[0]
                this.session.onDestroy = event => {
                    // TODO: Handle nicely other reasons: disconnect, forceDisconnectByUser,
                    //       forceDisconnectByServer, networkDisconnect?
                    if (event.reason == 'sessionClosedByServer' && !this.session.owner) {
                        $('#leave-dialog').on('hide.bs.modal', () => {
                            // FIXME: Where exactly the user should land? Currently he'll land
                            //        on dashboard (if he's logged in) or login form (if he's not).

                            window.location = window.config['app.url']
                        }).modal()
                    }
                }

                this.meet.joinRoom(this.session)
            },
            logout() {
                if (this.session.owner) {
                    axios.post('/api/v4/openvidu/rooms/' + this.room + '/close')
                        .then(response => {
                            this.meet.leaveRoom()
                            this.meet = null
                            window.location = window.config['app.url']
                        })
                } else {
                    this.meet.leaveRoom()
                    this.meet = null
                    window.location = window.config['app.url']
                }
            },
            securityOptions() {
                $('#security-options-dialog').modal()
            },
            setMenuItem(type, state, disabled) {
                let button = $('#meet-session-menu').find('.link-' + type)

                button[state ? 'removeClass' : 'addClass']('text-danger')

                if (disabled !== undefined) {
                    button.prop('disabled', disabled)
                }
            },
            setupSession() {
                this.meet.setup({
                    videoElement: $('#setup-preview video')[0],
                    volumeElement: $('#setup-preview .volume')[0],
                    onSuccess: setup => {
                        this.setup = setup
                        this.microphone = setup.audioSource
                        this.camera = setup.videoSource

                        this.setMenuItem('audio', setup.audioActive)
                        this.setMenuItem('video', setup.videoActive)
                    },
                    onError: error => {
                        this.setMenuItem('audio', false, true)
                        this.setMenuItem('video', false, true)
                    }
                })
            },
            setupCameraChange() {
                this.meet.setupSetVideoDevice(this.camera).then(enabled => {
                    this.setMenuItem('video', enabled)
                })
            },
            setupMicrophoneChange() {
                this.meet.setupSetAudioDevice(this.microphone).then(enabled => {
                    this.setMenuItem('audio', enabled)
                })
            },
            switchChat() {
                let chat = $('#meet-chat')
                let enabled = chat.is('.open')

                this.setMenuItem('chat', !enabled)
                chat.toggleClass('open')

                if (!enabled) {
                    chat.find('textarea').focus()
                }

                // Trigger resize, so participant matrix can update its layout
                window.dispatchEvent(new Event('resize'));
            },
            switchFullscreen() {
                const element = this.$el

                $(element).off('fullscreenchange').on('fullscreenchange', (e) => {
                    let enabled = document.fullscreenElement == element
                    let buttons = $('#meet-session-menu').find('.link-fullscreen')

                    buttons.first()[enabled ? 'addClass' : 'removeClass']('hidden')
                    buttons.last()[!enabled ? 'addClass' : 'removeClass']('hidden')
                })

                if (document.fullscreenElement) {
                    document.exitFullscreen()
                } else {
                    element.requestFullscreen()
                }
            },
            switchSound() {
                const enabled = this.meet.switchAudio()
                this.setMenuItem('audio', enabled)
            },
            switchVideo() {
                const enabled = this.meet.switchVideo()
                this.setMenuItem('video', enabled)
            },
            switchScreen() {
                this.meet.switchScreen(enabled => {
                    this.setMenuItem('screen', enabled)

                    // After one screen sharing session ended request a new token
                    // for the next screen sharing session
                    if (!enabled) {
                        // TODO: This might need to be a different route. E.g. the room password might have
                        //       changed since user joined the session
                        axios.post('/api/v4/openvidu/rooms/' + this.room, this.post, { ignoreErrors: true })
                            .then(response => {
                                // Response data contains: session, token and shareToken
                                this.session.shareToken = response.data.token
                                this.meet.updateSession(this.session)
                            })
                    }
                })
            }
        }
    }
</script>
