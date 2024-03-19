<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

class WellKnownController extends Controller
{
    /**
     * Return the mtaSts policy
     *
     * @return \Illuminate\Http\Response The response
     */
    public function mtaSts()
    {
        $policy = \config('app.mta_sts');

        if (!$policy) {
            $domain = \config('app.domain');
            $policy = <<<EOF
            version: STSv1
            mode: enforce
            mx: $domain
            max_age: 604800
            EOF;
        }

        return response($policy, 200)->header('Content-Type', 'text/plain');
    }
}
