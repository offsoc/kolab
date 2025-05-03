<?php

namespace App;

use App\Traits\BelongsToTenantTrait;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * The eloquent definition of a ReferralProgram.
 *
 * @property int       $award_amount       Award amount (in cents) - to apply to the referrer's wallet
 * @property int       $award_percent      Award percent - to apply to the referrer's wallet
 * @property bool      $active             Program state
 * @property string    $description        Program description
 * @property ?string   $discount_id        Discount identifier - to apply to the created account
 * @property int       $id                 Program identifier
 * @property string    $name               Program name
 * @property int       $payments_threshold Sum of payments (in cents) at which the award is applied
 * @property ?int      $tenant_id          Tenant identifier
 */
class ReferralProgram extends Model
{
    use BelongsToTenantTrait;
    use HasTranslations;

    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = [
        'award_amount',
        'award_percent',
        'active',
        'description',
        'discount_id',
        'name',
        'payments_threshold',
        'tenant_id',
    ];

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'active' => 'boolean',
        'award_amount' => 'integer',
        'award_percent' => 'integer',
        'payments_threshold' => 'integer',
    ];

    /** @var array<int, string> Translatable properties */
    public $translatable = [
        'name',
        'description',
    ];

    /**
     * Check wallet state and award the referrer if applicable.
     *
     * @param User $user A wallet owner (to who's any wallet a payment has been made)
     */
    public static function accounting(User $user): void
    {
        $referral = Referral::where('user_id', $user->id)->first();

        // Note: For now we bail out if redeemed_at is set, but it may change
        // in the future. We can use this timestamp to store time of the previous
        // award operation, but it does not have to be the last one.
        if (!$referral || $referral->redeemed_at) {
            return;
        }

        $code = $referral->code()->first();
        $program = $code->program;
        $owner = $code->owner;

        if (!$owner) {
            // The code owner is soft-deleted, "close" the referral
            $referral->redeemed_at = $referral->created_at;
            $referral->save();
            return;
        }

        // For now we support only one mode where award_amount is applied once after reaching payments_threshold
        // TODO: award_percent handling and/or other future modes

        if ($program->payments_threshold > 0) {
            $payments_amount = Payment::whereIn('wallet_id', $user->wallets()->select('id'))
                ->where('status', Payment::STATUS_PAID)
                ->sum('credit_amount');

            if ($payments_amount < $program->payments_threshold) {
                return;
            }
        }

        $referrer_wallet = $owner->wallets()->first();

        // Note: We could insert the referree email in the description, but I think we should not
        $description = 'Referral program award (' . $program->name . ')';

        $referrer_wallet->award($program->award_amount, $description);

        $referral->redeemed_at = \now();
        $referral->save();
    }

    /**
     * The referral codes that use this program.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ReferralCode, $this>
     */
    public function codes()
    {
        return $this->hasMany(ReferralCode::class, 'program_id');
    }

    /**
     * The discount assigned to the program.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Discount, $this>
     */
    public function discount()
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }
}
