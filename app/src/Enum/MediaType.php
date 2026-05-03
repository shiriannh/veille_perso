<?php

namespace App\Enum;

enum MediaType: string
{
    case VideoGame = 'video_game';
    case Book = 'book';
    case ScienceFictionNovel = 'science_fiction_novel';
    case FantasyNovel = 'fantasy_novel';
    case SpaceOperaNovel = 'space_opera_novel';
    case Bd = 'bd';
    case Manga = 'manga';
    case Manhwa = 'manhwa';
    case Manhua = 'manhua';
    case Comics = 'comics';
    case Comic = 'comic';
    case Anime = 'anime';
    case Movie = 'movie';
    case Series = 'series';
    case Ttrpg = 'ttrpg';
    case Figurines = 'figurines';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::VideoGame => 'Jeu video',
            self::Book => 'Livre',
            self::ScienceFictionNovel => 'Roman SF',
            self::FantasyNovel => 'Roman fantasy',
            self::SpaceOperaNovel => 'Space opera',
            self::Bd => 'BD',
            self::Manga => 'Manga',
            self::Manhwa => 'Manhwa',
            self::Manhua => 'Manhua',
            self::Comics => 'Comics',
            self::Comic => 'BD / manga / comics',
            self::Anime => 'Anime',
            self::Movie => 'Film',
            self::Series => 'Serie',
            self::Ttrpg => 'JDR',
            self::Figurines => 'Figurines',
            self::Other => 'Autre',
        };
    }
}
