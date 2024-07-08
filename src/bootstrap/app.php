<?php

// Stuff from Roundcube Framework bootstrap required by rcube_* classes
if (!defined('RCUBE_CHARSET')) {
    define('RCUBE_CHARSET', 'UTF-8');
}

// This function is defined by phpunit as well
if (!function_exists("array_first")) {
    /**
    * Get first element from an array
    *
    * @param array $array Input array
    *
    * @return mixed First element if found, Null otherwise
    */
    function array_first($array)
    {
        // @phpstan-ignore-next-line
        if (is_array($array) && !empty($array)) {
            reset($array);
            foreach ($array as $element) {
                return $element;
            }
        }

        return null;
    }
}

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
