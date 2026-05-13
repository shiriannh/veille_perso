<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\Attributes\DataProvider;

class MainRoutesTest extends KernelTestCase
{
    #[DataProvider('routeProvider')]
    public function testMainPagesLoad(string $path, string $expectedText): void
    {
        self::bootKernel();
        $response = self::$kernel->handle(Request::create($path));

        self::assertSame(200, $response->getStatusCode(), $path);
        self::assertStringContainsString($expectedText, $response->getContent());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function routeProvider(): iterable
    {
        yield 'sources' => ['/sources', 'Sources'];
        yield 'entries' => ['/entries', 'Entrees'];
        yield 'reviews' => ['/reviews', 'Fiches'];
        yield 'imports' => ['/imports', 'Imports'];
        yield 'syntheses' => ['/syntheses', 'Syntheses'];
        yield 'admin' => ['/admin', 'Admin'];
        yield 'admin status' => ['/admin/status', 'Statut local'];
    }
}
