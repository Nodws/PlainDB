<?php
require_once 'pdb.php';
echo "<pre>";
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
?>