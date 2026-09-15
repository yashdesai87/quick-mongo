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

    // Get and validate action
    $action = Security::param($_GET['action'] ?? 'databases');
    $validActions = ['databases', 'collections', 'documents', 'document', 'download'];

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
            $database = Security::param($_GET['db'] ?? '');

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
            $database = Security::param($_GET['db'] ?? '');
            $collection = Security::param($_GET['collection'] ?? '');
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

        case 'document':
            // Get and validate parameters. The id is extended JSON from
            // MongoClient::idToParam(), so it is escaped at output, not sanitized here.
            $database = Security::param($_GET['db'] ?? '');
            $collection = Security::param($_GET['collection'] ?? '');
            $id = Security::param($_GET['id'] ?? '');

            if (! Security::validateDatabaseName($database)) {
                View::error('Invalid database name', 400);
            }

            if (! Security::validateCollectionName($collection)) {
                View::error('Invalid collection name', 400);
            }

            if ($id === '') {
                View::error('Document ID is required', 400);
            }

            // Get document
            $document = $mongo->getDocument($database, $collection, $id);

            if (! $document) {
                View::error('Document not found', 404);
            }

            $documentId = View::documentId($document->_id ?? null);

            $pageTitle = "Document: $documentId";
            $breadcrumbs = View::breadcrumbs([
                ['label' => $database, 'url' => View::url(['action' => 'collections', 'db' => $database])],
                ['label' => $collection, 'url' => View::url(['action' => 'documents', 'db' => $database, 'collection' => $collection])],
                ['label' => 'Document', 'url' => null],
            ]);

            $content = View::render('document', [
                'database' => $database,
                'collection' => $collection,
                'document' => $document,
                'documentId' => $documentId,
                'idParam' => $id,
                'currentDatabase' => $database,
            ]);
            break;

        case 'download':
            // Stream a GridFS file: the collection must be a <bucket>.files collection
            $database = Security::param($_GET['db'] ?? '');
            $collection = Security::param($_GET['collection'] ?? '');
            $id = Security::param($_GET['id'] ?? '');

            if (! Security::validateDatabaseName($database)) {
                View::error('Invalid database name', 400);
            }

            if (! Security::validateCollectionName($collection)) {
                View::error('Invalid collection name', 400);
            }

            if (substr($collection, -6) !== '.files') {
                View::error('Downloads are only available for GridFS *.files collections', 400);
            }

            $bucket = substr($collection, 0, -6);
            $file = $mongo->getGridFsFile($database, $bucket, $id);

            if ($file === null) {
                View::error('File not found', 404);
            }

            $fileDoc = $file['file'];

            $contentType = $fileDoc->contentType ?? $fileDoc->metadata->contentType ?? '';
            if (! is_string($contentType) || ! preg_match('#^[\w.+-]+/[\w.+-]+$#', $contentType)) {
                $contentType = 'application/octet-stream';
            }

            $filename = Security::sanitizeFilename($fileDoc->filename ?? '');
            if ($filename === '') {
                $filename = Security::sanitizeFilename(json_encode($fileDoc->_id)) ?: 'download';
            }

            // Release the session lock: a large file takes as long as it takes,
            // and holding it blocks every other request from the same browser
            session_write_close();

            // Send chunks straight to the client; GridFS files can exceed PHP memory
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            header('Content-Type: '.$contentType);
            if (isset($fileDoc->length)) {
                header('Content-Length: '.(int) $fileDoc->length);
            }
            header('Content-Disposition: attachment; filename="'.$filename.'"');

            foreach ($file['chunks'] as $chunk) {
                echo $chunk->data->getData();
                flush();
            }
            exit;
    }

    // Get list of all databases for sidebar
    $allDatabases = $mongo->listDatabases();
    $currentDatabase = Security::param($_GET['db'] ?? '');

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
