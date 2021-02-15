<template>
    <div id="meet-component">
        <div id="meet-session-toolbar" class="hidden">
            <div id="meet-session-menu">
                <button class="btn btn-link link-audio" @click="switchSound" :disabled="!isPublisher()" title="Mute audio">
                    <svg-icon icon="microphone"></svg-icon>
                </button>
                <button class="btn btn-link link-video" @click="switchVideo" :disabled="!isPublisher()" title="Mute video">
                    <svg-icon icon="video"></svg-icon>
                </button>
                <button class="btn btn-link link-screen text-danger" @click="switchScreen" :disabled="!canShareScreen || !isPublisher()" title="Share screen">
                    <svg-icon icon="desktop"></svg-icon>
                </button>
                <button class="btn btn-link link-hand text-danger" v-if="!isPublisher()" @click="switchHand" title="Raise hand">
                    <svg-icon icon="hand-paper"></svg-icon>
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
                <button class="btn btn-link link-security" v-if="isRoomOwner()" @click="securityOptions" title="Security options">
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
                    <form class="media-setup-form row" @submit.prevent="joinSession">
                        <div class="media-setup-preview col-sm-6 mb-3 mb-sm-0">
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
                                <button type="submit" id="join-button"
                                        :class="'btn w-100 btn-' + (isRoomReady() ? 'success' : 'primary')"
                                >
                                    <span v-if="isRoomReady()">JOIN NOW</span>
                                    <span v-else-if="roomState == 323">I'm the owner</span>
                                    <span v-else>JOIN</span>
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
                <div class="head" title="Q &amp; A"><svg-icon icon="microphone-alt"></svg-icon></div>
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

        <div id="media-setup-dialog" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Media setup</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form class="media-setup-form">
                            <div class="media-setup-preview"></div>
                            <div class="input-group mt-2">
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
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-action" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <session-security-options v-if="session.config" :config="session.config" :room="room" @config-update="configUpdate"></session-security-options>
    </div>
</template>

<script>
    import { Meet, Roles } from '../../js/meet/app.js'
    import StatusMessage from '../Widgets/StatusMessage'
    import LogonForm from '../Login'
    import SessionSecurityOptions from './SessionSecurityOptions'

    // Register additional icons
    import { library } from '@fortawesome/fontawesome-svg-core'

    import {
        faAlignLeft,
        faCog,
        faCompress,
        faCrown,
        faDesktop,
        faExpand,
        faHandPaper,
        faMicrophone,
        faMicrophoneAlt,
        faPowerOff,
        faUser,
        faShieldAlt,
        faVideo,
        faVolumeMute
    } from '@fortawesome/free-solid-svg-icons'

    // Register only these icons we need
    library.add(
        faAlignLeft,
        faCog,
        faCompress,
        faCrown,
        faDesktop,
        faExpand,
        faHandPaper,
        faMicrophone,
        faMicrophoneAlt,
        faPowerOff,
        faUser,
        faShieldAlt,
        faVideo,
        faVolumeMute
    )

    let roomRequest
    const authHeader = 'X-Meet-Auth-Token'

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
                    323: 'The room is closed. Please, wait for the owner to start the session.',
                    324: 'The room is closed. It will be open for others after you join.',
                    325: 'The room is ready. Please, provide a valid password.',
                    326: 'The room is locked. Please, enter your name and try again.',
                    327: 'Waiting for permission to join the room.',
                    404: 'The room does not exist.',
                    429: 'Too many requests. Please, wait.',
                    500: 'Failed to connect to the room. Server error.'
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
            clearTimeout(roomRequest)

            if (this.meet) {
                this.meet.leaveRoom()
            }

            delete axios.defaults.headers.common[authHeader]
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
            dismissParticipant(id) {
                axios.post('/api/v4/openvidu/rooms/' + this.room + '/connections/' + id + '/dismiss')
            },
            initSession(init) {
                const button = $('#join-button').prop('disabled', true)

                this.post = {
                    password: this.password,
                    nickname: this.nickname,
                    screenShare: this.canShareScreen ? 1 : 0,
                    init: init ? 1 : 0,
                    picture: init ? this.makePicture() : '',
                    requestId: this.requestId(),
                    canPublish: !!this.camera || !!this.microphone
                }

                $('#setup-password,#setup-nickname').removeClass('is-invalid')

                axios.post('/api/v4/openvidu/rooms/' + this.room, this.post, { ignoreErrors: true })
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

                        if (this.session.authToken) {
                            axios.defaults.headers.common[authHeader] = this.session.authToken
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
            // An event received by the room owner when a participant is asking for a permission to join the room
            joinRequest(data) {
                // The toast for this user request already exists, ignore
                // It's not really needed as we do this on server-side already
                if ($('#i' + data.requestId).length) {
                    return
                }

                // FIXME: Should the message close button act as the Deny button? Do we need the Deny button?

                let body = $(
                    `<div>`
                    + `<div class="picture"><img src="${data.picture}"></div>`
                    + `<div class="content">`
                        + `<p class="mb-2"></p>`
                        + `<div class="text-right">`
                            + `<button type="button" class="btn btn-sm btn-success accept">Accept</button>`
                            + `<button type="button" class="btn btn-sm btn-danger deny ml-2">Deny</button>`
                )

                this.$toast.message({
                    className: 'join-request',
                    icon: 'user',
                    timeout: 0,
                    title: 'Join request',
                    // titleClassName: '',
                    body: body.html(),
                    onShow: element => {
                        const id = data.requestId

                        $(element).find('p').text((data.nickname || '') + ' requested to join.')

                        // add id attribute, so we can identify it
                        $(element).attr('id', 'i' + id)
                            // add action to the buttons
                            .find('button.accept,button.deny').on('click', e => {
                                const action = $(e.target).is('.accept') ? 'accept' : 'deny'
                                axios.post('/api/v4/openvidu/rooms/' + this.room + '/request/' + id + '/' + action)
                                    .then(response => {
                                        $('#i' + id).remove()
                                    })
                            })
                    }
                })
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
                this.session.menuElement = $('#meet-session-menu')[0]
                this.session.chatElement = $('#meet-chat')[0]
                this.session.queueElement = $('#meet-queue')[0]
                this.session.onSuccess = () => {
                    $('#app').addClass('meet')
                    $('#meet-setup').addClass('hidden')
                    $('#meet-session-toolbar,#meet-session-layout').removeClass('hidden')

                    if (!this.canShareScreen) {
                        this.setMenuItem('screen', false, true)
                    }
                }
                this.session.onError = () => {
                    this.roomState = 500
                }
                this.session.onDestroy = event => {
                    // TODO: Display different message for each reason: forceDisconnectByUser,
                    //       forceDisconnectByServer, sessionClosedByServer?
                    if (event.reason != 'disconnect' && event.reason != 'networkDisconnect' && !this.isRoomOwner()) {
                        $('#leave-dialog').on('hide.bs.modal', () => {
                            // FIXME: Where exactly the user should land? Currently he'll land
                            //        on dashboard (if he's logged in) or login form (if he's not).

                            this.$router.push({ name: 'dashboard' })
                        }).modal()
                    }
                }
                this.session.onDismiss = connId => { this.dismissParticipant(connId) }
                this.session.onSessionDataUpdate = data => { this.updateSession(data) }
                this.session.onConnectionChange = (connId, data) => { this.updateParticipant(connId, data) }
                this.session.onJoinRequest = data => { this.joinRequest(data) }
                this.session.onMediaSetup = () => { this.setupMedia() }

                this.meet.joinRoom(this.session)
            },
            logout() {
                const logout = () => {
                    this.meet.leaveRoom()
                    this.meet = null
                    this.$router.push({ name: 'dashboard' })
                }

                if (this.isRoomOwner()) {
                    axios.post('/api/v4/openvidu/rooms/' + this.room + '/close').then(logout)
                } else {
                    logout()
                }
            },
            makePicture() {
                const video = $("#meet-setup video")[0];

                // Skip if video is not "playing"
                if (!video.videoWidth || !this.camera) {
                    return ''
                }

                // we're going to crop a square from the video and resize it
                const maxSize = 64

                // Calculate sizing
                let sh = Math.floor(video.videoHeight / 1.5)
                let sw = sh
                let sx = (video.videoWidth - sw) / 2
                let sy = (video.videoHeight - sh) / 2

                let dh = Math.min(sh, maxSize)
                let dw = sh < maxSize ? sw : Math.floor(sw * dh/sh)

                const canvas = $("<canvas>")[0];
                canvas.width = dw;
                canvas.height = dh;

                // draw the image on the canvas (square cropped and resized)
                canvas.getContext('2d').drawImage(video, sx, sy, sw, sh, 0, 0, dw, dh);

                // convert it to a usable data URL (png format)
                return canvas.toDataURL();
            },
            requestId() {
                if (!this.reqId) {
                    // FIXME: Shall we use some UUID generator? Or better something that identifies the
                    //        user/browser so we could deny the join request for a longer time.
                    //        I'm thinking about e.g. a bad actor knocking again and again and again,
                    //        we don't want the room owner to be bothered every few seconds.
                    //        Maybe a solution would be to store the identifier in the browser storage
                    //        This would not prevent hackers from sending the new identifier on every request,
                    //        but could make sure that it is kept after page refresh for the avg user.

                    // This will create max. 24-char numeric string
                    this.reqId = (String(Date.now()) + String(Math.random()).substring(2)).substring(0, 24)
                }

                return this.reqId
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
            setupMedia() {
                let dialog = $('#media-setup-dialog')

                if (!dialog.find('video').length) {
                    $('#meet-setup').find('video,div.volume').appendTo(dialog.find('.media-setup-preview'))
                }

                dialog.on('show.bs.modal', () => { this.meet.setupStart() })
                    .on('hide.bs.modal', () => { this.meet.setupStop() })
                    .modal()
            },
            setupSession() {
                this.meet.setupStart({
                    videoElement: $('#meet-setup video')[0],
                    volumeElement: $('#meet-setup .volume')[0],
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
            switchHand() {
                let enabled = $('#meet-session-menu').find('.link-hand').is('.text-danger')
                this.updateSelf({ hand: enabled }, () => { this.setMenuItem('hand', enabled) })
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
                const switchScreenAction = () => {
                    this.meet.switchScreen((enabled, error) => {
                        this.setMenuItem('screen', enabled)
                        if (!enabled && !error) {
                            // Closing a screen sharing connection invalidates the token
                            delete this.session.shareToken
                        }
                    })
                }

                if (this.session.shareToken || !$('#meet-session-menu').find('.link-screen').is('.text-danger')) {
                    switchScreenAction()
                } else {
                    axios.post('/api/v4/openvidu/rooms/' + this.room + '/connections')
                        .then(response => {
                            this.session.shareToken = response.data.token
                            this.meet.updateSession(this.session)
                            switchScreenAction()
                        })
                }
            },
            updateParticipant(connId, params) {
                if (this.isModerator()) {
                    axios.put('/api/v4/openvidu/rooms/' + this.room + '/connections/' + connId, params)
                }
            },
            updateSelf(params, onSuccess) {
                axios.put('/api/v4/openvidu/rooms/' + this.room + '/connections/' + this.session.connectionId, params)
                    .then(response => {
                        if (onSuccess) {
                            onSuccess(response)
                        }
                    })
            },
            updateSession(data) {
                let params = {}

                if ('role' in data) {
                    params.role = data.role
                }

                // merge new params into the object
                this.session = Object.assign({}, this.session, params)

                if ('hand' in data) {
                    this.setMenuItem('hand', data.hand)
                }

                // update some buttons state e.g. when switching from publisher to subscriber
                if (!this.isPublisher()) {
                    this.setMenuItem('audio', false)
                    this.setMenuItem('video', false)
                } else {
                    if ('videoActive' in data) {
                        this.setMenuItem('video', data.videoActive)
                    }
                    if ('audioActive' in data) {
                        this.setMenuItem('audio', data.audioActive)
                    }
                }
            }
        }
    }
</script>
