<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class DiscoveryController extends Controller
{
    /**
     * Handle the Mozilla client autoconfig request
     */
    public function mozilla(Request $request): Response
    {
        $engine = new \App\Discovery\Mozilla();
        return $engine->handle($request);
    }

    /**
     * Handle the Microsoft Autodiscovery v2 request
     */
    public function microsoftJson(Request $request): Response
    {
        $engine = new \App\Discovery\MicrosoftJson();
        return $engine->handle($request);
    }

    /**
     * Handle the Microsoft Outlook/Activesync request
     */
    public function microsoftXml(Request $request): Response
    {
        $engine = new \App\Discovery\MicrosoftXml();
        return $engine->handle($request);
    }

    /**
     * Register all controller routes
     */
    public static function registerRoutes(): void
    {
        Route::post('/autodiscover/autodiscover.xml', [self::class, 'microsoftXml']);
        Route::post('/Autodiscover/Autodiscover.xml', [self::class, 'microsoftXml']);
        Route::post('/AutoDiscover/AutoDiscover.xml', [self::class, 'microsoftXml']);
        Route::get('/autodiscover/autodiscover.json', [self::class, 'microsoftJson']);
        Route::get('/autodiscover/autodiscover.json/v1.0/{email}', [self::class, 'microsoftJson']);
        Route::get('/mail/config-v1.1.xml', [self::class, 'mozilla']);
        Route::get('/.well-known/autoconfig/mail/config-v1.1.xml', [self::class, 'mozilla']);
    }
}
