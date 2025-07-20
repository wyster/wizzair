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
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class WizzMultipassService
{
    private const string AUTH_CACHE_KEY = 'wizz_auth';
    private const string WALLETS_CACHE_KEY = 'wizz_wallets';

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
        try {
            $cookieJar = $this->authenticate();
            $wallets = $this->getWallets($cookieJar);
            $urls = WizzMultiPassUtil::parseUrls($wallets);
            $availability = $this->wizzMultipass->getAvailability($urls['searchFlightJson'], $origin, $destination, $departure->format('Y-m-d'), $cookieJar);

            if (Response::HTTP_BAD_REQUEST === $availability->getStatusCode()) {
                $data = $availability->toArray(false);
                if (($data['code'] ?? null) === 'error.availability') {
                    throw new RouteNotAvailableException();
                }
            }

            return $availability->toArray();
        } catch (HttpExceptionInterface $e) {
            throw $e;
        }
    }

    public function getRoutes(): array
    {
        $cookieJar = $this->authenticate();
        $wallets = $this->wizzMultipass->getWallets($cookieJar);

        return WizzMultiPassUtil::parseFlights($wallets->getContent())['searchFlight']['options']['routes'];
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

    private function getWallets(CookieJar $cookieJar): string
    {
        if (null !== WizzMultiPassUtil::getSessionUniqueId($cookieJar)) {
            $cacheItem = $this->cache->getItem(self::WALLETS_CACHE_KEY.WizzMultiPassUtil::getSessionUniqueId($cookieJar));
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }
        $wallets = $this->wizzMultipass->getWallets($cookieJar);
        WizzMultiPassUtil::updateFromResponse($cookieJar, $wallets);

        if (null === WizzMultiPassUtil::getSessionUniqueId($cookieJar)) {
            throw new \RuntimeException('Could not get session unique ID');
        }

        $cacheItem = $this->cache->getItem(self::WALLETS_CACHE_KEY.WizzMultiPassUtil::getSessionUniqueId($cookieJar));
        $this->cache->save(
            $this->cache->getItem(self::AUTH_CACHE_KEY)
                ->set($cookieJar)
        );

        $cacheItem->set($wallets->getContent());
        $this->cache->save($cacheItem); // @todo set expr time

        return $wallets->getContent();
    }
}
