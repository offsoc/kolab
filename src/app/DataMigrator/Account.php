<?php

namespace App\DataMigrator;

/**
 * Data object representing user account on an external service
 */
class Account
{
    /** @var string User name (login) */
    public $username;

    /** @var string User password */
    public $password;

    /** @var string User email address */
    public $email;

    /** @var string Full account location URI */
    public $uri;

    /** @var string Username for proxy auth */
    public $proxy_authnz;


    /**
     * Object constructor
     */
    public function __construct(string $input)
    {
        // Input can be a valid URL or "<username>:<password>"
        $url = parse_url($input);

        if ($url === false || !array_key_exists('user', $url)) {
            list($user, $password) = explode(':', $input, 2);
            $url = ['user' => $user, 'pass' => $password];
        }

        if (isset($url['user'])) {
            $this->username = $url['user'];
        }

        if (isset($url['pass'])) {
            $this->password = $url['pass'];
        }

        if (isset($url['host'])) {
            $this->location = $input;
        }

        if (strpos($this->username, '@')) {
            $this->email = $this->username;
        }
    }
}
