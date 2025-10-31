<?php
session_start();
include './Includes/Functions/functions.php';

$user = $_POST['user']; // ✅ sửa lại từ $params

if (empty($user['username']) || empty($user['password'])) {
    $_SESSION['ERROR'] = "Please enter all fields";
    header("Location: login.php");
    die;
}

$username = $user['username'];
$password = $user['password'];

$conn = mysqli_connect("localhost", "root", "", "expense_tracker");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ✅ dùng prepared statement để tránh SQL injection
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
$stmt->bind_param("ss", $username, $password);
$stmt->execute();
$result = $stmt->get_result();
$check = $result->fetch_assoc();

mysqli_close($conn);

if (empty($check)) {
    $_SESSION['ERROR'] = "Invalid Username or Password";
    header("Location: login.php");
    die;
} else {
    $_SESSION['user_id'] = $check['id']; // ✅ đồng bộ với index.php
    header("Location: index.php");
    die;
}
?>
