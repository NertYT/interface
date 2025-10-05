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

// Функция для получения данных администратора из БД
function getAdminByUsername($conn, $username) {
    $sql = "SELECT id, username, password, failed_attempts, is_locked, lock_until, role, last_login FROM admins WHERE username = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return false;
}

// Функция для проверки логина и пароля
function verifyAdminLogin($conn, $username, $password) {
    $admin = getAdminByUsername($conn, $username);
    
    if (!$admin) {
        return false;
    }
    
    // Проверка блокировки
    if ($admin['is_locked'] == 1) {
        // Проверяем, истекла ли блокировка
        if ($admin['lock_until'] && strtotime($admin['lock_until']) > time()) {
            return ['blocked' => true, 'unlock_time' => $admin['lock_until']];
        } else {
            // Разблокируем аккаунт, если время истекло
            unlockAdminAccount($conn, $admin['id']);
            $admin = getAdminByUsername($conn, $username);
        }
    }
    
    // Проверка пароля с использованием password_verify
    if (password_verify($password, $admin['password'])) {
        // Успешный вход - сбрасываем счетчик неудачных попыток и обновляем last_login
        resetFailedAttempts($conn, $admin['id']);
        updateLastLogin($conn, $admin['id']);
        return true;
    }
    
    // Увеличиваем счетчик неудачных попыток
    incrementFailedAttempts($conn, $admin['id']);
    
    // Проверяем, нужно ли заблокировать аккаунт
    if ($admin['failed_attempts'] + 1 >= 3) {
        lockAdminAccount($conn, $admin['id']);
        return ['blocked' => true];
    }
    
    return false;
}

// Функция для увеличения счетчика неудачных попыток
function incrementFailedAttempts($conn, $admin_id) {
    $sql = "UPDATE admins SET failed_attempts = failed_attempts + 1, last_failed_attempt = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
}

// Функция для сброса счетчика неудачных попыток
function resetFailedAttempts($conn, $admin_id) {
    $sql = "UPDATE admins SET failed_attempts = 0, last_failed_attempt = NULL WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
}

// Функция для блокировки аккаунта
function lockAdminAccount($conn, $admin_id) {
    $lock_until = date('Y-m-d H:i:s', strtotime('+24 hours')); // Блокировка на 24 часа
    $sql = "UPDATE admins SET is_locked = 1, failed_attempts = 0, lock_until = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $lock_until, $admin_id);
    $stmt->execute();
}

// Функция для разблокировки аккаунта
function unlockAdminAccount($conn, $admin_id) {
    $sql = "UPDATE admins SET is_locked = 0, failed_attempts = 0, lock_until = NULL WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
}

// Функция для обновления времени последнего входа
function updateLastLogin($conn, $admin_id) {
    $sql = "UPDATE admins SET last_login = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
}

// Функция для получения информации о блокировке
function getAccountStatus($conn, $username) {
    $admin = getAdminByUsername($conn, $username);
    if ($admin) {
        if ($admin['is_locked'] == 1 && $admin['lock_until'] && strtotime($admin['lock_until']) > time()) {
            return [
                'is_locked' => true,
                'failed_attempts' => $admin['failed_attempts'],
                'lock_until' => $admin['lock_until']
            ];
        }
        return [
            'is_locked' => false,
            'failed_attempts' => $admin['failed_attempts']
        ];
    }
    return false;
}

// Функция для добавления нового пользователя
function addAdminUser($conn, $username, $password, $role = 'User') {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO admins (username, password, role, failed_attempts, is_locked, lock_until, last_login) VALUES (?, ?, ?, 0, 0, NULL, NULL)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $hashed_password, $role);
    
    if ($stmt->execute()) {
        return ['status' => 'success', 'message' => "Пользователь $username успешно создан"];
    } else {
        return ['status' => 'error', 'message' => "Ошибка при создании пользователя: " . $stmt->error];
    }
}

function updateAdminPassword($conn, $username, $new_password) {
    try {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE username = ?");
        $stmt->execute([$hashed_password, $username]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Ошибка обновления пароля: " . $e->getMessage());
        return false;
    }
}
?>