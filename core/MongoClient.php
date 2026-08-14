<?php

/**
 * MongoDB Client Wrapper - Singleton Pattern
 */
class MongoClient
{
    private static $instance = null;

    private $client;

    private $connected = false;

    private function __construct()
    {
        try {
            // Create MongoDB client
            $this->client = new MongoDB\Driver\Manager(MONGO_URI);

            // Test connection
            $command = new MongoDB\Driver\Command(['ping' => 1]);
            $this->client->executeCommand('admin', $command);

            $this->connected = true;
        } catch (Exception $e) {
            error_log('MongoDB connection failed: '.$e->getMessage());
            throw new Exception('Unable to connect to MongoDB server');
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * List all databases
     */
    public function listDatabases()
    {
        $command = new MongoDB\Driver\Command(['listDatabases' => 1]);
        $cursor = $this->client->executeCommand('admin', $command);

        $databases = [];
        $foundDatabases = [];

        foreach ($cursor as $document) {
            if (isset($document->databases)) {
                foreach ($document->databases as $db) {
                    // Skip system databases
                    if (! in_array($db->name, ['admin', 'config', 'local'])) {
                        $databases[] = [
                            'name' => $db->name,
                            'sizeOnDisk' => $db->sizeOnDisk ?? 0,
                            'empty' => $db->empty ?? false,
                        ];
                        $foundDatabases[] = $db->name;
                    }
                }
            }
        }

        // Always include the default database even if it doesn't exist yet
        if (! in_array(MONGO_DEFAULT_DB, $foundDatabases) && MONGO_DEFAULT_DB) {
            $databases[] = [
                'name' => MONGO_DEFAULT_DB,
                'sizeOnDisk' => 0,
                'empty' => true,
            ];
        }

        // Sort by name
        usort($databases, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $databases;
    }

    /**
     * List collections in a database
     */
    public function listCollections($database)
    {
        $command = new MongoDB\Driver\Command(['listCollections' => 1]);
        $cursor = $this->client->executeCommand($database, $command);

        $collections = [];
        foreach ($cursor as $collection) {
            // Skip system collections
            if (strpos($collection->name, 'system.') !== 0) {
                $collections[] = [
                    'name' => $collection->name,
                    'type' => $collection->type ?? 'collection',
                    'options' => $collection->options ?? new stdClass,
                ];
            }
        }

        // Sort by name
        usort($collections, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $collections;
    }

    /**
     * Get a page of documents sorted by _id.
     *
     * Each row carries the lossless link id next to the converted document,
     * because conversion flattens BSON types (ObjectId, dates) into strings.
     *
     * @return array<int, array{id: string, document: array}>
     */
    public function getDocuments($database, $collection, $page = 1, $limit = 50, $sortOrder = 'desc')
    {
        $skip = ($page - 1) * $limit;
        $sortDirection = $sortOrder === 'asc' ? 1 : -1;

        $query = new MongoDB\Driver\Query(
            [],
            [
                'limit' => $limit,
                'skip' => $skip,
                'sort' => ['_id' => $sortDirection],
            ]
        );

        $cursor = $this->client->executeQuery("$database.$collection", $query);

        $rows = [];
        foreach ($cursor as $document) {
            $rows[] = [
                'id' => $this->idToParam($document->_id),
                'document' => $this->convertDocument($document),
            ];
        }

        return $rows;
    }

    /**
     * Get a single document by ID
     */
    public function getDocument($database, $collection, $id, $convert = true)
    {
        $query = new MongoDB\Driver\Query(['_id' => $this->toIdFilter($id)], ['limit' => 1]);
        $cursor = $this->client->executeQuery("$database.$collection", $query);

        foreach ($cursor as $document) {
            return $convert ? $this->convertDocument($document) : $document;
        }

        return null;
    }

    /**
     * Count documents in a collection
     */
    public function countDocuments($database, $collection)
    {
        $command = new MongoDB\Driver\Command([
            'count' => $collection,
            'query' => new stdClass,
        ]);

        $cursor = $this->client->executeCommand($database, $command);
        foreach ($cursor as $document) {
            return $document->n ?? 0;
        }

        return 0;
    }

    /**
     * Read a GridFS file document plus a cursor over its chunks in order.
     * The chunks are streamed by the caller, never buffered here.
     *
     * @return array{file: object, chunks: MongoDB\Driver\Cursor}|null
     */
    public function getGridFsFile($database, $bucket, $fileId)
    {
        $id = $this->toIdFilter($fileId);

        $query = new MongoDB\Driver\Query(['_id' => $id], ['limit' => 1]);
        $cursor = $this->client->executeQuery("$database.$bucket.files", $query);
        $file = null;
        foreach ($cursor as $doc) {
            $file = $doc;
        }

        if ($file === null) {
            return null;
        }

        $chunksQuery = new MongoDB\Driver\Query(['files_id' => $id], ['sort' => ['n' => 1]]);
        $chunks = $this->client->executeQuery("$database.$bucket.chunks", $chunksQuery);

        return ['file' => $file, 'chunks' => $chunks];
    }

    /**
     * Get collection statistics
     */
    public function getCollectionStats($database, $collection)
    {
        try {
            $command = new MongoDB\Driver\Command([
                'collStats' => $collection,
            ]);

            $cursor = $this->client->executeCommand($database, $command);
            foreach ($cursor as $stats) {
                return [
                    'count' => $stats->count ?? null,
                    'size' => $stats->size ?? null,
                    'avgObjSize' => $stats->avgObjSize ?? 0,
                    'storageSize' => $stats->storageSize ?? 0,
                    'indexes' => $stats->nindexes ?? 0,
                ];
            }

            return;
        } catch (Exception $e) {
            error_log('Failed to get collection stats: '.$e->getMessage());

            return;
        }
    }

    /**
     * Encode a raw BSON _id as canonical extended JSON for use in links.
     * Preserves every BSON type exactly, including compound keys.
     */
    public function idToParam($rawId)
    {
        return MongoDB\BSON\toCanonicalExtendedJSON(MongoDB\BSON\fromPHP(['_id' => $rawId]));
    }

    /**
     * Build an _id filter value: the inverse of idToParam(). Hand-typed ids
     * fall back to ObjectId when valid, raw string otherwise.
     */
    private function toIdFilter($id)
    {
        try {
            return MongoDB\BSON\toPHP(MongoDB\BSON\fromJSON($id))->_id;
        } catch (Exception $e) {
            // Not extended JSON produced by idToParam()
        }

        try {
            return new MongoDB\BSON\ObjectId($id);
        } catch (Exception $e) {
            return $id;
        }
    }

    /**
     * Recursively convert a BSON document to a plain array with readable values
     */
    private function convertDocument($document)
    {
        return $this->convertValue($document);
    }

    private function convertValue($value)
    {
        if ($value instanceof MongoDB\BSON\ObjectId) {
            return (string) $value;
        }
        if ($value instanceof MongoDB\BSON\UTCDateTime) {
            return $value->toDateTime()->format('Y-m-d H:i:s');
        }
        if ($value instanceof MongoDB\BSON\Binary) {
            return base64_encode($value->getData());
        }
        if ($value instanceof MongoDB\BSON\Regex) {
            return '/'.$value->getPattern().'/'.$value->getFlags();
        }
        if ($value instanceof MongoDB\BSON\Decimal128 || $value instanceof MongoDB\BSON\Int64) {
            return (string) $value;
        }
        if ($value instanceof MongoDB\BSON\Timestamp) {
            return (string) $value;
        }
        if (is_object($value) || is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->convertValue($item);
            }

            return $result;
        }

        return $value;
    }

    /**
     * Check if connected
     */
    public function isConnected()
    {
        return $this->connected;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent deserialization
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }
}
