<?php

namespace App\Livewire\Synthesizers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class ModelSynth extends \Livewire\Features\SupportModels\ModelSynth
{
    public function dehydrate($target)
    {
        if (! config('ninja.db.multi_db_enabled')) {
            return parent::dehydrate($target);
        }

        [$data, $meta] = parent::dehydrate($target);
        $meta['connection'] = $target->getConnectionName() ?? config('database.default');

        return [$data, $meta];
    }

    public function hydrate($data, $meta)
    {
        if (! config('ninja.db.multi_db_enabled') && ! isset($meta['connection'])) {
            return parent::hydrate($data, $meta);
        }

        $class = Relation::getMorphedModel($meta['class'] ?? '') ?? ($meta['class'] ?? null);

        if (! $class || ! is_a($class, Model::class, true)) {
            throw new \Exception('Livewire: Invalid model class.');
        }

        // The signed snapshot pins the connection. Livewire's shared model cache
        // identifies models by class and ID only, which can collide across shards.
        $model = (new $class)->setConnection($meta['connection'] ?? config('database.default'));

        return array_key_exists('key', $meta)
            ? $model->newQueryForRestoration($meta['key'])->useWritePdo()->firstOrFail()
            : $model;
    }
}
