<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

/**
 * Contract for every health check.
 *
 * Implementations are collected automatically: any service implementing this interface
 * is tagged with `pimcore_plugin_health_check.check` and picked up by the HealthCheckService.
 */
interface CheckInterface
{
    /**
     * Stable, machine readable identifier, e.g. `system:php_version`.
     */
    public function getIdentifier(): string;

    /**
     * Human readable label, e.g. `PHP Version`.
     */
    public function getLabel(): string;

    public function isEnabled(): bool;

    /**
     * Runs the check. Must never throw - failures are reported as
     * CheckStatus::Critical or CheckStatus::NotAvailable.
     */
    public function run(): CheckResult;
}
