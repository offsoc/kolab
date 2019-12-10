<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a SignupCode.
 */
class SignupCode extends Model
{
    // Note: Removed '0', 'O', '1', 'I' as problematic with some fonts
    const SHORTCODE_CHARS   = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    const SHORTCODE_LENGTH  = 5;
    const CODE_LENGTH       = 32;

    // Code expires after so many hours
    const CODE_EXP_HOURS    = 24;


    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'code';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['code', 'short_code', 'data', 'expires_at'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['data' => 'array'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['expires_at'];

    /**
     * Check if code is expired.
     *
     * @return bool True if code is expired, False otherwise
     */
    public function isExpired()
    {
        return $this->expires_at ? Carbon::now()->gte($this->expires_at) : false;
    }

    /**
     * Generate a short code (for human).
     *
     * @return string
     */
    public static function generateShortCode(): string
    {
        $code_length = env('SIGNUP_CODE_LENGTH', self::SHORTCODE_LENGTH);
        $code_chars  = env('SIGNUP_CODE_CHARS', self::SHORTCODE_CHARS);
        $random      = [];

        for ($i = 1; $i <= $code_length; $i++) {
            $random[] = $code_chars[rand(0, strlen($code_chars) - 1)];
        }

        shuffle($random);

        return implode('', $random);
    }
}
