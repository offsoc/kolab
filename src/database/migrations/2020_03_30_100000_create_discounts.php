<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// phpcs:ignore
class CreateDiscounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'discounts',
            function (Blueprint $table) {
                $table->string('id', 36);
                $table->tinyInteger('discount')->unsigned();
                $table->json('description');
                $table->string('code', 32)->nullable();
                $table->boolean('active')->default(false);
                $table->timestamps();

                $table->primary('id');
            }
        );

        Schema::table(
            'wallets',
            function (Blueprint $table) {
                $table->string('discount_id', 36)->nullable();

                $table->foreign('discount_id')->references('id')
                    ->on('discounts')->onDelete('set null');
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
            'wallets',
            function (Blueprint $table) {
                $table->dropForeign(['discount_id']);
                $table->dropColumn('discount_id');
            }
        );

        Schema::dropIfExists('discounts');
    }
}
