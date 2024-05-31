<?php

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Facade;

return [

    'mix_url' => env('MIX_ASSET_URL', env('APP_URL', null)),

    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Laravel Framework Service Providers...
         */
        /*
         * Dependency Service Providers
         */
        'Webpatser\Countries\CountriesServiceProvider',

        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\ComposerServiceProvider::class,
        App\Providers\MultiDBProvider::class,
        App\Providers\ClientPortalServiceProvider::class,
        App\Providers\NinjaTranslationServiceProvider::class,
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->merge([
        'Collector' => Turbo124\Collector\CollectorFacade::class,
        'Countries' => 'Webpatser\Countries\CountriesFacade',
        'CustomMessage' => App\Utils\ClientPortal\CustomMessage\CustomMessageFacade::class,
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
