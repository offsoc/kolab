<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'groups',
            static function (Blueprint $table) {
                $table->bigInteger('id');
                $table->string('email')->unique();
                $table->text('members')->nullable();
                $table->smallInteger('status');

                $table->timestamps();
                $table->softDeletes();

                $table->primary('id');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('groups');
    }
}
