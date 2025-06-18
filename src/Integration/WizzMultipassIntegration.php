<?php

declare(strict_types=1);

namespace App\Integration;

use App\Util\WizzMultiPassUtil;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class WizzMultipassIntegration
{
    private HttpClientInterface $httpClient;

    public const string BASE_URL = 'https://multipass.wizzair.com';
    private const string AUTH_URL = '/auth/realms/w6/login-actions/authenticate';
    private const string LOGIN_URL = '/w6/subscriptions/auth/login';
    private const string AVAILABILITY_URL = '/en/w6/subscriptions/json/availability';
    private const string WALLETS_URL = '/en/w6/subscriptions';

    public const string XSRF_TOKEN_COOKIE = 'XSRF-TOKEN';

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient->withOptions([
            'base_uri' => self::BASE_URL,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
            ],
        ]);
    }

    public function login(): ResponseInterface
    {
        return $this->httpClient->request(Request::METHOD_GET, self::LOGIN_URL);
    }

    public function authenticate(string $username, string $password, string $action, CookieJar $cookieJar): ResponseInterface
    {
        return $this->httpClient->request(Request::METHOD_POST, $action, [
            'body' => [
                'username' => $username,
                'password' => $password,
                'credentialId' => '',
            ],
            'headers' => [
                'Cookie' => WizzMultiPassUtil::prepareCookiesForRequestHeaders($cookieJar, self::AUTH_URL),
            ],
        ]);
    }

    public function getWallets(CookieJar $cookieJar): ResponseInterface
    {
        return $this->httpClient->request(Request::METHOD_GET, self::WALLETS_URL, [
            'headers' => [
                'Cookie' => WizzMultiPassUtil::prepareCookiesForRequestHeaders($cookieJar, self::WALLETS_URL),
            ],
        ]);
    }

    public function getAvailability(string $url, string $origin, string $destination, string $departure, CookieJar $cookieJar): ResponseInterface
    {
        if (null === $cookieJar->get(self::XSRF_TOKEN_COOKIE)) {
            throw new \RuntimeException('xsrf token not found');
        }

        return $this->httpClient->request(Request::METHOD_POST, $url, [
            'json' => [
                'flightType' => 'OW',
                'origin' => $origin,
                'destination' => $destination,
                'departure' => $departure,
                'arrival' => '',
                'intervalSubtype' => null,
            ],
            'headers' => [
                'Cookie' => WizzMultiPassUtil::prepareCookiesForRequestHeaders($cookieJar, self::AVAILABILITY_URL),
                'Accept' => 'application/json',
                'x-xsrf-token' => $cookieJar->get(self::XSRF_TOKEN_COOKIE)->getValue(),
            ],
        ]);
    }
}
