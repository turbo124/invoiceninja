<?php

namespace App\Livewire\Synthesizers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class EloquentCollectionSynth extends \Livewire\Features\SupportModels\EloquentCollectionSynth
{
    public function dehydrate(Collection $target, $dehydrate_child)
    {
        if (! config('ninja.db.multi_db_enabled')) {
            return parent::dehydrate($target, $dehydrate_child);
        }

        [$data, $meta] = parent::dehydrate($target, $dehydrate_child);
        $meta['connection'] = $target->getQueueableConnection() ?? config('database.default');

        return [$data, $meta];
    }

    public function hydrate($data, $meta, $hydrate_child)
    {
        if (! config('ninja.db.multi_db_enabled') && ! isset($meta['connection'])) {
            return parent::hydrate($data, $meta, $hydrate_child);
        }

        $class = $meta['class'];
        $keys = $meta['keys'] ?? [];

        if ($keys === []) {
            return new $class;
        }

        $model_class = Relation::getMorphedModel($meta['modelClass']) ?? $meta['modelClass'];

        if (! is_a($model_class, Model::class, true)) {
            throw new \Exception('Livewire: Invalid model class.');
        }

        $models = (new $model_class)
            ->setConnection($meta['connection'] ?? config('database.default'))
            ->newQueryForRestoration($keys)->useWritePdo()->get()->keyBy->getKey();

        return new $class(collect($keys)->map(fn ($key) => $models[$key] ?? null)->filter());
    }
}
