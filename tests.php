<?php
require_once 'pdb.php';

try {
    $db = new PlainDB();

    // Insert a new user
    $userId = $db->insert('users', [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);
    echo "Inserted user with ID: $userId\n";

    // Insert a new post
    $postId = $db->insert('posts', [
        'title' => 'My First Post',
        'body' => 'This is a test post.',
        'author' => $userId
    ]);
    echo "Inserted post with ID: $postId\n";

    // Query all users
    $users = $db->query('users');
    echo "All users:\n";
    print_r($users);

    // Get a single user
    $user = $db->get('users', $userId);
    echo "Single user:\n";
    print_r($user);

    // Update a post
    $db->patch('posts', $postId, [
        'body' => 'Updated post content.'
    ]);
    echo "Updated post:\n";
    print_r($db->get('posts', $postId));

    // Delete a user
    $db->delete('users', $userId);
    echo "Deleted user with ID: $userId\n";
    echo "All users after deletion:\n";
    print_r($db->query('users'));

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>