<?php

namespace App\Tests\Service;

use App\Service\LesLibrairesConnector;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LesLibrairesConnectorTest extends TestCase
{
    public function testItBuildsSupportedWindowUrls(): void
    {
        $connector = new LesLibrairesConnector($this->createStub(HttpClientInterface::class));

        self::assertSame(
            'https://www.leslibraires.fr/rayon/science-fiction-fantastique-fantasy/?f_release_date=-7d',
            $connector->listUrl('7d'),
        );
        self::assertSame(
            'https://www.leslibraires.fr/rayon/science-fiction-fantastique-fantasy/?f_release_date=-1m',
            $connector->listUrl('1m'),
        );
        self::assertSame(
            'https://www.leslibraires.fr/rayon/science-fiction-fantastique-fantasy/?f_release_date=-3m',
            $connector->listUrl('invalid'),
        );
    }

    public function testItNormalizesConfiguredRayonUrl(): void
    {
        $connector = new LesLibrairesConnector($this->createStub(HttpClientInterface::class));

        self::assertSame(
            'https://www.leslibraires.fr/rayon/science-fiction-fantastique-fantasy/?f_release_date=-1m',
            $connector->listUrl('1m', 'https://www.leslibraires.fr/rayon/science-fiction-fantastique-fantasy/?f_release_date=-3m'),
        );
    }
}
