<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used in the application.
    */

    'chart-created' => 'Created',
    'chart-deleted' => 'Deleted',
    'chart-average' => 'average',
    'chart-allusers' => 'All Users - last year',
    'chart-discounts' => 'Discounts',
    'chart-vouchers' => 'Vouchers',
    'chart-income' => 'Income in :currency - last 8 weeks',
    'chart-users' => 'Users - last 8 weeks',

    'mandate-delete-success' => 'The auto-payment has been removed.',
    'mandate-update-success' => 'The auto-payment has been updated.',

    'planbutton' => 'Choose :plan',

    'process-async' => 'Setup process has been pushed. Please wait.',
    'process-user-new' => 'Registering a user...',
    'process-user-ldap-ready' => 'Creating a user...',
    'process-user-imap-ready' => 'Creating a mailbox...',
    'process-domain-new' => 'Registering a custom domain...',
    'process-domain-ldap-ready' => 'Creating a custom domain...',
    'process-domain-verified' => 'Verifying a custom domain...',
    'process-domain-confirmed' => 'Verifying an ownership of a custom domain...',
    'process-success' => 'Setup process finished successfully.',
    'process-error-distlist-ldap-ready' => 'Failed to create a distribution list.',
    'process-error-domain-ldap-ready' => 'Failed to create a domain.',
    'process-error-domain-verified' => 'Failed to verify a domain.',
    'process-error-domain-confirmed' => 'Failed to verify an ownership of a domain.',
    'process-error-resource-imap-ready' => 'Failed to verify that a shared folder exists.',
    'process-error-resource-ldap-ready' => 'Failed to create a resource.',
    'process-error-user-ldap-ready' => 'Failed to create a user.',
    'process-error-user-imap-ready' => 'Failed to verify that a mailbox exists.',
    'process-distlist-new' => 'Registering a distribution list...',
    'process-distlist-ldap-ready' => 'Creating a distribution list...',
    'process-resource-new' => 'Registering a resource...',
    'process-resource-imap-ready' => 'Creating a shared folder...',
    'process-resource-ldap-ready' => 'Creating a resource...',

    'distlist-update-success' => 'Distribution list updated successfully.',
    'distlist-create-success' => 'Distribution list created successfully.',
    'distlist-delete-success' => 'Distribution list deleted successfully.',
    'distlist-suspend-success' => 'Distribution list suspended successfully.',
    'distlist-unsuspend-success' => 'Distribution list unsuspended successfully.',
    'distlist-setconfig-success' => 'Distribution list settings updated successfully.',

    'domain-create-success' => 'Domain created successfully.',
    'domain-delete-success' => 'Domain deleted successfully.',
    'domain-notempty-error' => 'Unable to delete a domain with assigned users or other objects.',
    'domain-verify-success' => 'Domain verified successfully.',
    'domain-verify-error' => 'Domain ownership verification failed.',
    'domain-suspend-success' => 'Domain suspended successfully.',
    'domain-unsuspend-success' => 'Domain unsuspended successfully.',
    'domain-setconfig-success' => 'Domain settings updated successfully.',

    'resource-update-success' => 'Resource updated successfully.',
    'resource-create-success' => 'Resource created successfully.',
    'resource-delete-success' => 'Resource deleted successfully.',
    'resource-setconfig-success' => 'Resource settings updated successfully.',

    'user-update-success' => 'User data updated successfully.',
    'user-create-success' => 'User created successfully.',
    'user-delete-success' => 'User deleted successfully.',
    'user-suspend-success' => 'User suspended successfully.',
    'user-unsuspend-success' => 'User unsuspended successfully.',
    'user-reset-2fa-success' => '2-Factor authentication reset successfully.',
    'user-setconfig-success' => 'User settings updated successfully.',
    'user-set-sku-success' => 'The subscription added successfully.',
    'user-set-sku-already-exists' => 'The subscription already exists.',

    'search-foundxdomains' => ':x domains have been found.',
    'search-foundxdistlists' => ':x distribution lists have been found.',
    'search-foundxresources' => ':x resources have been found.',
    'search-foundxusers' => ':x user accounts have been found.',

    'signup-invitations-created' => 'The invitation has been created.|:count invitations has been created.',
    'signup-invitations-csv-empty' => 'Failed to find any valid email addresses in the uploaded file.',
    'signup-invitations-csv-invalid-email' => 'Found an invalid email address (:email) on line :line.',
    'signup-invitation-delete-success' => 'Invitation deleted successfully.',
    'signup-invitation-resend-success' => 'Invitation added to the sending queue successfully.',

    'support-request-success' => 'Support request submitted successfully.',
    'support-request-error' => 'Failed to submit the support request.',

    'siteuser' => ':site User',

    'wallet-award-success' => 'The bonus has been added to the wallet successfully.',
    'wallet-penalty-success' => 'The penalty has been added to the wallet successfully.',
    'wallet-update-success' => 'User wallet updated successfully.',

    'wallet-notice-date' => 'With your current subscriptions your account balance will last until about :date (:days).',
    'wallet-notice-nocredit' => 'You are out of credit, top up your balance now.',
    'wallet-notice-today' => 'You will run out of credit today, top up your balance now.',
    'wallet-notice-trial' => 'You are in your free trial period.',
    'wallet-notice-trial-end' => 'Your free trial is about to end, top up to continue.',
];
