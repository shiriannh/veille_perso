<?php

namespace App\Enum;

enum FetchMode: string
{
    case Manual = 'manual';
    case Rss = 'rss';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manuel',
            self::Rss => 'RSS',
        };
    }
}
