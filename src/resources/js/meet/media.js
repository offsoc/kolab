'use strict'

function Media()
{

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
}

export { Media }
