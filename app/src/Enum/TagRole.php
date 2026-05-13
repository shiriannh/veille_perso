<?php

namespace App\Enum;

enum TagRole: string
{
    case Pivot = 'pivot';
    case Contextual = 'contextual';
    case Noise = 'noise';
    case Deprioritize = 'deprioritize';
    case Entity = 'entity';
    case EditorialFormat = 'editorial_format';

    public function label(): string
    {
        return match ($this) {
            self::Pivot => 'Pivot',
            self::Contextual => 'Contextuel',
            self::Noise => 'Bruit',
            self::Deprioritize => 'Declassant',
            self::Entity => 'Entite',
            self::EditorialFormat => 'Format editorial',
        };
    }
}
