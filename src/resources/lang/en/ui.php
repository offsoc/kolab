<?php

/**
 * This file will be converted to a Vue-i18n compatible JSON format on build time
 *
 * Note: The Laravel localization features do not work here. Vue-i18n rules are different
 */

return [

    'button' => [
        'accept' => "Accept",
        'back' => "Back",
        'cancel' => "Cancel",
        'close' => "Close",
        'continue' => "Continue",
        'deny' => "Deny",
        'save' => "Save",
        'submit' => "Submit",
    ],

    'dashboard' => [
        'beta' => "beta",
    ],

    'distlist' => [
        'list-title' => "Distribution list | Distribution lists",
        'create' => "Create list",
        'delete' => "Delete list",
        'email' => "Email",
        'list-empty' => "There are no distribution lists in this account.",
        'new' => "New distribution list",
        'recipients' => "Recipients",
    ],

    'form' => [
        'code' => "Confirmation Code",
        'email' => "Email Address",
        'none' => "none",
        'password' => "Password",
        'password-confirm' => "Confirm Password",
        'status' => "Status",
    ],

    'lang' => [
        'en' => "English",
        'de' => "German",
        'fr' => "French",
        'it' => "Italian",
    ],

    'login' => [
        '2fa' => "Second factor code",
        '2fa_desc' => "Second factor code is optional for users with no 2-Factor Authentication setup.",
        'forgot_password' => "Forgot password?",
        'header' => "Please sign in",
        'sign_in' => "Sign in",
        'webmail' => "Webmail"
    ],

    'meet' => [
        'title' => "Voice & Video Conferencing",
        'welcome' => "Welcome to our beta program for Voice & Video Conferencing.",
        'url' => "You have a room of your own at the URL below. This room is only open when you yourself are in attendance. Use this URL to invite people to join you.",
        'notice' => "This is a work in progress and more features will be added over time. Current features include:",
        'sharing' => "Screen Sharing",
        'sharing-text' => "Share your screen for presentations or show-and-tell.",
        'security' => "Room Security",
        'security-text' => "Increase the room security by setting a password that attendees will need to know"
            . " before they can enter, or lock the door so attendees will have to knock, and a moderator can accept or deny those requests.",
        'qa' => "Raise Hand (Q&A)",
        'qa-text' => "Silent audience members can raise their hand to facilitate a Question & Answer session with the panel members.",
        'moderation' => "Moderator Delegation",
        'moderation-text' => "Delegate moderator authority for the session, so that a speaker is not needlessly"
            . " interrupted with attendees knocking and other moderator duties.",
        'eject' => "Eject Attendees",
        'eject-text' => "Eject attendees from the session in order to force them to reconnect, or address policy"
            . " violations. Click the user icon for effective dismissal.",
        'silent' => "Silent Audience Members",
        'silent-text' => "For a webinar-style session, configure the room to force all new attendees to be silent audience members.",
        'interpreters' => "Language Specific Audio Channels",
        'interpreters-text' => "Designate a participant to interpret the original audio to a target language, for sessions"
            . " with multi-lingual attendees. The interpreter is expected to be able to relay the original audio, and override it.",
        'beta-notice' => "Keep in mind that this is still in beta and might come with some issues."
            . " Should you encounter any on your way, let us know by contacting support.",

        // Room options dialog
        'options' => "Room options",
        'password' => "Password",
        'password-none' => "none",
        'password-clear' => "Clear password",
        'password-set' => "Set password",
        'password-text' => "You can add a password to your meeting. Participants will have to provide the password before they are allowed to join the meeting.",
        'lock' => "Locked room",
        'lock-text' => "When the room is locked participants have to be approved by a moderator before they could join the meeting.",
        'nomedia' => "Subscribers only",
        'nomedia-text' => "Forces all participants to join as subscribers (with camera and microphone turned off)."
            . " Moderators will be able to promote them to publishers throughout the session.",

        // Room menu
        'partcnt' => "Number of participants",
        'menu-audio-mute' => "Mute audio",
        'menu-audio-unmute' => "Unmute audio",
        'menu-video-mute' => "Mute video",
        'menu-video-unmute' => "Unmute video",
        'menu-screen' => "Share screen",
        'menu-hand-lower' => "Lower hand",
        'menu-hand-raise' => "Raise hand",
        'menu-channel' => "Interpreted language channel",
        'menu-chat' => "Chat",
        'menu-fullscreen' => "Full screen",
        'menu-fullscreen-exit' => "Exit full screen",
        'menu-leave' => "Leave session",

        // Room setup screen
        'setup-title' => "Set up your session",
        'mic' => "Microphone",
        'cam' => "Camera",
        'nick' => "Nickname",
        'nick-placeholder' => "Your name",
        'join' => "JOIN",
        'joinnow' => "JOIN NOW",
        'imaowner' => "I'm the owner",

        // Room
        'qa' => "Q & A",
        'leave-title' => "Room closed",
        'leave-body' => "The session has been closed by the room owner.",
        'media-title' => "Media setup",
        'join-request' => "Join request",
        'join-requested' => "{user} requested to join.",

        // Status messages
        'status-init' => "Checking the room...",
        'status-323' => "The room is closed. Please, wait for the owner to start the session.",
        'status-324' => "The room is closed. It will be open for others after you join.",
        'status-325' => "The room is ready. Please, provide a valid password.",
        'status-326' => "The room is locked. Please, enter your name and try again.",
        'status-327' => "Waiting for permission to join the room.",
        'status-404' => "The room does not exist.",
        'status-429' => "Too many requests. Please, wait.",
        'status-500' => "Failed to connect to the room. Server error.",

        // Other menus
        'media-setup' => "Media setup",
        'perm' => "Permissions",
        'perm-av' => "Audio &amp; Video publishing",
        'perm-mod' => "Moderation",
        'lang-int' => "Language interpreter",
        'menu-options' => "Options",
    ],

    'menu' => [
        'cockpit' => "Cockpit",
        'login' => "Login",
        'logout' => "Logout",
        'signup' => "Signup",
        'toggle' => "Toggle navigation",
    ],

    'msg' => [
        'loading' => "Loading...",
        'notfound' => "Resource not found.",
    ],

    'nav' => [
        'step' => "Step {i}/{n}",
    ],

    'password' => [
        'reset' => "Password Reset",
        'reset-step1' => "Enter your email address to reset your password.",
        'reset-step1-hint' => "You may need to check your spam folder or unblock {email}.",
        'reset-step2' => "We sent out a confirmation code to your external email address."
            . " Enter the code we sent you, or click the link in the message.",
    ],

];
