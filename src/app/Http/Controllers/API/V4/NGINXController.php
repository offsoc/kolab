<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class NGINXController extends Controller
{
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

        \Log::debug("Authentication attempt");
        \Log::debug($request->headers);

        $login = $request->headers->get('Auth-User', null);

        if (empty($login)) {
            return $this->byebye($request, "Empty login");
        }

        // validate password, otherwise bye bye
        $password = $request->headers->get('Auth-Pass', null);

        if (empty($password)) {
            return $this->byebye($request, "Empty password");
        }

        $clientIP = $request->headers->get('Client-Ip', null);

        if (empty($clientIP)) {
            return $this->byebye($request, "No client ip");
        }

        // validate user exists, otherwise bye bye
        $user = \App\User::where('email', $login)->first();

        if (!$user) {
            return $this->byebye($request, "User not found");
        }

        // TODO: validate the user's domain is A-OK (active, confirmed, not suspended, ldapready)
        // TODO: validate the user is A-OK (active, not suspended, ldapready, imapready)

        if (!Hash::check($password, $user->password)) {
            $attempt = \App\AuthAttempt::recordAuthAttempt($user, $clientIP);
            // Avoid setting a password failure reason if we previously accepted the location.
            if (!$attempt->isAccepted()) {
                $attempt->reason = \App\AuthAttempt::REASON_PASSWORD;
                $attempt->save();
                $attempt->notify();
            }
            \Log::info("Failed authentication attempt due to password mismatch for user: {$login}");
            return $this->byebye($request, "Password mismatch");
        }

        // validate country of origin against restrictions, otherwise bye bye
        $countryCodes = json_decode($user->getSetting('limit_geo', "[]"));

        \Log::debug("Countries for {$user->email}: " . var_export($countryCodes, true));

        if (!empty($countryCodes)) {
            $country = \App\Utils::countryForIP($clientIP);
            if (!in_array($country, $countryCodes)) {
                \Log::info(
                    "Failed authentication attempt due to country code mismatch ({$country}) for user: {$login}"
                );
                $attempt = \App\AuthAttempt::recordAuthAttempt($user, $clientIP);
                $attempt->deny(\App\AuthAttempt::REASON_GEOLOCATION);
                $attempt->notify();
                return $this->byebye($request, "Country code mismatch");
            }
        }

        // TODO: Apply some sort of limit for Auth-Login-Attempt -- docs say it is the number of
        // attempts over the same authAttempt.

        // Check 2fa
        if ($user->getSetting('2fa_enabled', false)) {
            $authAttempt = \App\AuthAttempt::recordAuthAttempt($user, $clientIP);
            if (!$authAttempt->waitFor2FA()) {
                return $this->byebye($request, "2fa failed");
            }
        }

        // All checks passed
        switch ($request->headers->get('Auth-Protocol')) {
            case "imap":
                return $this->authenticateIMAP($request, $user->getSetting('guam_enabled', false), $password);
            case "smtp":
                return $this->authenticateSMTP($request, $password);
            default:
                return $this->byebye($request, "unknown protocol in request");
        }
    }

    /**
    * Create an imap authentication response.
    *
    * @param \Illuminate\Http\Request $request The API request.
    * @param bool $prefGuam Wether or not guam is enabled.
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

        \Log::debug("Response with headers:\n{$response->headers}");

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

        \Log::debug("Response with headers:\n{$response->headers}");

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

        \Log::debug("Response with headers:\n{$response->headers}");

        return $response;
    }
}
