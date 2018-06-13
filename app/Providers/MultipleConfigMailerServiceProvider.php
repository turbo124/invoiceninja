<?php

namespace App\Providers;

use Config;

use Illuminate\Support\ServiceProvider;
use App\Ninja\MultiMail\MultipleConfigMailer;

class MultipleConfigMailerServiceProvider extends ServiceProvider {

protected $defer = true;

/**
* Register the application services.
*
* @return void
*/
public function register() {
$this->app->bind('multimail', function ($app) {
return new MultipleConfigMailer(Config::get('multiple_config_mailer'));
});
}

/**
* Bootstrap the application services.
*
* @return void
*/
public function boot() {
//
}

public function provides() {
return ['App\Ninja\MultiMail\MultipleConfigMailer'];
}
}