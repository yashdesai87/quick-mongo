<?php

/**
 * Security utilities for input validation and sanitization
 */
class Security
{
    /**
     * Read a request parameter as a trimmed string.
     *
     * Nothing is encoded here. Database names, collection names and ids are
     * addresses sent to MongoDB verbatim; encoding belongs at output, where
     * escape() does it.
     */
    public static function param($value)
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * Validate database name
     */
    public static function validateDatabaseName($name)
    {
        if (! is_string($name) || $name === '') {
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
        if (! is_string($name) || $name === '') {
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

        // Cannot contain '$'
        if (strpos($name, '$') !== false) {
            return false;
        }

        // Cannot start or end with a dot
        if (str_starts_with($name, '.') || str_ends_with($name, '.')) {
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
        if ($output === null) {
            return '';
        }

        if (is_array($output) || is_object($output)) {
            return array_map([self::class, 'escape'], (array) $output);
        }

        // ENT_SUBSTITUTE: a byte sequence cut in half by truncation is
        // replaced, where the default would return an empty string and lose
        // the whole value
        return htmlspecialchars((string) $output, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
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
     * Sanitize filename
     */
    public static function sanitizeFilename($filename)
    {
        // Remove any path components
        $filename = basename($filename);

        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

        // Avoid filenames consisting only of dots
        if ($filename === '.' || $filename === '..') {
            return '';
        }

        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }

        return $filename;
    }
}

