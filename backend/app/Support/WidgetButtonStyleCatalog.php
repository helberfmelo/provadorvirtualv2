<?php

namespace App\Support;

class WidgetButtonStyleCatalog
{
    public const DEFAULT = 'gallery_1_text_icons';

    public static function keys(): array
    {
        return [
            self::DEFAULT,
            'gallery_2_side_icons',
            'gallery_3_dark_outline',
            'gallery_4_underlined_icons',
            'gallery_5_pills',
            'gallery_6_split_line',
            'gallery_7_editorial_links',
            'gallery_8_dotted_stack',
            'gallery_9_light_block',
            'gallery_10_badge_tooltip',
            'gradient',
            'clean',
            'outline',
            'soft',
        ];
    }
}
