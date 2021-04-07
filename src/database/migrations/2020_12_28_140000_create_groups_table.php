<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// phpcs:ignore
class CreateGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'groups',
            function (Blueprint $table) {
                $table->bigInteger('id');
                $table->string('email')->unique();
                $table->text('members')->nullable();
                $table->smallInteger('status');

                $table->timestamps();
                $table->softDeletes();

                $table->primary('id');
            }
        );

        if (!\App\Sku::where('title', 'group')->first()) {
            \App\Sku::create([
                'title' => 'group',
                'name' => 'Group',
                'description' => 'Distribution list',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Group',
                'active' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('groups');
    }
}
