<?php

namespace App\Livewire;

use App\Libraries\MultiDB;

class PersistentMiddleware extends \Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware
{
    private ?string $middleware_connection = null;

    protected function applyPersistentMiddleware()
    {
        if (config('ninja.db.multi_db_enabled')
            && $this->lastMiddlewareRoute === $this->method.'|'.$this->path
            && $this->middleware_connection !== null) {
            // Middleware is deduplicated between components. Restore its database
            // selection if the preceding component switched the default connection.
            MultiDB::setDb($this->middleware_connection);

            return;
        }

        parent::applyPersistentMiddleware();

        if ($this->lastMiddlewareRoute === $this->method.'|'.$this->path) {
            $this->middleware_connection = config('database.default');
        }
    }
}
