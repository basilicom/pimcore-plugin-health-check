# Pimcore Plugin Health Check
License: GPL v3

## Version information

| Bundle Version | PHP  | Pimcore      |
|----------------|------|--------------|
| ^1.0           | ^8.3 | ^11 or ^12.0 |

## Why?

This Pimcore plugin provides an endpoint
where upon access a couple of checks regarding system health are performed.
Output is SUCCESS or FAILURE. This is suitable for continuous monitoring
via StatusCake, Pingdom or a similar service.

The URL to trigger the check is:

    [domain]/health-check-status

## Motivation

Services like Pingdom and StatusCake should be used by detecting a specific
success state instead of looking for closing body tags, status codes or similar
indications. A dedicated list of checks helps ensuring a fully functioning
web system.

## Installation

```
composer require basilicom/pimcore-plugin-health-check
```

### Activate Plugin

* Add to config/bundles.php
``` 
return [
    ...
    PimcorePluginHealthCheckBundle::class => ['all' => true],
];

```

### Configuration
You can disable certain checks by setting the corresponding config value to false.

```
pimcore_plugin_health_check:
    database_check_enabled: true
    cache_check_enabled: true
    filesystem_check_enabled: true
    robots_txt_check_enabled: true
```