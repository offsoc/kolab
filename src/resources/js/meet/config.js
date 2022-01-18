export default {

    // Default audio options
    audioOptions: {
        autoGainControl: false,
        echoCancellation: true,
        noiseSuppression: true,
        voiceActivatedUnmute: false, // Automatically unmute speaking above noiseThreshold
        noiseThreshold: -60, // default -60 / This is only for voiceActivatedUnmute and audio-indicator
        sampleRate: 96000, // will not eat that much bandwith thanks to opus
        channelCount: 1, // usually mics are mono so this saves bandwidth
        volume: 1.0,
        sampleSize: 16,
        opusStereo: false, // usually mics are mono so this saves bandwidth
        opusDtx: true,  // will save bandwidth
        opusFec: true, // forward error correction
        opusPtime: '20', // minimum packet time (3, 5, 10, 20, 40, 60, 120)
        opusMaxPlaybackRate: 96000
    },

    // Default video options
    videoOptions: {
        width: { max: 1280 },
        aspectRatio: 1.777777778, // 16 : 9
        frameRate: { ideal: 15, max: 30 },
        simulcastEncodings: [
            { scaleResolutionDownBy: 4, maxBitrate: 300000 },
            { scaleResolutionDownBy: 2, maxBitrate: 800000 }
            // { scaleResolutionDownBy: 1, maxBitrate: 2500000 }
        ],
    },

    screenOptions: {
        width: { max: 1920 },
        height: { max: 1080 },
        frameRate: { ideal: 5, max: 30 },
        simulcastEncodings: [
            { maxBitrate: 1500000 },
            { maxBitrate: 5000000 }
        ],
    },

    // Socket.io request timeout
    requestTimeout: 20000,
    transportOptions: {
        tcp : true
    }
}
