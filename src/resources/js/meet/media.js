'use strict'

function Media()
{
    let audioActive = null     // True if the audio track is active
    let videoActive = null     // True if the video track is active
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
        const constraints = { audio: true, video: true }

        if (videoSource)
            constraints.video = { deviceId: videoSource }
        if (audioSource)
            constraints.audio = { deviceId: audioSource }

        navigator.mediaDevices.getUserMedia(constraints)
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

        const canvas = $("<canvas>")[0]
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
    this.setupStart = (props) => {
        setupVideoElement = props.videoElement
        setupVolumeElement = props.volumeElement

        const callback = async (mediaStream) => {
            if (audioActive === false) {
                this.removeTracksFromStream(mediaStream, 'Audio')
            }
            if (videoActive === false) {
                this.removeTracksFromStream(mediaStream, 'Video')
            }

            let videoStream = mediaStream.getVideoTracks()[0]
            let audioStream = mediaStream.getAudioTracks()[0]

            audioActive = !!audioStream
            videoActive = !!videoStream

            this.setVideoProps(setupVideoElement, { mirror: true, muted: true })
            setupVideoElement.srcObject = mediaStream

            if (audioActive) {
                volumeMeterStart()
            }

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

        // Unset the video element tracks
        if (setupVideoElement) {
            const mediaStream = new MediaStream()
            setupVideoElement.srcObject = mediaStream
        }
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
        const mediaStream = setupVideoElement.srcObject

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
            this.removeTracksFromStream(mediaStream, 'Video')
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
            this.removeTracksFromStream(mediaStream, 'Video')

            // TODO: Error handling

            const track = await this.getTrack(constraints)

            mediaStream.addTrack(track)
            videoActive = true
            videoSource = deviceId
        }

        return videoActive
    }

    this.removeTracksFromStream = (stream, type) => {
        if (stream) {
            stream[`get${type}Tracks`]().forEach(track => {
                track.stop()
                stream.removeTrack(track)
            })
        }
    }

    const volumeMeterStart = () => {
        if (!setupVolumeElement) {
            return
        }

        const audioContext = new AudioContext()
        const source = audioContext.createMediaStreamSource(setupVideoElement.srcObject)

        // Create a new volume meter
        const processor = audioContext.createScriptProcessor(512)

        processor.volume = 0
        processor.averaging = 0.95

        processor.onaudioprocess = function(event) {
            let buf = event.inputBuffer.getChannelData(0)
            let bufLength = buf.length
            let sum = 0

            // Do a root-mean-square on the samples: sum up the squares...
            for (let x, i=0; i<bufLength; i++) {
                x = buf[i]
                sum += x * x
            }

            // ... then take the square root of the sum.
            const rms = Math.sqrt(sum / bufLength)

            // Now smooth this out with the averaging factor applied
            // to the previous sample - take the max here because we
            // want "fast attack, slow release."
            this.volume = Math.max(rms, this.volume * this.averaging)
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

        const update = () => { volumeMeterUpdate(processor.volume  * 100) }

        this.audioContext = audioContext
        this.volumeInterval = setInterval(update, 25)
    }

    const volumeMeterStop = () => {
        if (this.audioContext) {
            clearInterval(this.volumeInterval)
            this.audioContext.close()
            this.audioContext = null
            volumeMeterUpdate(0)
        }
    }

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
