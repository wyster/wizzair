<?php

declare(strict_types=1);

namespace App\Util;

use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\ResponseInterface;

class WizzMultiPassUtil
{
    public static function updateFromResponse(CookieJar $cookieJar, ResponseInterface $response, ?string $uri = null): void
    {
        $cookies = $response->getHeaders(false)['set-cookie'] ?? [];
        $headers = $response->getInfo('response_headers');
        foreach ($headers as $header) {
            if (!str_starts_with($header, 'Set-Cookie')
                && !str_starts_with($header, 'set-cookie')
            ) {
                continue;
            }

            $cookies[] = trim(str_ireplace('Set-Cookie:', '', $header));
        }
        $cookieJar->updateFromSetCookie(array_unique($cookies), $uri);
    }

    public static function prepareCookiesForRequestHeaders(CookieJar $cookieJar, string $uri): string
    {
        $preparedCookies = [];
        foreach ($cookieJar->allRawValues($uri) as $name => $value) {
            $preparedCookies[] = "$name=$value";
        }

        return implode(';', $preparedCookies);
    }

    public static function parseAuthenticateAction(string $content): string
    {
        $crawler = new Crawler($content);

        return $crawler->filter('#kc-form-login')->eq(0)->attr('action');
    }

    /**
     * @return array<{searchFlightJson: string}>
     */
    public static function parseUrls(string $content): array
    {
        $crawler = new Crawler($content);
        $result = [];
        $crawler->filter('script')->each(function (Crawler $node) use (&$result) {
            $scriptText = $node->text();
            if (preg_match('/const\s+urls\s*=\s*(\{.*?\});?/s', $scriptText, $matches)) {
                $jsonString = $matches[1];
                $result = json_decode($jsonString, true) ?: [];
            }
        });

        return $result;
    }
}
