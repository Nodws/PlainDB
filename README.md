# PlainDB - A Simple PHP Text-File Database

PlainDB is a lightweight, file-based database system inspired by the Convex database, implemented using PHP and JSON text files. It mimics basic database operations like insert, query, update, and delete, storing data in JSON files within a `data/` directory. This project is designed for small-scale applications where a full database is not available or is overkill.

## Features
- **Document Storage**: Stores data as JSON documents in text files, with one file per "table" (e.g., `users.json`, `posts.json`).
- **Schema Validation**: Enforces basic field type validation using a `schema.json` file.
- **CRUD Operations**: Supports `insert`, `query`, `get`, `patch`, and `delete` methods, similar to Convex's `db` API.
- **Atomic Writes**: Uses PHP's file locking (`flock`) to ensure atomic writes, simulating transactional mutations.
- **Simple Setup**: No external database required; uses text files for storage.

## Limitations
- No real-time sync or WebSocket support (unlike Convex).
- Limited scalability due to text file storage.
- Basic query capabilities (no complex filtering or joins).
- No built-in authentication or access control.
- Not suitable for high-concurrency or large datasets.

## Installation

### Prerequisites
- PHP 7.4 or higher.
- A web server (e.g., Apache, Nginx) with PHP enabled.
- Write permissions for the `data/` directory.

### Setup
1. **Clone the Repository**:
   ```bash
   git clone https://github.com/nodws/plaindb.git
   cd plaindb
   ```

2. **Set Up Directory Permissions**:
   Ensure the `data/` directory is writable by the web server:
   ```bash
   chmod -R 0755 data/
   ```

3. **Copy Files**:
   Place the project files in your web server’s document root (e.g., `/var/www/html/` for Apache).

4. **Test the Database**:
   - Ensure `schema.json` is in the `data/` directory with your table definitions.
   - Run `tests.php` via a web browser or CLI to test CRUD operations:
     ```bash
     php tests.php
     ```

## Directory Structure
```
plaindb/
├── data/
│   ├── schema.json       // Defines tables and field types
│   ├── ids.json         // Tracks last used ID per table
│   ├── users.json       // Table for user data
│   ├── posts.json       // Table for post data
│   ├── error.log        // Error log file
├── pdb.php         // Core PlainDB class
├── tests.php            // Example usage script
├── README.md            // This file
```

## Usage
The `PlainDB` class provides methods to interact with the database. Below is an example from `tests.php`:

```php
require_once 'pdb.php';

try {
    $db = new PlainDB();

    // Insert multiple users to test auto-incremental IDs
    echo "Attempting to insert users...\n";
    $userId1 = $db->insert('users', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30
    ]);
    echo "Inserted user with ID: $userId1\n";

    $userId2 = $db->insert('users', [
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'age' => 25
    ]);
    echo "Inserted user with ID: $userId2\n";

    // Verify users.json content
    $users = $db->query('users');
    echo "Content of users.json:\n";
    print_r($users);

    // Insert a post
    echo "Attempting to insert post...\n";
    $postId = $db->insert('posts', [
        'title' => 'My First Post',
        'body' => 'This is a test post.',
        'author' => $userId1
    ]);
    echo "Inserted post with ID: $postId\n";

    // Test query filtering
    echo "Querying users with age > 25:\n";
    $filteredUsers = $db->query('users', ['age' => ['gt' => 25]]);
    print_r($filteredUsers);

    // Delete a user to test ID persistence
    echo "Deleting user with ID: $userId1...\n";
    $db->delete('users', $userId1);
    echo "All users after deletion:\n";
    print_r($db->query('users'));

    // Insert another user to verify ID increment
    echo "Inserting new user after deletion...\n";
    $userId3 = $db->insert('users', [
        'name' => 'Alice Brown',
        'email' => 'alice@example.com',
        'age' => 28
    ]);
    echo "Inserted new user with ID: $userId3\n";
    echo "All users after new insert:\n";
    print_r($db->query('users'));

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Check data/error.log for details.\n";
}
```

### Schema Example (`data/schema.json`)
```json
{
  "tables": {
    "users": {
      "fields": {
        "id": "integer",
        "name": "string",
        "email": "string",
        "age": "integer"
      }
    },
    "posts": {
      "fields": {
        "id": "integer",
        "title": "string",
        "body": "string",
        "author": "integer"
      }
    }
  }
}
```

## ToDo List
Here are suggested improvements to enhance PlainDB:

- [ ] **Support Relations**: Add support for simple document relations (e.g., linking `posts.author` to `users.id`) using foreign key-like references.
- [ ] **Improve Schema Validation**: Support more complex types (e.g., arrays, nested objects) and optional fields, similar to Convex’s schema system.
- [ ] **Transaction Log**: Implement a transaction log file to track changes and enable rollback, simulating Convex’s durability.
- [ ] **Basic Authentication**: Add user authentication (e.g., via PHP sessions or JWT) to restrict database access.
- [ ] **Indexing**: Create index files to speed up queries for large datasets, though limited by text file constraints.
- [ ] **Error Logging**: Log errors to a file instead of throwing exceptions for better debugging in production.
- [ ] **REST API**: Create a RESTful API interface to interact with PlainDB via HTTP requests, similar to Convex’s client API.
- [ ] **Backup System**: Add a mechanism to back up JSON files periodically to prevent data loss.
- [ ] **CLI Interface**: Develop a command-line interface for managing tables and data outside a web server.

## Contributing
Contributions are welcome! Please submit a pull request or open an issue on GitHub to suggest improvements or report bugs.

## License
This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Acknowledgments
Inspired by the Convex database’s document-based model and API structure.