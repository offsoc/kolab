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

    /** @var ?int Port number */
    public $port;

    /** @var string Full account definition */
    protected $input;


    /**
     * Object constructor
     *
     * Input can be a valid URI or "<username>:<password>".
     * For user impersonation use: ?user=<user> in the query part of the URI.
     *
     * @param string $input Account specification (URI)
     */
    public function __construct(string $input)
    {
        if (!preg_match('|^[a-z]+://.*|', $input)) {
            throw new \Exception("Invalid URI specified");
        }

        $url = parse_url($input);

        // Not valid URI
        if (!is_array($url) || empty($url)) {
            throw new \Exception("Invalid URI specified");
        }

        if (isset($url['user'])) {
            $this->username = urldecode($url['user']);
        }

        if (isset($url['pass'])) {
            $this->password = urldecode($url['pass']);
        }

        if (isset($url['scheme'])) {
            $this->scheme = strtolower($url['scheme']);
        }

        if (isset($url['port'])) {
            $this->port = $url['port'];
        }

        if (isset($url['host'])) {
            $this->host = $url['host'];
            $this->uri = $this->scheme . '://' . $url['host']
                . ($this->port ? ":{$this->port}" : null)
                . ($url['path'] ?? '');
        }

        if (!empty($url['query'])) {
            parse_str($url['query'], $this->params);
        }

        if (!empty($this->params['user'])) {
            $this->loginas = $this->params['user'];
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
