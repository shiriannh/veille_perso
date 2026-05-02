<?php

namespace App\Enum;

enum MediaType: string
{
    case VideoGame = 'video_game';
    case ScienceFictionNovel = 'science_fiction_novel';
    case FantasyNovel = 'fantasy_novel';
    case SpaceOperaNovel = 'space_opera_novel';
    case Comic = 'comic';
    case Movie = 'movie';
    case Series = 'series';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::VideoGame => 'Jeu vidéo',
            self::ScienceFictionNovel => 'Roman SF',
            self::FantasyNovel => 'Roman fantasy',
            self::SpaceOperaNovel => 'Space opera',
            self::Comic => 'BD / manga / comics',
            self::Movie => 'Film',
            self::Series => 'Série',
            self::Other => 'Autre',
        };
    }
}
