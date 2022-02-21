<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a VerificationCode
 *
 * @property bool           $active      Active status
 * @property string         $code        The code
 * @property \Carbon\Carbon $expires_at  Expiration date-time
 * @property string         $mode        Mode, e.g. password-reset
 * @property \App\User      $user        User object
 * @property int            $user_id     User identifier
 * @property string         $short_code  Short code
 */
class VerificationCode extends Model
{
    // Code expires after so many hours
    public const SHORTCODE_LENGTH = 8;

    public const CODE_LENGTH = 32;

    // Code expires after so many hours
    public const CODE_EXP_HOURS = 8;

    /** @var string The primary key associated with the table */
    protected $primaryKey = 'code';

    /** @var bool Indicates if the IDs are auto-incrementing */
    public $incrementing = false;

    /** @var string The "type" of the auto-incrementing ID */
    protected $keyType = 'string';

    /** @var bool Indicates if the model should be timestamped */
    public $timestamps = false;

    /** @var array<string, string> Casts properties as type */
    protected $casts = [
        'active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = ['user_id', 'code', 'short_code', 'mode', 'expires_at', 'active'];


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
     * The user to which this code belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('\App\User', 'user_id', 'id');
    }
}
