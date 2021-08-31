'use strict'

function Media()
{
    let audioActive = false     // True if the audio track is active
    let videoActive = false     // True if the video track is active
    let audioSource = ''        // Current audio device identifier
    let videoSource = ''        // Current video device identifier
    let cameras = []            // List of user video devices
    let microphones = []        // List of user audio devices
    let setupVideoElement       // <video> element for setup process
    let setupVolumeElement      // Volume indicator element for setup process


    this.getAudioDevices = async () => {
        let audioDevices = {}

        try
        {
            const devices = await navigator.mediaDevices.enumerateDevices()

            for (const device of devices) {
                if (device.kind !== 'audioinput') {
                    continue
                }

                audioDevices[device.deviceId] = device
            }
        }
        catch (error) {
            console.error(error)
        }

        return audioDevices
    }

    this.getWebcams = async () => {
        let webcamDevices = {}

        try {
            const devices = await navigator.mediaDevices.enumerateDevices()

            for (const device of devices) {
                if (device.kind !== 'videoinput') {
                    continue
                }

                webcamDevices[device.deviceId] = device
            }
        }
        catch (error) {
            console.error(error)
        }

        return webcamDevices
    }

    this.getMediaStream = async (successCallback, errorCallback) => {
        navigator.mediaDevices.getUserMedia({ audio: true, video: true })
            .then(mediaStream => {
                successCallback(mediaStream)
            })
            .catch(error => {
                errorCallback(error)
            })
    }

    this.getTrack = async (constraints) => {
        const stream = await navigator.mediaDevices.getUserMedia(constraints)

        if (constraints['audio']) {
            return stream.getAudioTracks()[0]
        }

        return stream.getVideoTracks()[0]
    }

    this.createVideoElement = (tracks, props) => {
        const videoElement = document.createElement('video')

        const stream = new MediaStream()

        tracks.forEach(track => stream.addTrack(track))

        videoElement.srcObject = stream

        this.setVideoProps(videoElement, props)

        return videoElement
    }

    this.setVideoProps = (videoElement, props) => {
        videoElement.autoplay = true
        videoElement.controls = false
        videoElement.muted = props.muted || false
        videoElement.disablePictureInPicture = true // this does not work in Firefox
        videoElement.tabIndex = -1
        videoElement.setAttribute('playsinline', 'true')

        if (props.mirror) {
            videoElement.style.transform = 'rotateY(180deg)'
            videoElement.style.webkitTransform = 'rotateY(180deg)'
        }
    }

    /**
     * Sets the audio and video devices for the session.
     * This will ask user for permission to access media devices.
     *
     * @param props Setup properties (videoElement, volumeElement, onSuccess, onError)
     */
    this.setupStart = (props) => {
        setupVideoElement = props.videoElement
        setupVolumeElement = props.volumeElement

        const callback = async (mediaStream) => {
            let videoStream = mediaStream.getVideoTracks()[0]
            let audioStream = mediaStream.getAudioTracks()[0]

            audioActive = !!audioStream
            videoActive = !!videoStream

            this.setVideoProps(setupVideoElement, { mirror: true, muted: true })
            setupVideoElement.srcObject = mediaStream

            volumeMeterStart()

            microphones = await this.getAudioDevices()
            cameras = await this.getWebcams()

            Object.keys(cameras).forEach(deviceId => {
                // device's props: deviceId, kind, label
                const device = cameras[deviceId]
                if (videoStream && videoStream.label == device.label) {
                    videoSource = device.deviceId
                }
            })

            Object.keys(microphones).forEach(deviceId => {
                const device = microphones[deviceId]
                if (audioStream && audioStream.label == device.label) {
                    audioSource = device.deviceId
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
        }

        this.getMediaStream(callback, props.onError)
    }

    /**
     * Stop the setup "process", cleanup after it.
     */
    this.setupStop = () => {
        volumeMeterStop()

        if (setupVideoElement) {
            const mediaStream = new MediaStream()
            setupVideoElement.srcObject = mediaStream
        }
    }

    this.setupData = () => {
        return {
            microphones,
            cameras,
            audioSource,
            videoSource,
            audioActive,
            videoActive
        }
    }

    /**
     * Change the publisher audio device
     *
     * @param deviceId Device identifier string
     */
    this.setupSetAudio = async (deviceId) => {
        const mediaStream = setupVideoElement.srcObject

        if (!deviceId) {
            volumeMeterStop()
            removeTracksFromStream(mediaStream, 'Audio')
            audioActive = false
            audioSource = ''
        } else if (deviceId == audioSource) {
            volumeMeterStart()
            audioActive = true
        } else {
            const constraints = {
                audio: {
                    deviceId: { ideal: deviceId }
                }
            }

            volumeMeterStop()

            // Stop and remove the old track, otherwise you get "Concurrent mic process limit." error
            removeTracksFromStream(mediaStream, 'Audio')

            // TODO: Error handling

            const track = await this.getTrack(constraints)

            mediaStream.addTrack(track)
            volumeMeterStart()
            audioActive = true
            audioSource = deviceId
        }

        return audioActive
    }

    /**
     * Change the publisher video device
     *
     * @param deviceId Device identifier string
     */
    this.setupSetVideo = async (deviceId) => {
        const mediaStream = setupVideoElement.srcObject

        if (!deviceId) {
            removeTracksFromStream(mediaStream, 'Video')
            // Without the next line the video element will freeze on the last video frame
            // instead of turning black.
            setupVideoElement.srcObject = mediaStream
            videoActive = false
            videoSource = ''
        } else if (deviceId == audioSource) {
            videoActive = true
        } else {
            const constraints = {
                video: {
                    deviceId: { ideal: deviceId }
                }
            }

            // Stop and remove the old track, otherwise you get "Concurrent mic process limit." error
            removeTracksFromStream(mediaStream, 'Video')

            // TODO: Error handling

            const track = await this.getTrack(constraints)

            mediaStream.addTrack(track)
            videoActive = true
            videoSource = deviceId
        }

        return videoActive
    }

    const removeTracksFromStream = (stream, type) => {
        stream[`get${type}Tracks`]().forEach(track => {
            track.stop()
            stream.removeTrack(track)
        })
    }

    const volumeMeterStart = () => {
        // TODO
    }

    const volumeMeterStop = () => {
        // TODO
    }
}

export { Media }
