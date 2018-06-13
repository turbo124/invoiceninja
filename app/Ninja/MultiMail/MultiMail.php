<?php

namespace App\Ninja\MultiMail;

use Illuminate\Support\Facades\Facade;

class Multimail extends Facade {
    protected static function getFacadeAccessor() {
        return 'App\Ninja\MultiMail\MultipleConfigMailer';
    }
}