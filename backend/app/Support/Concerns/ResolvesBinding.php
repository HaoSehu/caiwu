<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Services\Integrations\Plugins\PluginBindingResolver;

trait ResolvesBinding
{
    private function bindingResolver(): PluginBindingResolver
    {
        return $this->bindingResolver ??= app(PluginBindingResolver::class);
    }
}
