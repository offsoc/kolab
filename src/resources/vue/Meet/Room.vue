<template>
    <div id="meet-component">
        <div id="meet-session-toolbar" class="d-none">
            <div id="meet-session-menu">
                <button class="btn btn-link link-audio" @click="switchSound">
                    <svg-icon icon="microphone"></svg-icon>
                </button>
                <button class="btn btn-link link-video" @click="switchVideo">
                    <svg-icon icon="video"></svg-icon>
                </button>
                <button class="btn btn-link link-screen text-danger" @click="switchScreen" :disabled="!canShareScreen">
                    <svg-icon icon="desktop"></svg-icon>
                </button>
                <button class="btn btn-link link-logout" @click="sessionLogout">
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
                            <button class="btn btn-primary pl-5 pr-5">JOIN</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="meet-session"></div>
    </div>
</template>

<script>
    import Meet from '../../js/meet/app.js'

    export default {
        data() {
            return {
                setup: {
                    cameras: [],
                    microphones: [],
                },
                canShareScreen: false,
                camera: '',
                microphone: '',
                nickname: ''
            }
        },
        mounted() {
            this.room = this.$route.params.room

            this.meet = new Meet($('#meet-session')[0]);

            this.canShareScreen = this.meet.isScreenSharingSupported()
            this.setupSession()
        },
        beforeDestroy() {
            this.leaveSession()
        },
        methods: {
            joinSession() {
                $('#meet-setup').addClass('d-none')
                $('#meet-session-toolbar').removeClass('d-none')

                let addUrl = ''
                if (this.canShareScreen) {
                    addUrl = '?screenShare=1'
                }

                axios.get('/api/v4/meet/openvidu/' + this.room + addUrl)
                    .then(response => {
                        // Response data contains: session, token and shareToken
                        this.meet.joinRoom(response.data)
                        $('#app').addClass('meet')
                    })
                    .catch(this.$root.errorHandler)
            },
            leaveSession() {
                this.meet.leaveRoom()
            },
            setMenuItem(type, state) {
                $('#meet-session-menu').find('.link-' + type)[state ? 'removeClass' : 'addClass']('text-danger')
            },
            setupSession() {
                this.meet.setup($('#setup-preview video')[0],
                    setup => {
                        this.setup = setup
                        this.microphone = setup.audioSource
                        this.camera = setup.videoSource

                        this.setMenuItem('audio', this.setup.audioEnabled)
                        this.setMenuItem('audio', this.setup.videoEnabled)
                    },
                    error => {
                        // TODO: display nice error to the user
                        // FIXME: It looks like OpenVidu requires audio or video,
                        //        otherwise it will not connect to the session?
                    }
                )
            },
            setupCameraChange() {
                const enabled = this.meet.setupSetVideoDevice(this.camera)
                this.setMenuItem('video', enabled)
            },
            setupMicrophoneChange() {
                const enabled = this.meet.setupSetAudioDevice(this.microphone)
                this.setMenuItem('audio', enabled)
            },
            sessionLogout() {
                this.leaveSession()
                this.$router.push({ name: 'dashboard' })
                // TODO: If user is logged in, log him out?
            },
            switchSound(event) {
                const enabled = this.meet.switchAudio()
                this.setMenuItem('audio', enabled)
            },
            switchVideo(event) {
                const enabled = this.meet.switchVideo()
                this.setMenuItem('video', enabled)
            },
            switchScreen(event) {
                this.meet.switchScreen(enabled => {
                    this.setMenuItem('screen', enabled)
                })
            }
        }
    }
</script>
