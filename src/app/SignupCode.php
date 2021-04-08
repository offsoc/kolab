<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The eloquent definition of a SignupCode.
 *
 * @property string         $code        The full code identifier
 * @property \Carbon\Carbon $created_at  The creation timestamp
 * @property \Carbon\Carbon $deleted_at  The deletion timestamp
 * @property string         $domain_part Email domain
 * @property string         $email       Email address
 * @property \Carbon\Carbon $expires_at  The code expiration timestamp
 * @property string         $first_name  Firstname
 * @property string         $ip_address  IP address the request came from
 * @property string         $last_name   Lastname
 * @property string         $local_part  Email local part
 * @property string         $plan        Plan title
 * @property string         $short_code  Short validation code
 * @property \Carbon\Carbon $updated_at  The update timestamp
 * @property string         $voucher     Voucher discount code
 */
class SignupCode extends Model
{
    use SoftDeletes;

    public const SHORTCODE_LENGTH  = 5;
    public const CODE_LENGTH       = 32;

    // Code expires after so many hours
    public const CODE_EXP_HOURS    = 24;


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
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'email',
        'expires_at',
        'first_name',
        'last_name',
        'plan',
        'short_code',
        'voucher'
    ];

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
        // @phpstan-ignore-next-line
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

        return \App\Utils::randStr($code_length);
    }
}
