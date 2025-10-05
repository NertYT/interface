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

// Получение данных ингредиента для редактирования
$id = $_GET['id'];
$sql = "SELECT * FROM Ingredient WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$ingredient = $result->fetch_assoc();
$stmt->close();

if (!$ingredient) {
    die("Ингредиент не найден.");
}

// Обработка обновления ингредиента
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_ingredient'])) {
    $name = $_POST['name'];
    $unit = $_POST['unit'];
    $stock_quantity = $_POST['stock_quantity'];
    $ingredient_dish = $_POST['ingredient_dish'];

    $sql = "UPDATE Ingredient SET Name = ?, Unit = ?, Stock_Quantity = ?, Ingredient_Dish = ? WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisi", $name, $unit, $stock_quantity, $ingredient_dish, $id);

    if ($stmt->execute()) {
        header("Location: ingredients.php");
        exit();
    } else {
        echo "<p style='color: red;'>Ошибка: Не удалось обновить ингредиент. Детали ошибки: " . $stmt->error . "</p>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать ингредиент | Кухня</title>
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
        input[type="text"], input[type="number"], textarea {
            padding: 10px;
            font-size: 16px;
            border: 2px solid #0077ff;
            border-radius: 8px;
        }
        textarea {
            height: 100px;
            resize: none;
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
            <h1>Редактировать ингредиент</h1>
            <a href="ingredients.php" class="back-btn action-btn"><span class="material-icons">arrow_back</span></a>

            <form method="POST">
                <input type="text" name="name" placeholder="Название ингредиента" value="<?php echo htmlspecialchars($ingredient['Name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <input type="text" name="unit" placeholder="Единица измерения" value="<?php echo htmlspecialchars($ingredient['Unit'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <input type="number" name="stock_quantity" placeholder="Количество на складе" value="<?php echo $ingredient['Stock_Quantity']; ?>" required>
                <textarea name="ingredient_dish" placeholder="Ингредиент блюда" required><?php echo htmlspecialchars($ingredient['Ingredient_Dish'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                <a href="#" class="action-btn" onclick="this.closest('form').submit(); return false;"><span class="material-icons">save</span></a>
                <input type="hidden" name="update_ingredient" value="1">
            </form>
        </div>
    </div>

    <footer>
        <p>© Семкин Иван и Щегольков Максим. Все права защищены.</p>
    </footer>
</body>
</html>