<?php

    // Password Plugin options
    // -----------------------
    // A driver to use for password change. Default: "sql".
    // See README file for list of supported driver names.
    //FIXME configure to update the password via sasl? Or just remove
    $config['password_driver'] = 'ldap_simple';

    // Determine whether current password is required to change password.
    // Default: false.
    $config['password_confirm_current'] = true;

    // Require the new password to be a certain length.
    // set to blank to allow passwords of any length
    $config['password_minimum_length'] = 6;

    // Require the new password to contain a letter and punctuation character
    // Change to false to remove this check.
    $config['password_require_nonalpha'] = false;

    // Enables logging of password changes into logs/password
    $config['password_log'] = true;

    if (file_exists(RCUBE_CONFIG_DIR . '/' . $_SERVER["HTTP_HOST"] . '/' . basename(__FILE__))) {
        include_once(RCUBE_CONFIG_DIR . '/' . $_SERVER["HTTP_HOST"] . '/' . basename(__FILE__));
    }

?>
