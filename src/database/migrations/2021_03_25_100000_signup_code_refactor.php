<?php

use App\SignupCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SignupCodeRefactor extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'signup_codes',
            static function (Blueprint $table) {
                $table->string('email');
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('plan', 128)->nullable();
                $table->string('voucher', 32)->nullable();
                $table->string('local_part')->nullable();
                $table->string('domain_part')->nullable();
                $table->string('ip_address')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->softDeletes();
            }
        );

        SignupCode::withTrashed()->get()->each(static function ($code) {
            if (empty($code->data)) {
                return;
            }

            $data = json_decode($code->data);

            if (!empty($data->email)) {
                $parts = explode('@', $data->email);

                $data->local_part = $parts[0] ?? null;
                $data->domain_part = $parts[1] ?? null;
            }

            DB::table('signup_codes')
                ->where('code', $code->code)
                ->update([
                    'email' => $data->email ?? null,
                    'first_name' => $data->first_name ?? null,
                    'last_name' => $data->last_name ?? null,
                    'plan' => $data->plan ?? null,
                    'voucher' => $data->voucher ?? null,
                    'local_part' => $data->local_part ?? null,
                    'domain_part' => $data->domain_part ?? null,
                ]);
        });

        Schema::table(
            'signup_codes',
            static function (Blueprint $table) {
                $table->dropColumn('data');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'signup_codes',
            static function (Blueprint $table) {
                $table->text('data');
            }
        );

        DB::table('signup_codes')->get()->each(static function ($code) {
            $data = json_encode([
                'email' => $code->email,
                'first_name' => $code->first_name,
                'last_name' => $code->last_name,
                'plan' => $code->plan,
                'voucher' => $code->voucher,
            ]);

            DB::table('signup_codes')
                ->where('code', $code->code)
                ->update(['data' => $data]);
        });

        Schema::table(
            'signup_codes',
            static function (Blueprint $table) {
                $table->dropColumn([
                    'created_at',
                    'updated_at',
                    'deleted_at',
                    'ip_address',
                    'email',
                    'local_part',
                    'domain_part',
                    'first_name',
                    'last_name',
                    'plan',
                    'voucher',
                ]);
            }
        );
    }
}
