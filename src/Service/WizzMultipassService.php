<?php

declare(strict_types=1);

namespace App\Service;

use App\Integration\WizzMultipassIntegration;
use App\Service\Exception\RouteNotAvailableException;
use App\Util\WizzMultiPassUtil;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class WizzMultipassService
{
    private const string AUTH_CACHE_KEY = 'wizz_auth';

    public function __construct(
        private readonly WizzMultipassIntegration $wizzMultipass,
        #[Autowire(env: 'WIZZAIR_USERNAME')]
        private readonly string $username,
        #[Autowire(env: 'WIZZAIR_PASSWORD')]
        private readonly string $password,
        #[Autowire(service: 'cache.app.taggable')]
        private readonly CacheItemPoolInterface&TagAwareCacheInterface $cache,
    ) {
    }

    public function getAvailability(string $origin, string $destination, \DateTimeInterface $departure): array
    {
        $cookieJar = $this->authenticate();
        $wallet = $this->wizzMultipass->getWallets($cookieJar);
        WizzMultiPassUtil::updateFromResponse($cookieJar, $wallet);
        $urls = WizzMultiPassUtil::parseUrls($wallet->getContent());
        $availability = $this->wizzMultipass->getAvailability($urls['searchFlightJson'], $origin, $destination, $departure->format('Y-m-d'), $cookieJar);

        if (Response::HTTP_BAD_REQUEST === $availability->getStatusCode()) {
            $data = $availability->toArray(false);
            if (($data['code'] ?? null) === 'error.availability') {
                throw new RouteNotAvailableException();
            }
        }

        return $availability->toArray();
    }

    public function getRoutes(): array
    {
        $cookieJar = $this->authenticate();
        $wallet = $this->wizzMultipass->getWallets($cookieJar);

        return WizzMultiPassUtil::parseFlights($wallet->getContent())['searchFlight']['options']['routes'];
    }

    private function authenticate(): CookieJar
    {
        $cacheItem = $this->cache->getItem(self::AUTH_CACHE_KEY);
        if ($cacheItem->isHit()) {
            /** @var CookieJar $cookieJar */
            $cookieJar = $cacheItem->get();
            if (false === $cookieJar->get(WizzMultipassIntegration::XSRF_TOKEN_COOKIE)?->isExpired()) {
                return $cookieJar; // We should return the object only if it is not null and not expired.
            }
        }
        $cookieJar = new CookieJar();
        $response = $this->wizzMultipass->login();
        WizzMultiPassUtil::updateFromResponse($cookieJar, $response);
        $action = WizzMultiPassUtil::parseAuthenticateAction($response->getContent());
        $this->wizzMultipass->authenticate($this->username, $this->password, $action, $cookieJar);

        $cacheItem->set($cookieJar);
        $this->cache->save($cacheItem);

        return $cookieJar;
    }
}
