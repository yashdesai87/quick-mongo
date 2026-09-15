<?php

/**
 * Router for PHP's built-in server: php -S localhost:8080 router.php
 *
 * The built-in server ignores .htaccess, so this applies the same deny
 * rules: no dotfiles, no application internals. Only assets/ is served
 * statically; everything else goes through index.php.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// A dot segment anywhere, not just a leading one, so .git/config and a
// dotfile dropped inside assets/ are both refused
if (preg_match('#(^|/)\.#', $path) || preg_match('#^/(config|core|views)(/|$)#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

$file = realpath(__DIR__.$path);
if ($file !== false && strpos($file, __DIR__.'/assets/') === 0 && is_file($file)) {
    return false;
}

require __DIR__.'/index.php';
