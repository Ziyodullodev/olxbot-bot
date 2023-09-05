<?php

// Create or open the SQLite database file
$db = new SQLite3('users.db');

// Create a table if it doesn't exist
$query = "CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    chat_id VARCHAR(255),
    name VARCHAR(255),
    step VARCHAR(255),
    location VARCHAR(255),
    lang VARCHAR(255),
    phone_number VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP

)";
$db->exec($query);

// Close the database connection
$db->close();

echo "Database created and data added successfully.";

?>
