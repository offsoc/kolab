<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NGINXController extends Controller
{
    /**
     * Authentication request.
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

        /**
         * ports:
         *
         * 143 nginx
         * 465 nginx
         * 587 nginx
         * 993 nginx
         *
         *  9143 guam starttls
         *  9993 guam ssl
         * 10143 cyrus-imapd allows plaintext
         * 10465 postfix ssl
         * 10587 postfix starttls
         * 11143 cyrus-imapd starttls
         * 11993 cyrus-imapd ssl
         */
        switch ($request->headers->get("Auth-Protocol")) {
            case "imap":
                // without guam
                $response = response("")->withHeaders(
                    [
                        "Auth-Status" => 'OK',
                        "Auth-Server" => '127.0.0.1',
                        "Auth-Port" => '12143',
                        "Auth-Pass" => $request->headers->get('Auth-Pass')
                    ]
                );

                // with guam
                $response = response("")->withHeaders(
                    [
                        "Auth-Status" => 'OK',
                        "Auth-Server" => '127.0.0.1',
                        "Auth-Port" => '9143',
                        "Auth-Pass" => $request->headers->get('Auth-Pass')
                    ]
                );

                break;

            case "smtp":
                $response = response("")->withHeaders(
                    [
                        "Auth-Status" => "OK",
                        "Auth-Server" => '127.0.0.1',
                        "Auth-Port" => '10465',
                        "Auth-Pass" => $request->headers->get('Auth-Pass')
                    ]
                );

                break;
        }

        \Log::debug($response->headers);

        return $response;
    }
}
