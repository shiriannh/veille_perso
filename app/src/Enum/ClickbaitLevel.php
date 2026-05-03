<?php

namespace App\Enum;

enum ClickbaitLevel: string
{
    case Clean = 'clean';
    case Suspicious = 'suspicious';
    case Clickbait = 'clickbait';

    public function label(): string
    {
        return match ($this) {
            self::Clean => 'Clean',
            self::Suspicious => 'Suspect',
            self::Clickbait => 'Putaclic',
        };
    }
}
