<?php

namespace App\Enum;

enum ReviewNextAction: string
{
    case Buy = 'buy';
    case Watch = 'watch';
    case Read = 'read';
    case Play = 'play';
    case WaitForSale = 'wait_for_sale';
    case Monitor = 'monitor';
    case Ignore = 'ignore';

    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Acheter',
            self::Watch => 'Regarder',
            self::Read => 'Lire',
            self::Play => 'Tester',
            self::WaitForSale => 'Attendre promo',
            self::Monitor => 'Surveiller',
            self::Ignore => 'Ignorer',
        };
    }
}
