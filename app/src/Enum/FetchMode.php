<?php

namespace App\Enum;

enum FetchMode: string
{
    case Manual = 'manual';
    case Rss = 'rss';
    case LesLibrairesCatalog = 'leslibraires_catalog';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manuel',
            self::Rss => 'RSS',
            self::LesLibrairesCatalog => 'Catalogue leslibraires.fr',
        };
    }
}
