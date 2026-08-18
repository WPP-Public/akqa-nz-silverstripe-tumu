<?php

declare(strict_types=1);

namespace Akqa\SilverStripe\SSR;

use SilverStripe\View\TemplateGlobalProvider;

/**
 * Exposes `$ReactComponent($Name, $Props)` to Silverstripe templates.
 *
 * Props should be a JSON object string (typically produced in PHP) to match
 * the existing `data-props` client hydration pattern.
 */
class ReactComponentProvider implements TemplateGlobalProvider
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function get_template_global_variables(): array
    {
        return [
            'ReactComponent' => [
                'method' => 'react_component',
                'casting' => 'HTMLFragment',
            ],
        ];
    }


    public static function react_component(string $name, mixed $props = null): string
    {
        return ReactRenderer::singleton()->render($name, $props ?? [])->getValue() ?? '';
    }
}
