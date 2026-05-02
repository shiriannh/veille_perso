<?php

namespace App\Enum;

enum ReviewVerdict: string
{
    case MustHave = 'must_have';
    case Recommended = 'recommended';
    case Curious = 'curious';
    case Wait = 'wait';
    case Skip = 'skip';

    public function label(): string
    {
        return match ($this) {
            self::MustHave => 'Prioritaire',
            self::Recommended => 'Recommandé',
            self::Curious => 'Curiosité',
            self::Wait => 'Attendre',
            self::Skip => 'Passer',
        };
    }
}
