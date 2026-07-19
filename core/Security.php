<?php

/**
 * Security utilities for input validation and sanitization
 */
class Security
{
    /**
     * Sanitize user input
     */
    public static function sanitize($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }

        // Remove PHP and HTML tags
        $input = strip_tags($input);

        // Convert special characters to HTML entities
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove any null bytes
        $input = str_replace(chr(0), '', $input);

        // Trim whitespace
        $input = trim($input);

        return $input;
    }

    /**
     * Validate MongoDB ObjectId
     */
    public static function validateMongoId($id)
    {
        // MongoDB ObjectId is 24 character hex string
        if (! is_string($id) || strlen($id) !== 24) {
            return false;
        }

        return preg_match('/^[a-f0-9]{24}$/i', $id) === 1;
    }

    /**
     * Validate database name
     */
    public static function validateDatabaseName($name)
    {
        if (empty($name) || ! is_string($name)) {
            return false;
        }

        // MongoDB database name restrictions
        // Cannot contain: / \ . " $ * < > : | ? null character
        // Cannot be longer than 64 characters
        if (strlen($name) > 64) {
            return false;
        }

        // Check for invalid characters
        $invalidChars = ['/', '\\', '.', '"', '$', '*', '<', '>', ':', '|', '?', chr(0), ' '];
        foreach ($invalidChars as $char) {
            if (strpos($name, $char) !== false) {
                return false;
            }
        }

        // Cannot be empty string
        if ($name === '') {
            return false;
        }

        // System databases that should not be accessed
        $systemDbs = ['admin', 'config', 'local'];
        if (in_array(strtolower($name), $systemDbs)) {
            return false;
        }

        return true;
    }

    /**
     * Validate collection name
     */
    public static function validateCollectionName($name)
    {
        if (empty($name) || ! is_string($name)) {
            return false;
        }

        // Cannot start with "system."
        if (strpos($name, 'system.') === 0) {
            return false;
        }

        // Cannot contain null character
        if (strpos($name, chr(0)) !== false) {
            return false;
        }

        // Cannot contain '$' unless it's a special collection
        if (strpos($name, '$') !== false && strpos($name, 'oplog.$') !== 0) {
            return false;
        }

        // Cannot be empty
        if ($name === '' || $name === '.') {
            return false;
        }

        // Maximum length
        $fullName = 'database.'.$name;
        if (strlen($fullName) > 120) {
            return false;
        }

        return true;
    }

    /**
     * Escape output for display
     */
    public static function escape($output)
    {
        if (is_array($output) || is_object($output)) {
            return array_map([self::class, 'escape'], (array) $output);
        }

        return htmlspecialchars($output, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Validate page number for pagination
     */
    public static function validatePageNumber($page)
    {
        if (! is_numeric($page)) {
            return 1;
        }

        $page = (int) $page;

        if ($page < 1) {
            return 1;
        }

        // Maximum page limit to prevent abuse
        if ($page > 10000) {
            return 10000;
        }

        return $page;
    }

    /**
     * Generate secure random token
     */
    public static function generateToken($length = 32)
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Validate URL parameter
     */
    public static function validateUrlParam($param, $allowedValues = [])
    {
        $param = self::sanitize($param);

        if (! empty($allowedValues) && ! in_array($param, $allowedValues)) {
            return false;
        }

        return $param;
    }

    /**
     * Clean and validate JSON string
     */
    public static function validateJson($jsonString)
    {
        if (! is_string($jsonString)) {
            return false;
        }

        json_decode($jsonString);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Sanitize filename
     */
    public static function sanitizeFilename($filename)
    {
        // Remove any path components
        $filename = basename($filename);

        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }

        return $filename;
    }

    /**
     * Check for common XSS patterns
     */
    public static function hasXssPattern($input)
    {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i', // onclick, onload, etc.
            '/<iframe/i',
            '/<embed/i',
            '/<object/i',
            '/data:text\/html/i',
            '/vbscript:/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rate limiting check (basic implementation)
     */
    public static function checkRateLimit($identifier, $maxAttempts = 100, $window = 60)
    {
        $key = 'rate_limit_'.md5($identifier);

        if (! isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'window_start' => time(),
            ];
        }

        $data = &$_SESSION[$key];

        // Reset window if expired
        if (time() - $data['window_start'] > $window) {
            $data['count'] = 0;
            $data['window_start'] = time();
        }

        $data['count']++;

        if ($data['count'] > $maxAttempts) {
            return false; // Rate limit exceeded
        }

        return true;
    }

    /**
     * Validate referrer for CSRF protection
     */
    public static function validateReferrer()
    {
        if (! isset($_SERVER['HTTP_REFERER'])) {
            return true; // Allow if no referrer (some browsers don't send it)
        }

        $referrer = parse_url($_SERVER['HTTP_REFERER']);
        $current = parse_url('http://'.$_SERVER['HTTP_HOST']);

        return $referrer['host'] === $current['host'];
    }
}
