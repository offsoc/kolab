<?php

namespace App;

use App\SignupCode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a VerificationCode
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
     * @return \App\User
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
        $code_chars  = env('VERIFICATION_CODE_CHARS', self::SHORTCODE_CHARS);
        $random      = [];

        for ($i = 1; $i <= $code_length; $i++) {
            $random[] = $code_chars[rand(0, strlen($code_chars) - 1)];
        }

        shuffle($random);

        return implode('', $random);
    }
}
