<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

class ConfirmController extends Controller
{
    public function confirm($code)
    {
        // signal the other request waiting somehow

        \Log::debug("confirm on {$code}");

        $confirmCode = \App\SignupCode::where('short_code', $code)->first();

        if ($confirmCode) {
            $confirmCode->delete();
        }

        return response("", 200);
    }
}
