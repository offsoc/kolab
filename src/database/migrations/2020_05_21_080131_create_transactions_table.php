<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'transactions',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('user_email')->nullable();
                $table->string('object_id', 36)->index();
                $table->string('object_type', 36)->index();
                $table->string('type', 8);
                $table->integer('amount')->nullable();
                $table->string('description')->nullable();
                $table->string('transaction_id', 36)->nullable()->index();
                $table->timestamps();

                $table->index(['object_id', 'object_type']);
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
        Schema::dropIfExists('transactions');
    }
}
