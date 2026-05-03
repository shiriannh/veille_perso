<?php

namespace App\Enum;

enum AnalysisStatus: string
{
    case Pending = 'pending';
    case RulesOnly = 'rules_only';
    case AiAssisted = 'ai_assisted';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À analyser',
            self::RulesOnly => 'Règles',
            self::AiAssisted => 'IA',
            self::Failed => 'Échec',
        };
    }
}
