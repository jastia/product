<?php

$servername = "localhost";  
$username = "root";      
$password = "";             
$database = "product_db"; 


$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$sql = "CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'products' is ready.";
} else {
    echo "Error creating table: " . $conn->error;
}


$conn->close();
?>

