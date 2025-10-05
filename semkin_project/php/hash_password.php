<?php
require_once 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    error_log($e->getMessage());
    die("Ошибка подключения к базе данных");
}

// Функция для обновления пароля пользователя
function updateAdminPassword($conn, $username, $plain_password) {
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
    $sql = "UPDATE admins SET password = ?, role = 'Administrator' WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $hashed_password, $username);
    
    if ($stmt->execute()) {
        echo "Пароль и роль для пользователя '$username' успешно обновлены.\n";
    } else {
        echo "Ошибка при обновлении пароля для пользователя '$username'.\n";
    }
}
?>