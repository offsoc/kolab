<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropGreylistSettingsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::dropIfExists('greylist_settings');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // FIXME: Should we really create the table?

        Schema::create(
            'greylist_settings',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('object_id');
                $table->string('object_type', 16);
                $table->string('key', 64);
                $table->text('value');
                $table->timestamps();

                $table->index(['object_id', 'object_type', 'key'], 'ook_idx');
            }
        );
    }
}
