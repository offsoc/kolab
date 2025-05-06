<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayments extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'payments',
            static function (Blueprint $table) {
                $table->string('id', 32)->primary();
                $table->string('wallet_id', 36);
                $table->string('status', 16);
                $table->integer('amount');
                $table->text('description');
                $table->string('provider', 16);
                $table->string('type', 16);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
