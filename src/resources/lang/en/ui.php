<?php

/**
 * This file will be converted to a Vue-i18n compatible JSON format on build time
 *
 * Note: The Laravel localization features do not work here. Vue-i18n rules are different
 */

return [

    'app' => [
        'faq' => "FAQ",
    ],

    'btn' => [
        'add' => "Add",
        'accept' => "Accept",
        'back' => "Back",
        'cancel' => "Cancel",
        'close' => "Close",
        'continue' => "Continue",
        'copy' => "Copy",
        'delete' => "Delete",
        'deny' => "Deny",
        'download' => "Download",
        'edit' => "Edit",
        'file' => "Choose file...",
        'moreinfo' => "More information",
        'refresh' => "Refresh",
        'reset' => "Reset",
        'resend' => "Resend",
        'save' => "Save",
        'search' => "Search",
        'share' => "Share",
        'signup' => "Sign Up",
        'submit' => "Submit",
        'suspend' => "Suspend",
        'unsuspend' => "Unsuspend",
        'verify' => "Verify",
    ],

    'companion' => [
        'title' => "Companion App",
        'name' => "Name",
        'description' => "Use the Companion App on your mobile phone for advanced two factor authentication.",
        'pair-new' => "Pair new device",
        'paired' => "Paired devices",
        'pairing-instructions' => "Pair a new device using the following QR-Code:",
        'deviceid' => "Device ID",
        'nodevices' => "There are currently no devices",
        'delete' => "Remove devices",
        'remove-devices' => "Remove Devices",
        'remove-devices-text' => "Do you really want to remove all devices permanently?"
            . " Please note that this action cannot be undone, and you can only remove all devices together."
            . " You may pair devices you would like to keep individually again.",
    ],

    'dashboard' => [
        'beta' => "beta",
        'distlists' => "Distribution lists",
        'chat' => "Video chat",
        'companion' => "Companion app",
        'domains' => "Domains",
        'files' => "Files",
        'invitations' => "Invitations",
        'profile' => "Your profile",
        'resources' => "Resources",
        'settings' => "Settings",
        'shared-folders' => "Shared folders",
        'users' => "User accounts",
        'wallet' => "Wallet",
        'webmail' => "Webmail",
        'stats' => "Stats",
    ],

    'distlist' => [
        'list-title' => "Distribution list | Distribution lists",
        'create' => "Create list",
        'delete' => "Delete list",
        'email' => "Email",
        'list-empty' => "There are no distribution lists in this account.",
        'name' => "Name",
        'new' => "New distribution list",
        'recipients' => "Recipients",
        'sender-policy' => "Sender Access List",
        'sender-policy-text' => "With this list you can specify who can send mail to the distribution list."
            . " You can put a complete email address (jane@kolab.org), domain (kolab.org) or suffix (.org) that the sender email address is compared to."
            . " If the list is empty, mail from anyone is allowed.",
    ],

    'domain' => [
        'delete' => "Delete domain",
        'delete-domain' => "Delete {domain}",
        'delete-text' => "Do you really want to delete this domain permanently?"
            . " This is only possible if there are no users, aliases or other objects in this domain."
            . " Please note that this action cannot be undone.",
        'dns-verify' => "Domain DNS verification sample:",
        'dns-config' => "Domain DNS configuration sample:",
        'namespace' => "Namespace",
        'spf-whitelist' => "SPF Whitelist",
        'spf-whitelist-text' => "The Sender Policy Framework allows a sender domain to disclose, through DNS, "
            . "which systems are allowed to send emails with an envelope sender address within said domain.",
        'spf-whitelist-ex' => "Here you can specify a list of allowed servers, for example: <var>.ess.barracuda.com</var>.",
        'verify' => "Domain verification",
        'verify-intro' => "In order to confirm that you're the actual holder of the domain, we need to run a verification process before finally activating it for email delivery.",
        'verify-dns' => "The domain <b>must have one of the following entries</b> in DNS:",
        'verify-dns-txt' => "TXT entry with value:",
        'verify-dns-cname' => "or CNAME entry:",
        'verify-outro' => "When this is done press the button below to start the verification.",
        'verify-sample' => "Here's a sample zone file for your domain:",
        'config' => "Domain configuration",
        'config-intro' => "In order to let {app} receive email traffic for your domain you need to adjust the DNS settings, more precisely the MX entries, accordingly.",
        'config-sample' => "Edit your domain's zone file and replace existing MX entries with the following values:",
        'config-hint' => "If you don't know how to set DNS entries for your domain, please contact the registration service where you registered the domain or your web hosting provider.",
        'create' => "Create domain",
        'new' => "New domain",
    ],

    'error' => [
        '400' => "Bad request",
        '401' => "Unauthorized",
        '403' => "Access denied",
        '404' => "Not found",
        '405' => "Method not allowed",
        '500' => "Internal server error",
        'unknown' => "Unknown Error",
        'server' => "Server Error",
        'form' => "Form validation error",
    ],

    'file' => [
        'create' => "Create file",
        'delete' => "Delete file",
        'list-empty' => "There are no files in this account.",
        'mimetype' => "Mimetype",
        'mtime' => "Modified",
        'new' => "New file",
        'search' => "File name",
        'sharing' => "Sharing",
        'sharing-links-text' => "You can share the file with other users by giving them read-only access "
            . "to the file via a unique link.",
    ],

    'form' => [
        'acl' => "Access rights",
        'acl-full' => "All",
        'acl-read-only' => "Read-only",
        'acl-read-write' => "Read-write",
        'amount' => "Amount",
        'anyone' => "Anyone",
        'code' => "Confirmation Code",
        'config' => "Configuration",
        'date' => "Date",
        'description' => "Description",
        'details' => "Details",
        'disabled' => "disabled",
        'domain' => "Domain",
        'email' => "Email Address",
        'emails' => "Email Addresses",
        'enabled' => "enabled",
        'firstname' => "First Name",
        'general' => "General",
        'lastname' => "Last Name",
        'name' => "Name",
        'months' => "months",
        'none' => "none",
        'or' => "or",
        'password' => "Password",
        'password-confirm' => "Confirm Password",
        'phone' => "Phone",
        'settings' => "Settings",
        'shared-folder' => "Shared Folder",
        'size' => "Size",
        'status' => "Status",
        'surname' => "Surname",
        'type' => "Type",
        'user' => "User",
        'primary-email' => "Primary Email",
        'id' => "ID",
        'created' => "Created",
        'deleted' => "Deleted",
    ],

    'invitation' => [
        'create' => "Create invite(s)",
        'create-title' => "Invite for a signup",
        'create-email' => "Enter an email address of the person you want to invite.",
        'create-csv' => "To send multiple invitations at once, provide a CSV (comma separated) file, or alternatively a plain-text file, containing one email address per line.",
        'empty-list' => "There are no invitations in the database.",
        'title' => "Signup invitations",
        'search' => "Email address or domain",
        'send' => "Send invite(s)",
        'status-completed' => "User signed up",
        'status-failed' => "Sending failed",
        'status-sent' => "Sent",
        'status-new' => "Not sent yet",
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
        'qa-title' => "Raise Hand (Q&A)",
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
        'initializing' => "Initializing...",
        'loading' => "Loading...",
        'loading-failed' => "Failed to load data.",
        'notfound' => "Resource not found.",
        'info' => "Information",
        'error' => "Error",
        'uploading' => "Uploading...",
        'warning' => "Warning",
        'success' => "Success",
    ],

    'nav' => [
        'more' => "Load more",
        'step' => "Step {i}/{n}",
    ],

    'password' => [
        'link-invalid' => "The password reset code is expired or invalid.",
        'reset' => "Password Reset",
        'reset-step1' => "Enter your email address to reset your password.",
        'reset-step1-hint' => "You may need to check your spam folder or unblock {email}.",
        'reset-step2' => "We sent out a confirmation code to your external email address."
            . " Enter the code we sent you, or click the link in the message.",
    ],

    'resource' => [
        'create' => "Create resource",
        'delete' => "Delete resource",
        'invitation-policy' => "Invitation policy",
        'invitation-policy-text' => "Event invitations for a resource are normally accepted automatically"
            . " if there is no conflicting event on the requested time slot. Invitation policy allows"
            . " for rejecting such requests or to require a manual acceptance from a specified user.",
        'ipolicy-manual' => "Manual (tentative)",
        'ipolicy-accept' => "Accept",
        'ipolicy-reject' => "Reject",
        'list-title' => "Resource | Resources",
        'list-empty' => "There are no resources in this account.",
        'new' => "New resource",
    ],

    'settings' => [
        'password-policy' => "Password Policy",
        'password-retention' => "Password Retention",
        'password-max-age' => "Require a password change every",
    ],

    'shf' => [
        'aliases-none' => "This shared folder has no email aliases.",
        'create' => "Create folder",
        'delete' => "Delete folder",
        'acl-text' => "Defines user permissions to access the shared folder.",
        'list-title' => "Shared folder | Shared folders",
        'list-empty' => "There are no shared folders in this account.",
        'new' => "New shared folder",
        'type-mail' => "Mail",
        'type-event' => "Calendar",
        'type-contact' => "Address Book",
        'type-task' => "Tasks",
        'type-note' => "Notes",
        'type-file' => "Files",
    ],

    'signup' => [
        'email' => "Existing Email Address",
        'login' => "Login",
        'title' => "Sign Up",
        'step1' => "Sign up to start your free month.",
        'step2' => "We sent out a confirmation code to your email address. Enter the code we sent you, or click the link in the message.",
        'step3' => "Create your Kolab identity (you can choose additional addresses later).",
        'voucher' => "Voucher Code",
    ],

    'status' => [
        'prepare-account' => "We are preparing your account.",
        'prepare-domain' => "We are preparing the domain.",
        'prepare-distlist' => "We are preparing the distribution list.",
        'prepare-resource' => "We are preparing the resource.",
        'prepare-shared-folder' => "We are preparing the shared folder.",
        'prepare-user' => "We are preparing the user account.",
        'prepare-hint' => "Some features may be missing or readonly at the moment.",
        'prepare-refresh' => "The process never ends? Press the \"Refresh\" button, please.",
        'ready-account' => "Your account is almost ready.",
        'ready-domain' => "The domain is almost ready.",
        'ready-distlist' => "The distribution list is almost ready.",
        'ready-resource' => "The resource is almost ready.",
        'ready-shared-folder' => "The shared-folder is almost ready.",
        'ready-user' => "The user account is almost ready.",
        'verify' => "Verify your domain to finish the setup process.",
        'verify-domain' => "Verify domain",
        'degraded' => "Degraded",
        'deleted' => "Deleted",
        'suspended' => "Suspended",
        'notready' => "Not Ready",
        'active' => "Active",
    ],

    'support' => [
        'title' => "Contact Support",
        'id' => "Customer number or email address you have with us",
        'id-pl' => "e.g. 12345678 or john@kolab.org",
        'id-hint' => "Leave blank if you are not a customer yet",
        'name' => "Name",
        'name-pl' => "how we should call you in our reply",
        'email' => "Working email address",
        'email-pl' => "make sure we can reach you at this address",
        'summary' => "Issue Summary",
        'summary-pl' => "one sentence that summarizes your issue",
        'expl' => "Issue Explanation",
    ],

    'user' => [
        '2fa-hint1' => "This will remove 2-Factor Authentication entitlement as well as the user-configured factors.",
        '2fa-hint2' => "Please, make sure to confirm the user identity properly.",
        'add-beta' => "Enable beta program",
        'address' => "Address",
        'aliases' => "Aliases",
        'aliases-email' => "Email Aliases",
        'aliases-none' => "This user has no email aliases.",
        'add-bonus' => "Add bonus",
        'add-bonus-title' => "Add a bonus to the wallet",
        'add-penalty' => "Add penalty",
        'add-penalty-title' => "Add a penalty to the wallet",
        'auto-payment' => "Auto-payment",
        'auto-payment-text' => "Fill up by <b>{amount}</b> when under <b>{balance}</b> using {method}",
        'country' => "Country",
        'create' => "Create user",
        'custno' => "Customer No.",
        'degraded-warning' => "The account is degraded. Some features have been disabled.",
        'degraded-hint' => "Please, make a payment.",
        'delete' => "Delete user",
        'delete-account' => "Delete this account?",
        'delete-email' => "Delete {email}",
        'delete-text' => "Do you really want to delete this user permanently?"
            . " This will delete all account data and withdraw the permission to access the email account."
            . " Please note that this action cannot be undone.",
        'discount' => "Discount",
        'discount-hint' => "applied discount",
        'discount-title' => "Account discount",
        'distlists' => "Distribution lists",
        'domains' => "Domains",
        'domains-none' => "There are no domains in this account.",
        'ext-email' => "External Email",
        'finances' => "Finances",
        'greylisting' => "Greylisting",
        'greylisting-text' => "Greylisting is a method of defending users against spam. Any incoming mail from an unrecognized sender "
            . "is temporarily rejected. The originating server should try again after a delay. "
            . "This time the email will be accepted. Spammers usually do not reattempt mail delivery.",
        'list-title' => "User accounts",
        'managed-by' => "Managed by",
        'new' => "New user account",
        'org' => "Organization",
        'package' => "Package",
        'pass-input' => "Enter password",
        'pass-link' => "Set via link",
        'pass-link-label' => "Link:",
        'pass-link-hint' => "Press Submit to activate the link",
        'passwordpolicy' => "Password Policy",
        'price' => "Price",
        'profile-title' => "Your profile",
        'profile-delete' => "Delete account",
        'profile-delete-title' => "Delete this account?",
        'profile-delete-text1' => "This will delete the account as well as all domains, users and aliases associated with this account.",
        'profile-delete-warning' => "This operation is irreversible",
        'profile-delete-text2' => "As you will not be able to recover anything after this point, please make sure that you have migrated all data before proceeding.",
        'profile-delete-support' => "As we always strive to improve, we would like to ask for 2 minutes of your time. "
            . "The best tool for improvement is feedback from users, and we would like to ask "
            . "for a few words about your reasons for leaving our service. Please send your feedback to <a href=\"{href}\">{email}</a>.",
        'profile-delete-contact' => "Also feel free to contact {app} Support with any questions or concerns that you may have in this context.",
        'reset-2fa' => "Reset 2-Factor Auth",
        'reset-2fa-title' => "2-Factor Authentication Reset",
        'resources' => "Resources",
        'title' => "User account",
        'search' => "User email address or name",
        'search-pl' => "User ID, email or domain",
        'skureq' => "{sku} requires {list}.",
        'subscription' => "Subscription",
        'subscriptions' => "Subscriptions",
        'subscriptions-none' => "This user has no subscriptions.",
        'users' => "Users",
        'users-none' => "There are no users in this account.",
    ],

    'wallet' => [
        'add-credit' => "Add credit",
        'auto-payment-cancel' => "Cancel auto-payment",
        'auto-payment-change' => "Change auto-payment",
        'auto-payment-failed' => "The setup of automatic payments failed. Restart the process to enable automatic top-ups.",
        'auto-payment-hint' => "Here is how it works: Every time your account runs low, we will charge your preferred payment method for an amount you choose."
            . " You can cancel or change the auto-payment option at any time.",
        'auto-payment-setup' => "Set up auto-payment",
        'auto-payment-disabled' => "The configured auto-payment has been disabled. Top up your wallet or raise the auto-payment amount.",
        'auto-payment-info' => "Auto-payment is <b>set</b> to fill up your account by <b>{amount}</b> every time your account balance gets under <b>{balance}</b>.",
        'auto-payment-inprogress' => "The setup of the automatic payment is still in progress.",
        'auto-payment-next' => "Next, you will be redirected to the checkout page, where you can provide your credit card details.",
        'auto-payment-disabled-next' => "The auto-payment is disabled. Immediately after you submit new settings we'll enable it and attempt to top up your wallet.",
        'auto-payment-update' => "Update auto-payment",
        'banktransfer-hint' => "Please note that a bank transfer can take several days to complete.",
        'currency-conv' => "Here is how it works: You specify the amount by which you want to top up your wallet in {wc}."
            . " We will then convert this to {pc}, and on the next page you will be provided with the bank-details to transfer the amount in {pc}.",
        'fill-up' => "Fill up by",
        'history' => "History",
        'month' => "month",
        'noperm' => "Only account owners can access a wallet.",
        'payment-amount-hint' => "Choose the amount by which you want to top up your wallet.",
        'payment-method' => "Method of payment: {method}",
        'payment-warning' => "You will be charged for {price}.",
        'pending-payments' => "Pending Payments",
        'pending-payments-warning' => "You have payments that are still in progress. See the \"Pending Payments\" tab below.",
        'pending-payments-none' => "There are no pending payments for this account.",
        'receipts' => "Receipts",
        'receipts-hint' => "Here you can download receipts (in PDF format) for payments in specified period. Select the period and press the Download button.",
        'receipts-none' => "There are no receipts for payments in this account. Please, note that you can download receipts after the month ends.",
        'title' => "Account balance",
        'top-up' => "Top up your wallet",
        'transactions' => "Transactions",
        'transactions-none' => "There are no transactions for this account.",
        'when-below' => "when account balance is below",
    ],
];
