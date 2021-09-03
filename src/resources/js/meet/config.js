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
        resolution: 'medium',
        aspectRatio: 1.777, // 16 : 9
        frameRate: 15, // Note: OpenVidu default was 30
        simulcast: true
    },

    screenOptions: {
        resolution: 'veryhigh',
        frameRate: 5,
        simulcast: false
    },

    // Simulcast encoding layers and levels
    simulcastEncodings: [
        { scaleResolutionDownBy: 4 },
        { scaleResolutionDownBy: 2 },
        { scaleResolutionDownBy: 1 }
    ],

    // Socket.io request timeout
    requestTimeout: 20000,
    transportOptions: {
        tcp : true
    }
}
