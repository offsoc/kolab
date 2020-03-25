<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class NGINXController extends Controller
{
    /**
     * Authentication request.
     *
     * @todo: Separate IMAP(+STARTTLS) from IMAPS, same for SMTP/submission.
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
        \Log::debug($request->headers);

        // TODO: Apply some sort of limit for Auth-Login-Attempt -- docs say it is the number of
        // attempts over the same connection.

        switch ($request->headers->get('Auth-Protocol')) {
            case "imap":
                return $this->authenticateIMAP($request);
            case "smtp":
                return $this->authenticateSMTP($request);
            default:
                return $this->byebye($request);
        }
    }

    private function authenticateIMAP(Request $request)
    {
        $login = $request->headers->get('Auth-User', null);

        if (empty($login)) {
            return $this->byebye($request, __LINE__);
        }

        // validate user exists, otherwise bye bye
        $user = \App\User::where('email', $login)->first();

        if (!$user) {
            return $this->byebye($request, __LINE__);
        }

        // validate password, otherwise bye bye
        $password = $request->headers->get('Auth-Pass', null);

        if (empty($password)) {
            return $this->byebye($request, __LINE__);
        }

        $result = Hash::check($password, $user->password);

        if (!$result) {
            // TODO: Log, notify user.
            return $this->byebye($request, __LINE__);
        }

        // validate country of origin against restrictions, otherwise bye bye
        $clientIP = $request->headers->get('Client-Ip', null);

        $countryCodes = json_decode($user->getSetting('limit_geo', "[]"));

        \Log::debug("Countries for {$user->email}: " . var_export($countryCodes, true));

        // TODO: Consider "new geographical area notification".

        if (!empty($countryCodes)) {
            // fake the country is NL, and the limitation is CH
            if ($clientIP == '127.0.0.1' && $login == "piet@kolab.org") {
                $country = "NL";
            } else {
                // TODO: GeoIP reliance
                $country = "CH";
            }

            if (!in_array($country, $countryCodes)) {
                // TODO: Log, notify user.
                return $this->byebye($request, __LINE__);
            }
        }

        // determine 2fa preference
        $pref2fa = $user->getSetting('2fa_plz', false);

        if ($pref2fa) {
            $result = $this->waitFor2fa($request);

            if (!$result) {
                return $this->byebye($request, __LINE__);
            }
        }

        $prefGuam = $user->getSetting('guam_plz', false);

        if ($prefGuam) {
            $port = 9143;
        } else {
            $port = 10143;
        }

        $response = response("")->withHeaders(
            [
                "Auth-Status" => "OK",
                "Auth-Server" => "127.0.0.1",
                "Auth-Port" => $port,
                "Auth-Pass" => $password
            ]
        );

        \Log::debug("Response with headers:\n{$response->headers}");

        return $response;
    }

    private function authenticateSMTP(Request $request)
    {
        $login = $request->headers->get('Auth-User', null);

        if (empty($login)) {
            return $this->byebye($request, __LINE__);
        }

        // validate user exists, otherwise bye bye
        $user = \App\User::where('email', $login)->first();

        if (!$user) {
            return $this->byebye($request, __LINE__);
        }

        // validate password, otherwise bye bye
        $password = $request->headers->get('Auth-Pass', null);

        if (empty($password)) {
            return $this->byebye($request, __LINE__);
        }

        $result = Hash::check($password, $user->password);

        if (!$result) {
            // TODO: Log, notify user.
            return $this->byebye($request, __LINE__);
        }

        // validate country of origin against restrictions, otherwise bye bye
        $clientIP = $request->headers->get('Client-Ip', null);

        $countryCodes = json_decode($user->getSetting('limit_geo', "[]"));

        \Log::debug("Countries for {$user->email}: " . var_export($countryCodes, true));

        // TODO: Consider "new geographical area notification".

        if (!empty($countryCodes)) {
            // fake the country is NL, and the limitation is CH
            if ($clientIP == '127.0.0.1' && $login == "piet@kolab.org") {
                $country = "NL";
            } else {
                // TODO: GeoIP reliance
                $country = "CH";
            }

            if (!in_array($country, $countryCodes)) {
                // TODO: Log, notify user.
                return $this->byebye($request, __LINE__);
            }
        }

        // determine 2fa preference
        $pref2fa = $user->getSetting('2fa_plz', false);

        if ($pref2fa) {
            $result = $this->waitFor2fa($request);

            if (!$result) {
                return $this->byebye($request, __LINE__);
            }
        }

        $response = response("")->withHeaders(
            [
                "Auth-Status" => "OK",
                "Auth-Server" => "127.0.0.1",
                "Auth-Port" => 10465,
                "Auth-Pass" => $password
            ]
        );

        \Log::debug("Response with headers:\n{$response->headers}");

        return $response;
    }

    private function byebye(Request $request, $code = null)
    {
        $response = response("")->withHeaders(
            [
                // TODO code only for development
                "Auth-Status" => "NO {$code}",
                "Auth-Wait" => 3
            ]
        );

        \Log::debug("Response with headers:\n{$response->headers}");

        return $response;
    }

    private function waitFor2fa(Request $request)
    {
        // TODO: Don't require a confirmation for every single hit.
        //
        // This likely means storing (a hash of) the client IP, a timeout, and a user ID.
        $code = \App\SignupCode::create(
            [
                'data' => [
                    'email' => $request->headers->get('Auth-User')
                ],
                'expires_at' => Carbon::now()->addMinutes(2)
            ]
        );

        \Log::debug("visit http://127.0.0.1:8000/api/confirm/{$code->short_code}");

        $confirmed = false;
        $maxTries = 300;

        do {
            $confirmCode = \App\SignupCode::find($code->code);
            if (!$confirmCode) {
                $confirmed = true;
                break;
            }

            sleep(1);
            $maxTries--;
        } while (!$confirmed && $maxTries > 0);

        return $confirmed;
    }
}
