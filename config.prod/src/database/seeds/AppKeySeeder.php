<?php

namespace Database\Seeds;

use Laravel\Passport\Passport;
use Illuminate\Database\Seeder;
use Illuminate\Encryption\Encrypter;

class AppKeySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This emulates './artisan key:generate'
     *
     * @return void
     */
    public function run()
    {
        $key = $this->generateRandomKey();
        $this->writeNewEnvironmentFileWith($key);
    }

    /**
     * Generate a random key for the application.
     *
     * @return string
     */
    protected function generateRandomKey()
    {
        return 'base64:' . base64_encode(
            Encrypter::generateKey(\config('app.cipher'))
        );
    }

    /**
     * Write a new environment file with the given key.
     *
     * @param  string  $key
     * @return void
     */
    protected function writeNewEnvironmentFileWith($key)
    {
        file_put_contents(\app()->environmentFilePath(), preg_replace(
            $this->keyReplacementPattern(),
            'APP_KEY=' . $key,
            file_get_contents(\app()->environmentFilePath())
        ));
    }

    /**
     * Get a regex pattern that will match env APP_KEY with any random key.
     *
     * @return string
     */
    protected function keyReplacementPattern()
    {
        $escaped = preg_quote('=' . \config('app.key'), '/');
        return "/^APP_KEY{$escaped}/m";
    }
}

