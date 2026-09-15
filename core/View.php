<?php

/**
 * Simple template rendering engine
 */
class View
{
    /**
     * Render a view template
     */
    public static function render($template, $data = [])
    {
        // Extract data variables
        extract($data, EXTR_SKIP);

        // Make Security class available in views
        $Security = 'Security';

        // Start output buffering
        ob_start();

        // Include the template file
        $templatePath = VIEWS_PATH.'/'.$template.'.php';

        if (! file_exists($templatePath)) {
            throw new Exception("View template not found: $template");
        }

        include $templatePath;

        // Get the contents and clean buffer
        $content = ob_get_clean();

        return $content;
    }

    /**
     * Render a partial view
     */
    public static function partial($name, $data = [])
    {
        return self::render('partials/'.$name, $data);
    }

    /**
     * Render layout with content
     */
    public static function renderWithLayout($content, $data = [])
    {
        // Merge content into data
        $data['content'] = $content;

        // Render the layout
        return self::render('layout', $data);
    }

    /**
     * Render JSON response
     */
    public static function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirect to URL
     */
    public static function redirect($url, $statusCode = 302)
    {
        header("Location: $url", true, $statusCode);
        exit;
    }

    /**
     * Display error page
     */
    public static function error($message, $statusCode = 500)
    {
        http_response_code($statusCode);

        echo '<div style="padding: 20px; font-family: sans-serif;">';
        echo '<h1>Error '.$statusCode.'</h1>';
        echo '<p>'.htmlspecialchars($message).'</p>';
        echo '<a href="?">Go back</a>';
        echo '</div>';
        exit;
    }

    /**
     * Format bytes to human readable
     */
    public static function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }

    /**
     * Render a UTC 'Y-m-d H:i:s' timestamp as a <time> element that
     * assets/script.js reformats into the selected timezone.
     * Escapes its own output; echo the return value raw.
     */
    public static function time($utc, $relative = false)
    {
        if ($utc === null || $utc === '' || $utc === '-') {
            return '-';
        }

        $iso = str_replace(' ', 'T', $utc).'Z';
        $relAttr = $relative ? ' data-relative="1"' : '';

        return '<time class="js-time" datetime="'.htmlspecialchars($iso, ENT_QUOTES, 'UTF-8').'"'.$relAttr.'>'
            .htmlspecialchars($utc, ENT_QUOTES, 'UTF-8').' UTC</time>';
    }

    /**
     * Truncate a string to a length in bytes.
     *
     * The cut lands mid-word on purpose. Document previews are base64 and JSON
     * at least as often as prose, and wrapping on whitespace left any value
     * without a space in it at its full length. Security::escape() replaces a
     * UTF-8 sequence cut in half rather than dropping the whole string.
     */
    public static function truncate($string, $length = 100, $append = '...')
    {
        $string = trim($string);

        if (strlen($string) <= $length) {
            return $string;
        }

        return substr($string, 0, $length).$append;
    }

    /**
     * Format JSON for display
     */
    public static function formatJson($data, $pretty = true)
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if ($pretty) {
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return json_encode($data);
    }

    /**
     * Generate a relative URL with query parameters, so the app works from
     * any subdirectory
     */
    public static function url(array $params = [])
    {
        return '?'.http_build_query($params);
    }

    /**
     * Human-readable form of a converted _id (compound keys become JSON).
     * Escape the result at output.
     */
    public static function documentId($id)
    {
        return is_scalar($id) ? (string) $id : json_encode($id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Check if current page matches
     */
    public static function isCurrentPage($action, $currentAction)
    {
        return $action === $currentAction;
    }

    /**
     * Create breadcrumb items
     */
    public static function breadcrumbs($items = [])
    {
        $breadcrumbs = [
            ['label' => 'Home', 'url' => self::url()],
        ];

        foreach ($items as $item) {
            $breadcrumbs[] = $item;
        }

        return $breadcrumbs;
    }

    /**
     * Display alert message
     */
    public static function alert($message, $type = 'info')
    {
        $types = [
            'success' => 'alert-success',
            'info' => 'alert-info',
            'warning' => 'alert-warning',
            'danger' => 'alert-danger',
            'error' => 'alert-danger',
        ];

        $class = $types[$type] ?? 'alert-info';

        return '<div class="alert '.$class.'">'.htmlspecialchars($message).'</div>';
    }
}
