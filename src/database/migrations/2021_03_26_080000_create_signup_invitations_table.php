<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSignupInvitationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'signup_invitations',
            static function (Blueprint $table) {
                $table->string('id', 36);
                $table->string('email');
                $table->smallInteger('status');
                $table->bigInteger('user_id')->nullable();
                $table->bigInteger('tenant_id')->unsigned()->nullable();
                $table->timestamps();

                $table->primary('id');

                $table->index('email');
                $table->index('created_at');

                $table->foreign('tenant_id')->references('id')->on('tenants')
                    ->onUpdate('cascade')->onDelete('set null');
                $table->foreign('user_id')->references('id')->on('users')
                    ->onUpdate('cascade')->onDelete('set null');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('signup_invitations');
    }
}
