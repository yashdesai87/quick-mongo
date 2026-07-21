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
