<?php

namespace App\Tests\Service;

use App\Service\LesLibrairesConnector;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

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

    public function testItBuildsPaginatedUrls(): void
    {
        $connector = new LesLibrairesConnector($this->createStub(HttpClientInterface::class));

        self::assertSame(
            'https://www.leslibraires.fr/rayon/science-fiction-fantastique-fantasy/?f_release_date=-7d&page=2',
            $connector->listPageUrl('7d', null, 2),
        );
    }

    public function testItStopsPaginationWhenPageHasNoNewBook(): void
    {
        $requestedUrls = [];
        $pages = [
            '<html><body><a href="/livre/alpha">Alpha</a></body></html>',
            '<html><body><a href="/livre/beta">Beta</a><a href="/livre/alpha">Alpha duplicate</a></body></html>',
            '<html><body><p>Aucun resultat</p></body></html>',
        ];

        $responses = array_map(function (string $html): ResponseInterface {
            $response = $this->createStub(ResponseInterface::class);
            $response->method('getContent')->willReturn($html);

            return $response;
        }, $pages);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects(self::exactly(3))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url) use (&$requestedUrls, &$responses): ResponseInterface {
                $requestedUrls[] = $url;

                return array_shift($responses);
            });

        $connector = new LesLibrairesConnector($client);
        $result = $connector->collectCandidates('7d');

        self::assertSame(3, $result['pagesVisited']);
        self::assertCount(2, $result['candidates']);
        self::assertSame('https://www.leslibraires.fr/rayon/science-fiction-fantastique-fantasy/?f_release_date=-7d', $requestedUrls[0]);
        self::assertSame('https://www.leslibraires.fr/rayon/science-fiction-fantastique-fantasy/?f_release_date=-7d&page=2', $requestedUrls[1]);
        self::assertSame('https://www.leslibraires.fr/rayon/science-fiction-fantastique-fantasy/?f_release_date=-7d&page=3', $requestedUrls[2]);
    }
}
