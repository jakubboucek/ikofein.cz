<?php

declare(strict_types=1);

/** @testCase */

use Tester\Environment;
use Tester\HttpAssert;
use Tester\TestCase;

require __DIR__ . '/../vendor/autoload.php';
Environment::setup();

final class PublicPagesTest extends TestCase
{
    /** @dataProvider languages */
    public function testLanguageSelection(?string $cookie, ?string $acceptLanguage, string $expected): void
    {
        HttpAssert::fetch(
            'http://localhost/',
            headers: $acceptLanguage === null ? [] : ['Accept-Language' => $acceptLanguage],
            cookies: $cookie === null ? [] : ['lang' => $cookie],
        )->expectCode(302)->expectHeader('Location', 'http://localhost/' . $expected . '/');
    }

    protected function languages(): array
    {
        return [
            'no language preference' => [null, null, 'en'],
            'unsupported browser language' => [null, 'fr-FR', 'en'],
            'Czech browser' => [null, 'cs-CZ,cs;q=0.9,en;q=0.8', 'cs'],
            'English browser' => [null, 'en-US,en;q=0.9', 'en'],
            'cookie overrides browser' => ['cs', 'en-US', 'cs'],
            'invalid cookie falls back to default' => ['invalid', 'cs-CZ', 'en'],
        ];
    }
}

(new PublicPagesTest)->run();
