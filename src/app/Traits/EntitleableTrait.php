<?php

namespace App\Traits;

use App\Entitlement;
use App\Sku;
use App\Wallet;
use Illuminate\Support\Str;

trait EntitleableTrait
{
    /**
     * Assign a package to an entitleable object. It should not have any existing entitlements.
     *
     * @param \App\Package $package The package
     * @param \App\Wallet  $wallet  The wallet
     *
     * @return $this
     */
    public function assignPackageAndWallet(\App\Package $package, Wallet $wallet)
    {
        // TODO: There should be some sanity checks here. E.g. not package can be
        // assigned to any entitleable, but we don't really have package types.

        foreach ($package->skus as $sku) {
            for ($i = $sku->pivot->qty; $i > 0; $i--) {
                Entitlement::create([
                        'wallet_id' => $wallet->id,
                        'sku_id' => $sku->id,
                        'cost' => $sku->pivot->cost(),
                        'fee' => $sku->pivot->fee(),
                        'entitleable_id' => $this->id,
                        'entitleable_type' => self::class
                ]);
            }
        }

        return $this;
    }

    /**
     * Assign a Sku to an entitleable object.
     *
     * @param \App\Sku $sku   The sku to assign.
     * @param int      $count Count of entitlements to add
     *
     * @return $this
     * @throws \Exception
     */
    public function assignSku(Sku $sku, int $count = 1)
    {
        // TODO: I guess wallet could be parametrized in future
        $wallet = $this->wallet();
        $exists = $this->entitlements()->where('sku_id', $sku->id)->count();

        // TODO: Make sure the SKU can be assigned to the object

        while ($count > 0) {
            Entitlement::create([
                'wallet_id' => $wallet->id,
                'sku_id' => $sku->id,
                'cost' => $exists >= $sku->units_free ? $sku->cost : 0,
                'fee' => $exists >= $sku->units_free ? $sku->fee : 0,
                'entitleable_id' => $this->id,
                'entitleable_type' => self::class
            ]);

            $exists++;
            $count--;
        }

        return $this;
    }

    /**
     * Assign the object to a wallet.
     *
     * @param \App\Wallet $wallet The wallet
     *
     * @return $this
     * @throws \Exception
     */
    public function assignToWallet(Wallet $wallet)
    {
        if (empty($this->id)) {
            throw new \Exception("Object not yet exists");
        }

        if ($this->entitlements()->count()) {
            throw new \Exception("Object already assigned to a wallet");
        }

        // Find the SKU title, e.g. \App\SharedFolder -> shared-folder
        // Note: it does not work with User/Domain model (yet)
        $title = Str::kebab(\class_basename(self::class));

        $sku = Sku::withObjectTenantContext($this)->where('title', $title)->first();
        $exists = $wallet->entitlements()->where('sku_id', $sku->id)->count();

        Entitlement::create([
            'wallet_id' => $wallet->id,
            'sku_id' => $sku->id,
            'cost' => $exists >= $sku->units_free ? $sku->cost : 0,
            'fee' => $exists >= $sku->units_free ? $sku->fee : 0,
            'entitleable_id' => $this->id,
            'entitleable_type' => self::class
        ]);

        return $this;
    }

    /**
     * Boot function from Laravel.
     */
    protected static function bootEntitleableTrait()
    {
        // Soft-delete and force-delete object's entitlements on object's delete
        static::deleting(function ($model) {
            $force = $model->isForceDeleting();
            $entitlements = $model->entitlements();

            if ($force) {
                $entitlements = $entitlements->withTrashed();
            }

            $list = $entitlements->get()
                ->map(function ($entitlement) use ($force) {
                    if ($force) {
                        $entitlement->forceDelete();
                    } else {
                        $entitlement->delete();
                    }
                    return $entitlement->id;
                })
                ->all();

            // Remove transactions, they have no foreign key constraint
            if ($force && !empty($list)) {
                \App\Transaction::where('object_type', \App\Entitlement::class)
                    ->whereIn('object_id', $list)
                    ->delete();
            }
        });

        // Restore object's entitlements on restore
        static::restored(function ($model) {
            $model->restoreEntitlements();
        });
    }

    /**
     * Entitlements for this object.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function entitlements()
    {
        return $this->hasMany(Entitlement::class, 'entitleable_id', 'id')
            ->where('entitleable_type', self::class);
    }

    /**
     * Check if an entitlement for the specified SKU exists.
     *
     * @param string $title The SKU title
     *
     * @return bool True if specified SKU entitlement exists
     */
    public function hasSku(string $title): bool
    {
        $sku = Sku::withObjectTenantContext($this)->where('title', $title)->first();

        if (!$sku) {
            return false;
        }

        return $this->entitlements()->where('sku_id', $sku->id)->count() > 0;
    }

    /**
     * Remove a number of entitlements for the SKU.
     *
     * @param \App\Sku $sku   The SKU
     * @param int      $count The number of entitlements to remove
     *
     * @return $this
     */
    public function removeSku(Sku $sku, int $count = 1)
    {
        $entitlements = $this->entitlements()
            ->where('sku_id', $sku->id)
            ->orderBy('cost', 'desc')
            ->orderBy('created_at')
            ->get();

        $entitlements_count = count($entitlements);

        foreach ($entitlements as $entitlement) {
            if ($entitlements_count <= $sku->units_free) {
                continue;
            }

            if ($count > 0) {
                $entitlement->delete();
                $entitlements_count--;
                $count--;
            }
        }

        return $this;
    }

    /**
     * Restore object entitlements.
     */
    public function restoreEntitlements(): void
    {
        // We'll restore only these that were deleted last. So, first we get
        // the maximum deleted_at timestamp and then use it to select
        // entitlements for restore
        $deleted_at = $this->entitlements()->withTrashed()->max('deleted_at');

        if ($deleted_at) {
            $threshold = (new \Carbon\Carbon($deleted_at))->subMinute();

            // Restore object entitlements
            $this->entitlements()->withTrashed()
                ->where('deleted_at', '>=', $threshold)
                ->update(['updated_at' => now(), 'deleted_at' => null]);

            // Note: We're assuming that cost of entitlements was correct
            // on deletion, so we don't have to re-calculate it again.
            // TODO: We should probably re-calculate the cost
        }
    }

    /**
     * Returns the wallet by which the object is controlled
     *
     * @return ?\App\Wallet A wallet object
     */
    public function wallet(): ?Wallet
    {
        $entitlement = $this->entitlements()->withTrashed()->orderBy('created_at', 'desc')->first();

        if ($entitlement) {
            return $entitlement->wallet;
        }

        // TODO: No entitlement should not happen, but in tests we have
        //       such cases, so we fallback to the user's wallet in this case
        if ($this instanceof \App\User) {
            return $this->wallets()->first();
        }

        return null;
    }
}
