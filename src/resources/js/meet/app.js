import { OpenVidu } from 'openvidu-browser'

//function Meet(container, config)
function Meet(container)
{
    let OV                     // OpenVidu object to initialize a session
    let session                // Session object where the user will connect
    let publisher              // Publisher object which the user will publish
    let sessionId              // Unique identifier of the session
    let audioEnabled = true    // True if the audio track of publisher is active
    let videoEnabled = true    // True if the video track of publisher is active
    let numOfVideos = 0        // Keeps track of the number of videos that are being shown

    $(container).append('<div id="session"><div id="videos"></div></div>')

    OV = new OpenVidu()

    //OV.setAdvancedConfiguration(config)

    // Disconnect participant on browser's window closed
/*
    window.addEventListener('beforeunload', () => {
        if (session) session.disconnect();
    })
*/

    // Public methods
    this.joinRoom = joinRoom

    function joinRoom(data) {
        sessionId = data.session

        // Init a session
        session = OV.initSession()

        // On every new Stream received...
        session.on('streamCreated', function (event) {
            // Subscribe to the Stream to receive it. HTML video will be appended to element with 'subscriber' id
            var subscriber = session.subscribe(event.stream, 'videos');
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

        // Connect with the token
        session.connect(data.token)
            .then(() => {
                // Update the URL shown in the browser's navigation bar to show the session id
                ///var path = (location.pathname.slice(-1) == "/" ? location.pathname : location.pathname + "/");
                ///window.history.pushState("", "", path + '#' + sessionId);

                // Auxiliary methods to show the session's view
                //showSessionHideJoin()

                // Get the camera stream with the desired properties
                publisher = OV.initPublisher('videos', {
                    audioSource: undefined, // The source of audio. If undefined default audio input
                    videoSource: undefined, // The source of video. If undefined default video input
                    publishAudio: true,     // Whether to start publishing with your audio unmuted or not
                    publishVideo: true,     // Whether to start publishing with your video enabled or not
                    resolution: '640x480',  // The resolution of your video
                    frameRate: 30,          // The frame rate of your video
                    insertMode: 'PREPEND',  // How the video is inserted in target element 'video-container'
                    mirror: true            // Whether to mirror your local video or not
                })

                // When our HTML video has been added to DOM...
                publisher.on('videoElementCreated', (event) => {
                    // When your own video is added to DOM, update the page layout to fit it
                    numOfVideos++
                    updateLayout()
                    $(event.element).prop('muted', true) // Mute local video to avoid feedback
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
        session.disconnect();
    }

    function muteAudio() {
        audioEnabled = !audioEnabled
        publisher.publishAudio(audioEnabled)

        if (!audioEnabled) {
            $('#mute-audio').removeClass('btn-primary')
            $('#mute-audio').addClass('btn-default')
        } else {
            $('#mute-audio').addClass('btn-primary')
            $('#mute-audio').removeClass('btn-default')
        }
    }

    function muteVideo() {
        videoEnabled = !videoEnabled
        publisher.publishVideo(videoEnabled)

        if (!videoEnabled) {
            $('#mute-video').removeClass('btn-primary')
            $('#mute-video').addClass('btn-default')
        } else {
            $('#mute-video').addClass('btn-primary')
            $('#mute-video').removeClass('btn-default')
        }
    }

    // 'Session' page
    function showSessionHideJoin() {
        $('#nav-join').hide()
        $('#nav-session').show()
        $('#join').hide()
        $('#session').show()
        $('footer').hide()
        $('#main-container').removeClass('container')
    }

    // 'Join' page
    function showJoinHideSession() {
        $('#nav-join').show()
        $('#nav-session').hide()
        $('#join').show()
        $('#session').hide()
        $('footer').show()
        $('#main-container').addClass('container')
    }

    // Dynamic layout adjustemnt depending on number of videos
    function updateLayout() {
        console.warn('There are now ' + numOfVideos + ' videos')

        var publisherDiv = $('#publisher')
        var publisherVideo = $("#publisher video")
        var subscriberVideos = $('#videos > video')

        switch (numOfVideos) {
            case 1:
                publisherVideo.addClass('video1')
                break
            case 2:
                publisherDiv.addClass('video2')
                subscriberVideos.addClass('video2')
                break
            case 3:
                publisherDiv.addClass('video3')
                subscriberVideos.addClass('video3')
                break
            case 4:
                publisherDiv.addClass('video4')
                publisherVideo.addClass('video4')
                subscriberVideos.addClass('video4')
                break
            default:
                publisherDiv.addClass('videoMore')
                publisherVideo.addClass('videoMore')
                subscriberVideos.addClass('videoMore')
                break
        }
    }
}

export default Meet
