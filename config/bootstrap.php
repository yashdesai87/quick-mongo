<?php

/**
 * Bootstrap file - Application initialization
 */

// Error reporting: log everything, never display to users
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Define base paths
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH.'/config');
define('CORE_PATH', BASE_PATH.'/core');
define('VIEWS_PATH', BASE_PATH.'/views');
define('ASSETS_PATH', BASE_PATH.'/assets');

// Autoloader for core classes
spl_autoload_register(function ($class) {
    $file = CORE_PATH.'/'.$class.'.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load configuration from the project .env file; real environment variables win
function loadEnvironment()
{
    $envFile = BASE_PATH.'/.env';
    $config = [];

    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }

                $config[$key] = $value;
            }
        }
    }

    foreach (['MONGO_URI', 'APP_DEBUG'] as $key) {
        $value = getenv($key);
        if ($value !== false) {
            $config[$key] = $value;
        }
    }

    return $config;
}

// Load environment
$env = loadEnvironment();

// Store in constants
define('MONGO_URI', $env['MONGO_URI'] ?? 'mongodb://localhost:27017');
define('APP_DEBUG', ($env['APP_DEBUG'] ?? 'false') === 'true');

// Error handler
set_error_handler(function ($severity, $message, $file, $line) {
    if (! (error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Exception handler
set_exception_handler(function ($exception) {
    error_log($exception->getMessage());

    // Show generic error to user
    if (! headers_sent()) {
        http_response_code(500);
    }

    echo '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px;">';
    echo '<h3>An error occurred</h3>';
    echo '<p>Sorry, something went wrong. Please try again later.</p>';

    // Show details only in development
    if (APP_DEBUG) {
        echo '<hr>';
        echo '<pre>'.htmlspecialchars($exception->getMessage()).'</pre>';
        echo '<pre>'.htmlspecialchars($exception->getTraceAsString()).'</pre>';
    }

    echo '</div>';
    exit;
});
