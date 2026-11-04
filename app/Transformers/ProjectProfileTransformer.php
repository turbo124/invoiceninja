<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\Project;

class ProjectProfileTransformer extends ProjectTransformer
{
    protected array $defaultIncludes = [];

    protected array $availableIncludes = [];

    public function transform(Project $project)
    {
        return array_merge(parent::transform($project), [
            'private_notes' => '',
            'budgeted_hours' => 0.0,
            'budgeted_amount' => 0.0,
        ]);
    }
}
