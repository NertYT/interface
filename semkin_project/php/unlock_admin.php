<?php
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = $_POST['admin_id'] ?? 0;
    
    if ($admin_id > 0) {
        unlockAdminAccount($conn, $admin_id);
        $message = "Аккаунт администратора ID $admin_id успешно разблокирован!";
    } else {
        $message = "Ошибка: Неверный ID администратора";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Разблокировка администратора</title>
</head>
<body>
    <h1>Разблокировка учетной записи администратора</h1>
    
    <?php if (isset($message)): ?>
        <div style="color: green; padding: 10px; border: 1px solid green; margin: 10px 0;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <label>ID администратора: <input type="number" name="admin_id" required min="1"></label><br><br>
        <button type="submit">Разблокировать</button>
    </form>
    
    <h3>Список администраторов:</h3>
    <?php
    $result = $conn->query("SELECT id, username, is_locked, failed_attempts, lock_until FROM admins ORDER BY id");
    if ($result->num_rows > 0):
    ?>
    <table border="1" style="border-collapse: collapse; margin-top: 10px;">
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Заблокирован</th>
            <th>Неудачные попытки</th>
            <th>Разблокируется</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['username']) ?></td>
            <td><?= $row['is_locked'] ? 'Да' : 'Нет' ?></td>
            <td><?= $row['failed_attempts'] ?></td>
            <td><?= $row['lock_until'] ? date('d.m.Y H:i', strtotime($row['lock_until'])) : '-' ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php endif; ?>
</body>
</html>