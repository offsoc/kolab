<?php

namespace App\Console\Commands\Scalpel\Entitlement;

use App\Console\ObjectCreateCommand;
use App\Entitlement;
use App\Sku;
use App\Wallet;

class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Entitlement::class;
    protected $objectName = 'entitlement';
    protected $objectTitle;

    public function handle()
    {
        $this->properties = $this->getProperties();

        $entitleable = call_user_func_array(
            [$this->properties['entitleable_type'], 'find'],
            [$this->properties['entitleable_id']]
        );

        if (!$entitleable) {
            $this->error("No such {$this->properties['entitleable_type']}");
            return 1;
        }

        if (!array_key_exists('entitleable_id', $this->properties)) {
            $this->error("Specify --entitleable_id");
            return 1;
        }

        if (array_key_exists('sku_id', $this->properties)) {
            $sku = Sku::find($this->properties['sku_id']);

            if (!$sku) {
                $this->error("No such SKU {$this->properties['sku_id']}");
                return 1;
            }

            if ($this->properties['cost'] === null) {
                $this->properties['cost'] = $sku->cost;
            }
        }

        if (array_key_exists('wallet_id', $this->properties)) {
            $wallet = Wallet::find($this->properties['wallet_id']);

            if (!$wallet) {
                $this->error("No such wallet {$this->properties['wallet_id']}");
                return 1;
            }
        }

        parent::handle();
    }
}
