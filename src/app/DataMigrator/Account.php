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
    public $loginas;


    /**
     * Object constructor
     *
     * Input can be a valid URI or "<username>:<password>".
     * For proxy authentication use: "<loginas>^<username>:<password>"
     * or "https://service-admin@password:hostname.domain.tld?loginas=user"
     *
     * @param string $input Account specification
     */
    public function __construct(string $input)
    {
        $url = parse_url($input);

        // Not valid URI, try the other form of input
        if ($url === false || !array_key_exists('user', $url)) {
            list($user, $password) = explode(':', $input, 2);
            $url = ['user' => $user, 'pass' => $password];

            if (strpos($user, '^')) {
                list($loginas, $url['user']) = explode('^', $user, 2);
                $url['query'] = 'loginas=' . urlencode($loginas);
            }
        }

        if (isset($url['user'])) {
            $this->username = urldecode($url['user']);
        }

        if (isset($url['pass'])) {
            $this->password = urldecode($url['pass']);
        }

        if (isset($url['host'])) {
            $this->uri = preg_replace('/\?.*$/', '', $input);
        }

        if (isset($url['query'])) {
            parse_str($url['query'], $params);
            if (isset($params['loginas'])) {
                $this->loginas = urldecode($params['loginas']);
            }
        }

        if (strpos($this->loginas, '@')) {
            $this->email = $this->loginas;
        } elseif (strpos($this->username, '@')) {
            $this->email = $this->username;
        }
    }
}
