<?php

namespace App\Enum;

enum ReviewStatus: string
{
    case Draft = 'draft';
    case ToComplete = 'to_complete';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::ToComplete => 'A completer',
            self::Completed => 'Terminee',
        };
    }
}
