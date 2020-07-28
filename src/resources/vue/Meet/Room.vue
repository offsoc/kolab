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
                <button class="btn btn-link link-logout" @click="sessionLogout" title="Leave session">
                    <svg-icon icon="power-off"></svg-icon>
                </button>
            </div>
        </div>

        <div id="meet-setup" class="card container mt-5 mb-5">
            <div class="card-body">
                <div class="card-title">Set up your session</div>
                <div class="card-text">
                    <form class="setup-form row" @submit.prevent="joinSession">
                        <div id="setup-preview" class="col-sm-6">
                            <video class="rounded"></video>
                            <div class="volume"><div class="bar"></div></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Microphone</label>
                                <select class="custom-select" id="setup-microphone" v-model="microphone" @change="setupMicrophoneChange">
                                    <option value="">None</option>
                                    <option v-for="mic in setup.microphones" :value="mic.deviceId" :key="mic.deviceId">{{ mic.label }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Camera</label>
                                <select class="custom-select" id="setup-camera" v-model="camera" @change="setupCameraChange">
                                    <option value="">None</option>
                                    <option v-for="cam in setup.cameras" :value="cam.deviceId" :key="cam.deviceId">{{ cam.label }}</option>
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label>Nickname</label>
                                <input class="form-control" type="text" id="setup-nickname" v-model="nickname">
                            </div>
                        </div>
                        <div class="text-center mt-4 col-sm-12">
                            <status-message :status="roomState" :status-labels="roomStateLabels"></status-message>
                            <button v-if="roomState == 'ready'" class="btn btn-primary pl-5 pr-5">JOIN</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="meet-session-layout" class="d-flex">
            <div id="meet-session"></div>
            <div id="meet-chat">
                <div class="chat"></div>
                <div class="chat-input m-2">
                    <textarea class="form-control" rows="1"></textarea>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import Meet from '../../js/meet/app.js'
    import StatusMessage from '../Widgets/StatusMessage'

    export default {
        components: {
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
                    423: 'The room is closed. Refresh the page to try again.',
                    500: 'Failed to create a session. Server error.'
                },
                session: null
            }
        },
        mounted() {
            this.room = this.$route.params.room

            if (!this.$store.state.isLoggedIn) {
                this.$store.state.afterLogin = this.$router.currentRoute
                this.$router.push({ name: 'login' })
                return
            }

            // this.nickname = this.$store.state.authInfo.email.replace(/@.*$/, '')

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
                this.leaveSession()
            }
        },
        methods: {
            initSession() {
                let addUrl = this.canShareScreen ? '?screenShare=1' : ''

                axios.get('/api/v4/openvidu/rooms/' + this.room + addUrl)
                    .then(response => {
                        // Response data contains: session, token and shareToken
                        this.roomState = 'ready'
                        this.session = response.data
                        $('#app').addClass('meet')
                    })
                    .catch(error => {
                        this.roomState = String(error.response.status)
                    })

                if (document.fullscreenEnabled) {
                    $('#meet-session-menu').find('.link-fullscreen').first().removeClass('d-none')
                }
            },
            joinSession() {
                $('#meet-setup').addClass('d-none')
                $('#meet-session-toolbar').removeClass('d-none')

                this.session.nickname = this.nickname
                this.session.menuElement = $('#meet-session-menu')[0]
                this.session.chatElement = $('#meet-chat')[0]

                this.meet.joinRoom(this.session)
            },
            leaveSession() {
                this.meet.leaveRoom()
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

                        this.setMenuItem('audio', setup.audioEnabled)
                        this.setMenuItem('audio', setup.videoEnabled)
                    },
                    error: error => {
                        // TODO: display nice error to the user
                        // FIXME: It looks like OpenVidu requires audio or video,
                        //        otherwise it will not connect to the session?
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
            sessionLogout() {
                this.leaveSession()
                this.$router.push({ name: 'dashboard' })
                // TODO: If user is logged in, log him out?
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
                if (document.fullscreenElement) {
                    document.exitFullscreen()
                } else {
                    this.$el.requestFullscreen()
                }

                $('#meet-session-menu').find('.link-fullscreen').toggleClass('d-none')
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
