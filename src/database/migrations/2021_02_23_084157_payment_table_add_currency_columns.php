<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// phpcs:ignore
class PaymentTableAddCurrencyColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'payments',
            function (Blueprint $table) {
                $table->string('currency')->nullable();
                $table->integer('currency_amount')->nullable();
            }
        );

        DB::table('payments')->update([
            'currency' => 'CHF',
            'currency_amount' => DB::raw("`amount`")
        ]);

        Schema::table(
            'payments',
            function (Blueprint $table) {
                $table->string('currency')->nullable(false)->change();
                $table->integer('currency_amount')->nullable(false)->change();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(
            'payments',
            function (Blueprint $table) {
                $table->dropColumn('currency');
                $table->dropColumn('currency_amount');
            }
        );
    }
}
