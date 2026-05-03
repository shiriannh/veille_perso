<?php

namespace App\Enum;

enum ImportRunStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'En cours',
            self::Success => 'Réussi',
            self::Failed => 'Échec',
        };
    }
}
