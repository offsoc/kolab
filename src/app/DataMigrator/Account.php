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

    /** @var string Hostname */
    public $host;

    /** @var string Connection scheme (service type) */
    public $scheme;

    /** @var string Full account location URI (w/o parameters) */
    public $uri;

    /** @var string Username for proxy auth */
    public $loginas;

    /** @var array Additional parameters from the input */
    public $params;

    /** @var string Full account definition */
    protected $input;


    /**
     * Object constructor
     *
     * Input can be a valid URI or "<username>:<password>".
     * For proxy authentication use: "<proxy-user>**<username>" as username.
     *
     * @param string $input Account specification
     */
    public function __construct(string $input)
    {
        $url = parse_url($input);

        // Not valid URI, try the other form of input
        if ($url === false || !array_key_exists('scheme', $url)) {
            list($user, $password) = explode(':', $input, 2);
            $url = ['user' => $user, 'pass' => $password];
        }

        if (isset($url['user'])) {
            $this->username = urldecode($url['user']);

            if (strpos($this->username, '**')) {
                list($this->username, $this->loginas) = explode('**', $this->username, 2);
            }
        }

        if (isset($url['pass'])) {
            $this->password = urldecode($url['pass']);
        }

        if (isset($url['scheme'])) {
            $this->scheme = strtolower($url['scheme']);
        }

        if (isset($url['host'])) {
            $this->host = $url['host'];
            $this->uri = $this->scheme . '://' . $url['host'] . ($url['path'] ?? '');
        }

        if (!empty($url['query'])) {
            parse_str($url['query'], $this->params);
        }

        if (strpos($this->loginas, '@')) {
            $this->email = $this->loginas;
        } elseif (strpos($this->username, '@')) {
            $this->email = $this->username;
        }

        $this->input = $input;
    }

    /**
     * Returns string representation of the object.
     * You can use the result as an input to the object constructor.
     *
     * @return string Account string representation
     */
    public function __toString(): string
    {
        return $this->input;
    }
}
