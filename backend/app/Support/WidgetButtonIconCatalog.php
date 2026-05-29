<?php

namespace App\Support;

class WidgetButtonIconCatalog
{
    public const DEFAULT_PRIMARY = 'hanger';

    public const DEFAULT_SECONDARY = 'ruler';

    public static function keys(): array
    {
        return [
            self::DEFAULT_PRIMARY,
            self::DEFAULT_SECONDARY,
            'tape',
            'ruler_combined',
            'shirt',
            'body',
            'chart',
            'size_tag',
        ];
    }
}
