<?php

namespace App\Enum;

enum AnalysisDecision: string
{
    case Relevant = 'relevant';
    case MaybeRelevant = 'maybe_relevant';
    case Ignored = 'ignored';
    case Clickbait = 'clickbait';

    public function label(): string
    {
        return match ($this) {
            self::Relevant => 'Pertinent',
            self::MaybeRelevant => 'À vérifier',
            self::Ignored => 'Ignoré',
            self::Clickbait => 'Putaclic',
        };
    }
}
