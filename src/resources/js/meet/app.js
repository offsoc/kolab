import { OpenVidu } from 'openvidu-browser'

function Meet(container)
{
    let OV                      // OpenVidu object to initialize a session
    let session                 // Session object where the user will connect
    let publisher               // Publisher object which the user will publish
    let sessionId               // Unique identifier of the session
    let audioEnabled = true     // True if the audio track of publisher is active
    let videoEnabled = true     // True if the video track of publisher is active
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

    let containerWidth
    let containerHeight

    OV = new OpenVidu()
    screenOV = new OpenVidu()

    // if there's anything to do, do it here.
    //OV.setAdvancedConfiguration(config)

    // Disconnect participant on browser's window closed
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


    function setup(videoElement, success_callback, error_callback) {
        publisher = OV.initPublisher(null, publisherDefaults)

        publisher.once('accessDenied', error => {
            error_callback(error)
        })

        publisher.once('accessAllowed', async () => {
            let mediaStream = publisher.stream.getMediaStream()
            let videoStream = mediaStream.getVideoTracks()[0]
            let audioStream = mediaStream.getAudioTracks()[0]

            audioEnabled = !!audioStream
            videoEnabled = !!videoStream

            publisher.addVideoElement(videoElement)

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

            success_callback({
                microphones,
                cameras,
                audioSource,
                videoSource,
                audioEnabled,
                videoEnabled
            })
        })
    }

    async function setupSetAudioDevice(deviceId) {
        if (!deviceId) {
            publisher.publishAudio(false)
            audioEnabled = false
        } else if (deviceId == audioSource) {
            publisher.publishAudio(true)
            audioEnabled = true
        } else {
/*
            let mediaStream = publisher.stream.getMediaStream()
            let audioStream = mediaStream.getAudioTracks()[0]

            audioStream.stop()

            publisher = OV.initPublisher(null, properties);
            publisher.addVideoElement(videoElement)
*/

            // FIXME: None of this is working

            let properties = Object.assign({}, publisherDefaults, {
                publishAudio: true,
                publishVideo: videoEnabled,
                audioSource: deviceId,
                videoSource: videoSource
            })

            await OV.getUserMedia(properties)
                .then(async (mediaStream) => {
                    const track = mediaStream.getAudioTracks()[0]
                    await publisher.replaceTrack(track)
                    audioEnabled = true
                })
        }

        return audioEnabled
    }

    function setupSetVideoDevice(deviceId) {
        if (!deviceId) {
            publisher.publishVideo(false)
            videoEnabled = false
        } else if (deviceId == videoSource) {
            publisher.publishVideo(true)
            videoEnabled = true
        } else {
            // TODO
        }

        return videoEnabled
    }

    /**
     * Join the room session
     *
     * @param data Session metadata (session, token, shareToken)
     */
    function joinRoom(data) {
        resize();

        // TODO
        data.params = {
            clientData: 'Test', // user nickname
            avatar: undefined   // avatar image
        }

        sessionData = data
        sessionId = data.session

        // Init a session
        session = OV.initSession()

        // On every new Stream received...
        session.on('streamCreated', function (event) {
            // Subscribe to the Stream to receive it
            let subscriber = session.subscribe(event.stream, addVideoWrapper(container));

            // When the new video is added to DOM, update the page layout
            subscriber.on('videoElementCreated', (event) => {
                numOfVideos++
                updateLayout()
            })

            // When a video is removed from DOM, update the page layout
            subscriber.on('videoElementDestroyed', (event) => {
                numOfVideos--
                updateLayout()
            })
        })
/*
        // On every new Stream destroyed...
        session.on('streamDestroyed', (event) => {
            // Update the page layout
            numOfVideos--
            updateLayout()
        })
*/
        // Connect with the token
        session.connect(data.token, data.params)
            .then(() => {
                // When our HTML video has been added to DOM...
                let wrapper = addVideoWrapper(container, 'publisher')

                publisher.on('videoElementCreated', (event) => {
                    $(event.element).prop('muted', true) // Mute local video to avoid feedback

                    // When your own video is added to DOM, update the page layout to fit it
                    numOfVideos++
                    updateLayout()
                })

                publisher.createVideoElement(wrapper, 'PREPEND')

                // Publish the stream
                session.publish(publisher)
            })
            .catch(error => {
                console.error('There was an error connecting to the session:', error.code, error.message);
            })
    }


    /**
     * Leave the room (disconnect)
     */
    function leaveRoom() {
        if (session) {
            session.disconnect();
        }

        if (screenSession) {
            screenSession.disconnect();
        }
    }

    /**
     * Mute/Unmute audio for current session publisher
     */
    function switchAudio() {
        audioEnabled = !audioEnabled
        publisher.publishAudio(audioEnabled)

        return audioEnabled
    }

    /**
     * Mute/Unmute video for current session publisher
     */
    function switchVideo() {
        videoEnabled = !videoEnabled
        publisher.publishVideo(videoEnabled)

        return videoEnabled
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

    function isScreenSharingSupported() {
        return !!OV.checkScreenSharingCapabilities();
    }

    function addVideoWrapper(container, className) {
        return $('<div class="meet-video">').addClass(className || '')
            .appendTo(container).get(0)
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
                if (!video) {
                    // Remove orphaned video wrappers (after video has been removed)
                    $(elem).remove()
                } else if (video.videoWidth && video.videoHeight && video.videoWidth > video.videoHeight) {
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
}

export default Meet
