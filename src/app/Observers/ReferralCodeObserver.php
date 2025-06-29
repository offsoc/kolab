<?php

namespace App\Observers;

use App\ReferralCode;

class ReferralCodeObserver
{
    /**
     * Handle the "creating" event.
     *
     * Ensure that the code entry is created with a random code.
     *
     * @param ReferralCode $code the code being created
     */
    public function creating(ReferralCode $code): void
    {
        if (empty($code->code)) {
            while (true) {
                $code->code = ReferralCode::generateCode();
                if (!ReferralCode::find($code->code)) {
                    break;
                }
            }
        }
    }
}
