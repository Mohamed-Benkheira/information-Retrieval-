<?php
function connectDatabase()
{
    $host = '127.0.0.1'; // Change to your host
    $dbname = 'positional_index'; // Database name
    $username = 'root'; // Your MySQL username
    $password = ''; // Your MySQL password

    try {
        $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
?>