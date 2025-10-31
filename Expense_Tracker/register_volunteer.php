<?php
header('Content-Type: application/json');
include './Includes/Functions/functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $age = $_POST['age'] ?? '';
    $address = $_POST['address'] ?? '';
    $img = '';
    if (isset($_FILES['id_image']) && $_FILES['id_image']['error'] == 0) {
        $img = 'uploads/' . uniqid() . '_' . $_FILES['id_image']['name'];
        move_uploaded_file($_FILES['id_image']['tmp_name'], $img);
    }
    // Lưu vào bảng volunteer_register (tạo bảng này nếu chưa có)
    $sql = "INSERT INTO volunteer_register (name, age, address, id_image) VALUES ('$name', '$age', '$address', '$img')";
    $success = mysqli_query($db, $sql);
    echo json_encode(['success' => $success]);
}
?>