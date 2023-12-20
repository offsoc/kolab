<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'signup_tokens',
            function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('plan_id', 36);
                $table->integer('counter')->unsigned()->default(0);
                $table->timestamp('created_at')->useCurrent();

                $table->index('plan_id');
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
        Schema::dropIfExists('signup_tokens');
    }
};
