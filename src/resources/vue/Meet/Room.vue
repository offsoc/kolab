<template>
    <div class="meet-component">
        <div id="meet-session-toolbar" class="d-none">
            <div id="meet-session-menu">
                <button class="btn btn-link link-audio" @click="sessionMuteSound">
                    <svg-icon icon="microphone"></svg-icon>
                </button>
                <button class="btn btn-link link-video" @click="sessionMuteVideo">
                    <svg-icon icon="video"></svg-icon>
                </button>
                <button class="btn btn-link link-logout" @click="sessionLogout">
                    <svg-icon icon="power-off"></svg-icon>
                </button>
            </div>
        </div>

        <div id="meet-session"></div>

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
                camera: '',
                microphone: '',
                nickname: ''
            }
        },
        mounted() {
            this.room = this.$route.path.replace(/^\//, '')

            this.meet = new Meet($('#meet-session')[0]);

            this.setupSession()
        },
        beforeDestroy() {
            this.leaveSession()
        },
        methods: {
            joinSession() {
                $('#meet-setup').addClass('d-none')
                $('#meet-session-toolbar').removeClass('d-none')

                axios.get('/api/v4/meet/openvidu/' + this.room)
                    .then(response => {
                        // Response data contains: sessionName, user, tokens
                        this.meet.joinRoom(response.data)
                    })
                    .catch(this.$root.errorHandler)
            },
            leaveSession() {
                this.meet.leaveRoom()
            },
            setupSession() {
                this.meet.setup($('#setup-preview video')[0],
                    setup => {
                        this.setup = setup
                        this.microphone = setup.audioSource
                        this.camera = setup.videoSource

                        if (!this.setup.audioEnabled) {
                            $('#meet-session-menu .link-audio').addClass('text-danger')
                        }
                        if (!this.setup.videoEnabled) {
                            $('#meet-session-menu .link-video').addClass('text-danger')
                        }
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
                $('#meet-session-menu .link-video')[enabled ? 'removeClass' : 'addClass']('text-danger')
            },
            setupMicrophoneChange() {
                const enabled = this.meet.setupSetAudioDevice(this.microphone)
                $('#meet-session-menu .link-audio')[enabled ? 'removeClass' : 'addClass']('text-danger')
            },
            sessionLogout() {
                this.leaveSession()
                this.$router.push({ name: 'dashboard' })
                // TODO: If user is logged in, log him out?
            },
            sessionMuteSound(event) {
                const enabled = this.meet.muteAudio()
                $(event.target)[enabled ? 'removeClass' : 'addClass']('text-danger')
            },
            sessionMuteVideo() {
                const enabled = this.meet.muteVideo()
                $(event.target)[enabled ? 'removeClass' : 'addClass']('text-danger')
            }
        }
    }
</script>
