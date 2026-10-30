<?php

namespace App\Enums;

enum ChatMessageType: string
{
    case TEXT = 'text';     // free-typed message
    case SYSTEM = 'system'; // info bubble (e.g. conversation started)
    case IMAGE = 'image';   // uploaded image attachment (path in meta['path'])
    case FILE = 'file';     // uploaded document attachment, e.g. PDF (meta['path'], meta['name'])

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
