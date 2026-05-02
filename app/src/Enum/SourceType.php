<?php

namespace App\Enum;

enum SourceType: string
{
    case Website = 'website';
    case Rss = 'rss';
    case Newsletter = 'newsletter';
    case Youtube = 'youtube';
    case Podcast = 'podcast';
    case Social = 'social';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Site web',
            self::Rss => 'Flux RSS',
            self::Newsletter => 'Newsletter',
            self::Youtube => 'YouTube',
            self::Podcast => 'Podcast',
            self::Social => 'Réseau social',
            self::Other => 'Autre',
        };
    }
}
