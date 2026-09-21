<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Services;

use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Thin client for the public endoflife.date API.
 *
 * Responses are cached in the Symfony application cache. Every kind of failure results in
 * `null` - checks translate that into a `NotAvailable` result, never into a critical one.
 */
class EndOfLifeDateClient implements EndOfLifeDateClientInterface
{
    private const BASE_URL = 'https://endoflife.date/api/';

    public function __construct(
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly bool $enabled = true,
        private readonly int $timeout = 3,
        private readonly int $cacheTtl = 86400,
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
    }

    public function getCycles(string $product): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        $product = strtolower(trim($product));
        if ($product === '' || preg_match('/^[a-z0-9._-]+$/', $product) !== 1) {
            return null;
        }

        try {
            if ($this->cache instanceof CacheInterface) {
                /** @var list<array<string, mixed>> $cycles */
                $cycles = $this->cache->get(
                    'basilicom_health_check_eol_' . str_replace('.', '_', $product),
                    function (ItemInterface $item) use ($product): array {
                        $item->expiresAfter($this->cacheTtl);

                        return $this->request($product);
                    },
                );

                return $cycles;
            }

            return $this->request($product);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function request(string $product): array
    {
        $url = self::BASE_URL . $product . '.json';
        $body = $this->httpClient instanceof HttpClientInterface
            ? $this->httpClient->request('GET', $url, ['timeout' => $this->timeout])->getContent()
            : $this->requestViaStream($url);

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new RuntimeException('Unexpected response from endoflife.date');
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    private function requestViaStream(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'ignore_errors' => false,
                'header' => "Accept: application/json\r\nUser-Agent: basilicom-pimcore-plugin-health-check\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            throw new RuntimeException('Could not reach ' . $url);
        }

        return $body;
    }
}
