import anchorme from 'anchorme'
import { library } from '@fortawesome/fontawesome-svg-core'
import { OpenVidu } from 'openvidu-browser'


function Meet(container)
{
    let OV                      // OpenVidu object to initialize a session
    let session                 // Session object where the user will connect
    let publisher               // Publisher object which the user will publish
    let audioActive = false     // True if the audio track of the publisher is active
    let videoActive = false     // True if the video track of the publisher is active
    let numOfVideos = 0         // Keeps track of the number of videos that are being shown
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

    OV = new OpenVidu()
    screenOV = new OpenVidu()

    // if there's anything to do, do it here.
    //OV.setAdvancedConfiguration(config)

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


    /**
     * Join the room session
     *
     * @param data Session metadata and event handlers (session, token, shareToken, nickname,
     *             chatElement, menuElement, onDestroy)
     */
    function joinRoom(data) {
        resize();
        volumeMeterStop()

        data.params = {
            nickname: data.nickname, // user nickname
            // avatar: undefined        // avatar image
        }

        sessionData = data

        // Init a session
        session = OV.initSession()

        // Handle connection creation events
        session.on('connectionCreated', event => {
            // Ignore the current user connection
            if (!event.connection.options) {
                return
            }

            // This is the first event executed when a user joins in.
            // We'll create the video wrapper here, which will be re-used
            // in 'streamCreated' event handler.
            // Note: For a user with no cam/mic enabled streamCreated even
            // is not being dispatched at all

            // TODO: We may consider placing users with no video enabled
            // in a separate place, so they do not fill the precious
            // screen estate

            let connectionId = event.connection.connectionId
            let metadata = JSON.parse(event.connection.data)
            let wrapper = videoWrapperCreate(container, metadata)

            connections[connectionId] = {
                element: wrapper
            }

            updateLayout()

            // Send the current user status to the connecting user
            // otherwise e.g. nickname might be not up to date
            signalUserUpdate(event.connection)
        })

        session.on('connectionDestroyed', event => {
            let conn = connections[event.connection.connectionId]
            if (conn) {
                $(conn.element).remove()
                numOfVideos--
                updateLayout()
                delete connections[event.connection.connectionId]
            }
        })

        // On every new Stream received...
        session.on('streamCreated', event => {
            let connection = event.stream.connection
            let connectionId = connection.connectionId
            let metadata = JSON.parse(connection.data)
            let wrapper = connections[connectionId].element

            // Subscribe to the Stream to receive it
            let subscriber = session.subscribe(event.stream, wrapper);
/*
            // When the new video is added to DOM, update the page layout
            subscriber.on('videoElementCreated', event => {
                updateLayout()
            })

            // When a video is removed from DOM, update the page layout
            subscriber.on('videoElementDestroyed', event => {
                updateLayout()
            })
*/
            // Update the wrapper controls/status
            videoWrapperUpdate(wrapper, event.stream)
        })
/*
        session.on('streamDestroyed', event => {
        })
*/
        // Handle session disconnection events
        session.on('sessionDisconnected', event => {
            if (data.onDestroy) {
                data.onDestroy(event)
            }

            updateLayout()
        })

        // Handle signals from all participants
        session.on('signal', signalEventHandler)

        // Connect with the token
        session.connect(data.token, data.params)
            .then(() => {
                let params = { publisher: true, audioActive, videoActive }
                let wrapper = videoWrapperCreate(container, Object.assign({}, data.params, params))

                publisher.on('videoElementCreated', event => {
                    $(event.element).prop('muted', true) // Mute local video to avoid feedback
                    updateLayout()
                })

                publisher.createVideoElement(wrapper, 'PREPEND')
                sessionData.wrapper = wrapper

                // Publish the stream
                session.publish(publisher)
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
            if (audioActive) {
                publisher.publishAudio(false)
            }
            if (videoActive) {
                publisher.publishVideo(false)
            }
        }

        if (session) {
            session.disconnect();
            session = null
        }

        if (screenSession) {
            screenSession.disconnect();
            screenSession = null
        }

        publisher = null
    }

    /**
     * Sets the audio and video devices for the session.
     * This will ask user for permission to access media devices.
     *
     * @param props Setup properties (videoElement, volumeElement, success, error)
     */
    function setup(props) {
        setupProps = props

        publisher = OV.initPublisher(undefined, publisherDefaults)

        publisher.once('accessDenied', error => {
            props.error(error)
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

            props.success({
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
            .on('click', () => { button.find('.badge').text('') })
    }

    /**
     * Signal events handler
     */
    function signalEventHandler(signal) {
        let conn, data

        switch (signal.type) {
            case 'signal:userChanged':
                if (conn = connections[signal.from.connectionId]) {
                    data = JSON.parse(signal.data)
                    videoWrapperUpdate(conn.element, data)
                }
                break

            case 'signal:chat':
                data = JSON.parse(signal.data)
                data.id = signal.from.connectionId
                pushChatMessage(data)
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
        let isSelf = data.id == publisher.stream.connection.connectionId
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
            audioActive,
            videoActive,
            nickname: sessionData.params.nickname
        }

        // TODO: The same for screen sharing session?
        session.signal({
            data: JSON.stringify(data),
            type: 'userChanged',
            to: connection ? [connection] : undefined
        })
    }

    /**
     * Mute/Unmute audio for current session publisher
     */
    function switchAudio() {
        audioActive = !audioActive
        publisher.publishAudio(audioActive)

        // TODO: Handle enabling audio if it was never enabled (e.g. user joined the room
        //       without giving access to his mic)

        videoWrapperUpdate(sessionData.wrapper, { audioActive })

        signalUserUpdate()

        return audioActive
    }

    /**
     * Mute/Unmute video for current session publisher
     */
    function switchVideo() {
        videoActive = !videoActive
        publisher.publishVideo(videoActive)

        // TODO: Handle enabling video if it was never enabled (e.g. user joined the room
        //       without giving access to his camera)

        videoWrapperUpdate(sessionData.wrapper, { videoActive })

        signalUserUpdate()

        return videoActive
    }

    /**
     * Switch on/off screen sharing
     */
    function switchScreen(callback) {
        if (screenPublisher) {
            screenSession.unpublish(screenPublisher)
            screenPublisher = null

            if (callback) {
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
     * Create a <video> element wrapper with controls
     *
     * @param container The parent element
     * @param params  Connection metadata/params
     */
    function videoWrapperCreate(container, params) {
        // Create the element
        let wrapper = $('<div class="meet-video">').html(`
            <div class="nickname" title="Nickname">
                <span></span>
            </div>
            <div class="controls">
                <button class="btn btn-link link-audio d-none" title="Mute audio">` + svgIcon("volume-mute") + `</button>
                <button class="btn btn-link link-fullscreen d-none" title="Full screen">` + svgIcon("expand") + `</button>
                <button class="btn btn-link link-fullscreen-close d-none" title="Full screen">` + svgIcon("compress") + `</button>
            </div>
            <div class="status">
                <span class="bg-danger status-audio d-none">` + svgIcon("microphone") + `</span>
                <span class="bg-danger status-video d-none">` + svgIcon("video") + `</span>
            </div>`)

        if (params.publisher) {
            // Add events for nickname change
            let nickname = wrapper.addClass('publisher').find('.nickname')
            let editable = nickname.find('span').get(0)
            let editableEnable = () => {
                editable.contentEditable = true
                editable.focus()
            }
            let editableUpdate = () => {
                editable.contentEditable = false
                sessionData.params.nickname = editable.innerText
                signalUserUpdate()
            }

            nickname.on('click', editableEnable)

            $(editable).on('blur', editableUpdate)
                .on('click', editableEnable)
                .on('keydown', e => {
                    // Enter or Esc
                    if (e.keyCode == 13 || e.keyCode == 27) {
                        editableUpdate()
                        return false
                    }
                })
        } else {
            wrapper.find('.nickname > svg').addClass('d-none')

            wrapper.find('.link-audio').removeClass('d-none')
                .on('click', e => {
                    let video = wrapper.find('video')[0]
                    video.muted = !video.muted
                    wrapper.find('.link-audio')[video.muted ? 'addClass' : 'removeClass']('text-danger')
                })
        }

        videoWrapperUpdate(wrapper, params)

        // Fullscreen control
        if (document.fullscreenEnabled) {
            wrapper.find('.link-fullscreen').removeClass('d-none')
                .on('click', () => {
                    wrapper.get(0).requestFullscreen()
                })

            wrapper.find('.link-fullscreen-close')
                .on('click', () => {
                    document.exitFullscreen()
                })

            wrapper.on('fullscreenchange', () => {
                // const enabled = document.fullscreenElement
                wrapper.find('.link-fullscreen').toggleClass('d-none')
                wrapper.find('.link-fullscreen-close').toggleClass('d-none')
            })
        }

        numOfVideos++

        return wrapper[params.publisher ? 'prependTo' : 'appendTo'](container).get(0)
    }

    /**
     * Update the <video> wrapper controls
     *
     * @param wrapper The wrapper element
     * @param params  Connection metadata/params
     */
    function videoWrapperUpdate(wrapper, params) {
        if ('audioActive' in params) {
            $(wrapper).find('.status-audio')[params.audioActive ? 'addClass' : 'removeClass']('d-none')
        }

        if ('videoActive' in params) {
            $(wrapper).find('.status-video')[params.videoActive ? 'addClass' : 'removeClass']('d-none')
        }

        if ('nickname' in params) {
            $(wrapper).find('.nickname > span').text(params.nickname)
        }
    }

    /**
     * Window onresize event handler (updates room layout)
     */
    function resize() {
        containerWidth = container.offsetWidth
        containerHeight = container.offsetHeight
        updateLayout()
    }

    /**
     * Update the room "matrix" layout
     */
    function updateLayout() {
        if (!numOfVideos) {
            return
        }

        let css, rows, cols, height

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

        height = containerHeight / rows
        css = {
            width: (100 / cols) + '%',
            // Height must be in pixels to make object-fit:cover working
            height: height + 'px'
        }

        // Update the matrix
        $(container).find('.meet-video').css(css)
            .each((idx, elem) => {
                let video = $(elem).children('video')[0]
/*
                if (!video) {
                    // Remove orphaned video wrappers (after video has been removed)
                    $(elem).remove()
                } else */
                if (video && video.videoWidth && video.videoHeight && video.videoWidth > video.videoHeight) {
                    // Set max-width to keep the original aspect ratio in cases
                    // when there's enough room to display the element
                    let maxWidth = height * video.videoWidth / video.videoHeight
                    $(elem).css('max-width', maxWidth)
                }
            })
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
    function svgIcon(name, type) {
        // Note: the library will contain definitions for all icons registered elswhere
        const icon = library.definitions[type || 'fas'][name]

        return $(`<svg><path fill="currentColor" d="${icon[4]}"></path></svg>`)
            .attr({
                'class': 'svg-inline--fa',
                'aria-hidden': true,
                focusable: false,
                role: 'img',
                xmlns: 'http://www.w3.org/2000/svg',
                viewBox: `0 0 ${icon[0]} ${icon[1]}`
            })
            .get(0).outerHTML
    }

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

    function volumeMeterStart() {
        if (publisher && volumeElement) {
            publisher.on('streamAudioVolumeChange', volumeChangeHandler)
        }
    }

    function volumeMeterStop() {
        if (publisher && volumeElement) {
            publisher.off('streamAudioVolumeChange')
            volumeElement.firstChild.style.height = 0
        }
    }
}

export default Meet
