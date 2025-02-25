'use strict'

function Media()
{
    let audioSource = localStorage.getItem('kolab-meet-audio-source') // Current audio device identifier
    let videoSource = localStorage.getItem('kolab-meet-video-source') // Current video device identifier
    let audioActive = null      // True if the audio track is active
    let videoActive = null      // True if the video track is active
    let mediaStream = null      // Current media stream
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

                // Firefox on my laptop reports the same device twice with the same deviceId, but different labels.
                // We ignore this edgecase as both devices seem to work.
                webcamDevices[device.deviceId] = device
            }
        }
        catch (error) {
            console.error(error)
        }

        return webcamDevices
    }

    this.getTrack = async (constraints) => {
        // Use navigator.mediaDevices.getSupportedConstraints() to see which constraints are supported
        const stream = await navigator.mediaDevices.getUserMedia(constraints)

        if (constraints.audio) {
            return stream.getAudioTracks()[0]
        }

        return stream.getVideoTracks()[0]
    }

    this.getDisplayTrack = async (constraints) => {
        // Use navigator.mediaDevices.getSupportedConstraints() to see which constraints are supported
        const stream = await navigator.mediaDevices.getDisplayMedia(constraints)
        return stream.getVideoTracks()[0]
    }

    /**
     * Creates a <video> element with media stream/tracks assigned
     */
    this.createVideoElement = (tracks, props) => {
        const videoElement = document.createElement('video')

        const stream = new MediaStream()

        tracks.forEach(track => stream.addTrack(track))

        videoElement.srcObject = stream

        this.setVideoProps(videoElement, props)

        return videoElement
    }

    /**
     * Resets a <video> element media streams
     */
    this.resetVideoElement = (element, remove) => {
        if (!element) {
            return
        }

        const stream = element.srcObject

        if (stream) {
            stream.getTracks().forEach(track => {
                track.stop()
                stream.removeTrack(track)
            })
        }

        if (remove) {
            element.remove()
        }
    }

    /**
     * Make a picture from a video element
     */
    this.makePicture = (videoElement) => {
        // Skip if video is not "playing"
        if (!videoElement.videoWidth) {
            return
        }

        // we're going to crop a square from the video and resize it
        const maxSize = 64

        // Calculate sizing
        let sh = Math.floor(videoElement.videoHeight / 1.5)
        let sw = sh
        let sx = (videoElement.videoWidth - sw) / 2
        let sy = (videoElement.videoHeight - sh) / 2

        let dh = Math.min(sh, maxSize)
        let dw = sh < maxSize ? sw : Math.floor(sw * dh/sh)

        const canvas = document.createElement('canvas')
        canvas.width = dw
        canvas.height = dh

        // draw the image on the canvas (square cropped and resized)
        canvas.getContext('2d').drawImage(videoElement, sx, sy, sw, sh, 0, 0, dw, dh)

        // convert it to a usable data URL (png format)
        return canvas.toDataURL()
    }

    /**
     * Set video element properties
     */
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
    this.setupStart = async (props) => {
        setupVideoElement = props.videoElement
        this.setVideoProps(setupVideoElement, { mirror: true, muted: true })
        setupVolumeElement = props.volumeElement

        try {
            // This will list the devices without label if we don't have given the permission yet,
            // but it allows us to detect wether there is a webcam at all.
            const availableWebcams = await this.getWebcams()
            const hasWebcam = availableWebcams && Object.keys(availableWebcams).length >= 1
            if (!hasWebcam) {
                console.warn("No webcam found, requesting audio only.")
            }

            // Ask for permission and then return a stream
            // Firefox is buggy and will never return if we request video while not having video,
            // otherwise it will throw an exception.
            mediaStream = await navigator.mediaDevices.getUserMedia( {
                video: hasWebcam ? (videoSource ? { deviceId: videoSource } : true) : false,
                audio: audioSource ? { deviceId: audioSource } : true
            })

            // If audio or video was explicitly disabled we remove all tracks.
            if (videoActive === false) {
                this.removeTracksFromStream(mediaStream, 'Video')
            }
            if (audioActive === false) {
                this.removeTracksFromStream(mediaStream, 'Audio')
            }

            let videoTrack = mediaStream.getVideoTracks()[0]
            if (videoTrack) {
                videoSource = videoTrack.getSettings().deviceId
                videoActive = true
                setupVideoElement.srcObject = mediaStream
            }

            let audioTrack = mediaStream.getAudioTracks()[0]
            if (audioTrack) {
                audioSource = audioTrack.getSettings().deviceId
                audioActive = true
                volumeMeterStart()
            }

            localStorage.setItem('kolab-meet-audio-source', audioSource);
            localStorage.setItem('kolab-meet-video-source', videoSource);

            // The labels are only available after we have permission, so we re-list the available devices.
            microphones = await this.getAudioDevices()
            cameras = await this.getWebcams()

            props.onSuccess({
                microphones,
                cameras,
                audioSource,
                videoSource,
                audioActive,
                videoActive
            })
        } catch (error) {
           props.onError(error)
        }
    }

    /**
     * Stop the setup "process", cleanup after it.
     */
    this.setupStop = () => {
        volumeMeterStop()

        // Unset the video element tracks, if any set
        this.resetVideoElement(setupVideoElement)
    }

    /**
     * Return current setup information
     */
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
        if (!deviceId) {
            volumeMeterStop()
            this.removeTracksFromStream(mediaStream, 'Audio')
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
            this.removeTracksFromStream(mediaStream, 'Audio')

            // TODO: Error handling

            let stream = await navigator.mediaDevices.getUserMedia(constraints)
            const track = stream.getAudioTracks()[0]

            mediaStream.addTrack(track)
            volumeMeterStart()
            audioActive = true
            audioSource = deviceId
        }

        localStorage.setItem('kolab-meet-audio-source', audioSource);

        return audioActive
    }

    /**
     * Change the publisher video device
     *
     * @param deviceId Device identifier string
     */
    this.setupSetVideo = async (deviceId) => {
        if (!deviceId) {
            this.removeTracksFromStream(mediaStream, 'Video')
            // Without the next line the video element will freeze on the last video frame
            // instead of turning black.
            setupVideoElement.srcObject = mediaStream
            videoActive = false
            videoSource = ''
        } else if (deviceId == videoSource) {
            videoActive = true
        } else {
            const constraints = {
                video: {
                    deviceId: { ideal: deviceId }
                }
            }

            // Stop and remove the old track, otherwise you get "Concurrent mic process limit." error
            this.removeTracksFromStream(mediaStream, 'Video')

            // TODO: Error handling

            let stream = await navigator.mediaDevices.getUserMedia(constraints)
            const track = stream.getVideoTracks()[0]

            mediaStream.addTrack(track)
            videoActive = true
            videoSource = deviceId
        }

        localStorage.setItem('kolab-meet-video-source', videoSource);

        return videoActive
    }

    /**
     * Removes tracks of specified kind (audio or video) from a stream
     */
    this.removeTracksFromStream = (stream, type) => {
        if (stream) {
            type = type.replace(/^a/, 'A').replace(/^v/, 'V')
            stream[`get${type}Tracks`]().forEach(track => {
                track.stop()
                stream.removeTrack(track)
            })
        }
    }

    /**
     * Starts volume changes tracking on the setup video element
     */
    const volumeMeterStart = () => {
        if (!setupVolumeElement) {
            return
        }

        const audioContext = new AudioContext()
        const source = audioContext.createMediaStreamSource(mediaStream)

        // Create a new volume meter
        const processor = audioContext.createScriptProcessor(512)

        processor.volume = 0
        processor.averaging = 0.95

        processor.onaudioprocess = function(event) {
            const buf = event.inputBuffer.getChannelData(0)

            // Do a root-mean-square on the samples: sum up the squares...
            const sum = buf.reduce((prev, curr) => prev + curr * curr, 0)

            // ... then take the square root of the sum.
            // multiply by 2 to make the levels visible in the indicator
            const volume = Math.sqrt(sum / buf.length) * 2 * 100

            // Now smooth this out with the averaging factor applied
            // to the previous sample - take the max here because we
            // want "fast attack, slow release."
            this.volume = Math.max(volume, this.volume * this.averaging)
        }

        processor.shutdown = function() {
            this.disconnect()
            this.onaudioprocess = null
        }

        // this will have no effect, since we don't copy the input to the output,
        // but works around a current Chrome bug.
        processor.connect(audioContext.destination)

        // Connect the volume processor to the source
        source.connect(processor)

        const update = () => { volumeMeterUpdate(processor.volume) }

        this.audioContext = audioContext
        this.volumeInterval = setInterval(update, 25)
    }

    /**
     * Stops volume changes tracking on the setup video element
     */
    const volumeMeterStop = () => {
        if (this.audioContext) {
            clearInterval(this.volumeInterval)
            this.audioContext.close()
            this.audioContext = null
            volumeMeterUpdate(0)
        }
    }

    /**
     * Updates volume meter widget on voluma level change
     */
    const volumeMeterUpdate = (volume) => {
        const value = Math.min(100, Math.ceil(volume))
        const bar = setupVolumeElement.firstChild

        let color = 'lime'
        if (value >= 70) {
            color = '#ff3300'
        } else if (value >= 50) {
            color = '#ff9933'
        }

        bar.style.height = value + '%'
        bar.style.background = color
    }
}

export { Media }
