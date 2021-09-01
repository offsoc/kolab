'use strict'

import anchorme from 'anchorme'
import { Client } from './client.js'
import { Roles } from './constants.js'
import { Dropdown } from 'bootstrap'
import { library } from '@fortawesome/fontawesome-svg-core'

function Room(container)
{
    let session                 // Session object where the user will connect
    let sessionData             // Room session metadata
/*
    let publisher               // Publisher object which the user will publish
    let screenSession           // Session object where the user will connect for screen sharing
    let screenPublisher         // Publisher object which the user will publish the screen sharing
    let publisherDefaults = {
        publishAudio: true,     // Whether to start publishing with your audio unmuted or not
        publishVideo: true,     // Whether to start publishing with your video enabled or not
        resolution: '640x480',  // The resolution of your video
        frameRate: 30,          // The frame rate of your video
        mirror: true            // Whether to mirror your local video or not
    }
*/
    let connections = {}        // Connected users in the session
    let peers = {}
    let chatCount = 0
    let publishersContainer
    let subscribersContainer
    let scrollStop
    let $t

    const client = new Client()

    // Disconnect participant when browser's window close
    window.addEventListener('beforeunload', () => {
        leaveRoom()
    })

    window.addEventListener('resize', resize)

    // Public methods
    this.isScreenSharingSupported = isScreenSharingSupported
    this.joinRoom = joinRoom
    this.leaveRoom = leaveRoom
    this.raiseHand = raiseHand
    this.setupStart = setupStart
    this.setupStop = setupStop
    this.setupSetAudioDevice = setupSetAudioDevice
    this.setupSetVideoDevice = setupSetVideoDevice
    this.switchAudio = switchAudio
    this.switchChannel = switchChannel
    this.switchScreen = switchScreen
    this.switchVideo = switchVideo
    this.updateSession = updateSession

    /**
     * Join the room session
     *
     * @param data Session metadata and event handlers:
     *      token       - A token for the main connection,
     *      shareToken  - A token for screen-sharing connection,
     *      nickname    - Participant name,
     *      role        - connection (participant) role(s),
     *      connections - Optional metadata for other users connections (current state),
     *      channel     - Selected interpreted language channel (two-letter language code)
     *      languages   - Supported languages (code-to-label map)
     *      chatElement     - DOM element for the chat widget,
     *      counterElement  - DOM element for the participants counter,
     *      menuElement     - DOM element of the room toolbar,
     *      queueElement    - DOM element for the Q&A queue (users with a raised hand)
     *      onSuccess           - Callback for session connection (join) success
     *      onError             - Callback for session connection (join) error
     *      onDestroy           - Callback for session disconnection event,
     *      onJoinRequest       - Callback for join request,
     *      onConnectionChange  - Callback for participant changes, e.g. role update,
     *      onSessionDataUpdate - Callback for current user connection update,
     *      onMediaSetup        - Called when user clicks the Media setup button
     *      translate           - Translation function
     */
    function joinRoom(data) {
        // Create a container for subscribers and publishers
        publishersContainer = $('<div id="meet-publishers">').appendTo(container).get(0)
        subscribersContainer = $('<div id="meet-subscribers">').appendTo(container).get(0)

        resize();

        $t = data.translate

        // Make sure all supported callbacks exist, so we don't have to check
        // their existence everywhere anymore
        let events = ['Success', 'Error', 'Destroy', 'JoinRequest', 'ConnectionChange',
            'SessionDataUpdate', 'MediaSetup']

        events.map(event => 'on' + event).forEach(event => {
            if (!data[event]) {
                data[event] = () => {}
            }
        })

        sessionData = data

        // Participant added (including self)
        client.on('addPeer', (event) => {
            console.log('addPeer', event)

            event.element = participantCreate(event)

            if (event.videoElement) {
                $(event.element).prepend(event.videoElement)
            }

            peers[event.id] = event
        })

        // Participant removed
        client.on('removePeer', (peerId) => {
            console.log('removePeer', peerId)

            let peer = peers[peerId]

            if (peer) {
                // Remove elements related to the participant
                peerHandDown(peer)
                $(peer.element).remove()
                delete peers[peerId]
            }

            resize()
        })

        // Participant properties changed e.g. audio/video muted/unmuted
        client.on('updatePeer', (event, changed) => {
            console.log('updatePeer', event)

            let peer = peers[event.id]

            if (!peer) {
                return
            }

            event.element = peer.element

            if (event.videoElement && event.videoElement.parentNode != event.element) {
                $(event.element).prepend(event.videoElement)
            } else if (!event.videoElement) {
                $(event.element).find('video').remove()
            }

            if (changed && changed.includes('nickname')) {
                 nicknameUpdate(event.nickname, event.id)
            }

            if (changed && changed.includes('raisedHand')) {
                 if (event.raisedHand) {
                     peerHandUp(event)
                } else {
                    peerHandDown(event)
                }
            }

            participantUpdate(event.element, event)

            peers[event.id] = event
        })

        client.on('joinSuccess', () => {
            data.onSuccess()
        })

        // Handle session disconnection events
        client.on('closeSession', event => {
            // Notify the UI
            data.onDestroy(event)

            // Remove all participant elements
            Object.keys(peers).forEach(peerId => {
                $(peers[peerId].element).remove()
                delete peers[peerId]
            })

            // refresh the matrix
            resize()
        })

        const { audioSource, videoSource } = client.media.setupData()

        // Start the session
        client.joinSession(data.token, { videoSource, audioSource, nickname: data.nickname })

        // Prepare the chat
        initChat()
    }

    /**
     * Leave the room (disconnect)
     */
    function leaveRoom() {
        client.closeSession()
        peers = {}
    }

    /**
     * Raise or lower the hand
     *
     * @param status Hand raised or not
     */
    async function raiseHand(status) {
        return await client.raiseHand(status)
    }

    /**
     * Sets the audio and video devices for the session.
     * This will ask user for permission to access media devices.
     *
     * @param props Setup properties (videoElement, volumeElement, onSuccess, onError)
     */
    function setupStart(props) {
        client.media.setupStart(props)
    }

    /**
     * Stop the setup "process", cleanup after it.
     */
    function setupStop() {
        client.media.setupStop()
    }

    /**
     * Change the publisher audio device
     *
     * @param deviceId Device identifier string
     */
    async function setupSetAudioDevice(deviceId) {
        return await client.media.setupSetAudio(deviceId)
    }

    /**
     * Change the publisher video device
     *
     * @param deviceId Device identifier string
     */
    async function setupSetVideoDevice(deviceId) {
        return await client.media.setupSetVideo(deviceId)
    }

    /**
     * Setup the chat UI
     */
    function initChat() {
        // Handle arriving chat messages
        client.on('chatMessage', pushChatMessage)

        // The UI elements are created in the vue template
        // Here we add a logic for how they work

        const chat = $(sessionData.chatElement).find('.chat').get(0)
        const textarea = $(sessionData.chatElement).find('textarea')
        const button = $(sessionData.menuElement).find('.link-chat')

        textarea.on('keydown', e => {
            if (e.keyCode == 13 && !e.shiftKey) {
                if (textarea.val().length) {
                    client.chatMessage(textarea.val())
                    textarea.val('')
                }

                return false
            }
        })

        // Add an element for the count of unread messages on the chat button
        button.append('<span class="badge badge-dark blinker">')
            .on('click', () => {
                button.find('.badge').text('')
                chatCount = 0
                // When opening the chat scroll it to the bottom, or we shouldn't?
                scrollStop = false
                chat.scrollTop = chat.scrollHeight
            })

        $(chat).on('scroll', event => {
            // Detect manual scrollbar moves, disable auto-scrolling until
            // the scrollbar is positioned on the element bottom again
            scrollStop = chat.scrollTop + chat.offsetHeight < chat.scrollHeight
        })
    }

/*
    function signalEventHandler(signal) {
        let conn, data
        let connId = signal.from ? signal.from.connectionId : null

        switch (signal.type) {
            case 'signal:joinRequest':
                // accept requests from the server only
                if (!connId) {
                    sessionData.onJoinRequest(JSON.parse(signal.data))
                }
                break
        }
    }
*/

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
        let isSelf = false // TODO
        let chat = $(sessionData.chatElement).find('.chat')
        let box = chat.find('.message').last()

        message = $('<div>').html(message)

        message.find('a').attr('rel', 'noreferrer')

        if (box.length && box.data('id') == data.peerId) {
            // A message from the same user as the last message, no new box needed
            message.appendTo(box)
        } else {
            box = $('<div class="message">').data('id', data.peerId)
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

        // Scroll the chat element to the end
        if (!scrollStop) {
            chat.get(0).scrollTop = chat.get(0).scrollHeight
        }
    }

    /**
     * Switch interpreted language channel
     *
     * @param channel Two-letter language code
     */
    function switchChannel(channel) {
        sessionData.channel = channel

        // Mute/unmute all connections depending on the selected channel
        participantUpdateAll()
    }

    /**
     * Mute/Unmute audio for current session publisher
     */
    async function switchAudio() {
        const isActive = client.micStatus()

        if (isActive) {
            return await client.micMute()
        } else {
            return await client.micUnmute()
        }
    }

    /**
     * Mute/Unmute video for current session publisher
     */
    async function switchVideo() {
        const isActive = client.camStatus()

        if (isActive) {
            return await client.camMute()
        } else {
            return await client.camUnmute()
        }
    }

    /**
     * Switch on/off screen sharing
     */
    function switchScreen(callback) {
        if (screenPublisher) {
            // Note: This is what the original openvidu-call app does.
            // It is probably better for performance reasons to close the connection,
            // than to use unpublish() and keep the connection open.
            screenSession.disconnect()
            screenSession = null
            screenPublisher = null

            if (callback) {
                // Note: Disconnecting invalidates the token, we have to inform the vue component
                // to update UI state (and be prepared to request a new token).
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
        return false // TODO !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia)
    }

    /**
     * Update participant connection state
     */
    function connectionUpdate(data) {
        let conn = connections[data.connectionId]
        let refresh = false

        // It's me
        if (session.connection.connectionId == data.connectionId) {
            const rolePublisher = data.role && data.role & Roles.PUBLISHER
            const roleModerator = data.role && data.role & Roles.MODERATOR
            const isPublisher = sessionData.role & Roles.PUBLISHER
            const isModerator = sessionData.role & Roles.MODERATOR

            // demoted to a subscriber
            if ('role' in data && isPublisher && !rolePublisher) {
                session.unpublish(publisher)
                // FIXME: There's a reference in OpenVidu to a video element that should not
                // exist anymore. It causes issues when we try to do publish/unpublish
                // sequence multiple times in a row. So, we're clearing the reference here.
                let videos = publisher.stream.streamManager.videos
                publisher.stream.streamManager.videos = videos.filter(video => video.video.parentNode != null)
            }

            // merge the changed data into internal session metadata object
            sessionData = Object.assign({}, sessionData, data, { audioActive, videoActive })

            // update the participant element
            sessionData.element = participantUpdate(sessionData.element, sessionData)

            // promoted/demoted to/from a moderator
            if ('role' in data) {
                // Update all participants, to enable/disable the popup menu
                refresh = (!isModerator && roleModerator) || (isModerator && !roleModerator)
            }

            // promoted to a publisher
            if ('role' in data && !isPublisher && rolePublisher) {
                publisher.createVideoElement(sessionData.element, 'PREPEND')
                session.publish(publisher).then(() => {
                    sessionData.audioActive = publisher.stream.audioActive
                    sessionData.videoActive = publisher.stream.videoActive

                    sessionData.onSessionDataUpdate(sessionData)
                })

                // Open the media setup dialog
                // Note: If user didn't give permission to media before joining the room
                // he will not be able to use them now. Changing permissions requires
                // a page refresh.
                // Note: In Firefox I'm always being asked again for media permissions.
                // It does not happen in Chrome. In Chrome the cam/mic will be just re-used.
                // I.e. streaming starts automatically.
                // It might make sense to not start streaming automatically in any cirmustances,
                // display the dialog and wait until user closes it, but this would be
                // a bigger refactoring.
                sessionData.onMediaSetup()
            }
        } else if (conn) {
            // merge the changed data into internal session metadata object
            Object.keys(data).forEach(key => { conn[key] = data[key] })

            conn.element = participantUpdate(conn.element, conn)
        }

        // Update channels list
        sessionData.channels = getChannels(connections)

        // The channel user was using has been removed (or rather the participant stopped being an interpreter)
        if (sessionData.channel && !sessionData.channels.includes(sessionData.channel)) {
            sessionData.channel = null
            refresh = true
        }

        if (refresh) {
            participantUpdateAll()
        }

        // Inform the vue component, so it can update some UI controls
        sessionData.onSessionDataUpdate(sessionData)
    }

    /**
     * Handler for Hand-Up "signal"
     */
    function peerHandUp(peer) {
        let element = $(nicknameWidget(peer))

        participantUpdate(element, peer)

        element.attr('id', 'qa' + peer.id)
            .appendTo($(sessionData.queueElement).show())

        setTimeout(() => element.addClass('widdle'), 50)
    }

    /**
     * Handler for Hand-Down "signal"
     */
    function peerHandDown(peer) {
        let list = $(sessionData.queueElement)

        list.find('#qa' + peer.id).remove();

        if (!list.find('.meet-nickname').length) {
            list.hide();
        }
    }

    /**
     * Update participant nickname in the UI
     *
     * @param nickname Nickname
     * @param peerId   Connection identifier of the user
     */
    function nicknameUpdate(nickname, peerId) {
        if (peerId) {
            $(sessionData.chatElement).find('.chat').find('.message').each(function() {
                let elem = $(this)
                if (elem.data('id') == peerId) {
                    elem.find('.nickname').text(nickname || '')
                }
            })

            $(sessionData.queueElement).find('#qa' + peerId + ' .content').text(nickname || '')
        }
    }

    /**
     * Create a participant element in the matrix. Depending on the connection role
     * parameter it will be a video element wrapper inside the matrix or a simple
     * tag-like element on the subscribers list.
     *
     * @param params  Connection metadata/params
     * @param content Optional content to prepend to the element
     *
     * @return The element
     */
    function participantCreate(params, content) {
        let element

        if ((!params.language && params.role & Roles.PUBLISHER) || params.role & Roles.SCREEN) {
            // publishers and shared screens
            element = publisherCreate(params, content)
        } else {
            // subscribers and language interpreters
            element = subscriberCreate(params, content)
        }

        setTimeout(resize, 50);

        return element
    }

    /**
     * Create a <video> element wrapper with controls
     *
     * @param params  Connection metadata/params
     * @param content Optional content to prepend to the element
     */
    function publisherCreate(params, content) {
        let isScreen = params.role & Roles.SCREEN

        // Create the element
        let wrapper = $(
            '<div class="meet-video">'
            + svgIcon('user', 'fas', 'watermark')
            + '<div class="controls">'
                // TODO + '<button type="button" class="btn btn-link link-setup hidden" title="' + $t('meet.media-setup') + '">' + svgIcon('cog') + '</button>'
                + '<div class="volume hidden"><input type="range" min="0" max="1" step="0.1" /></div>'
                + '<button type="button" class="btn btn-link link-audio hidden" title="' + $t('meet.menu-audio-mute') + '">' + svgIcon('volume-mute') + '</button>'
                + '<button type="button" class="btn btn-link link-fullscreen closed hidden" title="' + $t('meet.menu-fullscreen') + '">' + svgIcon('expand') + '</button>'
                + '<button type="button" class="btn btn-link link-fullscreen open hidden" title="' + $t('meet.menu-fullscreen') + '">' + svgIcon('compress') + '</button>'
            + '</div>'
            + '<div class="status">'
                + '<span class="bg-warning status-audio hidden">' + svgIcon('microphone-slash') + '</span>'
                + '<span class="bg-warning status-video hidden">' + svgIcon('video-slash') + '</span>'
            + '</div>'
            + '</div>'
        )

        // Append the nickname widget
        wrapper.find('.controls').before(nicknameWidget(params))

        if (content) {
            wrapper.prepend(content)
        }

        if (isScreen) {
            wrapper.addClass('screen')
        }

        if (params.isSelf) {
            wrapper.find('.link-setup').removeClass('hidden').on('click', () => sessionData.onMediaSetup())
        } else {
            let volumeInput = wrapper.find('.volume input')
            let audioButton = wrapper.find('.link-audio')
            let inVolume = false
            let hideVolumeTimeout
            let hideVolume = () => {
                if (inVolume) {
                    hideVolumeTimeout = setTimeout(hideVolume, 1000)
                } else {
                    volumeInput.parent().addClass('hidden')
                }
            }

            // Enable and set up the audio mute button
            audioButton.removeClass('hidden')
                .on('click', e => {
                    let video = wrapper.find('video')[0]

                    video.muted = !video.muted
                    video.volume = video.muted ? 0 : 1

                    audioButton[video.muted ? 'addClass' : 'removeClass']('text-danger')
                    volumeInput.val(video.volume)
                })
                // Show the volume slider when mouse is over the audio mute/unmute button
                .on('mouseenter', () => {
                    let video = wrapper.find('video')[0]

                    clearTimeout(hideVolumeTimeout)
                    volumeInput.parent().removeClass('hidden')
                    volumeInput.val(video.volume)
                })
                .on('mouseleave', () => {
                    hideVolumeTimeout = setTimeout(hideVolume, 1000)
                })

            // Set up the audio volume control
            volumeInput
                .on('mouseenter', () => { inVolume = true })
                .on('mouseleave', () => { inVolume = false })
                .on('change input', () => {
                    let video = wrapper.find('video')[0]
                    let volume = volumeInput.val()

                    video.volume = volume
                    video.muted = volume == 0
                    audioButton[video.muted ? 'addClass' : 'removeClass']('text-danger')
                })
        }

        participantUpdate(wrapper, params, true)

        // Fullscreen control
        if (document.fullscreenEnabled) {
            wrapper.find('.link-fullscreen.closed').removeClass('hidden')
                .on('click', () => {
                    wrapper.get(0).requestFullscreen()
                })

            wrapper.find('.link-fullscreen.open')
                .on('click', () => {
                    document.exitFullscreen()
                })

            wrapper.on('fullscreenchange', () => {
                // const enabled = document.fullscreenElement
                wrapper.find('.link-fullscreen').toggleClass('hidden')
            })
        }

        // Remove the subscriber element, if exists
        $('#subscriber-' + params.id).remove()

        let prio = params.isSelf || (isScreen && !$(publishersContainer).children('.screen').length)

        return wrapper[prio ? 'prependTo' : 'appendTo'](publishersContainer)
            .attr('id', 'publisher-' + params.id)
            .get(0)
    }

    /**
     * Update the publisher/subscriber element controls
     *
     * @param wrapper The wrapper element
     * @param params  Connection metadata/params
     */
    function participantUpdate(wrapper, params, noupdate) {
        const element = $(wrapper)
        const isModerator = sessionData.role & Roles.MODERATOR
        const isSelf = params.isSelf
        const rolePublisher = params.role & Roles.PUBLISHER
        const roleModerator = params.role & Roles.MODERATOR
        const roleScreen = params.role & Roles.SCREEN
        const roleOwner = params.role & Roles.OWNER
        const roleInterpreter = rolePublisher && !!params.language

        if (!noupdate && !roleScreen) {
            const isPublisher = element.is('.meet-video')

            // Publisher-to-interpreter or vice-versa, move element to the subscribers list or vice-versa,
            // but keep the existing video element
            if (
                !isSelf
                && element.find('video').length
                && ((roleInterpreter && isPublisher) || (!roleInterpreter && !isPublisher && rolePublisher))
            ) {
                wrapper = participantCreate(params, element.find('video'))
                element.remove()
                return wrapper
            }

            // Handle publisher-to-subscriber and subscriber-to-publisher change
            if (
                !roleInterpreter
                && (rolePublisher && !isPublisher) || (!rolePublisher && isPublisher)
            ) {
                element.remove()
                return participantCreate(params)
            }
        }

        let muted = false
        let video = element.find('video')[0]

        // When a channel is selected - mute everyone except the interpreter of the language.
        // When a channel is not selected - mute language interpreters only
        if (sessionData.channel) {
            muted = !(roleInterpreter && params.language == sessionData.channel)
        } else {
            muted = roleInterpreter
        }

        if (muted && !isSelf) {
            element.find('.status-audio').removeClass('hidden')
            element.find('.link-audio').addClass('hidden')
        } else {
            element.find('.status-audio')[params.audioActive ? 'addClass' : 'removeClass']('hidden')

            if (!isSelf) {
                element.find('.link-audio').removeClass('hidden')
            }

            muted = !params.audioActive || isSelf
        }

        element.find('.status-video')[params.videoActive ? 'addClass' : 'removeClass']('hidden')

        if (video) {
            video.muted = muted
        }

        if ('nickname' in params) {
            element.find('.meet-nickname > .content').text(params.nickname)
        }

        if (isSelf) {
            element.addClass('self')
        }

        if (isModerator) {
            element.addClass('moderated')
        }

        const withPerm = isModerator && !roleScreen && !(roleOwner && !isSelf);
        const withMenu = isSelf || (isModerator && !roleOwner)

        // TODO: This probably could be better done with css
        let elements = {
            '.dropdown-menu': withMenu,
            '.permissions': withPerm,
            '.interpreting': withPerm && rolePublisher,
            'svg.moderator': roleModerator,
            'svg.user': !roleModerator && !roleInterpreter,
            'svg.interpreter': !roleModerator && roleInterpreter
        }

        Object.keys(elements).forEach(key => {
            element.find(key)[elements[key] ? 'removeClass' : 'addClass']('hidden')
        })

        element.find('.action-role-publisher input').prop('checked', rolePublisher)
        element.find('.action-role-moderator input').prop('checked', roleModerator)
            .prop('disabled', roleOwner)

        element.find('.interpreting select').val(roleInterpreter ? params.language : '')

        return wrapper
    }

    /**
     * Update/refresh state of all participants' elements
     */
    function participantUpdateAll() {
        Object.keys(connections).forEach(key => {
            const conn = connections[key]
            participantUpdate(conn.element, conn)
        })
    }

    /**
     * Create a tag-like element for a subscriber participant
     *
     * @param params  Connection metadata/params
     * @param content Optional content to prepend to the element
     */
    function subscriberCreate(params, content) {
        // Create the element
        let wrapper = $('<div class="meet-subscriber">').append(nicknameWidget(params))

        if (content) {
            wrapper.prepend(content)
        }

        participantUpdate(wrapper, params, true)

        return wrapper[params.isSelf ? 'prependTo' : 'appendTo'](subscribersContainer)
            .attr('id', 'subscriber-' + params.id)
            .get(0)
    }

    /**
     * Create a tag-like nickname widget
     *
     * @param object params Connection metadata/params
     */
    function nicknameWidget(params) {
        let languages = []

        // Append languages selection options
        Object.keys(sessionData.languages).forEach(code => {
            languages.push(`<option value="${code}">${$t(sessionData.languages[code])}</option>`)
        })

        // Create the element
        let element = $(
            '<div class="dropdown">'
                + '<a href="#" class="meet-nickname btn" aria-haspopup="true" aria-expanded="false" role="button">'
                    + '<span class="content"></span>'
                    + '<span class="icon">'
                        + svgIcon('user', null, 'user')
                        + svgIcon('crown', null, 'moderator hidden')
                        + svgIcon('headphones', null, 'interpreter hidden')
                    + '</span>'
                + '</a>'
                + '<div class="dropdown-menu">'
                    + '<a class="dropdown-item action-nickname" href="#">Nickname</a>'
                    + '<a class="dropdown-item action-dismiss" href="#">Dismiss</a>'
                    + '<div class="dropdown-divider permissions"></div>'
                    + '<div class="permissions">'
                        + '<h6 class="dropdown-header">' + $t('meet.perm') + '</h6>'
                        + '<label class="dropdown-item action-role-publisher form-check form-switch">'
                            + '<input type="checkbox" class="form-check-input">'
                            + ' <span class="form-check-label">' + $t('meet.perm-av') + '</span>'
                        + '</label>'
                        + '<label class="dropdown-item action-role-moderator form-check form-switch">'
                            + '<input type="checkbox" class="form-check-input">'
                            + ' <span class="form-check-label">' + $t('meet.perm-mod') + '</span>'
                        + '</label>'
                    + '</div>'
                    + '<div class="dropdown-divider interpreting"></div>'
                    + '<div class="interpreting">'
                        + '<h6 class="dropdown-header">' + $t('meet.lang-int') + '</h6>'
                        + '<div class="ps-3 pe-3"><select class="form-select">'
                            + '<option value="">- ' + $t('form.none') + ' -</option>'
                            + languages.join('')
                        + '</select></div>'
                    + '</div>'
                + '</div>'
            + '</div>'
        )

        let nickname = element.find('.meet-nickname')
            .addClass('btn btn-outline-' + (params.isSelf ? 'primary' : 'secondary'))
            .attr({title: $t('meet.menu-options'), 'data-bs-toggle': 'dropdown'})

        const dropdown = new Dropdown(nickname[0], { boundary: container.parentNode })

        if (params.isSelf) {
            // Add events for nickname change
            let editable = element.find('.content')[0]
            let editableEnable = () => {
                editable.contentEditable = true
                editable.focus()
            }
            let editableUpdate = () => {
                // Skip redundant update on blur, if it was already updated
                if (editable.contentEditable !== 'false') {
                    editable.contentEditable = false
                    client.setNickname(editable.innerText)
                }
            }

            element.find('.action-nickname').on('click', editableEnable)
            element.find('.action-dismiss').remove()

            $(editable).on('blur', editableUpdate)
                .on('keydown', e => {
                    // Enter or Esc
                    if (e.keyCode == 13 || e.keyCode == 27) {
                        editableUpdate()
                        return false
                    }

                    // Do not propagate the event, so it does not interfere with our
                    // keyboard shortcuts
                    e.stopPropagation()
                })
        } else {
            element.find('.action-nickname').remove()

            element.find('.action-dismiss').on('click', () => {
                client.kickPeer(params.id)
            })
        }

        let connectionRole = () => {
            if (params.isSelf) {
                return sessionData.role
            }
            if (params.id in connections) {
                return connections[params.peerId].role
            }
            return 0
        }

        // Don't close the menu on permission change
        element.find('.dropdown-menu > label').on('click', e => { e.stopPropagation() })

        element.find('.action-role-publisher input').on('change', e => {
            const enabled = e.target.checked
            let role = connectionRole()

            if (enabled) {
                role |= Roles.PUBLISHER
            } else {
                role |= Roles.SUBSCRIBER
                if (role & Roles.PUBLISHER) {
                    role ^= Roles.PUBLISHER
                }
            }

            sessionData.onConnectionChange(params.id, { role })
        })

        element.find('.action-role-moderator input').on('change', e => {
            const enabled = e.target.checked
            let role = connectionRole()

            if (enabled) {
                role |= Roles.MODERATOR
            } else if (role & Roles.MODERATOR) {
                role ^= Roles.MODERATOR
            }

            sessionData.onConnectionChange(params.id, { role })
        })

        element.find('.interpreting select')
            .on('change', e => {
                const language = $(e.target).val()
                sessionData.onConnectionChange(params.id, { language })
                dropdown.hide()
            })
            .on('click', e => {
                // Prevents from closing the dropdown menu on click
                e.stopPropagation()
            })

        return element.get(0)
    }

    /**
     * Window onresize event handler (updates room layout)
     */
    function resize() {
        if (publishersContainer) {
            updateLayout()
        }

        $(container).parent()[window.screen.width <= 768 ? 'addClass' : 'removeClass']('mobile')
    }

    /**
     * Update the room "matrix" layout
     */
    function updateLayout() {
        let publishers = $(publishersContainer).find('.meet-video')
        let numOfVideos = publishers.length

        if (sessionData && sessionData.counterElement) {
            sessionData.counterElement.innerHTML = Object.keys(peers).length
        }

        if (!numOfVideos) {
            subscribersContainer.style.minHeight = 'auto'
            return
        }

        // Note: offsetHeight/offsetWidth return rounded values, but for proper matrix
        // calculations we need more precision, therefore we use getBoundingClientRect()

        let allHeight = container.offsetHeight
        let scrollHeight = subscribersContainer.scrollHeight
        let bcr = publishersContainer.getBoundingClientRect()
        let containerWidth = bcr.width
        let containerHeight = bcr.height
        let limit = Math.ceil(allHeight * 0.25) // max subscribers list height

        // Fix subscribers list height
        if (subscribersContainer.offsetHeight <= scrollHeight) {
            limit = Math.min(scrollHeight, limit)
            subscribersContainer.style.minHeight = limit + 'px'
            containerHeight = allHeight - limit
        } else {
            subscribersContainer.style.minHeight = 'auto'
        }

        let css, rows, cols, height, padding = 0

        // Make the first screen sharing tile big
        let screenVideo = publishers.filter('.screen').find('video').get(0)

        if (screenVideo) {
            let element = screenVideo.parentNode
            let connId = element.id.replace(/^publisher-/, '')
/*
            let connection = connections[connId]

            // We know the shared screen video dimensions, we can calculate
            // width/height of the tile in the matrix
            if (connection && connection.videoDimensions) {
                let screenWidth = connection.videoDimensions.width
                let screenHeight = containerHeight

                // TODO: When the shared window is minimized the width/height is set to 1 (or 2)
                //       - at least on my system. We might need to handle this case nicer. Right now
                //       it create a 1-2px line on the left of the matrix - not a big issue.
                // TODO: Make the 0.666 factor bigger for wide screen and small number of participants?
                let maxWidth = Math.ceil(containerWidth * 0.666)

                if (screenWidth > maxWidth) {
                    screenWidth = maxWidth
                }

                // Set the tile position and size
                $(element).css({
                    width: screenWidth + 'px',
                    height: screenHeight + 'px',
                    position: 'absolute',
                    top: 0,
                    left: 0
                })

                padding = screenWidth + 'px'

                // Now the estate for the rest of participants is what's left on the right side
                containerWidth -= screenWidth
                publishers = publishers.not(element)
                numOfVideos -= 1
            }
*/
        }

        // Compensate the shared screen estate with a padding
        $(publishersContainer).css('padding-left', padding)

        const factor = containerWidth / containerHeight

        if (factor >= 16/9) {
            if (numOfVideos <= 3) {
                rows = 1
            } else if (numOfVideos <= 8) {
                rows = 2
            } else if (numOfVideos <= 15) {
                rows = 3
            } else if (numOfVideos <= 20) {
                rows = 4
            } else {
                rows = 5
            }

            cols = Math.ceil(numOfVideos / rows)
        } else {
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
        }

        // console.log('factor=' + factor, 'num=' + numOfVideos, 'cols = '+cols, 'rows=' + rows);

        // Update all tiles (except the main shared screen) in the matrix
        publishers.css({
            width: (containerWidth / cols) + 'px',
            // Height must be in pixels to make object-fit:cover working
            height:  (containerHeight / rows) + 'px'
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

        if (!screenOV) {
            screenOV = ovInit()
        }

        // Init screen sharing session
        if (!gotSession) {
            screenSession = screenOV.initSession();
        }

        let successFunc = function() {
            screenSession.publish(screenPublisher)

            screenSession.on('sessionDisconnected', event => {
                callback(false)
                screenSession = null
                screenPublisher = null
            })

            if (callback) {
                callback(true)
            }
        }

        let errorFunc = function() {
            screenPublisher = null
            if (callback) {
                callback(false, true)
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
    function svgIcon(name, type, className) {
        // Note: the library will contain definitions for all icons registered elswhere
        const icon = library.definitions[type || 'fas'][name]

        let attrs = {
            'class': 'svg-inline--fa',
            'aria-hidden': true,
            focusable: false,
            role: 'img',
            xmlns: 'http://www.w3.org/2000/svg',
            viewBox: `0 0 ${icon[0]} ${icon[1]}`
        }

        if (className) {
            attrs['class'] += ' ' + className
        }

        return $(`<svg><path fill="currentColor" d="${icon[4]}"></path></svg>`)
            .attr(attrs)
            .get(0).outerHTML
    }

    /**
     * A way to update some session data, after you joined the room
     *
     * @param data Same input as for joinRoom()
     */
    function updateSession(data) {
        sessionData.shareToken = data.shareToken
    }

    /**
     * Get all existing language interpretation channels
     */
    function getChannels(connections) {
        let channels = []

        Object.keys(connections || {}).forEach(key => {
            let conn = connections[key]

            if (
                conn.language
                && !channels.includes(conn.language)
            ) {
                channels.push(conn.language)
            }
        })

        return channels
    }
}

export { Room }
