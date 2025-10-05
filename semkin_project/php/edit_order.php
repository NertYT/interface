<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include 'db_connect.php';
header('Content-Type: text/html; charset=UTF-8');

$conn->set_charset("utf8mb4");

// Проверка, что передан параметр ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<p style='color: red;'>Ошибка: Не указан ID заказа для редактирования.</p>";
    exit();
}

$id = $_GET['id'];

// Получаем данные заказа по ID
$sql = "SELECT * FROM Orders WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

// Если заказ не найден
if ($result->num_rows == 0) {
    echo "<p style='color: red;'>Ошибка: Заказ с таким ID не найден.</p>";
    exit();
}

$order = $result->fetch_assoc();
$stmt->close();

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_order'])) {
    $date = $_POST['date'];
    $time = $_POST['time'];
    $table_number = $_POST['table_number'];

    $update_sql = "UPDATE Orders SET Date = ?, Time = ?, Table_Number = ? WHERE ID = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssii", $date, $time, $table_number, $id);

    if ($stmt->execute()) {
        header("Location: orders.php");
        exit();
    } else {
        echo "<p style='color: red;'>Ошибка: Не удалось обновить заказ. Детали ошибки: " . $stmt->error . "</p>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать заказ | Кухня</title>
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: radial-gradient(circle at center, #66b2ff, #c0c0ff);
            margin: 0;
            padding: 0;
            color: #333;
            display: flex;
            min-height: 100vh;
        }
        .main-container {
            display: flex;
            width: 100%;
            max-width: 1400px;
            margin: 50px auto;
            gap: 20px;
        }
        .container {
            flex: 1;
            padding: 30px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(15px);
        }
        h1 {
            text-align: center;
            color: #444;
            font-size: 36px;
            margin-bottom: 40px;
            font-weight: bold;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
            margin: 0 auto;
        }
        input[type="date"], input[type="time"], input[type="number"] {
            padding: 10px;
            font-size: 16px;
            border: 2px solid #0077ff;
            border-radius: 8px;
        }
        input[readonly] {
            background-color: #f0f0f0;
            cursor: not-allowed;
        }
        a, .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle, #66b2ff, #3399ff);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        a:hover, .action-btn:hover {
            background: radial-gradient(circle, #3399ff, #0077ff);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
        }
        .back-btn {
            margin-bottom: 20px;
            display: inline-block;
        }
        footer {
            text-align: center;
            font-size: 14px;
            color: #000;
            padding: 10px 0;
            position: fixed;
            width: 100%;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0);
        }
        footer p {
            margin: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="container">
            <h1>Редактировать заказ</h1>
            <a href="orders.php" class="back-btn action-btn"><span class="material-icons">arrow_back</span></a>

            <form method="POST">
                <input type="date" name="date" value="<?php echo $order['Date']; ?>" required>
                <input type="time" name="time" value="<?php echo $order['Time']; ?>" required>
                <input type="number" name="table_number" placeholder="Номер стола" value="<?php echo $order['Table_Number']; ?>" required>
                <a href="#" class="action-btn" onclick="this.closest('form').submit(); return false;"><span class="material-icons">save</span></a>
                <input type="hidden" name="edit_order" value="1">
            </form>
        </div>
    </div>

    <footer>
        <p>© Семкин Иван и Щегольков Максим. Все права защищены.</p>
    </footer>
</body>
</html>