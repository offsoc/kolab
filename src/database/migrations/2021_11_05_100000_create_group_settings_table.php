<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// phpcs:ignore
class CreateGroupSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'group_settings',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('group_id');
                $table->string('key');
                $table->text('value');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->foreign('group_id')->references('id')->on('groups')
                    ->onDelete('cascade')->onUpdate('cascade');

                $table->unique(['group_id', 'key']);
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
        Schema::dropIfExists('group_settings');
    }
}
