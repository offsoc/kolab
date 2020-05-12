import { OpenVidu } from 'openvidu-browser'

function Meet(container)
{
    let OV                     // OpenVidu object to initialize a session
    let session                // Session object where the user will connect
    let publisher              // Publisher object which the user will publish
    let sessionId              // Unique identifier of the session
    let audioEnabled = true    // True if the audio track of publisher is active
    let videoEnabled = true    // True if the video track of publisher is active
    let numOfVideos = 0        // Keeps track of the number of videos that are being shown
    let audioSource = ''       // Currently selected microphone
    let videoSource = ''       // Currently selected camera

    let publisherDefaults = {
        publishAudio: true,     // Whether to start publishing with your audio unmuted or not
        publishVideo: true,     // Whether to start publishing with your video enabled or not
        resolution: '640x480',  // The resolution of your video
        frameRate: 30,          // The frame rate of your video
        mirror: true            // Whether to mirror your local video or not
    }

    let cameras = []           // List of user video devices
    let microphones = []       // List of user audio devices

    OV = new OpenVidu()

    // if there's anything to do, do it here.
    //OV.setAdvancedConfiguration(config)

    // Disconnect participant on browser's window closed
/*
    window.addEventListener('beforeunload', () => {
        if (session) session.disconnect();
    })
*/

    // Public methods
    this.joinRoom = joinRoom
    this.leaveRoom = leaveRoom
    this.muteAudio = muteAudio
    this.muteVideo = muteVideo
    this.setup = setup
    this.setupSetAudioDevice = setupSetAudioDevice
    this.setupSetVideoDevice = setupSetVideoDevice


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

    function joinRoom(data) {
        sessionId = data.session

        // Init a session
        session = OV.initSession()

        // On every new Stream received...
        session.on('streamCreated', function (event) {
            // Subscribe to the Stream to receive it
            let subscriber = session.subscribe(event.stream, addVideoWrapper(container));

            // When the new video is added to DOM, update the page layout to fit one more participant
            subscriber.on('videoElementCreated', (event) => {
                numOfVideos++
                updateLayout()
            })
        })

        // On every new Stream destroyed...
        session.on('streamDestroyed', (event) => {
            // Update the page layout
            numOfVideos--
            updateLayout()
        })

        // TODO
        let params = {
            clientData: 'Test', // user nickname
            avatar: undefined   // avatar image
        }

        // Connect with the token
        session.connect(data.token, params)
            .then(() => {
                publisher.createVideoElement(addVideoWrapper(container), 'PREPEND')

                // When our HTML video has been added to DOM...
                publisher.on('videoElementCreated', (event) => {
                    $(event.element).addClass('publisher')
                        .prop('muted', true) // Mute local video to avoid feedback

                    // When your own video is added to DOM, update the page layout to fit it
                    numOfVideos++
                    updateLayout()
                })

                // Publish the stream
                session.publish(publisher)
            })
            .catch(error => {
                console.log('There was an error connecting to the session:', error.code, error.message);
            })
    }

    function leaveRoom() {
        // Leave the session by calling 'disconnect' method over the Session object
        if (session) {
            session.disconnect();
        }
    }

    function muteAudio() {
        audioEnabled = !audioEnabled
        publisher.publishAudio(audioEnabled)

        return audioEnabled
    }

    function muteVideo() {
        videoEnabled = !videoEnabled
        publisher.publishVideo(videoEnabled)

        return videoEnabled
    }

    function updateLayout() {
        // update the "matrix" layout
    }

    function addVideoWrapper(container) {
        return $('<div class="meet-video">').appendTo(container).get(0)
    }
}

export default Meet
