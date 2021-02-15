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
    let subscribersContainer
    let scrollStop

    OV = ovInit()

    // Disconnect participant when browser's window close
    window.addEventListener('beforeunload', () => {
        leaveRoom()
    })

    window.addEventListener('resize', resize)

    // Public methods
    this.isScreenSharingSupported = isScreenSharingSupported
    this.joinRoom = joinRoom
    this.leaveRoom = leaveRoom
    this.setupStart = setupStart
    this.setupStop = setupStop
    this.setupSetAudioDevice = setupSetAudioDevice
    this.setupSetVideoDevice = setupSetVideoDevice
    this.switchAudio = switchAudio
    this.switchScreen = switchScreen
    this.switchVideo = switchVideo
    this.updateSession = updateSession

    /**
     * Initialize OpenVidu instance
     */
    function ovInit()
    {
        let ov = new OpenVidu()

        // If there's anything to do, do it here.
        //ov.setAdvancedConfiguration(config)

        // Disable all logging except errors
        // ov.enableProdMode()

        return ov
    }

    /**
     * Join the room session
     *
     * @param data Session metadata and event handlers:
     *      token       - OpenVidu token for the main connection,
     *      shareToken  - OpenVidu token for screen-sharing connection,
     *      nickname    - Participant name,
     *      role        - connection (participant) role(s),
     *      connections - Optional metadata for other users connections (current state),
     *      chatElement - DOM element for the chat widget,
     *      menuElement - DOM element of the room toolbar,
     *      queueElement - DOM element for the Q&A queue (users with a raised hand)
     *      onSuccess           - Callback for session connection (join) success
     *      onError             - Callback for session connection (join) error
     *      onDestroy           - Callback for session disconnection event,
     *      onDismiss           - Callback for Dismiss action,
     *      onJoinRequest       - Callback for join request,
     *      onConnectionChange  - Callback for participant changes, e.g. role update,
     *      onSessionDataUpdate - Callback for current user connection update,
     *      onMediaSetup        - Called when user clicks the Media setup button
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

            let metadata = connectionData(event.connection)
            const connId = metadata.connectionId

            // The connection metadata here is the initial metadata set on
            // connection initialization. There's no way to update it via OpenVidu API.
            // So, we merge the initial connection metadata with up-to-dated one that
            // we got from our database.
            if (sessionData.connections && connId in sessionData.connections) {
                Object.assign(metadata, sessionData.connections[connId])
            }

            metadata.element = participantCreate(metadata)

            connections[connId] = metadata

            // Send the current user status to the connecting user
            // otherwise e.g. nickname might be not up to date
            signalUserUpdate(event.connection)
        })

        session.on('connectionDestroyed', event => {
            let connectionId = event.connection.connectionId
            let conn = connections[connectionId]

            if (conn) {
                // Remove elements related to the participant
                connectionHandDown(connectionId)
                $(conn.element).remove()
                delete connections[connectionId]
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
                if (data.onSuccess) {
                    data.onSuccess()
                }

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

                sessionData.element = wrapper

                // Create Q&A queue from the existing connections with rised hand.
                // Here we expect connections in a proper queue order
                Object.keys(data.connections || {}).forEach(key => {
                    let conn = data.connections[key]
                    if (conn.hand) {
                        conn.connectionId = key
                        connectionHandUp(conn)
                    }
                })
            })
            .catch(error => {
                console.error('There was an error connecting to the session: ', error.message);

                if (data.onError) {
                    data.onError(error)
                }
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
    function setupStart(props) {
        // Note: After changing media permissions in Chrome/Firefox a page refresh is required.
        // That means that in a scenario where you first blocked access to media devices
        // and then allowed it we can't ask for devices list again and expect a different
        // result than before.
        // That's why we do not bother, and return ealy when we open the media setup dialog.
        if (publisher) {
            volumeMeterStart()
            return
        }

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
     * Stop the setup "process", cleanup after it.
     */
    function setupStop() {
        volumeMeterStop()
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
            const properties = Object.assign({}, publisherDefaults, {
                publishAudio: true,
                publishVideo: videoActive,
                audioSource: deviceId,
                videoSource: videoSource
            })

            volumeMeterStop()

            // Stop and remove the old track, otherwise you get "Concurrent mic process limit." error
            mediaStream.getAudioTracks().forEach(track => {
                track.stop()
                mediaStream.removeTrack(track)
            })

            // TODO: Handle errors

            await OV.getUserMedia(properties)
                .then(async (newMediaStream) => {
                    await replaceTrack(newMediaStream.getAudioTracks()[0])
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
            const properties = Object.assign({}, publisherDefaults, {
                publishAudio: audioActive,
                publishVideo: true,
                audioSource: audioSource,
                videoSource: deviceId
            })

            volumeMeterStop()

            // Stop and remove the old track, otherwise you get "Concurrent mic process limit." error
            mediaStream.getVideoTracks().forEach(track => {
                track.stop()
                mediaStream.removeTrack(track)
            })

            // TODO: Handle errors

            await OV.getUserMedia(properties)
                .then(async (newMediaStream) => {
                    await replaceTrack(newMediaStream.getVideoTracks()[0])
                    volumeMeterStart()
                    videoActive = true
                    videoSource = deviceId
                })
        }

        return videoActive
    }

    /**
     * A way to switch tracks in a stream.
     * Note: This is close to what publisher.replaceTrack() does but it does not
     * require the session.
     * Note: The old track needs to be removed before OV.getUserMedia() call,
     * otherwise we get "Concurrent mic process limit" error.
     */
    function replaceTrack(track) {
        const stream = publisher.stream

        const replaceMediaStreamTrack = () => {
            stream.mediaStream.addTrack(track);

            if (session) {
                session.sendVideoData(publisher.stream.streamManager, 5, true, 5);
            }
        }

        // Fix a bug in Chrome where you would start hearing yourself after audio device change
        // https://github.com/OpenVidu/openvidu/issues/449
        publisher.videoReference.muted = true

        return new Promise((resolve, reject) => {
            if (stream.isLocalStreamPublished) {
                // Only if the Publisher has been published it is necessary to call the native
                // Web API RTCRtpSender.replaceTrack()
                const senders = stream.getRTCPeerConnection().getSenders()
                let sender

                if (track.kind === 'video') {
                    sender = senders.find(s => !!s.track && s.track.kind === 'video')
                } else {
                    sender = senders.find(s => !!s.track && s.track.kind === 'audio')
                }

                if (!sender) return

                sender.replaceTrack(track).then(() => {
                    replaceMediaStreamTrack()
                    resolve()
                }).catch(error => {
                    reject(error)
                })
            } else {
                // Publisher not published. Simply modify local MediaStream tracks
                replaceMediaStreamTrack()
                resolve()
            }
        })
    }

    /**
     * Setup the chat UI
     */
    function setupChat() {
        // The UI elements are created in the vue template
        // Here we add a logic for how they work

        const chat = $(sessionData.chatElement).find('.chat').get(0)
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
                // When opening the chat scroll it to the bottom, or we shouldn't?
                scrollStop = false
                chat.scrollTop = chat.scrollHeight
            })

        $(chat).on('scroll', event => {
            // Detect manual scrollbar moves, disable auto-scrolling until
            // the scrollbar is positioned on the element bottom again
            scrollStop = chat.scrollTop + chat.offsetHeight < chat.scrollHeight
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

        // Scroll the chat element to the end
        if (!scrollStop) {
            chat.get(0).scrollTop = chat.get(0).scrollHeight
        }
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
            // Note: This is what the original openvidu-call app does.
            // It is probably better for performance reasons to close the connection,
            // than to use unpublish() and keep the connection open.
            screenSession.disconnect()
            screenSession = null
            screenPublisher = null

            if (callback) {
                // Note: Disconnecting invalidates the token, we have to inform the vue component
                // to update UI state (and be prepared to request a new token).
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

        let handUpdate = conn => {
            if ('hand' in data && data.hand != conn.hand) {
                if (data.hand) {
                    connectionHandUp(conn)
                } else {
                    connectionHandDown(data.connectionId)
                }
            }
        }

        // It's me
        if (session.connection.connectionId == data.connectionId) {
            const rolePublisher = data.role && data.role & Roles.PUBLISHER
            const roleModerator = data.role && data.role & Roles.MODERATOR
            const isPublisher = sessionData.role & Roles.PUBLISHER
            const isModerator = sessionData.role & Roles.MODERATOR

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

            handUpdate(sessionData)

            // merge the changed data into internal session metadata object
            Object.keys(data).forEach(key => { sessionData[key] = data[key] })

            // update the participant element
            sessionData.element = participantUpdate(sessionData.element, sessionData)

            // promoted/demoted to/from a moderator
            if ('role' in data) {
                if ((!isModerator && roleModerator) || (isModerator && !roleModerator)) {
                    // Update all participants, to enable/disable the popup menu
                    Object.keys(connections).forEach(key => {
                        const conn = connections[key]
                        participantUpdate(conn.element, conn)
                    })
                }
            }

            // Inform the vue component, so it can update some UI controls
            update()

            // promoted to a publisher
            if ('role' in data && !isPublisher && rolePublisher) {
                publisher.createVideoElement(sessionData.element, 'PREPEND')
                session.publish(publisher).then(() => {
                    data.audioActive = publisher.stream.audioActive
                    data.videoActive = publisher.stream.videoActive
                    update()
                })

                // Open the media setup dialog
                // Note: If user didn't give permission to media before joining the room
                // he will not be able to use them now. Changing permissions requires
                // a page refresh.
                // Note: In Firefox I'm always being asked again for media permissions.
                // It does not happen in Chrome. In Chrome the cam/mic will be just re-used.
                // I.e. streaming starts automatically.
                // It might make sense to not start streaming automatically in any cirmustances,
                // display the dialog and wait until user closes it, but this would be
                // a bigger refactoring.
                if (sessionData.onMediaSetup) {
                    sessionData.onMediaSetup()
                }
            }
        } else if (conn) {
            handUpdate(conn)

            // merge the changed data into internal session metadata object
            Object.keys(data).forEach(key => { conn[key] = data[key] })

            conn.element = participantUpdate(conn.element, conn)
        }
    }

    /**
     * Handler for Hand-Up "signal"
     */
    function connectionHandUp(connection) {
        connection.isSelf = session.connection.connectionId == connection.connectionId

        let element = $(nicknameWidget(connection))

        participantUpdate(element, connection)

        element.attr('id', 'qa' + connection.connectionId)
            .appendTo($(sessionData.queueElement).show())

        setTimeout(() => element.addClass('widdle'), 50)
    }

    /**
     * Handler for Hand-Down "signal"
     */
    function connectionHandDown(connectionId) {
        let list = $(sessionData.queueElement)

        list.find('#qa' + connectionId).remove();

        if (!list.find('.meet-nickname').length) {
            list.hide();
        }
    }

    /**
     * Update participant nickname in the UI
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

            $(sessionData.queueElement).find('#qa' + connectionId + ' .content').text(nickname || '')
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
        let element

        params.isSelf = params.isSelf || session.connection.connectionId == params.connectionId

        if (params.role & Roles.PUBLISHER || params.role & Roles.SCREEN) {
            element = publisherCreate(params)
        } else {
            element = subscriberCreate(params)
        }

        setTimeout(resize, 50);

        return element
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
                + '<button type="button" class="btn btn-link link-setup hidden" title="Media setup">' + svgIcon('cog') + '</button>'
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

        if (params.isSelf) {
            if (sessionData.onMediaSetup) {
                wrapper.find('.link-setup').removeClass('hidden')
                    .click(() => sessionData.onMediaSetup())
            }
        } else {
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
        const rolePublisher = params.role & Roles.PUBLISHER
        const roleModerator = params.role & Roles.MODERATOR
        const roleScreen = params.role & Roles.SCREEN
        const roleOwner = params.role & Roles.OWNER

        // Handle publisher-to-subscriber and subscriber-to-publisher change
        if (!roleScreen) {
            const isPublisher = element.is('.meet-video')

            if ((rolePublisher && !isPublisher) || (!rolePublisher && isPublisher)) {
                element.remove()
                return participantCreate(params)
            }
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

        const withPerm = isModerator && !roleScreen && !(roleOwner && !isSelf);
        const withMenu = isSelf || (isModerator && !roleOwner)

        let elements = {
            '.dropdown-menu': withMenu,
            '.permissions': withPerm,
            'svg.moderator': roleModerator,
            'svg.user': !roleModerator
        }

        Object.keys(elements).forEach(key => {
            element.find(key)[elements[key] ? 'removeClass' : 'addClass']('hidden')
        })

        element.find('.action-role-publisher input').prop('checked', rolePublisher)
        element.find('.action-role-moderator input').prop('checked', roleModerator)
            .prop('disabled', roleOwner)

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
                    + '<span class="icon">'
                        + svgIcon('user', null, 'user')
                        + svgIcon('crown', null, 'moderator hidden')
                    + '</span>'
                + '</a>'
                + '<div class="dropdown-menu">'
                    + '<a class="dropdown-item action-nickname" href="#">Nickname</a>'
                    + '<a class="dropdown-item action-dismiss" href="#">Dismiss</a>'
                    + '<div class="dropdown-divider permissions"></div>'
                    + '<div class="permissions">'
                        + '<h6 class="dropdown-header">Permissions</h6>'
                        + '<label class="dropdown-item action-role-publisher custom-control custom-switch">'
                            + '<input type="checkbox" class="custom-control-input">'
                            + ' <span class="custom-control-label">Audio &amp; Video publishing</span>'
                        + '</label>'
                        + '<label class="dropdown-item action-role-moderator custom-control custom-switch">'
                            + '<input type="checkbox" class="custom-control-input">'
                            + ' <span class="custom-control-label">Moderation</span>'
                        + '</label>'
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

        let connectionRole = () => {
            if (params.isSelf) {
                return sessionData.role
            }
            if (params.connectionId in connections) {
                return connections[params.connectionId].role
            }
            return 0
        }

        // Don't close the menu on permission change
        element.find('.dropdown-menu > label').on('click', e => { e.stopPropagation() })

        if (sessionData.onConnectionChange) {
            element.find('.action-role-publisher input').on('change', e => {
                const enabled = e.target.checked
                let role = connectionRole()

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
                let role = connectionRole()

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

        if (!screenOV) {
            screenOV = ovInit()
        }

        // Init screen sharing session
        if (!gotSession) {
            screenSession = screenOV.initSession();
        }

        let successFunc = function() {
            screenSession.publish(screenPublisher)

            screenSession.on('sessionDisconnected', event => {
                callback(false)
                screenSession = null
                screenPublisher = null
            })

            if (callback) {
                callback(true)
            }
        }

        let errorFunc = function() {
            screenPublisher = null
            if (callback) {
                callback(false, true)
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
