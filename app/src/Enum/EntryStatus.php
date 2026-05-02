<?php

namespace App\Enum;

enum EntryStatus: string
{
    case ToTest = 'to_test';
    case ToWatch = 'to_watch';
    case WaitForSale = 'wait_for_sale';
    case Ignore = 'ignore';
    case SafeBet = 'safe_bet';

    public function label(): string
    {
        return match ($this) {
            self::ToTest => 'À tester',
            self::ToWatch => 'À surveiller',
            self::WaitForSale => 'À attendre en promo',
            self::Ignore => 'À ignorer',
            self::SafeBet => 'Valeur sûre',
        };
    }
}
