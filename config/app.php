<?php

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Facade;

return [

    'mix_url' => env('MIX_ASSET_URL', env('APP_URL', null)),


    'aliases' => Facade::defaultAliases()->merge([
        'Collector' => Turbo124\Collector\CollectorFacade::class,
        'Countries' => 'Webpatser\Countries\CountriesFacade',
        'CustomMessage' => App\Utils\ClientPortal\CustomMessage\CustomMessageFacade::class,
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
