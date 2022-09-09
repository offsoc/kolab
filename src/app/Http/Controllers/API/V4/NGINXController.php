<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class NGINXController extends Controller
{
    /**
     * Authorize with the provided credentials.
     *
     * @param string $login    The login name
     * @param string $password The password
     * @param string $clientIP The client ip
     *
     * @return \App\User The user
     *
     * @throws \Exception If the authorization fails.
     */
    private function authorizeRequest($login, $password, $clientIP)
    {
        if (empty($login)) {
            throw new \Exception("Empty login");
        }

        if (empty($password)) {
            throw new \Exception("Empty password");
        }

        if (empty($clientIP)) {
            throw new \Exception("No client ip");
        }

        $result = \App\User::findAndAuthenticate($login, $password, $clientIP);

        if (empty($result['user'])) {
            throw new \Exception($result['errorMessage'] ?? "Unknown error");
        }

        // TODO: validate the user's domain is A-OK (active, confirmed, not suspended, ldapready)
        // TODO: validate the user is A-OK (active, not suspended, ldapready, imapready)
        // TODO: Apply some sort of limit for Auth-Login-Attempt -- docs say it is the number of
        //       attempts over the same authAttempt.

        return $result['user'];
    }


    /**
     * Convert domain.tld\username into username@domain for activesync
     *
     * @param string $username The original username.
     *
     * @return string The username in canonical form
     */
    private function normalizeUsername($username)
    {
        $usernameParts = explode("\\", $username);
        if (count($usernameParts) == 2) {
            $username = $usernameParts[1];
            if (!strpos($username, '@') && !empty($usernameParts[0])) {
                $username .= '@' . $usernameParts[0];
            }
        }
        return $username;
    }


    /**
     * Authentication request from the ngx_http_auth_request_module
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\Response The response
     */
    public function httpauth(Request $request)
    {
        /**
            Php-Auth-Pw:               simple123
            Php-Auth-User:             john@kolab.org
            Sec-Fetch-Dest:            document
            Sec-Fetch-Mode:            navigate
            Sec-Fetch-Site:            cross-site
            Sec-Gpc:                   1
            Upgrade-Insecure-Requests: 1
            User-Agent:                Mozilla/5.0 (X11; Fedora; Linux x86_64; rv:93.0) Gecko/20100101 Firefox/93.0
            X-Forwarded-For:           31.10.153.58
            X-Forwarded-Proto:         https
            X-Original-Uri:            /iRony/
            X-Real-Ip:                 31.10.153.58
         */

        $username = $this->normalizeUsername($request->headers->get('Php-Auth-User', ""));
        $password = $request->headers->get('Php-Auth-Pw', null);

        if (empty($username)) {
            //Allow unauthenticated requests
            return response("");
        }

        if (empty($password)) {
            \Log::debug("Authentication attempt failed: Empty password provided.");
            return response("", 401);
        }

        try {
            $this->authorizeRequest(
                $username,
                $password,
                $request->headers->get('X-Real-Ip', null),
            );
        } catch (\Exception $e) {
            \Log::debug("Authentication attempt failed: {$e->getMessage()}");
            return response("", 403);
        }

        \Log::debug("Authentication attempt succeeded");
        return response("");
    }


    /**
     * Authentication request.
     *
     * @todo: Separate IMAP(+STARTTLS) from IMAPS, same for SMTP/submission. =>
     *   I suppose that's not necessary given that we have the information avialable in the headers?
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\Response The response
     */
    public function authenticate(Request $request)
    {
        /**
         *  Auth-Login-Attempt: 1
         *  Auth-Method:        plain
         *  Auth-Pass:          simple123
         *  Auth-Protocol:      imap
         *  Auth-Ssl:           on
         *  Auth-User:          john@kolab.org
         *  Client-Ip:          127.0.0.1
         *  Host:               127.0.0.1
         *
         *  Auth-SSL: on
         *  Auth-SSL-Verify: SUCCESS
         *  Auth-SSL-Subject: /CN=example.com
         *  Auth-SSL-Issuer: /CN=example.com
         *  Auth-SSL-Serial: C07AD56B846B5BFF
         *  Auth-SSL-Fingerprint: 29d6a80a123d13355ed16b4b04605e29cb55a5ad
         */

        $password = $request->headers->get('Auth-Pass', null);
        $username = $request->headers->get('Auth-User', null);
        $ip = $request->headers->get('Client-Ip', null);

        try {
            $user = $this->authorizeRequest(
                $username,
                $password,
                $ip,
            );
        } catch (\Exception $e) {
            return $this->byebye($request, $e->getMessage());
        }

        // All checks passed
        switch ($request->headers->get('Auth-Protocol')) {
            case "imap":
                return $this->authenticateIMAP($request, (bool) $user->getSetting('guam_enabled'), $password);
            case "smtp":
                return $this->authenticateSMTP($request, $password);
            default:
                return $this->byebye($request, "unknown protocol in request");
        }
    }

    /**
     * Authentication request for roundcube imap.
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\Response The response
     */
    public function authenticateRoundcube(Request $request)
    {
        /**
         *  Auth-Login-Attempt: 1
         *  Auth-Method:        plain
         *  Auth-Pass:          simple123
         *  Auth-Protocol:      imap
         *  Auth-Ssl:           on
         *  Auth-User:          john@kolab.org
         *  Client-Ip:          127.0.0.1
         *  Host:               127.0.0.1
         *
         *  Auth-SSL: on
         *  Auth-SSL-Verify: SUCCESS
         *  Auth-SSL-Subject: /CN=example.com
         *  Auth-SSL-Issuer: /CN=example.com
         *  Auth-SSL-Serial: C07AD56B846B5BFF
         *  Auth-SSL-Fingerprint: 29d6a80a123d13355ed16b4b04605e29cb55a5ad
         */

        $password = $request->headers->get('Auth-Pass', null);
        $username = $request->headers->get('Auth-User', null);
        $ip = $request->headers->get('Proxy-Protocol-Addr', null);

        try {
            $user = $this->authorizeRequest(
                $username,
                $password,
                $ip,
            );
        } catch (\Exception $e) {
            return $this->byebye($request, $e->getMessage());
        }

        // All checks passed
        switch ($request->headers->get('Auth-Protocol')) {
            case "imap":
                return $this->authenticateIMAP($request, false, $password);
            default:
                return $this->byebye($request, "unknown protocol in request");
        }
    }


    /**
    * Create an imap authentication response.
    *
    * @param \Illuminate\Http\Request $request The API request.
    * @param bool   $prefGuam Whether or not Guam is enabled.
    * @param string $password The password to include in the response.
    *
    * @return \Illuminate\Http\Response The response
    */
    private function authenticateIMAP(Request $request, $prefGuam, $password)
    {
        if ($prefGuam) {
            $port = \config('imap.guam_port');
        } else {
            $port = \config('imap.imap_port');
        }

        $response = response("")->withHeaders(
            [
                "Auth-Status" => "OK",
                "Auth-Server" => \config('imap.host'),
                "Auth-Port" => $port,
                "Auth-Pass" => $password
            ]
        );

        return $response;
    }

    /**
    * Create an smtp authentication response.
    *
    * @param \Illuminate\Http\Request $request The API request.
    * @param string $password The password to include in the response.
    *
    * @return \Illuminate\Http\Response The response
    */
    private function authenticateSMTP(Request $request, $password)
    {
        $response = response("")->withHeaders(
            [
                "Auth-Status" => "OK",
                "Auth-Server" => \config('smtp.host'),
                "Auth-Port" => \config('smtp.port'),
                "Auth-Pass" => $password
            ]
        );

        return $response;
    }

    /**
    * Create a failed-authentication response.
    *
    * @param \Illuminate\Http\Request $request The API request.
    * @param string $reason The reason for the failure.
    *
    * @return \Illuminate\Http\Response The response
    */
    private function byebye(Request $request, $reason = null)
    {
        \Log::debug("Byebye: {$reason}");
        $response = response("")->withHeaders(
            [
                "Auth-Status" => "authentication failure",
                "Auth-Wait" => 3
            ]
        );

        return $response;
    }
}
