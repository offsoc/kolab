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
        'resync' => "Resync",
        'save' => "Save",
        'search' => "Search",
        'share' => "Share",
        'signup' => "Sign Up",
        'submit' => "Submit",
        'subscribe' => "Subscribe",
        'suspend' => "Suspend",
        'tryagain' => "Try again",
        'unsuspend' => "Unsuspend",
        'verify' => "Verify",
    ],

    'companion' => [
        'title' => "Companion Apps",
        'companion' => "Companion App",
        'name' => "Name",
        'create' => "Pair new device",
        'create-recovery-device' => "Prepare recovery code",
        'description' => "Use the Companion App on your mobile phone as multi-factor authentication device.",
        'download-description' => "You may download the Companion App for Android here: "
            . "<a href=\"{href}\">Download</a>",
        'description-detailed' => "Here is how this works: " .
            "Pairing a device will automatically enable multi-factor autentication for all login attempts. " .
            "This includes not only the Cockpit, but also logins via Webmail, IMAP, SMPT, DAV and ActiveSync. " .
            "Any authentication attempt will result in a notification on your device, " .
            "that you can use to confirm if it was you, or deny otherwise. " .
            "Once confirmed, the same username + IP address combination will be whitelisted for 8 hours. " .
            "Unpair all your active devices to disable multi-factor authentication again.",
        'description-warning' => "Warning: Loosing access to all your multi-factor authentication devices, " .
            "will permanently lock you out of your account with no course for recovery. " .
            "Always make sure you have a recovery QR-Code printed to pair a recovery device.",
        'new' => "Pair new device",
        'recovery' => "Prepare recovery device",
        'paired' => "Paired devices",
        'print' => "Print for backup",
        'pairing-instructions' => "Pair your device using the following QR-Code.",
        'recovery-device' => "Recovery Device",
        'new-device' => "New Device",
        'deviceid' => "Device ID",
        'list-empty' => "There are currently no devices",
        'delete' => "Delete/Unpair",
        'delete-companion' => "Delete/Unpair",
        'delete-text' => "You are about to delete this entry and unpair any paired companion app. " .
            "This cannot be undone, but you can pair the device again.",
        'pairing-successful' => "Your companion app is paired and ready to be used " .
            "as a multi-factor authentication device.",
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
        'list-empty' => "There are no domains in this account.",
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
        'companion' => "Companion App",
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
        'geolocation' => "Your current location: {location}",
        'lastname' => "Last Name",
        'name' => "Name",
        'months' => "months",
        'none' => "none",
        'norestrictions' => "No restrictions",
        'or' => "or",
        'password' => "Password",
        'password-confirm' => "Confirm Password",
        'phone' => "Phone",
        'selectcountries' => "Select countries",
        'settings' => "Settings",
        'shared-folder' => "Shared Folder",
        'size' => "Size",
        'status' => "Status",
        'subscriptions' => "Subscriptions",
        'surname' => "Surname",
        'type' => "Type",
        'unknown' => "unknown",
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
        'list-empty' => "There are no invitations in the database.",
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
        'signing_in' => "Signing in...",
        'webmail' => "Webmail"
    ],

    'meet' => [
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

    'room' => [
        'create' => "Create room",
        'delete' => "Delete room",
        'copy-location' => "Copy room location",
        'description-hint' => "This is an optional short description for the room, so you can find it more easily on the list.",
        'goto' => "Enter the room",
        'list-empty' => "There are no conference rooms in this account.",
        'list-empty-nocontroller' => "Do you need a room? Ask your account owner to create one and share it with you.",
        'list-title' => "Voice & video conferencing rooms",
        'moderators' => "Moderators",
        'moderators-text' => "You can share your room with other users. They will become the room moderators with all moderator powers and ability to open the room without your presence.",
        'new' => "New room",
        'new-hint' => "We'll generate a unique name for the room that will then allow you to access the room.",
        'title' => "Room: {name}",
        'url' => "You can access the room at the URL below. Use this URL to invite people to join you. This room is only open when you (or another room moderator) is in attendance.",
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
        'step3' => "Create your {app} identity (you can choose additional addresses later).",
        'created' => "The account is about to be created!",
        'token' => "Signup authorization token",
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
        'restricted' => "Restricted",
        'suspended' => "Suspended",
        'notready' => "Not Ready",
        'active' => "Active",
    ],

    'support' => [
        'title' => "Contact Support",
        'id' => "Customer number or email address you have with us",
        'id-pl' => "e.g. 12345678 or the affected email address",
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
        'ext-email' => "External Email",
        'email-aliases' => "Email Aliases",
        'finances' => "Finances",
        'geolimit' => "Geo-lockin",
        'geolimit-text' => "Defines a list of locations that are allowed for logon. You will not be able to login from a country that is not listed here.",
        'greylisting' => "Greylisting",
        'greylisting-text' => "Greylisting is a method of defending users against spam. Any incoming mail from an unrecognized sender "
            . "is temporarily rejected. The originating server should try again after a delay. "
            . "This time the email will be accepted. Spammers usually do not reattempt mail delivery.",
        'imapproxy' => "IMAP proxy",
        'imapproxy-text' => "Enables IMAP proxy that filters out non-mail groupware folders, so your IMAP clients do not see them.",
        'list-title' => "User accounts",
        'list-empty' => "There are no users in this account.",
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
        'subscriptions-none' => "This user has no subscriptions.",
        'users' => "Users",
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
        'coinbase-hint' => "Here is how it works: You specify the amount by which you want to top up your wallet in {wc}."
            . " We will then create a charge on Coinbase for the specified amount that you can pay using Bitcoin.",
        'currency-conv' => "Here is how it works: You specify the amount by which you want to top up your wallet in {wc}."
            . " We will then convert this to {pc}, and on the next page you will be provided with the bank-details to transfer the amount in {pc}.",
        'fill-up' => "Fill up by",
        'history' => "History",
        'locked-text' => "The account is locked until you set up auto-payment successfully.",
        'month' => "month",
        'noperm' => "Only account owners can access a wallet.",
        'norefund' => "The money in your wallet is non-refundable.",
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
