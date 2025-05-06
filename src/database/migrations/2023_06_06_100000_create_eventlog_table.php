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
            'eventlog',
            static function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('object_id', 36);
                $table->string('object_type', 36);
                $table->tinyInteger('type')->unsigned();
                $table->string('user_email')->nullable();
                $table->string('comment', 1024)->nullable();
                $table->text('data')->nullable(); // json
                $table->timestamp('created_at')->useCurrent();

                $table->index(['object_id', 'object_type', 'type']);
                $table->index('created_at');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('eventlog');
    }
};
