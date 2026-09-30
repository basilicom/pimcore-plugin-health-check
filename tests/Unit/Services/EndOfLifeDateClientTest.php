<?php

declare(strict_types=1);

/**
 * This source file is available under the terms of the MIT License.
 * Full copyright and license information is available in
 * LICENSE.txt which is distributed with this source code.
 *
 * @copyright Copyright (c) Basilicom GmbH (https://basilicom.de)
 * @license   MIT
 */

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Services;

use Basilicom\PimcorePluginHealthCheck\Services\EndOfLifeDateClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class EndOfLifeDateClientTest extends TestCase
{
    #[Test]
    public function cycles_returnsNullWhenLookupsAreDisabledWithoutTouchingTheCache(): void
    {
        // prepare
        $cache  = new ArrayAdapter();
        $client = new EndOfLifeDateClient($cache, false, 3, 86400, 600);

        // test / verify
        $this->assertNull($client->cycles('php'));
        $this->assertSame([], $cache->getValues());
    }

    #[Test]
    public function cycles_rejectsAProductNameThatCouldEscapeTheUrl(): void
    {
        // prepare
        $client = new EndOfLifeDateClient(new ArrayAdapter(), true, 3, 86400, 600);

        // test / verify
        $this->assertNull($client->cycles('../secret'));
        $this->assertNull($client->cycles(''));
    }

    #[Test]
    public function cycles_servesACachedAnswerWithoutGoingOnline(): void
    {
        // prepare
        $cache = new ArrayAdapter();
        $item  = $cache->getItem('basilicom_health_check_eol_php');
        $item->set([['cycle' => '8.4', 'eol' => '2028-12-31']]);
        $cache->save($item);
        $client = new EndOfLifeDateClient($cache, true, 3, 86400, 600);

        // test / verify
        $this->assertSame([['cycle' => '8.4', 'eol' => '2028-12-31']], $client->cycles('php'));
    }

    #[Test]
    public function cycles_servesARememberedFailureAsNullWithoutGoingOnline(): void
    {
        // prepare
        $cache = new ArrayAdapter();
        $item  = $cache->getItem('basilicom_health_check_eol_php');
        $item->set(false);
        $cache->save($item);
        $client = new EndOfLifeDateClient($cache, true, 3, 86400, 600);

        // test / verify
        $this->assertNull($client->cycles('php'));
    }
}
