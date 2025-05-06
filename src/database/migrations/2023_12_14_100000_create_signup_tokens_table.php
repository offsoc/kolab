<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'signup_tokens',
            static function (Blueprint $table) {
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
     */
    public function down()
    {
        Schema::dropIfExists('signup_tokens');
    }
};
