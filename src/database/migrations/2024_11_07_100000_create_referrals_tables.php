<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(
            'referral_programs',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('tenant_id')->unsigned()->nullable();
                $table->boolean('active')->default(false);
                $table->text('name');
                $table->text('description');
                $table->integer('award_amount')->default(0);
                $table->integer('award_percent')->default(0);
                $table->integer('payments_threshold')->default(0);
                $table->string('discount_id', 36)->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')
                    ->onDelete('set null')->onUpdate('cascade');
                $table->foreign('discount_id')->references('id')->on('discounts')
                    ->onDelete('set null')->onUpdate('cascade');
            }
        );

        Schema::create(
            'referral_codes',
            static function (Blueprint $table) {
                $table->string('code', 16)->primary();
                $table->bigInteger('user_id');
                $table->bigInteger('program_id')->unsigned();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['program_id', 'user_id']);
                $table->index('user_id');

                $table->foreign('user_id')->references('id')->on('users')
                    ->onDelete('cascade')->onUpdate('cascade');
                $table->foreign('program_id')->references('id')->on('referral_programs')
                    ->onDelete('cascade')->onUpdate('cascade');
            }
        );

        Schema::create(
            'referrals',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 16);
                $table->bigInteger('user_id');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('redeemed_at')->nullable();

                $table->unique(['user_id', 'code']);
                $table->index('code');

                $table->foreign('user_id')->references('id')->on('users')
                    ->onDelete('cascade')->onUpdate('cascade');
                $table->foreign('code')->references('code')->on('referral_codes')
                    ->onDelete('cascade')->onUpdate('cascade');
            }
        );

        Schema::table(
            'signup_codes',
            static function (Blueprint $table) {
                $table->string('referral', 16)->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(
            'signup_codes',
            static function (Blueprint $table) {
                $table->dropColumn('referral');
            }
        );

        Schema::dropIfExists('referrals');
        Schema::dropIfExists('referral_codes');
        Schema::dropIfExists('referral_programs');
    }
};
