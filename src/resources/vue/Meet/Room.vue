<template>
    <div id="meet-component">
        <div id="meet-session-toolbar" class="d-none">
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
                <button class="btn btn-link link-fullscreen d-none" @click="switchFullscreen" title="Full screen">
                    <svg-icon icon="expand"></svg-icon>
                </button>
                <button class="btn btn-link link-fullscreen d-none" @click="switchFullscreen" title="Full screen">
                    <svg-icon icon="compress"></svg-icon>
                </button>
                <button class="btn btn-link link-logout" @click="logout" title="Leave session">
                    <svg-icon icon="power-off"></svg-icon>
                </button>
            </div>
        </div>

        <div id="meet-setup" class="card container mt-5 mb-5">
            <div class="card-body">
                <div class="card-title">Set up your session</div>
                <div class="card-text">
                    <form class="setup-form row">
                        <div id="setup-preview" class="col-sm-6">
                            <video class="rounded"></video>
                            <div class="volume"><div class="bar"></div></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="setup-microphone">Microphone</label>
                                <select class="custom-select" id="setup-microphone" v-model="microphone" @change="setupMicrophoneChange">
                                    <option value="">None</option>
                                    <option v-for="mic in setup.microphones" :value="mic.deviceId" :key="mic.deviceId">{{ mic.label }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="setup-camera">Camera</label>
                                <select class="custom-select" id="setup-camera" v-model="camera" @change="setupCameraChange">
                                    <option value="">None</option>
                                    <option v-for="cam in setup.cameras" :value="cam.deviceId" :key="cam.deviceId">{{ cam.label }}</option>
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label for="setup-nickname">Nickname</label>
                                <input class="form-control" type="text" id="setup-nickname" v-model="nickname">
                            </div>
                        </div>
                        <div class="text-center mt-4 col-sm-12">
                            <status-message :status="roomState" :status-labels="roomStateLabels" class="mb-3"></status-message>
                            <button v-if="roomState == 'ready' || roomState == 424"
                                    type="button"
                                    @click="joinSession"
                                    class="btn btn-primary pl-5 pr-5"
                            >JOIN</button>
                            <button v-if="roomState == 423"
                                    type="button"
                                    @click="joinSession"
                                    class="btn btn-primary pl-5 pr-5"
                            >I'm the owner</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="meet-session-layout" class="d-flex d-none">
            <div id="meet-session"></div>
            <div id="meet-chat">
                <div class="chat"></div>
                <div class="chat-input m-2">
                    <textarea class="form-control" rows="1"></textarea>
                </div>
            </div>
        </div>

        <logon-form id="meet-auth" class="d-none" :dashboard="false" @success="authSuccess"></logon-form>

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
                        <button type="button" class="btn btn-danger modal-action" @click="leaveRoom()">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import Meet from '../../js/meet/app.js'
    import StatusMessage from '../Widgets/StatusMessage'
    import LogonForm from '../Login'

    export default {
        components: {
            LogonForm,
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
                room: null,
                roomState: 'init',
                roomStateLabels: {
                    init: 'Checking the room...',
                    404: 'The room does not exist.',
                    423: 'The room is closed. Please, wait for the owner to start the session.',
                    424: 'The room is closed. It will be open for others after you join.',
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
            if (this.meet) {
                this.meet.leaveRoom()
            }
        },
        methods: {
            authSuccess() {
                // The user (owner) authentication succeeded
                this.roomState = 'init'
                this.initSession()

                $('#meet-setup').removeClass('d-none')
                $('#meet-auth').addClass('d-none')
            },
            initSession(init) {
                let params = []

                if (this.canShareScreen) {
                    params.push('screenShare=1')
                }

                if (init) {
                    params.push('init=1')
                }

                axios.get('/api/v4/openvidu/rooms/' + this.room + '?' + params.join('&'))
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
                    })

                if (document.fullscreenEnabled) {
                    $('#meet-session-menu').find('.link-fullscreen').first().removeClass('d-none')
                }
            },
            joinSession() {
                if (this.roomState == 423) {
                    $('#meet-setup').addClass('d-none')
                    $('#meet-auth').removeClass('d-none')
                    return
                }

                if (this.roomState == 424) {
                    this.initSession(true)
                    return
                }

                $('#app').addClass('meet')
                $('#meet-setup').addClass('d-none')
                $('#meet-session-toolbar,#meet-session-layout').removeClass('d-none')

                this.session.nickname = this.nickname
                this.session.menuElement = $('#meet-session-menu')[0]
                this.session.chatElement = $('#meet-chat')[0]
                this.session.onDestroy = event => {
                    // TODO: Handle nicely other reasons: disconnect, forceDisconnectByUser,
                    //       forceDisconnectByServer, networkDisconnect?
                    if (event.reason == 'sessionClosedByServer') {
                        $('#leave-dialog').modal()
                    }
                }

                this.meet.joinRoom(this.session)
            },
            leaveRoom() {
                $('#leave-dialog').modal('hide')

                // FIXME: Where exactly the user should land? Currently he'll land
                //        on dashboard (if he's logged in) or login form (if he's not).

                window.location = window.config['app.url']
            },
            logout() {
                if (this.session.owner) {
                    axios.post('/api/v4/openvidu/rooms/' + this.room + '/close')
                        .then(response => {
                            this.meet.leaveRoom()
                            this.leaveRoom()
                        })
                } else {
                    this.meet.leaveRoom()
                    this.leaveRoom()
                }
            },
            setMenuItem(type, state) {
                $('#meet-session-menu').find('.link-' + type)[state ? 'removeClass' : 'addClass']('text-danger')
            },
            setupSession() {
                this.meet.setup({
                    videoElement: $('#setup-preview video')[0],
                    volumeElement: $('#setup-preview .volume')[0],
                    success: setup => {
                        this.setup = setup
                        this.microphone = setup.audioSource
                        this.camera = setup.videoSource

                        this.setMenuItem('audio', setup.audioActive)
                        this.setMenuItem('video', setup.videoActive)
                    },
                    error: error => {
                        // TODO: display nice error to the user
                        // FIXME: It looks like OpenVidu requires audio or video,
                        //        otherwise it will not connect to the session?

                        this.setMenuItem('audio', false)
                        this.setMenuItem('video', false)
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
            },
            switchFullscreen() {
                const element = this.$el

                $(element).off('fullscreenchange').on('fullscreenchange', (e) => {
                    let enabled = document.fullscreenElement == element
                    let buttons = $('#meet-session-menu').find('.link-fullscreen')

                    buttons.first()[enabled ? 'addClass' : 'removeClass']('d-none')
                    buttons.last()[!enabled ? 'addClass' : 'removeClass']('d-none')
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
                })
            }
        }
    }
</script>
