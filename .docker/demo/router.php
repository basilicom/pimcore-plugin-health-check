<?php

/**
 * This source file is available under the terms of the MIT License.
 * Full copyright and license information is available in
 * LICENSE.txt which is distributed with this source code.
 *
 * @copyright Copyright (c) Basilicom GmbH (https://basilicom.de)
 * @license   MIT
 */

/**
 * Router for `php -S` in the local demo (.docker/demo/run.sh).
 *
 * The CLI server calls this for every request, so existing static files are handed back with
 * `return false`. Everything else goes through Pimcore's front controller, with SCRIPT_NAME
 * rewritten so Symfony's base-URL detection never sees the router script.
 */
$root = $_SERVER['DOCUMENT_ROOT'];
$path = urldecode((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($path !== '/' && !str_ends_with($path, '.php') && is_file($root . $path)) {
    return false;
}

// Pimcore serves an asset's original from var/assets under its tree path, as its nginx rules do.
$asset = $root . '/var/assets' . $path;
if ($path !== '/' && !str_contains($path, '..') && is_file($asset)) {
    header('Content-Type: ' . (mime_content_type($asset) ?: 'application/octet-stream'));
    readfile($asset);

    return true;
}

$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';

require $root . '/index.php';
