'use strict'

import anchorme from 'anchorme'
import { Client } from './client.js'
import { Roles } from './constants.js'
import { Dropdown } from 'bootstrap'
import { library } from '@fortawesome/fontawesome-svg-core'

function Room(container)
{
    let sessionData             // Room session metadata
    let peers = {}              // Participants in the session (including self)
    let publishersContainer     // Container element for publishers
    let subscribersContainer    // Container element for subscribers
    let selfId                  // peer Id of the current user

    let chatCount = 0
    let scrollStop
    let $t
    let $toast

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
    this.getStats = getStats

    /**
     * Join the room session
     *
     * @param data Session metadata and event handlers:
     *      token           - A token for the main connection,
     *      nickname        - Participant name,
     *      languages       - Supported languages (code-to-label map)
     *      chatElement     - DOM element for the chat widget,
     *      counterElement  - DOM element for the participants counter,
     *      menuElement     - DOM element of the room toolbar,
     *      queueElement    - DOM element for the Q&A queue (users with a raised hand)
     *      onSuccess       - Callback for session connection (join) success
     *      onError         - Callback for session connection (join) error
     *      onDestroy       - Callback for session disconnection event,
     *      onMediaSetup    - Called when user clicks the Media setup button
     *      onUpdate        - Callback for current user/session update,
     *      toast           - Toast widget
     *      translate       - Translation function
     */
    function joinRoom(data) {
        // Create a container for subscribers and publishers
        publishersContainer = $('<div id="meet-publishers">').appendTo(container).get(0)
        subscribersContainer = $('<div id="meet-subscribers">').appendTo(container).get(0)

        resize()

        $t = data.translate
        $toast = data.toast

        // Make sure all supported callbacks exist, so we don't have to check
        // their existence everywhere anymore
        let events = ['Success', 'Error', 'Destroy', 'Update', 'MediaSetup']

        events.map(event => 'on' + event).forEach(event => {
            if (!data[event]) {
                data[event] = () => {}
            }
        })

        sessionData = data

        // Handle new participants (including self)
        client.on('addPeer', (event) => {
            if (event.isSelf) {
                selfId = event.id
            }

            peers[event.id] = event

            event.element = participantCreate(event, event.videoElement)

            if (event.screenVideoElement && !event.screen) {
                const screen = { id: event.id, role: event.role | Roles.SCREEN, nickname: event.nickname }
                event.screen = participantCreate(screen, event.screenVideoElement)
            }

            if (event.raisedHand) {
                peerHandUp(event)
            }
        })

        // Handle removed participants
        client.on('removePeer', (peerId) => {
            let peer = peers[peerId]

            if (peer) {
                // Remove elements related to the participant
                peerHandDown(peer)

                $(peer.element).remove()
                if (peer.screen) {
                    $(peer.screen).remove()
                }

                delete peers[peerId]
            }

            resize()
        })

        // Participant properties changed e.g. audio/video muted/unmuted
        client.on('updatePeer', (event, changed) => {
            let peer = peers[event.id]

            if (!peer) {
                return
            }

            event.element = peer.element
            event.screen = peer.screen

            // Video element added or removed
            if (event.videoElement && event.videoElement.parentNode != event.element) {
                $(event.element).prepend(event.videoElement)
            } else if (!event.videoElement) {
                $(event.element).find('video').remove()
            }

            // Video element of the shared screen added or removed
            if (event.screenVideoElement && !event.screen) {
                const screen = { id: event.id, role: event.role | Roles.SCREEN, nickname: event.nickname }
                event.screen = participantCreate(screen, event.screenVideoElement)
            } else if (!event.screenVideoElement && event.screen) {
                $(event.screen).remove()
                event.screen = null
                resize()
            }

            peers[event.id] = event

            if (changed && changed.length) {
                if (changed && changed.includes('nickname')) {
                     nicknameUpdate(event.nickname, event.id)
                }

                if (changed.includes('raisedHand')) {
                     if (event.raisedHand) {
                        peerHandUp(event)
                    } else {
                        peerHandDown(event)
                    }
                }

                if (changed && changed.includes('screenWidth')) {
                     resize()
                     return
                }
            }

            if (changed && changed.includes('interpreterRole')
                && !event.isSelf && $(event.element).find('video').length
            ) {
                // Publisher-to-interpreter or vice-versa, move element to the subscribers list or vice-versa,
                // but keep the existing video element
                let wrapper = participantCreate(event, $(event.element).find('video'))
                event.element.remove()
                event.element = wrapper
            } else if (changed && changed.includes('publisherRole') && !event.language) {
                // Handle publisher-to-subscriber and subscriber-to-publisher change
                event.element.remove()
                event.element = participantCreate(event, event.videoElement)
            } else {
                participantUpdate(event.element, event)
            }

            // It's me, got publisher role
            if (event.isSelf && (event.role & Roles.PUBLISHER) && changed && changed.includes('publisherRole')) {
                // Open the media setup dialog
                sessionData.onMediaSetup()
            }

            if (changed && changed.includes('moderatorRole')) {
                participantUpdateAll()
            }
        })

        // Handle successful connection to the room
        client.on('joinSuccess', () => {
            data.onSuccess()
            client.media.setupStop()
        })

        // Handle join requests from other users (knocking to the room)
        client.on('joinRequest', event => {
            joinRequest(event)
        })

        // Handle session disconnection events
        client.on('closeSession', event => {
            // Notify the UI
            data.onDestroy(event)

            // Remove all participant elements
            Object.keys(peers).forEach(peerId => {
                $(peers[peerId].element).remove()
            })
            peers = {}

            // refresh the matrix
            resize()
        })

        // Handle session update events (e.g. channel, channels list changes)
        client.on('updateSession', event => {
            // Inform the vue component, so it can update some UI controls
            sessionData.onUpdate(event)
        })

        const { audioSource, videoSource } = client.media.setupData()

        // Start the session
        client.joinSession(data.token, { videoSource, audioSource, nickname: data.nickname })

        // Prepare the chat
        initChat()
    }

    async function getStats() {
        return await client.getStats()
    }

    /**
     * Leave the room (disconnect)
     */
    function leaveRoom(forced) {
        client.closeSession(forced)
        peers = {}
    }

    /**
     * Handler for an event received by the moderator when a participant
     * is asking for a permission to join the room
     */
    function joinRequest(data) {
        const id = data.requestId

        // The toast for this user request already exists, ignore
        // It's not really needed as we do this on server-side already
        if ($('#i' + id).length) {
            return
        }

        const body = $(
            `<div>`
            + `<div class="picture"><img src="${data.picture}"></div>`
                + `<div class="content">`
                    + `<p class="mb-2"></p>`
                    + `<div class="text-end">`
                        + `<button type="button" class="btn btn-sm btn-success accept">${$t('btn.accept')}</button>`
                        + `<button type="button" class="btn btn-sm btn-danger deny ms-2">${$t('btn.deny')}</button>`
        )

        $toast.message({
            className: 'join-request',
            icon: 'user',
            timeout: 0,
            title: $t('meet.join-request'),
            // titleClassName: '',
            body: body.html(),
            onShow: element => {
                $(element).find('p').text($t('meet.join-requested', { user: data.nickname || '' }))

                // add id attribute, so we can identify it
                $(element).attr('id', 'i' + id)
                    // add action to the buttons
                    .find('button.accept,button.deny').on('click', e => {
                        const action = $(e.target).is('.accept') ? 'Accept' : 'Deny'
                        client['joinRequest' + action](id)
                        $('#i' + id).remove()
                    })
            }
        })
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
    async function setupStart(props) {
        client.media.setupStart(props)

        // When setting up devices while the session is ongoing we have to
        // disable currently selected devices (temporarily) otherwise e.g.
        // changing a mic or camera to another device will not be possible.
        if (client.isJoined()) {
            client.setMic('')
            client.setCamera('')
        }
    }

    /**
     * Stop the setup "process", cleanup after it.
     */
    async function setupStop() {
        client.media.setupStop()

        // Apply device changes to the client
        const { audioSource, videoSource } = client.media.setupData()
        await client.setMic(audioSource)
        await client.setCamera(videoSource)
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
        button.append('<span class="badge bg-dark blinker">')
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

            if (data.isSelf) {
                box.addClass('self')
            }
        }

        // Count unread messages
        if (!$(sessionData.chatElement).is('.open')) {
            if (!data.isSelf) {
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
        client.setLanguageChannel(channel)
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
    async function switchScreen() {
        const isActive = client.screenStatus()

        if (isActive) {
            return await client.screenUnshare()
        } else {
            return await client.screenShare()
        }
    }

    /**
     * Detect if screen sharing is supported by the browser
     */
    function isScreenSharingSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia)
    }

    /**
     * Handler for Hand-Up "signal"
     */
    function peerHandUp(peer) {
        let element = $(nicknameWidget(peer))

        participantUpdate(element, peer)

        element.attr('id', 'qa' + peer.id).appendTo($(sessionData.queueElement).show())

        setTimeout(() => element.addClass('wiggle'), 50)
    }

    /**
     * Handler for Hand-Down "signal"
     */
    function peerHandDown(peer) {
        let list = $(sessionData.queueElement)

        list.find('#qa' + peer.id).remove()

        if (!list.find('.meet-nickname').length) {
            list.hide()
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

            // Also update the nickname for the shared screen as we do not call
            // participantUpdate() for this element
            $('#screen-' + peerId).find('.meet-nickname .content').text(nickname || '')
        }
    }

    /**
     * Create a participant element in the matrix. Depending on the peer role
     * parameter it will be a video element wrapper inside the matrix or a simple
     * tag-like element on the subscribers list.
     *
     * @param params  Peer metadata/params
     * @param content Optional content to prepend to the element, e.g. video element
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

        setTimeout(resize, 50)

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
                + '<button type="button" class="btn btn-link link-setup hidden" title="' + $t('meet.media-setup') + '">' + svgIcon('cog') + '</button>'
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
        } else if (!isScreen) {
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
            let setVolume = (video, volume) => {
                video.volume = volume
                video.muted = volume == 0
                audioButton[video.muted ? 'addClass' : 'removeClass']('text-danger')
                client[video.muted ? 'peerMicMute' : 'peerMicUnmute'](params.id)
            }

            // Enable and set up the audio mute button
            audioButton.removeClass('hidden')
                .on('click', e => {
                    let video = wrapper.find('video')[0]

                    setVolume(video, !video.muted ? 0 : 1)
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
                    setVolume(wrapper.find('video')[0], volumeInput.val())
                })
        }

        participantUpdate(wrapper, params)

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
            .attr('id', (isScreen ? 'screen-' : 'publisher-') + params.id)
            .get(0)
    }

    /**
     * Update the publisher/subscriber element controls
     *
     * @param wrapper The wrapper element
     * @param params  Peer metadata/params
     */
    function participantUpdate(wrapper, params) {
        const element = $(wrapper)
        const isSelf = params.isSelf
        const rolePublisher = params.role & Roles.PUBLISHER
        const roleModerator = params.role & Roles.MODERATOR
        const roleScreen = params.role & Roles.SCREEN
        const roleOwner = params.role & Roles.OWNER
        const roleInterpreter = rolePublisher && !!params.language
        const audioActive = roleScreen ? true : params.audioActive
        const videoActive = roleScreen ? true : params.videoActive

        element.find('.status-audio')[audioActive ? 'addClass' : 'removeClass']('hidden')
        element.find('.status-video')[videoActive ? 'addClass' : 'removeClass']('hidden')
        element.find('.meet-nickname > .content').text(params.nickname || '')

        if (isSelf) {
            element.addClass('self')
        } else if (!roleScreen) {
            element.find('.link-audio').removeClass('hidden')
        }

        const isModerator = peers[selfId] && peers[selfId].role & Roles.MODERATOR
        const withPerm = isModerator && !roleScreen && !(roleOwner && !isSelf)
        const withMenu = isSelf || (isModerator && !roleOwner)

        if (isModerator) {
            element.addClass('moderated')
        }

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
    }

    /**
     * Update/refresh state of all participants' elements
     */
    function participantUpdateAll() {
        Object.keys(peers).forEach(peerId => {
            const peer = peers[peerId]
            participantUpdate(peer.element, peer)
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

        participantUpdate(wrapper, params)

        // Two event handlers below fix the dropdown in the subscribers list.
        // Normally it gets hidden because of the overflow/height of the container.
        // FIXME: I think it wasn't needed in BS4, but it's a problem with BS5.
        const nickname = wrapper.children('.dropdown')[0]
        nickname.addEventListener('show.bs.dropdown', () => {
            $(subscribersContainer).css({
                'overflow-y': 'unset',
                height: $(subscribersContainer).height() + 'px'
            })
        })
        nickname.addEventListener('hide.bs.dropdown', () => {
            $(subscribersContainer).css({
                'overflow-y': 'auto',
                height: 'unset'
            })
        })

        return wrapper[params.isSelf ? 'prependTo' : 'appendTo'](subscribersContainer)
            .attr('id', 'subscriber-' + params.id)
            .get(0)
    }

    /**
     * Create a tag-like nickname widget
     *
     * @param object params Peer metadata/params
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

        // Don't close the menu on permission change
        element.find('.dropdown-menu > label').on('click', e => { e.stopPropagation() })

        element.find('.action-role-publisher input').on('change', e => {
            client[e.target.checked ? 'addRole' : 'removeRole'](params.id, Roles.PUBLISHER)
        })

        element.find('.action-role-moderator input').on('change', e => {
            client[e.target.checked ? 'addRole' : 'removeRole'](params.id, Roles.MODERATOR)
        })

        element.find('.interpreting select')
            .on('change', e => {
                const language = $(e.target).val()
                client.setLanguage(params.id, language)
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
            const element = screenVideo.parentNode
            const peerId = element.id.replace(/^screen-/, '')
            const peer = peers[peerId]

            // We know the shared screen video dimensions, we can calculate
            // width/height of the tile in the matrix
            if (peer && peer.screenWidth) {
                let screenWidth = peer.screenWidth
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

        // console.log('factor=' + factor, 'num=' + numOfVideos, 'cols = '+cols, 'rows=' + rows)

        // Update all tiles (except the main shared screen) in the matrix
        publishers.css({
            width: (containerWidth / cols) + 'px',
            // Height must be in pixels to make object-fit:cover working
            height:  (containerHeight / rows) + 'px'
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
}

export { Room }
