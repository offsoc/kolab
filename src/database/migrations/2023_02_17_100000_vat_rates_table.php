<?php

use App\VatRate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('vat_rates', static function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('country', 2);
            $table->timestamp('start')->useCurrent();
            $table->double('rate');

            $table->unique(['country', 'start']);
        });

        Schema::table(
            'payments',
            static function (Blueprint $table) {
                $table->string('vat_rate_id', 36)->nullable();
                $table->integer('credit_amount')->nullable(); // temporarily allow null

                $table->foreign('vat_rate_id')->references('id')->on('vat_rates')->onUpdate('cascade');
            }
        );

        DB::table('payments')->update(['credit_amount' => DB::raw("`amount`")]);

        Schema::table(
            'payments',
            static function (Blueprint $table) {
                $table->integer('credit_amount')->nullable(false)->change(); // remove nullable
            }
        );

        // Migrate old tax rates (and existing payments)
        if (($countries = \env('VAT_COUNTRIES')) && ($rate = \env('VAT_RATE'))) {
            $countries = explode(',', strtoupper(trim($countries)));

            foreach ($countries as $country) {
                $vatRate = VatRate::create([
                    'start' => new DateTime('2010-01-01 00:00:00'),
                    'rate' => $rate,
                    'country' => $country,
                ]);

                DB::table('payments')->whereIn('wallet_id', static function ($query) use ($country) {
                    $query->select('id')
                        ->from('wallets')
                        ->whereIn('user_id', static function ($query) use ($country) {
                            $query->select('user_id')
                                ->from('user_settings')
                                ->where('key', 'country')
                                ->where('value', $country);
                        });
                })
                    ->update(['vat_rate_id' => $vatRate->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'payments',
            static function (Blueprint $table) {
                $table->dropForeign(['vat_rate_id']);
                $table->dropColumn('vat_rate_id');
                $table->dropColumn('credit_amount');
            }
        );

        Schema::dropIfExists('vat_rates');
    }
};
