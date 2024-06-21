<template>
    <div id="meet-component">
        <div id="meet-session-toolbar" class="hidden">
            <span id="meet-counter" :title="$t('meet.partcnt')"><svg-icon icon="users"></svg-icon> <span></span></span>
            <span id="meet-session-logo" v-html="$root.logo()"></span>
            <div id="meet-session-menu">
                <button :class="'btn link-audio' + (audioActive ? '' : ' on')" @click="switchSound" :disabled="!isPublisher()" :title="$t('meet.menu-audio-' + (audioActive ? 'mute' : 'unmute'))">
                    <svg-icon :icon="audioActive ? 'microphone' : 'microphone-slash'"></svg-icon>
                </button>
                <button :class="'btn link-video' + (videoActive ? '' : ' on')" @click="switchVideo" :disabled="!isPublisher()" :title="$t('meet.menu-video-' + (videoActive ? 'mute' : 'unmute'))">
                    <svg-icon :icon="videoActive ? 'video' : 'video-slash'"></svg-icon>
                </button>
                <button :class="'btn link-screen' + (screenActive ? ' on' : '')" @click="switchScreen" :disabled="!canShareScreen || !isPublisher()" :title="$t('meet.menu-screen')">
                    <svg-icon icon="desktop"></svg-icon>
                </button>
                <button :class="'btn link-hand' + (handRaised ? ' on' : '')" v-if="!isPublisher()" @click="switchHand" :title="$t('meet.menu-hand-' + (handRaised ? 'lower' : 'raise'))">
                    <svg-icon icon="hand"></svg-icon>
                </button>
                <span id="channel-select" :style="'display:' + (channels.length ? '' : 'none')" class="dropdown">
                    <button :class="'btn link-channel' + (session.channel ? ' on' : '')" data-bs-toggle="dropdown"
                            :title="$t('meet.menu-channel')" aria-haspopup="true" aria-expanded="false"
                    >
                        <svg-icon icon="headphones"></svg-icon>
                        <span class="badge bg-danger" v-if="session.channel">{{ session.channel.toUpperCase() }}</span>
                    </button>
                    <div class="dropdown-menu">
                        <a :class="'dropdown-item' + (!session.channel ? ' active' : '')" href="#" data-code="" @click="switchChannel">- {{ $t('form.none') }} -</a>
                        <a v-for="code in channels" :key="code" href="#" @click="switchChannel" :data-code="code"
                           :class="'dropdown-item' + (session.channel == code ? ' active' : '')"
                        >{{ $t('lang.' + code) }}</a>
                    </div>
                </span>
                <button :class="'btn link-chat' + (chatActive ? ' on' : '')" @click="switchChat" :title="$t('meet.menu-chat')">
                    <svg-icon icon="comment"></svg-icon>
                </button>
                <button class="btn link-fullscreen closed hidden" @click="switchFullscreen" :title="$t('meet.menu-fullscreen')">
                    <svg-icon icon="expand"></svg-icon>
                </button>
                <button class="btn link-fullscreen open hidden" @click="switchFullscreen" :title="$t('meet.menu-fullscreen-exit')">
                    <svg-icon icon="compress"></svg-icon>
                </button>
                <button class="btn link-options" v-if="isRoomOwner()" @click="$refs.optionsDialog.show()" :title="$t('meet.options')">
                    <svg-icon icon="gear"></svg-icon>
                </button>
                <button class="btn link-logout" @click="logout" :title="$t('meet.menu-leave')">
                    <svg-icon icon="power-off"></svg-icon>
                </button>
            </div>
        </div>

        <div id="meet-setup" class="card container mt-2 mt-md-5 mb-5">
            <div class="card-body">
                <div class="card-title">{{ $t('meet.setup-title') }}</div>
                <div class="card-text">
                    <form class="media-setup-form row" @submit.prevent="joinSession">
                        <div class="media-setup-preview col-sm-6 mb-3 mb-sm-0">
                            <video class="rounded"></video>
                            <div class="volume"><div class="bar"></div></div>
                        </div>
                        <div class="col-sm-6 align-self-center">
                            <div class="input-group mb-2">
                                <label for="setup-microphone" class="input-group-text mb-0" :title="$t('meet.mic')">
                                    <svg-icon icon="microphone"></svg-icon>
                                </label>
                                <select class="form-select" id="setup-microphone" v-model="microphone" @change="setupMicrophoneChange">
                                    <option value="">{{ $t('form.none') }}</option>
                                    <option v-for="mic in setup.microphones" :value="mic.deviceId" :key="mic.deviceId">{{ mic.label }}</option>
                                </select>
                            </div>
                            <div class="input-group mb-2">
                                <label for="setup-camera" class="input-group-text mb-0" :title="$t('meet.cam')">
                                    <svg-icon icon="video"></svg-icon>
                                </label>
                                <select class="form-select" id="setup-camera" v-model="camera" @change="setupCameraChange">
                                    <option value="">{{ $t('form.none') }}</option>
                                    <option v-for="cam in setup.cameras" :value="cam.deviceId" :key="cam.deviceId">{{ cam.label }}</option>
                                </select>
                            </div>
                            <div class="input-group mb-2">
                                <label for="setup-nickname" class="input-group-text mb-0" :title="$t('meet.nick')">
                                    <svg-icon icon="user"></svg-icon>
                                </label>
                                <input class="form-control" type="text" id="setup-nickname" v-model="nickname" :placeholder="$t('meet.nick-placeholder')">
                            </div>
                            <div class="input-group mt-2" v-if="session.config && session.config.requires_password">
                                <label for="setup-password" class="input-group-text mb-0" :title="$t('form.password')">
                                    <svg-icon icon="key"></svg-icon>
                                </label>
                                <input type="password" class="form-control" id="setup-password" v-model="password" :placeholder="$t('form.password')">
                            </div>
                            <div class="mt-3">
                                <button type="submit" id="join-button"
                                        :class="'btn w-100 btn-' + (isRoomReady() ? 'success' : 'primary')"
                                >
                                    <span v-if="isRoomReady()">{{ $t('meet.joinnow') }}</span>
                                    <span v-else-if="roomState == 323">{{ $t('meet.imaowner') }}</span>
                                    <span v-else>{{ $t('meet.join') }}</span>
                                </button>
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
            <div id="meet-queue">
                <div class="head" :title="$t('meet.qa')"><svg-icon icon="microphone-lines"></svg-icon></div>
            </div>
            <div id="meet-session"></div>
            <div id="meet-chat">
                <div class="chat"></div>
                <div class="chat-input m-2">
                    <textarea class="form-control" rows="1"></textarea>
                </div>
            </div>
        </div>

        <logon-form id="meet-auth" class="hidden" :dashboard="false" @success="authSuccess"></logon-form>

        <modal-dialog id="leave-dialog" ref="leaveDialog" :title="$t('meet.leave-title')">
            <p>{{ $t('meet.leave-body') }}</p>
        </modal-dialog>

        <modal-dialog id="media-setup-dialog" ref="setupDialog" :title="$t('meet.media-title')">
            <form class="media-setup-form">
                <div class="media-setup-preview"></div>
                <div class="input-group mt-2">
                    <label for="setup-mic" class="input-group-text mb-0" :title="$t('meet.mic')">
                        <svg-icon icon="microphone"></svg-icon>
                    </label>
                    <select class="form-select" id="setup-mic" v-model="microphone" @change="setupMicrophoneChange">
                        <option value="">{{ $t('form.none') }}</option>
                        <option v-for="mic in setup.microphones" :value="mic.deviceId" :key="mic.deviceId">{{ mic.label }}</option>
                    </select>
                </div>
                <div class="input-group mt-2">
                    <label for="setup-cam" class="input-group-text mb-0" :title="$t('meet.cam')">
                        <svg-icon icon="video"></svg-icon>
                    </label>
                    <select class="form-select" id="setup-cam" v-model="camera" @change="setupCameraChange">
                        <option value="">{{ $t('form.none') }}</option>
                        <option v-for="cam in setup.cameras" :value="cam.deviceId" :key="cam.deviceId">{{ cam.label }}</option>
                    </select>
                </div>
            </form>
        </modal-dialog>

        <room-options v-if="session.config" :config="session.config" :room="room" @config-update="configUpdate" ref="optionsDialog"></room-options>
        <room-stats ref="statsDialog" :room="room"></room-stats>
    </div>
</template>

<script>
    import { Dropdown } from 'bootstrap'
    import { Media } from '../../js/meet/media.js'
    import { Room as Meet } from '../../js/meet/room.js'
    import { Roles } from '../../js/meet/constants.js'
    import ModalDialog from '../Widgets/ModalDialog'
    import StatusMessage from '../Widgets/StatusMessage'
    import LogonForm from '../Login'
    import RoomOptions from './RoomOptions'
    import RoomStats from './RoomStats'

    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faComment').definition,
        require('@fortawesome/free-solid-svg-icons/faCompress').definition,
        require('@fortawesome/free-solid-svg-icons/faCrown').definition,
        require('@fortawesome/free-solid-svg-icons/faDesktop').definition,
        require('@fortawesome/free-solid-svg-icons/faExpand').definition,
        require('@fortawesome/free-solid-svg-icons/faHand').definition,
        require('@fortawesome/free-solid-svg-icons/faHeadphones').definition,
        require('@fortawesome/free-solid-svg-icons/faGear').definition,
        require('@fortawesome/free-solid-svg-icons/faKey').definition,
        require('@fortawesome/free-solid-svg-icons/faMicrophone').definition,
        require('@fortawesome/free-solid-svg-icons/faMicrophoneLines').definition,
        require('@fortawesome/free-solid-svg-icons/faMicrophoneSlash').definition,
        require('@fortawesome/free-solid-svg-icons/faPowerOff').definition,
        require('@fortawesome/free-solid-svg-icons/faUser').definition,
        require('@fortawesome/free-solid-svg-icons/faUsers').definition,
        require('@fortawesome/free-solid-svg-icons/faVideo').definition,
        require('@fortawesome/free-solid-svg-icons/faVideoSlash').definition,
        require('@fortawesome/free-solid-svg-icons/faVolumeMute').definition,
    )

    let roomRequest
    const authHeader = 'X-Meet-Auth-Token'

    export default {
        components: {
            LogonForm,
            ModalDialog,
            RoomOptions,
            RoomStats,
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
                channels: [],
                languages: {
                    en: 'lang.en',
                    de: 'lang.de',
                    fr: 'lang.fr',
                    it: 'lang.it'
                },
                meet: null,
                microphone: '',
                nickname: '',
                password: '',
                room: null,
                roomState: 'init',
                roomStateLabels: {
                    init: 'meet.status-init',
                    323: 'meet.status-323',
                    324: 'meet.status-324',
                    325: 'meet.status-325',
                    326: 'meet.status-326',
                    327: 'meet.status-327',
                    404: 'meet.status-404',
                    429: 'meet.status-429',
                    500: 'meet.status-500'
                },
                session: {},
                audioActive: false,
                videoActive: false,
                chatActive: false,
                handRaised: false,
                screenActive: false
            }
        },
        mounted() {
            this.room = this.$route.params.room

            // Initialize Meet client and do some basic checks
            this.meet = new Meet($('#meet-session')[0]);
            this.canShareScreen = this.meet.isScreenSharingSupported()

            // Check the room and init the session
            this.initSession()

            // Setup the room UI
            this.setupSession()

            // Configure dialog events
            this.$refs.leaveDialog.events({
                hide: () => {
                    // FIXME: Where exactly the user should land? Currently he'll land
                    //        on dashboard (if he's logged in) or login form (if he's not).
                    this.$router.push({ name: 'dashboard' })
                }
            })

            this.$refs.setupDialog.events({
                show: () => { this.setupSession() },
                hide: () => { this.meet.setupStop() }
            })
        },
        beforeDestroy() {
            clearTimeout(roomRequest)

            $('#app').removeClass('meet')

            if (this.meet) {
                this.meet.leaveRoom()
            }

            delete axios.defaults.headers.common[authHeader]

            $(document.body).off('keydown.meet')
        },
        methods: {
            authSuccess() {
                // The user authentication succeeded, we still don't know it's really the room owner
                this.initSession()

                $('#meet-setup').removeClass('hidden')
                $('#meet-auth').addClass('hidden')
            },
            configUpdate(config) {
                this.session.config = Object.assign({}, this.session.config, config)
            },
            initSession(init) {
                const button = $('#join-button').prop('disabled', true)

                const post = {
                    password: this.password,
                    nickname: this.nickname,
                    screenShare: this.canShareScreen ? 1 : 0,
                    init: init ? 1 : 0,
                    picture: init ? this.makePicture() : '',
                    requestId: this.requestId(),
                    canPublish: !!this.camera || !!this.microphone
                }

                $('#setup-password,#setup-nickname').removeClass('is-invalid')

                axios.post('/api/v4/meet/rooms/' + this.room, post, { ignoreErrors: true })
                    .then(response => {
                        button.prop('disabled', false)

                        // We already have token, the response is redundant
                        if (this.roomState == 'ready' && this.session.token) {
                            return
                        }

                        this.roomState = 'ready'
                        this.session = response.data

                        if (init) {
                            this.joinSession()
                        }
                    })
                    .catch(error => {
                        if (!error.response) {
                            console.error(error)
                            return
                        }

                        const data = error.response.data || {}

                        if (data.code) {
                            this.roomState = data.code
                        } else {
                            this.roomState = error.response.status
                        }

                        button.prop('disabled', this.roomState == 'init' || this.roomState == 327 || this.roomState >= 400)

                        if (data.config) {
                            this.session.config = data.config
                        }

                        switch (this.roomState) {
                            case 323:
                                // Waiting for the owner to open the room...
                                // Update room state every 10 seconds
                                roomRequest = setTimeout(() => { this.initSession() }, 10000)
                                break;

                            case 324:
                                // Room is ready for the owner, but the 'init' was not requested yet
                                clearTimeout(roomRequest)
                                break;

                            case 325:
                                // Missing/invalid password
                                if (init) {
                                    $('#setup-password').addClass('is-invalid').focus()
                                }
                                break;

                            case 326:
                                // Locked room prerequisites error
                                if (init && !$('#setup-nickname').val()) {
                                    $('#setup-nickname').addClass('is-invalid').focus()
                                }
                                break;

                            case 327:
                                // Waiting for the owner's approval to join
                                // Update room state every 10 seconds
                                roomRequest = setTimeout(() => { this.initSession(true) }, 10000)
                                break;

                            case 429:
                                // Rate limited, wait and try again
                                const waitTime = error.response.headers['retry-after'] || 10
                                roomRequest = setTimeout(() => { this.initSession(init) }, waitTime * 1000)
                                break;

                            default:
                                if (this.roomState >= 400 && this.roomState != 404) {
                                    this.roomState = 500
                                }
                        }
                    })

                if (document.fullscreenEnabled) {
                    $('#meet-session-menu').find('.link-fullscreen.closed').removeClass('hidden')
                }
            },
            isModerator() {
                return this.isRoomOwner() || (!!this.session.role && (this.session.role & Roles.MODERATOR) > 0)
            },
            isPublisher() {
                return !!this.session.role && (this.session.role & Roles.PUBLISHER) > 0
            },
            isRoomOwner() {
                return !!this.session.role && (this.session.role & Roles.OWNER) > 0
            },
            isRoomReady() {
                return ['ready', 322, 324, 325, 326, 327].includes(this.roomState)
            },
            // Entering the room
            joinSession() {
                // The form can be submitted not only via the submit button,
                // make sure the submit is allowed
                if ($('#meet-setup [type=submit]').prop('disabled')) {
                    return;
                }

                if (this.roomState == 323) {
                    $('#meet-setup').addClass('hidden')
                    $('#meet-auth').removeClass('hidden')
                    return
                }

                if (this.roomState != 'ready' && !this.session.token) {
                    this.initSession(true)
                    return
                }

                clearTimeout(roomRequest)

                this.session.nickname = this.nickname
                this.session.languages = this.languages
                this.session.menuElement = $('#meet-session-menu')[0]
                this.session.chatElement = $('#meet-chat')[0]
                this.session.queueElement = $('#meet-queue')[0]
                this.session.counterElement = $('#meet-counter span')[0]
                this.session.translate = (label, args) => this.$t(label, args)
                this.session.toast = this.$toast
                this.session.onSuccess = () => {
                    $('#app').addClass('meet')
                    $('#meet-setup').addClass('hidden')
                    $('#meet-session-toolbar,#meet-session-layout').removeClass('hidden')
                }
                this.session.onError = () => {
                    this.roomState = 500
                }
                this.session.onDestroy = event => {
                    // TODO: Display different message for every other reason
                    if (event.reason == 'session-closed' && !this.isRoomOwner()) {
                        this.$refs.leaveDialog.show()
                    }
                }
                this.session.onUpdate = data => { this.updateSession(data) }
                this.session.onMediaSetup = () => { this.setupMedia() }

                this.meet.joinRoom(this.session)

                this.keyboardShortcuts()
            },
            keyboardShortcuts() {
                $(document.body).on('keydown.meet', e => {
                    if ($(e.target).is('select,input,textarea')) {
                        return
                    }

                    // Self-Mute with 'm' key
                    if (e.key == 'm' || e.key == 'M') {
                        if ($('#meet-session-menu').find('.link-audio:not(:disabled)').length) {
                            this.switchSound()
                        }
                    }
                    // Show stats with '?' key
                    if (e.key == '?') {
                        this.$refs.statsDialog.toggle(this.meet)
                    }
                })
            },
            logout() {
                this.meet.leaveRoom(true)
                this.meet = null
                this.$router.push({ name: 'dashboard' })
            },
            makePicture() {
                return (new Media()).makePicture($('#meet-setup video')[0]) || '';
            },
            requestId() {
                const key = 'kolab-meet-uid'

                if (!this.reqId) {
                    this.reqId = localStorage.getItem(key)
                }

                if (!this.reqId) {
                    // We store the identifier in the browser to make sure that it is the same after
                    // page refresh for the avg user. This will not prevent hackers from sending
                    // the new identifier on every request.
                    // If we're afraid of a room owner being spammed with join requests we might invent
                    // a way to silently ignore all join requests after the owner pressed some button
                    // stating "all attendees already joined, lock the room for good!".

                    // This will create max. 24-char numeric string
                    this.reqId = (String(Date.now()) + String(Math.random()).substring(2)).substring(0, 24)
                    localStorage.setItem(key, this.reqId)
                }

                return this.reqId
            },
            roomOptions() {
                new Modal('#room-options-dialog').show()
            },
            setupMedia() {
                const dialog = $('#media-setup-dialog')[0]

                if (!$('video', dialog).length) {
                    $('#meet-setup').find('video,div.volume').appendTo($('.media-setup-preview', dialog))
                }

                this.$refs.setupDialog.show()
            },
            async setupSession() {
                this.meet.setupStart({
                    videoElement: $('#meet-setup video')[0] || $('#media-setup-dialog video')[0],
                    volumeElement: $('#meet-setup .volume')[0] || $('#media-setup-dialog .volume')[0],
                    onSuccess: setup => {
                        this.setup = setup
                        this.microphone = setup.audioSource
                        this.camera = setup.videoSource
                        this.audioActive = setup.audioActive
                        this.videoActive = setup.videoActive
                    },
                    onError: error => {
                        console.warn("Media setup failed: ", error);
                        this.audioActive = false
                        this.videoActive = false
                    }
                })
            },
            async setupCameraChange() {
                this.videoActive = await this.meet.setupSetVideoDevice(this.camera)
            },
            async setupMicrophoneChange() {
                this.audioActive = await this.meet.setupSetAudioDevice(this.microphone)
            },
            switchChannel(e) {
                this.meet.switchChannel($(e.target).data('code'))
                // FIXME: Why is the menu not closing by itself?
                new Dropdown('#meet-session-menu .link-channel').hide()
            },
            switchChat() {
                let chat = $('#meet-chat')
                let enabled = chat.is('.open')

                chat.toggleClass('open')

                if (!enabled) {
                    chat.find('textarea').focus()
                }

                this.chatActive = !enabled

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
            async switchHand() {
                this.handRaised = await this.meet.raiseHand(!this.handRaised)
            },
            async switchSound() {
                this.audioActive = await this.meet.switchAudio()
            },
            async switchVideo() {
                this.videoActive = await this.meet.switchVideo()
            },
            async switchScreen() {
                this.screenActive = await this.meet.switchScreen()
            },
            updateSession(data) {
                this.session = Object.assign({}, this.session, data)
                this.channels = data.channels || []

                const isPublisher = this.isPublisher()

                this.videoActive = isPublisher ? data.videoActive : false
                this.audioActive = isPublisher ? data.audioActive : false
                this.handRaised = data.raisedHand
            }
        }
    }
</script>
