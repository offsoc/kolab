<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscounts extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'discounts',
            static function (Blueprint $table) {
                $table->string('id', 36);
                $table->tinyInteger('discount')->unsigned();
                // was json, but mariadb
                $table->text('description');
                // end of
                $table->string('code', 32)->nullable();
                $table->boolean('active')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->primary('id');
            }
        );

        Schema::table(
            'wallets',
            static function (Blueprint $table) {
                $table->string('discount_id', 36)->nullable();

                $table->foreign('discount_id')->references('id')
                    ->on('discounts')->onDelete('set null');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'wallets',
            static function (Blueprint $table) {
                $table->dropForeign(['discount_id']);
                $table->dropColumn('discount_id');
            }
        );

        Schema::dropIfExists('discounts');
    }
}
