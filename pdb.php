<?php
class PlainDB {
    private $dataDir = 'data/';
    private $schema = [];
    private $logFile = 'data/error.log';
    private $idsFile = 'data/ids.json';

    public function __construct() {
        // Ensure data directory exists
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
        // Load schema
        $schemaFile = $this->dataDir . 'schema.json';
        if (file_exists($schemaFile)) {
            $this->schema = json_decode(file_get_contents($schemaFile), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logError("Failed to parse schema.json: " . json_last_error_msg());
            }
        } else {
            $this->logError("schema.json not found in {$this->dataDir}");
        }
        // Initialize ids.json if it doesn't exist
        if (!file_exists($this->idsFile)) {
            file_put_contents($this->idsFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    // Log errors to file
    private function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$timestamp] $message\n", FILE_APPEND);
    }

    // Get and increment ID for a table
    private function getNextId($table) {
        $ids = file_exists($this->idsFile) ? json_decode(file_get_contents($this->idsFile), true) : [];
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("Failed to parse ids.json: " . json_last_error_msg());
            $ids = [];
        }
        $currentId = isset($ids[$table]) ? (int)$ids[$table] : 0;
        $nextId = $currentId + 1;
        $ids[$table] = $nextId;

        // Write back with file locking
        $fp = fopen($this->idsFile, 'c');
        if ($fp === false) {
            $this->logError("Failed to open ids.json for writing.");
            throw new Exception("Failed to open ids.json for writing.");
        }
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, json_encode($ids, JSON_PRETTY_PRINT));
            flock($fp, LOCK_UN);
        } else {
            $this->logError("Could not lock file 'ids.json'.");
            throw new Exception("Could not lock file 'ids.json'.");
        }
        fclose($fp);

        return $nextId;
    }

    // Validate data against schema
    private function validateData($table, $data) {
        if (!isset($this->schema['tables'][$table])) {
            $this->logError("Table '$table' not defined in schema.");
            throw new Exception("Table '$table' not defined in schema.");
        }
        foreach ($this->schema['tables'][$table]['fields'] as $field => $type) {
            if ($field === 'id') {
                continue; // Skip id validation as it's set by getNextId
            }
            if (isset($data[$field]) && gettype($data[$field]) !== $type) {
                $this->logError("Field '$field' in table '$table' must be of type '$type'.");
                throw new Exception("Field '$field' in table '$table' must be of type '$type'.");
            }
        }
        return true;
    }

    // Insert a new document
    public function insert($table, $data) {
        $file = $this->dataDir . $table . '.json';
        $data['id'] = $this->getNextId($table); // Assign auto-incremental ID
        try {
            $this->validateData($table, $data);
        } catch (Exception $e) {
            $this->logError("Validation failed for table '$table': " . $e->getMessage());
            throw $e;
        }

        // Load existing data
        $records = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("Failed to parse $file: " . json_last_error_msg());
            $records = [];
        }
        if (!is_array($records)) {
            $records = [];
        }

        // Append new record
        $records[] = $data;

        // Write back with file locking for atomicity
        $fp = fopen($file, 'c');
        if ($fp === false) {
            $this->logError("Failed to open $file for writing.");
            throw new Exception("Failed to open $file for writing.");
        }
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, json_encode($records, JSON_PRETTY_PRINT));
            flock($fp, LOCK_UN);
        } else {
            $this->logError("Could not lock file '$file'.");
            throw new Exception("Could not lock file '$file'.");
        }
        fclose($fp);

        return $data['id'];
    }

    // Query documents with optional filtering
    public function query($table, $filter = []) {
        $file = $this->dataDir . $table . '.json';
        if (!file_exists($file)) {
            $this->logError("Table file '$file' does not exist.");
            return [];
        }
        $records = json_decode(file_get_contents($file), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("Failed to parse $file: " . json_last_error_msg());
            return [];
        }
        if (!is_array($records)) {
            return [];
        }

        // Apply filters
        if (!empty($filter)) {
            $records = array_filter($records, function($record) use ($filter) {
                foreach ($filter as $field => $condition) {
                    if (is_array($condition)) {
                        $operator = key($condition);
                        $value = $condition[$operator];
                        switch ($operator) {
                            case 'gt':
                                if (!isset($record[$field]) || $record[$field] <= $value) {
                                    return false;
                                }
                                break;
                            case 'lt':
                                if (!isset($record[$field]) || $record[$field] >= $value) {
                                    return false;
                                }
                                break;
                            case 'eq':
                                if (!isset($record[$field]) || $record[$field] !== $value) {
                                    return false;
                                }
                                break;
                            default:
                                $this->logError("Unsupported operator '$operator' in filter for field '$field'.");
                                return false;
                        }
                    } else {
                        if (!isset($record[$field]) || $record[$field] !== $condition) {
                            return false;
                        }
                    }
                }
                return true;
            });
            $records = array_values($records);
        }

        return $records;
    }

    // Query a single document by ID
    public function get($table, $id) {
        $records = $this->query($table);
        foreach ($records as $record) {
            if ($record['id'] === $id) {
                return $record;
            }
        }
        return null;
    }

    // Update a document (patch)
    public function patch($table, $id, $data) {
        $file = $this->dataDir . $table . '.json';
        try {
            $this->validateData($table, $data);
        } catch (Exception $e) {
            $this->logError("Validation failed for table '$table': " . $e->getMessage());
            throw $e;
        }
        $records = $this->query($table);

        foreach ($records as &$record) {
            if ($record['id'] === $id) {
                $record = array_merge($record, $data);
                break;
            }
        }

        // Write back with file locking
        $fp = fopen($file, 'c');
        if ($fp === false) {
            $this->logError("Failed to open $file for writing.");
            throw new Exception("Failed to open $file for writing.");
        }
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, json_encode($records, JSON_PRETTY_PRINT));
            flock($fp, LOCK_UN);
        } else {
            $this->logError("Could not lock file '$file'.");
            throw new Exception("Could not lock file '$file'.");
        }
        fclose($fp);
    }

    // Delete a document
    public function delete($table, $id) {
        $file = $this->dataDir . $table . '.json';
        $records = $this->query($table);
        $newRecords = array_filter($records, function($record) use ($id) {
            return $record['id'] !== $id;
        });

        // Write back with file locking
        $fp = fopen($file, 'c');
        if ($fp === false) {
            $this->logError("Failed to open $file for writing.");
            throw new Exception("Failed to open $file for writing.");
        }
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, json_encode(array_values($newRecords), JSON_PRETTY_PRINT));
            flock($fp, LOCK_UN);
        } else {
            $this->logError("Could not lock file '$file'.");
            throw new Exception("Could not lock file '$file'.");
        }
        fclose($fp);
    }
}
?>