<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a VerificationCode
 *
 * @property string    $code
 * @property string    $mode
 * @property \App\User $user
 * @property int       $user_id
 * @property string    $short_code
 */
class VerificationCode extends Model
{
    // Code expires after so many hours
    public const SHORTCODE_LENGTH = 8;

    public const CODE_LENGTH = 32;

    // Code expires after so many hours
    public const CODE_EXP_HOURS = 8;

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
    protected $fillable = ['user_id', 'code', 'short_code', 'mode', 'expires_at'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['expires_at'];


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
     * The user to which this setting belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('\App\User', 'user_id', 'id');
    }
}
