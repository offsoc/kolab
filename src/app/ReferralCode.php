<?php

namespace App;

use BaconQrCode;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a ReferralCode.
 *
 * @property string  $code        Referral code
 * @property int     $program_id  Referral program identifier
 * @property int     $user_id     User identifier
 */
class ReferralCode extends Model
{
    public const CODE_LENGTH = 8;

    /** @var bool Indicates if the IDs are auto-incrementing */
    public $incrementing = false;

    /** @var string The primary key associated with the table */
    protected $primaryKey = 'code';

    /** @var string The "type" of the auto-incrementing ID */
    protected $keyType = 'string';

    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = [
        // 'code',
        'program_id',
        'user_id',
    ];

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    /** @var bool Indicates if the model should be timestamped. */
    public $timestamps = false;


    /**
     * Generate a random code.
     *
     * @return string
     */
    public static function generateCode(): string
    {
        $code_length = env('REFERRAL_CODE_LENGTH', self::CODE_LENGTH);

        return \App\Utils::randStr($code_length);
    }

    /**
     * The referral code owner (user)
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The referral program
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<ReferralProgram, $this>
     */
    public function program()
    {
        return $this->belongsTo(ReferralProgram::class, 'program_id');
    }

    /**
     * The referrals using this code.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Referral, $this>
     */
    public function referrals()
    {
        return $this->hasMany(Referral::class, 'code', 'code');
    }

    /**
     * The signup URL as a QR code svg image
     */
    public function qrCode($as_url = false): string
    {
        $renderer_style = new BaconQrCode\Renderer\RendererStyle\RendererStyle(300, 1);
        $renderer_image = new BaconQrCode\Renderer\Image\SvgImageBackEnd();
        $renderer = new BaconQrCode\Renderer\ImageRenderer($renderer_style, $renderer_image);
        $writer = new BaconQrCode\Writer($renderer);
        $svg = $writer->writeString($this->signupUrl());

        if ($as_url) {
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        }

        return $svg;
    }

    /**
     * The signup URL.
     */
    public function signupUrl(): string
    {
        return \App\Utils::serviceUrl("signup/referral/{$this->code}", $this->program->tenant_id);
    }
}
