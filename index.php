<?php

/**
 * MongoDB Browser - Main entry point
 */

// Load bootstrap
require_once __DIR__.'/config/bootstrap.php';

try {
    // Check rate limiting
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (! Security::checkRateLimit($clientIp, 100, 60)) {
        View::error('Too many requests. Please try again later.', 429);
    }

    // Get and sanitize action
    $action = Security::sanitize($_GET['action'] ?? 'databases');
    $validActions = ['databases', 'collections', 'documents'];

    if (! in_array($action, $validActions)) {
        $action = 'databases';
    }

    // Get MongoDB client instance
    $mongo = MongoClient::getInstance();

    // Initialize variables
    $pageTitle = 'MongoDB Browser';
    $breadcrumbs = [];
    $content = '';

    // Route based on action
    switch ($action) {
        case 'databases':
            // List all databases
            $databases = $mongo->listDatabases();

            $pageTitle = 'Databases';
            $breadcrumbs = View::breadcrumbs();

            $content = View::render('databases', [
                'databases' => $databases,
                'currentDatabase' => null,
            ]);
            break;

        case 'collections':
            // Get and validate database name
            $database = Security::sanitize($_GET['db'] ?? '');

            if (! Security::validateDatabaseName($database)) {
                View::error('Invalid database name', 400);
            }

            // Get collections
            $collections = $mongo->listCollections($database);

            // Add collection counts and sizes (collStats; count fallback for views/time-series)
            foreach ($collections as &$collection) {
                $stats = $mongo->getCollectionStats($database, $collection['name']);
                $collection['count'] = $stats['count'] ?? $mongo->countDocuments($database, $collection['name']);
                $collection['size'] = $stats['size'] ?? null;
            }
            unset($collection);

            $pageTitle = "Database: $database";
            $breadcrumbs = View::breadcrumbs([
                ['label' => $database, 'url' => View::url(['action' => 'collections', 'db' => $database])],
            ]);

            $content = View::render('collections', [
                'database' => $database,
                'collections' => $collections,
                'currentDatabase' => $database,
            ]);
            break;

        case 'documents':
            // Get and validate parameters
            $database = Security::sanitize($_GET['db'] ?? '');
            $collection = Security::sanitize($_GET['collection'] ?? '');
            $page = Security::validatePageNumber($_GET['page'] ?? 1);
            $sortOrder = ($_GET['sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

            if (! Security::validateDatabaseName($database)) {
                View::error('Invalid database name', 400);
            }

            if (! Security::validateCollectionName($collection)) {
                View::error('Invalid collection name', 400);
            }

            // Get total count
            $totalCount = $mongo->countDocuments($database, $collection);

            // Create paginator
            $paginator = new Paginator($totalCount, 50, $page);

            // Get documents with sort order
            $rows = $mongo->getDocuments($database, $collection, $paginator->getCurrentPage(), $paginator->getLimit(), $sortOrder);

            $pageTitle = "Collection: $collection";
            $breadcrumbs = View::breadcrumbs([
                ['label' => $database, 'url' => View::url(['action' => 'collections', 'db' => $database])],
                ['label' => $collection, 'url' => View::url(['action' => 'documents', 'db' => $database, 'collection' => $collection])],
            ]);

            $content = View::render('documents', [
                'database' => $database,
                'collection' => $collection,
                'rows' => $rows,
                'paginator' => $paginator,
                'currentDatabase' => $database,
                'sortOrder' => $sortOrder,
            ]);
            break;

    }

    // Get list of all databases for sidebar
    $allDatabases = $mongo->listDatabases();
    $currentDatabase = Security::sanitize($_GET['db'] ?? '') ?: null;

    // Render with layout
    echo View::renderWithLayout($content, [
        'title' => $pageTitle,
        'breadcrumbs' => $breadcrumbs,
        'databases' => $allDatabases,
        'currentDatabase' => $currentDatabase,
        'currentAction' => $action,
    ]);
} catch (Exception $e) {
    // Log error
    error_log('MongoDB Browser Error: '.$e->getMessage());

    // Display user-friendly error
    $message = 'An error occurred while processing your request.';

    // Show detailed error in debug mode
    if (APP_DEBUG) {
        $message .= '<br><br><strong>Debug Info:</strong><br>'.$e->getMessage();
    }

    View::error($message, 500);
}
