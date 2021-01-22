import anchorme from 'anchorme'
import { library } from '@fortawesome/fontawesome-svg-core'
import { OpenVidu } from 'openvidu-browser'

class Roles {
    static get SUBSCRIBER() { return 1 << 0; }
    static get PUBLISHER() { return 1 << 1; }
    static get MODERATOR() { return 1 << 2; }
    static get SCREEN() { return 1 << 3; }
    static get OWNER() { return 1 << 4; }
}

function Meet(container)
{
    let OV                      // OpenVidu object to initialize a session
    let session                 // Session object where the user will connect
    let publisher               // Publisher object which the user will publish
    let audioActive = false     // True if the audio track of the publisher is active
    let videoActive = false     // True if the video track of the publisher is active
    let audioSource = ''        // Currently selected microphone
    let videoSource = ''        // Currently selected camera
    let sessionData             // Room session metadata

    let screenOV                // OpenVidu object to initialize a screen sharing session
    let screenSession           // Session object where the user will connect for screen sharing
    let screenPublisher         // Publisher object which the user will publish the screen sharing

    let publisherDefaults = {
        publishAudio: true,     // Whether to start publishing with your audio unmuted or not
        publishVideo: true,     // Whether to start publishing with your video enabled or not
        resolution: '640x480',  // The resolution of your video
        frameRate: 30,          // The frame rate of your video
        mirror: true            // Whether to mirror your local video or not
    }

    let cameras = []            // List of user video devices
    let microphones = []        // List of user audio devices
    let connections = {}        // Connected users in the session

    let containerWidth
    let containerHeight
    let chatCount = 0
    let volumeElement
    let setupProps
    let subscribersContainer

    OV = new OpenVidu()
    screenOV = new OpenVidu()

    // If there's anything to do, do it here.
    //OV.setAdvancedConfiguration(config)

    // Disable all logging except errors
    // OV.enableProdMode()

    // Disconnect participant when browser's window close
    window.addEventListener('beforeunload', () => {
        leaveRoom()
    })

    window.addEventListener('resize', resize)

    // Public methods
    this.isScreenSharingSupported = isScreenSharingSupported
    this.joinRoom = joinRoom
    this.leaveRoom = leaveRoom
    this.setup = setup
    this.setupSetAudioDevice = setupSetAudioDevice
    this.setupSetVideoDevice = setupSetVideoDevice
    this.switchAudio = switchAudio
    this.switchScreen = switchScreen
    this.switchVideo = switchVideo
    this.updateSession = updateSession

    /**
     * Join the room session
     *
     * @param data Session metadata and event handlers (token, shareToken, nickname, role,
     *             chatElement, menuElement, onDestroy, onJoinRequest, onDismiss, onConnectionChange,
     *             onSessionDataUpdate)
     */
    function joinRoom(data) {
        resize();
        volumeMeterStop()

        data.params = {
            nickname: data.nickname, // user nickname
            // avatar: undefined        // avatar image
        }

        // Create a container for subscribers
        if (!subscribersContainer) {
            subscribersContainer = $('<div id="meet-subscribers">').appendTo(container).get(0)
        }

        sessionData = data

        // Init a session
        session = OV.initSession()

        // Handle connection creation events
        session.on('connectionCreated', event => {
            // Ignore the current user connection
            if (event.connection.role) {
                return
            }

            // This is the first event executed when a user joins in.
            // We'll create the video wrapper here, which can be re-used
            // in 'streamCreated' event handler.
            // Note: For a user with a subscriber role 'streamCreated' event
            // is not being dispatched at all

            let metadata = connectionData(event.connection)

            metadata.element = participantCreate(metadata)

            connections[metadata.connectionId] = metadata

            resize()

            // Send the current user status to the connecting user
            // otherwise e.g. nickname might be not up to date
            signalUserUpdate(event.connection)
        })

        session.on('connectionDestroyed', event => {
            let conn = connections[event.connection.connectionId]
            if (conn) {
                $(conn.element).remove()
                delete connections[event.connection.connectionId]
            }
            resize()
        })

        // On every new Stream received...
        session.on('streamCreated', event => {
            let connectionId = event.stream.connection.connectionId
            let metadata = connections[connectionId]
            let props = {
                // Prepend the video element so it is always before the watermark element
                insertMode: 'PREPEND'
            }

            // Subscribe to the Stream to receive it
            let subscriber = session.subscribe(event.stream, metadata.element, props);

            subscriber.on('videoElementCreated', event => {
                $(event.element).prop({
                    tabindex: -1
                })

                resize()
            })

            metadata.audioActive = event.stream.audioActive
            metadata.videoActive = event.stream.videoActive

            // Update the wrapper controls/status
            participantUpdate(metadata.element, metadata)
        })

        // Stream properties changes e.g. audio/video muted/unmuted
        session.on('streamPropertyChanged', event => {
            let connectionId = event.stream.connection.connectionId
            let metadata = connections[connectionId]

            if (session.connection.connectionId == connectionId) {
                metadata = sessionData
            }

            if (metadata) {
                metadata[event.changedProperty] = event.newValue
                participantUpdate(metadata.element, metadata)
            }
        })

        // Handle session disconnection events
        session.on('sessionDisconnected', event => {
            if (data.onDestroy) {
                data.onDestroy(event)
            }

            resize()
        })

        // Handle signals from all participants
        session.on('signal', signalEventHandler)

        // Connect with the token
        session.connect(data.token, data.params)
            .then(() => {
                let params = {
                    connectionId: session.connection.connectionId,
                    role: data.role,
                    audioActive,
                    videoActive
                }

                params = Object.assign({}, data.params, params)

                publisher.on('videoElementCreated', event => {
                    $(event.element).prop({
                            muted: true, // Mute local video to avoid feedback
                            disablePictureInPicture: true, // this does not work in Firefox
                            tabindex: -1
                    })
                    resize()
                })

                let wrapper = participantCreate(params)

                if (data.role & Roles.PUBLISHER) {
                    publisher.createVideoElement(wrapper, 'PREPEND')
                    session.publish(publisher)
                }

                resize()
                sessionData.element = wrapper
            })
            .catch(error => {
                console.error('There was an error connecting to the session: ', error.message);
            })

        // Prepare the chat
        setupChat()
    }

    /**
     * Leave the room (disconnect)
     */
    function leaveRoom() {
        if (publisher) {
            volumeMeterStop()

            // FIXME: We have to unpublish streams only if there's no session yet
            if (!session && audioActive) {
                publisher.publishAudio(false)
            }
            if (!session && videoActive) {
                publisher.publishVideo(false)
            }

            publisher = null
        }

        if (session) {
            session.disconnect();
            session = null
        }

        if (screenSession) {
            screenSession.disconnect();
            screenSession = null
        }
    }

    /**
     * Sets the audio and video devices for the session.
     * This will ask user for permission to access media devices.
     *
     * @param props Setup properties (videoElement, volumeElement, onSuccess, onError)
     */
    function setup(props) {
        setupProps = props

        publisher = OV.initPublisher(undefined, publisherDefaults)

        publisher.once('accessDenied', error => {
            props.onError(error)
        })

        publisher.once('accessAllowed', async () => {
            let mediaStream = publisher.stream.getMediaStream()
            let videoStream = mediaStream.getVideoTracks()[0]
            let audioStream = mediaStream.getAudioTracks()[0]

            audioActive = !!audioStream
            videoActive = !!videoStream
            volumeElement = props.volumeElement

            publisher.addVideoElement(props.videoElement)

            volumeMeterStart()

            const devices = await OV.getDevices()

            devices.forEach(device => {
                // device's props: deviceId, kind, label
                if (device.kind == 'videoinput') {
                    cameras.push(device)
                    if (videoStream && videoStream.label == device.label) {
                        videoSource = device.deviceId
                    }
                } else if (device.kind == 'audioinput') {
                    microphones.push(device)
                    if (audioStream && audioStream.label == device.label) {
                        audioSource = device.deviceId
                    }
                }
            })

            props.onSuccess({
                microphones,
                cameras,
                audioSource,
                videoSource,
                audioActive,
                videoActive
            })
        })
    }

    /**
     * Change the publisher audio device
     *
     * @param deviceId Device identifier string
     */
    async function setupSetAudioDevice(deviceId) {
        if (!deviceId) {
            publisher.publishAudio(false)
            volumeMeterStop()
            audioActive = false
        } else if (deviceId == audioSource) {
            publisher.publishAudio(true)
            volumeMeterStart()
            audioActive = true
        } else {
            const mediaStream = publisher.stream.mediaStream
            const oldTrack = mediaStream.getAudioTracks()[0]

            let properties = Object.assign({}, publisherDefaults, {
                publishAudio: true,
                publishVideo: videoActive,
                audioSource: deviceId,
                videoSource: videoSource
            })

            volumeMeterStop()

            // Note: We're not using publisher.replaceTrack() as it wasn't working for me

            // Stop and remove the old track
            if (oldTrack) {
                oldTrack.stop()
                mediaStream.removeTrack(oldTrack)
            }

            // TODO: Handle errors

            await OV.getUserMedia(properties)
                .then(async (newMediaStream) => {
                    publisher.stream.mediaStream = newMediaStream
                    volumeMeterStart()
                    audioActive = true
                    audioSource = deviceId
                })
        }

        return audioActive
    }

    /**
     * Change the publisher video device
     *
     * @param deviceId Device identifier string
     */
    async function setupSetVideoDevice(deviceId) {
        if (!deviceId) {
            publisher.publishVideo(false)
            videoActive = false
        } else if (deviceId == videoSource) {
            publisher.publishVideo(true)
            videoActive = true
        } else {
            const mediaStream = publisher.stream.mediaStream
            const oldTrack = mediaStream.getAudioTracks()[0]

            let properties = Object.assign({}, publisherDefaults, {
                publishAudio: audioActive,
                publishVideo: true,
                audioSource: audioSource,
                videoSource: deviceId
            })

            volumeMeterStop()

            // Stop and remove the old track
            if (oldTrack) {
                oldTrack.stop()
                mediaStream.removeTrack(oldTrack)
            }

            // TODO: Handle errors

            await OV.getUserMedia(properties)
                .then(async (newMediaStream) => {
                    publisher.stream.mediaStream = newMediaStream
                    volumeMeterStart()
                    videoActive = true
                    videoSource = deviceId
                })
        }

        return videoActive
    }

    /**
     * Setup the chat UI
     */
    function setupChat() {
        // The UI elements are created in the vue template
        // Here we add a logic for how they work

        const textarea = $(sessionData.chatElement).find('textarea')
        const button = $(sessionData.menuElement).find('.link-chat')

        textarea.on('keydown', e => {
            if (e.keyCode == 13 && !e.shiftKey) {
                if (textarea.val().length) {
                    signalChat(textarea.val())
                    textarea.val('')
                }

                return false
            }
        })

        // Add an element for the count of unread messages on the chat button
        button.append('<span class="badge badge-dark blinker">')
            .on('click', () => {
                button.find('.badge').text('')
                chatCount = 0
            })
    }

    /**
     * Signal events handler
     */
    function signalEventHandler(signal) {
        let conn, data
        let connId = signal.from ? signal.from.connectionId : null

        switch (signal.type) {
            case 'signal:userChanged':
                // TODO: Use 'signal:connectionUpdate' for nickname updates?
                if (conn = connections[connId]) {
                    data = JSON.parse(signal.data)

                    conn.nickname = data.nickname
                    participantUpdate(conn.element, conn)
                    nicknameUpdate(data.nickname, connId)
                }
                break

            case 'signal:chat':
                data = JSON.parse(signal.data)
                data.id = connId
                pushChatMessage(data)
                break

            case 'signal:joinRequest':
                // accept requests from the server only
                if (!connId && sessionData.onJoinRequest) {
                    sessionData.onJoinRequest(JSON.parse(signal.data))
                }
                break

            case 'signal:connectionUpdate':
                // accept requests from the server only
                if (!connId) {
                    data = JSON.parse(signal.data)

                    connectionUpdate(data)
                }
                break
        }
    }

    /**
     * Send the chat message to other participants
     *
     * @param message Message string
     */
    function signalChat(message) {
        let data = {
            nickname: sessionData.params.nickname,
            message
        }

        session.signal({
            data: JSON.stringify(data),
            type: 'chat'
        })
    }

    /**
     * Add a message to the chat
     *
     * @param data Object with a message, nickname, id (of the connection, empty for self)
     */
    function pushChatMessage(data) {
        let message = $('<span>').text(data.message).text() // make the message secure

        // Format the message, convert emails and urls to links
        message = anchorme({
            input: message,
            options: {
                attributes: {
                    target: "_blank"
                },
                // any link above 20 characters will be truncated
                // to 20 characters and ellipses at the end
                truncate: 20,
                // characters will be taken out of the middle
                middleTruncation: true
            }
            // TODO: anchorme is extensible, we could support
            //       github/phabricator's markup e.g. backticks for code samples
        })

        message = message.replace(/\r?\n/, '<br>')

        // Display the message
        let isSelf = data.id == session.connectionId
        let chat = $(sessionData.chatElement).find('.chat')
        let box = chat.find('.message').last()

        message = $('<div>').html(message)

        message.find('a').attr('rel', 'noreferrer')

        if (box.length && box.data('id') == data.id) {
            // A message from the same user as the last message, no new box needed
            message.appendTo(box)
        } else {
            box = $('<div class="message">').data('id', data.id)
                .append($('<div class="nickname">').text(data.nickname || ''))
                .append(message)
                .appendTo(chat)

            if (isSelf) {
                box.addClass('self')
            }
        }

        // Count unread messages
        if (!$(sessionData.chatElement).is('.open')) {
            if (!isSelf) {
                chatCount++
            }
        } else {
            chatCount = 0
        }

        $(sessionData.menuElement).find('.link-chat .badge').text(chatCount ? chatCount : '')
    }

    /**
     * Send the user properties update signal to other participants
     *
     * @param connection Optional connection to which the signal will be sent
     *                   If not specified the signal is sent to all participants
     */
    function signalUserUpdate(connection) {
        let data = {
            nickname: sessionData.params.nickname
        }

        session.signal({
            data: JSON.stringify(data),
            type: 'userChanged',
            to: connection ? [connection] : undefined
        })

        // The same nickname for screen sharing session
        if (screenSession) {
            screenSession.signal({
                data: JSON.stringify(data),
                type: 'userChanged',
                to: connection ? [connection] : undefined
            })
        }
    }

    /**
     * Mute/Unmute audio for current session publisher
     */
    function switchAudio() {
        // TODO: If user has no devices or denied access to them in the setup,
        //       the button will just not work. Find a way to make it working
        //       after user unlocks his devices. For now he has to refresh
        //       the page and join the room again.
        if (microphones.length) {
            try {
                publisher.publishAudio(!audioActive)
                audioActive = !audioActive
            } catch (e) {
                console.error(e)
            }
        }

        return audioActive
    }

    /**
     * Mute/Unmute video for current session publisher
     */
    function switchVideo() {
        // TODO: If user has no devices or denied access to them in the setup,
        //       the button will just not work. Find a way to make it working
        //       after user unlocks his devices. For now he has to refresh
        //       the page and join the room again.
        if (cameras.length) {
            try {
                publisher.publishVideo(!videoActive)
                videoActive = !videoActive
            } catch (e) {
                console.error(e)
            }
        }

        return videoActive
    }

    /**
     * Switch on/off screen sharing
     */
    function switchScreen(callback) {
        if (screenPublisher) {
            screenSession.disconnect()
            screenSession = null
            screenPublisher = null

            if (callback) {
                // Note: Disconnecting invalidates the token. The callback should request
                //       a new token for the next screen sharing session.
                callback(false)
            }

            return
        }

        screenConnect(callback)
    }

    /**
     * Detect if screen sharing is supported by the browser
     */
    function isScreenSharingSupported() {
        return !!OV.checkScreenSharingCapabilities();
    }

    /**
     * Update participant connection state
     */
    function connectionUpdate(data) {
        let conn = connections[data.connectionId]

        // It's me
        if (session.connection.connectionId == data.connectionId) {
            const rolePublisher = data.role && data.role & Roles.PUBLISHER
            const isPublisher = sessionData.role & Roles.PUBLISHER

            // Inform the vue component, so it can update some UI controls
            let update = () => {
                if (sessionData.onSessionDataUpdate) {
                    sessionData.onSessionDataUpdate(data)
                }
            }

            // demoted to a subscriber
            if ('role' in data && isPublisher && !rolePublisher) {
                session.unpublish(publisher)
                // FIXME: There's a reference in OpenVidu to a video element that should not
                // exist anymore. It causes issues when we try to do publish/unpublish
                // sequence multiple times in a row. So, we're clearing the reference here.
                let videos = publisher.stream.streamManager.videos
                publisher.stream.streamManager.videos = videos.filter(video => video.video.parentNode != null)
            }

            // merge the changed data into internal session metadata object
            Object.keys(data).forEach(key => { sessionData[key] = data[key] })

            // update the participant element
            sessionData.element = participantUpdate(sessionData.element, sessionData)

            // promoted to a publisher
            if ('role' in data && !isPublisher && rolePublisher) {
                publisher.createVideoElement(sessionData.element, 'PREPEND')
                session.publish(publisher).then(() => {
                    data.audioActive = publisher.stream.audioActive
                    data.videoActive = publisher.stream.videoActive
                    update()
                })

                // TODO: Here the user is asked for media permissions again
                // should we rather start the stream without asking the user?
                // Or maybe we want to display the media setup/preview form?
                // Need to find a way to do this.
            } else {
                // Inform the vue component, so it can update some UI controls
                update()
            }
        } else if (conn) {
            // merge the changed data into internal session metadata object
            Object.keys(data).forEach(key => { conn[key] = data[key] })

            conn.element = participantUpdate(conn.element, conn)
        }
    }

    /**
     * Update nickname in chat
     *
     * @param nickname     Nickname
     * @param connectionId Connection identifier of the user
     */
    function nicknameUpdate(nickname, connectionId) {
        if (connectionId) {
            $(sessionData.chatElement).find('.chat').find('.message').each(function() {
                let elem = $(this)
                if (elem.data('id') == connectionId) {
                    elem.find('.nickname').text(nickname || '')
                }
            })
        }
    }

    /**
     * Create a participant element in the matrix. Depending on the connection role
     * parameter it will be a video element wrapper inside the matrix or a simple
     * tag-like element on the subscribers list.
     *
     * @param params Connection metadata/params
     *
     * @return The element
     */
    function participantCreate(params) {
        params.isSelf = params.isSelf || session.connection.connectionId == params.connectionId

        if (params.role & Roles.PUBLISHER || params.role & Roles.SCREEN) {
            return publisherCreate(params)
        }

        return subscriberCreate(params)
    }

    /**
     * Create a <video> element wrapper with controls
     *
     * @param params Connection metadata/params
     */
    function publisherCreate(params) {
        // Create the element
        let wrapper = $(
            '<div class="meet-video">'
            + svgIcon('user', 'fas', 'watermark')
            + '<div class="controls">'
                + '<button type="button" class="btn btn-link link-audio hidden" title="Mute audio">' + svgIcon('volume-mute') + '</button>'
                + '<button type="button" class="btn btn-link link-fullscreen closed hidden" title="Full screen">' + svgIcon('expand') + '</button>'
                + '<button type="button" class="btn btn-link link-fullscreen open hidden" title="Full screen">' + svgIcon('compress') + '</button>'
            + '</div>'
            + '<div class="status">'
                + '<span class="bg-danger status-audio hidden">' + svgIcon('microphone') + '</span>'
                + '<span class="bg-danger status-video hidden">' + svgIcon('video') + '</span>'
            + '</div>'
            + '</div>'
        )

        // Append the nickname widget
        wrapper.find('.controls').before(nicknameWidget(params))

        if (!params.isSelf) {
            // Enable audio mute button
            wrapper.find('.link-audio').removeClass('hidden')
                .on('click', e => {
                    let video = wrapper.find('video')[0]
                    video.muted = !video.muted
                    wrapper.find('.link-audio')[video.muted ? 'addClass' : 'removeClass']('text-danger')
                })
        }

        participantUpdate(wrapper, params)

        // Fullscreen control
        if (document.fullscreenEnabled) {
            wrapper.find('.link-fullscreen.closed').removeClass('hidden')
                .on('click', () => {
                    wrapper.get(0).requestFullscreen()
                })

            wrapper.find('.link-fullscreen.open')
                .on('click', () => {
                    document.exitFullscreen()
                })

            wrapper.on('fullscreenchange', () => {
                // const enabled = document.fullscreenElement
                wrapper.find('.link-fullscreen.closed').toggleClass('hidden')
                wrapper.find('.link-fullscreen.open').toggleClass('hidden')
                wrapper.toggleClass('fullscreen')
            })
        }

        // Remove the subscriber element, if exists
        $('#subscriber-' + params.connectionId).remove()

        return wrapper[params.isSelf ? 'prependTo' : 'appendTo'](container)
            .attr('id', 'publisher-' + params.connectionId)
            .get(0)
    }

    /**
     * Update the <video> wrapper controls
     *
     * @param wrapper The wrapper element
     * @param params  Connection metadata/params
     */
    function participantUpdate(wrapper, params) {
        const element = $(wrapper)
        const isModerator = sessionData.role & Roles.MODERATOR
        const isSelf = session.connection.connectionId == params.connectionId

        // Handle publisher-to-subscriber and subscriber-to-publisher change
        if ('role' in params && !(params.role & Roles.SCREEN)) {
            const rolePublisher = params.role & Roles.PUBLISHER
            const isPublisher = element.is('.meet-video')

            if ((rolePublisher && !isPublisher) || (!rolePublisher && isPublisher)) {
                element.remove()
                const wrapper = participantCreate(params)
                resize()
                return wrapper;
            }

            element.find('.action-role-publisher input').prop('checked', params.role & Roles.PUBLISHER)
        }

        if ('audioActive' in params) {
            element.find('.status-audio')[params.audioActive ? 'addClass' : 'removeClass']('hidden')
        }

        if ('videoActive' in params) {
            element.find('.status-video')[params.videoActive ? 'addClass' : 'removeClass']('hidden')
        }

        if ('nickname' in params) {
            element.find('.meet-nickname > .content').text(params.nickname)
        }

        if (isSelf) {
            element.addClass('self')
        }

        if (isModerator) {
            element.addClass('moderated')
        }

        element.find('.dropdown-menu')[isSelf || isModerator ? 'removeClass' : 'addClass']('hidden')
        element.find('.permissions')[isModerator ? 'removeClass' : 'addClass']('hidden')

        if ('role' in params && params.role & Roles.SCREEN) {
            element.find('.permissions').addClass('hidden')
        }

        return wrapper
    }

    /**
     * Create a tag-like element for a subscriber participant
     *
     * @param params Connection metadata/params
     */
    function subscriberCreate(params) {
        // Create the element
        let wrapper = $('<div class="meet-subscriber">').append(nicknameWidget(params))

        participantUpdate(wrapper, params)

        return wrapper[params.isSelf ? 'prependTo' : 'appendTo'](subscribersContainer)
            .attr('id', 'subscriber-' + params.connectionId)
            .get(0)
    }

    /**
     * Create a tag-like nickname widget
     *
     * @param object params Connection metadata/params
     */
    function nicknameWidget(params) {
        // Create the element
        let element = $(
            '<div class="dropdown">'
                + '<a href="#" class="meet-nickname btn" aria-haspopup="true" aria-expanded="false" role="button">'
                    + '<span class="content"></span>'
                    + '<span class="icon">' + svgIcon('user') + '</span>'
                + '</a>'
                + '<div class="dropdown-menu">'
                    + '<a class="dropdown-item action-nickname" href="#">Nickname</a>'
                    + '<a class="dropdown-item action-dismiss" href="#">Dismiss</a>'
                    + '<div class="dropdown-divider permissions"></div>'
                    + '<div class="permissions">'
                        + '<h6 class="dropdown-header">Permissions</h6>'
                        + '<label class="dropdown-item action-role-publisher custom-control custom-switch">'
                            + '<input type="checkbox" class="custom-control-input">'
                            + ' <span class="custom-control-label">CAN_HAZ_AUDIO_AND_VIDEO</span>'
                        + '</label>'
                        //+ '<label class="dropdown-item action-role-moderator custom-control custom-switch">'
                        //    + '<input type="checkbox" class="custom-control-input">'
                        //    + ' <span class="custom-control-label">CAN_MODERATE</span>'
                        //+ '</label>'
                    + '</div>'
                + '</div>'
            + '</div>'
        )

        let nickname = element.find('.meet-nickname')
            .addClass('btn btn-outline-' + (params.isSelf ? 'primary' : 'secondary'))
            .attr({title: 'Options', 'data-toggle': 'dropdown'})
            .dropdown({boundary: container})

        if (params.isSelf) {
            // Add events for nickname change
            let editable = element.find('.content')[0]
            let editableEnable = () => {
                editable.contentEditable = true
                editable.focus()
            }
            let editableUpdate = () => {
                editable.contentEditable = false
                sessionData.params.nickname = editable.innerText
                signalUserUpdate()
                nicknameUpdate(editable.innerText, session.connection.connectionId)
            }

            element.find('.action-nickname').on('click', editableEnable)
            element.find('.action-dismiss').remove()

            $(editable).on('blur', editableUpdate)
                .on('keydown', e => {
                    // Enter or Esc
                    if (e.keyCode == 13 || e.keyCode == 27) {
                        editableUpdate()
                        return false
                    }
                })
        } else {
            element.find('.action-nickname').remove()

            element.find('.action-dismiss').on('click', e => {
                if (sessionData.onDismiss) {
                    sessionData.onDismiss(params.connectionId)
                }
            })
        }

        // Don't close the menu on permission change
        element.find('.dropdown-menu > label').on('click', e => { e.stopPropagation() })

        if (sessionData.onConnectionChange) {
            element.find('.action-role-publisher input').on('change', e => {
                const enabled = e.target.checked
                let role = params.role

                if (enabled) {
                    role |= Roles.PUBLISHER
                } else {
                    role |= Roles.SUBSCRIBER
                    if (role & Roles.PUBLISHER) {
                        role ^= Roles.PUBLISHER
                    }
                }

                sessionData.onConnectionChange(params.connectionId, { role })
            })

            element.find('.action-role-moderator input').on('change', e => {
                const enabled = e.target.checked
                let role = params.role

                if (enabled) {
                    role |= Roles.MODERATOR
                } else if (role & Roles.MODERATOR) {
                    role ^= Roles.MODERATOR
                }

                sessionData.onConnectionChange(params.connectionId, { role })
            })
        }

        return element.get(0)
    }

    /**
     * Window onresize event handler (updates room layout)
     */
    function resize() {
        containerWidth = container.offsetWidth
        containerHeight = container.offsetHeight

        if (subscribersContainer) {
            containerHeight -= subscribersContainer.offsetHeight
        }

        updateLayout()
        $(container).parent()[window.screen.width <= 768 ? 'addClass' : 'removeClass']('mobile')
    }

    /**
     * Update the room "matrix" layout
     */
    function updateLayout() {
        let numOfVideos = $(container).find('.meet-video').length
        if (!numOfVideos) {
            return
        }

        let css, rows, cols, height

        const factor = containerWidth / containerHeight

        if (factor >= 16/9) {
            if (numOfVideos <= 3) {
                rows = 1
            } else if (numOfVideos <= 8) {
                rows = 2
            } else if (numOfVideos <= 15) {
                rows = 3
            } else if (numOfVideos <= 20) {
                rows = 4
            } else {
                rows = 5
            }

            cols = Math.ceil(numOfVideos / rows)
        } else {
            if (numOfVideos == 1) {
                cols = 1
            } else if (numOfVideos <= 4) {
                cols = 2
            } else if (numOfVideos <= 9) {
                cols = 3
            } else if (numOfVideos <= 16) {
                cols = 4
            } else if (numOfVideos <= 25) {
                cols = 5
            } else {
                cols = 6
            }

            rows = Math.ceil(numOfVideos / cols)

            if (rows < cols && containerWidth < containerHeight) {
                cols = rows
                rows = Math.ceil(numOfVideos / cols)
            }
        }

        // console.log('factor=' + factor, 'num=' + numOfVideos, 'cols = '+cols, 'rows=' + rows);

        height = containerHeight / rows
        css = {
            width: (100 / cols) + '%',
            // Height must be in pixels to make object-fit:cover working
            height: height + 'px'
        }

        // Update the matrix
        $(container).find('.meet-video').css(css)
        /*
            .each((idx, elem) => {
                let video = $(elem).children('video')[0]

                if (video && video.videoWidth && video.videoHeight && video.videoWidth > video.videoHeight) {
                    // Set max-width to keep the original aspect ratio in cases
                    // when there's enough room to display the element
                    let maxWidth = height * video.videoWidth / video.videoHeight
                    $(elem).css('max-width', maxWidth)
                }
            })
        */
    }

    /**
     * Initialize screen sharing session/publisher
     */
    function screenConnect(callback) {
        if (!sessionData.shareToken) {
            return false
        }

        let gotSession = !!screenSession

        // Init screen sharing session
        if (!gotSession) {
            screenSession = screenOV.initSession();
        }

        let successFunc = function() {
            screenSession.publish(screenPublisher)
            if (callback) {
                callback(true)
            }
        }

        let errorFunc = function() {
            screenPublisher = null
            if (callback) {
                callback(false)
            }
        }

        // Init the publisher
        let params = {
            videoSource: 'screen',
            publishAudio: false
        }

        screenPublisher = screenOV.initPublisher(null, params)

        screenPublisher.once('accessAllowed', (event) => {
            if (gotSession) {
                successFunc()
            } else {
                screenSession.connect(sessionData.shareToken, sessionData.params)
                    .then(() => {
                        successFunc()
                    })
                    .catch(error => {
                        console.error('There was an error connecting to the session:', error.code, error.message);
                        errorFunc()
                    })
            }
        })

        screenPublisher.once('accessDenied', () => {
            console.info('ScreenShare: Access Denied')
            errorFunc()
        })
    }

    /**
     * Create an svg element (string) for a FontAwesome icon
     *
     * @todo Find if there's a "official" way to do this
     */
    function svgIcon(name, type, className) {
        // Note: the library will contain definitions for all icons registered elswhere
        const icon = library.definitions[type || 'fas'][name]

        let attrs = {
            'class': 'svg-inline--fa',
            'aria-hidden': true,
            focusable: false,
            role: 'img',
            xmlns: 'http://www.w3.org/2000/svg',
            viewBox: `0 0 ${icon[0]} ${icon[1]}`
        }

        if (className) {
            attrs['class'] += ' ' + className
        }

        return $(`<svg><path fill="currentColor" d="${icon[4]}"></path></svg>`)
            .attr(attrs)
            .get(0).outerHTML
    }

    /**
     * A way to update some session data, after you joined the room
     *
     * @param data Same input as for joinRoom(), but for now it supports only shareToken
     */
    function updateSession(data) {
        sessionData.shareToken = data.shareToken
    }

    /**
     * A handler for volume level change events
     */
    function volumeChangeHandler(event) {
        let value = 100 + Math.min(0, Math.max(-100, event.value.newValue))
        let color = 'lime'
        const bar = volumeElement.firstChild

        if (value >= 70) {
            color = '#ff3300'
        } else if (value >= 50) {
            color = '#ff9933'
        }

        bar.style.height = value + '%'
        bar.style.background = color
    }

    /**
     * Start the volume meter
     */
    function volumeMeterStart() {
        if (publisher && volumeElement) {
            publisher.on('streamAudioVolumeChange', volumeChangeHandler)
        }
    }

    /**
     * Stop the volume meter
     */
    function volumeMeterStop() {
        if (publisher && volumeElement) {
            publisher.off('streamAudioVolumeChange')
            volumeElement.firstChild.style.height = 0
        }
    }

    function connectionData(connection) {
        // Note: we're sending a json from two sources (server-side when
        // creating a token/connection, and client-side when joining the session)
        // OpenVidu is unable to merge these two objects into one, for it it is only
        // two strings, so it puts a "%/%" separator in between, we'll replace it with comma
        // to get one parseable json object
        let data = JSON.parse(connection.data.replace('}%/%{', ','))

        data.connectionId = connection.connectionId

        return data
    }
}

export { Meet, Roles }
