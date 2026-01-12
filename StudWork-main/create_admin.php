<?php
include 'config.php';

$email = 'admin@studwork.ph';
$password = password_hash('Admin123!', PASSWORD_DEFAULT); // Choose a secure password
$role = 'admin';
$fullname = 'Administrator';

$stmt = $conn->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $fullname, $email, $password, $role);
if($stmt->execute()){
    echo "Admin user created successfully!";
}else{
    echo "Error: ".$stmt->error;
}
$stmt->close();
$conn->close();
?>
