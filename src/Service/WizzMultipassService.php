<?php

declare(strict_types=1);

namespace App\Service;

use App\Integration\WizzMultipassIntegration;
use App\Util\WizzMultiPassUtil;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class WizzMultipassService
{
    public function __construct(
        private readonly WizzMultipassIntegration $wizzMultipass,
        #[Autowire(env: 'WIZZAIR_USERNAME')]
        private readonly string $username,
        #[Autowire(env: 'WIZZAIR_PASSWORD')]
        private readonly string $password,
    ) {
    }

    public function getAvailability(string $origin, string $destination, \DateTimeInterface $departure): array
    {
        $cookieJar = new CookieJar();
        $response = $this->wizzMultipass->login();
        WizzMultiPassUtil::updateFromResponse($cookieJar, $response);
        $action = WizzMultiPassUtil::parseAuthenticateAction($response->getContent());

        $this->wizzMultipass->authenticate($this->username, $this->password, $action, $cookieJar);
        $wallet = $this->wizzMultipass->getWallets($cookieJar);
        WizzMultiPassUtil::updateFromResponse($cookieJar, $wallet);
        $urls = WizzMultiPassUtil::parseUrls($wallet->getContent());
        $availability = $this->wizzMultipass->getAvailability($urls['searchFlightJson'], $origin, $destination, $departure->format('Y-m-d'), $cookieJar);

        return $availability->toArray();
    }
}
