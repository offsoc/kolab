<?php

namespace App;

use App\SignupCode;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a VerificationCode
 *
 * @property string    $mode
 * @property \App\User $user
 */
class VerificationCode extends SignupCode
{
    // Code expires after so many hours
    public const CODE_EXP_HOURS = 8;
    public const SHORTCODE_LENGTH = 8;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id', 'code', 'short_code', 'mode', 'expires_at'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * The user to which this setting belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('\App\User', 'user_id', 'id');
    }

    /**
     * Generate a short code (for human).
     *
     * @return string
     */
    public static function generateShortCode(): string
    {
        $code_length = env('VERIFICATION_CODE_LENGTH', self::SHORTCODE_LENGTH);

        return \App\Utils::randStr($code_length);
    }
}
