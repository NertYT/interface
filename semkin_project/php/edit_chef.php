<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include 'db_connect.php';
header('Content-Type: text/html; charset=UTF-8');

// Устанавливаем кодировку соединения с базой данных
$conn->set_charset("utf8mb4");

// Проверка, что передан параметр ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('Ошибка: Не указан ID шефа для редактирования.'); window.location.href='chef.php';</script>";
    exit();
}

$id = (int)$_GET['id'];

// Получаем данные повара по ID
$sql = "SELECT * FROM Chef WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

// Если повар не найден
if ($result->num_rows == 0) {
    echo "<script>alert('Ошибка: Повар с таким ID не найден.'); window.location.href='chef.php';</script>";
    exit();
}

$chef = $result->fetch_assoc();
$stmt->close();

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_chef'])) {
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    
    if (!empty($name) && !empty($surname)) {
        // Обновление данных повара
        $update_sql = "UPDATE Chef SET Name = ?, Surname = ? WHERE ID = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssi", $name, $surname, $id);

        if ($stmt->execute()) {
            // Логируем изменение
            logAction($conn, "Редактирование шеф-повара", "ID: $id, Новое имя: $name, Новая фамилия: $surname");
            echo "<script>alert('Данные шеф-повара успешно обновлены!'); window.location.href='chef.php';</script>";
            exit();
        } else {
            $error_message = "Ошибка: Не удалось обновить данные шефа. " . $stmt->error;
            logAction($conn, "Ошибка редактирования шеф-повара", "ID: $id, Ошибка: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $error_message = "Ошибка: Заполните все поля корректно.";
    }
}

// Обработка AJAX-запроса для обновления
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_chef') {
    header('Content-Type: application/json');
    
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $response = ['status' => 'error', 'message' => 'Неверные данные'];
    
    if (!empty($name) && !empty($surname)) {
        $update_sql = "UPDATE Chef SET Name = ?, Surname = ? WHERE ID = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssi", $name, $surname, $id);

        if ($stmt->execute()) {
            logAction($conn, "Редактирование шеф-повара (AJAX)", "ID: $id, Новое имя: $name, Новая фамилия: $surname");
            $response = ['status' => 'success', 'message' => 'Данные успешно обновлены'];
        } else {
            $response['message'] = 'Ошибка: Не удалось обновить данные. ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['message'] = 'Заполните все поля корректно';
    }
    
    echo json_encode($response);
    exit;
}

// Обработка выхода
if (isset($_GET['action']) && $_GET['action'] === 'logout' && $_GET['confirm'] === 'yes') {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать шеф-повара | Панель админа</title>
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --primary-color: #60a5fa;
            --primary-hover: #3b82f6;
            --success-color: #34d399;
            --danger-color: #f87171;
            --warning-color: #fbbf24;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            transition: var(--transition);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            background: var(--bg-card);
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            justify-content: center;
            box-shadow: var(--shadow-sm);
        }

        .btn-icon:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .main-content {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            padding: 2rem;
            min-height: calc(100vh - 80px);
        }

        .sidebar {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            height: fit-content;
        }

        .nav-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .nav-list {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: var(--radius);
            transition: var(--transition);
            font-weight: 500;
        }

        .nav-link:hover {
            background: var(--bg-secondary);
            color: var(--primary-color);
            transform: translateX(4px);
        }

        .nav-link i {
            width: 20px;
            font-size: 1.125rem;
            opacity: 0.7;
            transition: var(--transition);
        }

        .nav-link:hover i {
            opacity: 1;
        }

        .content {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .chef-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .form-input {
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.875rem;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input[readonly] {
            background: var(--bg-secondary);
            color: var(--text-secondary);
            cursor: not-allowed;
        }

        .btn-save {
            background: var(--success-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-save:hover:not(:disabled) {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-save:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .chef-info {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .chef-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .chef-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            flex-shrink: 0;
        }

        .chef-details {
            flex: 1;
        }

        .chef-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .chef-id {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .message {
            padding: 1rem;
            border-radius: var(--radius);
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .message-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
        }

        .info-panel {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            line-height: 1.7;
        }

        .info-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .info-text {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .info-list {
            list-style: none;
        }

        .info-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-item strong {
            color: var(--text-primary);
            min-width: 180px;
            font-weight: 500;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: var(--radius);
            max-width: 450px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            transform: translateY(-20px);
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .modal-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .modal-success .modal-icon {
            background: var(--success-color);
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .modal-description {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn-modal {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            flex: 1;
            max-width: 120px;
        }

        .btn-confirm {
            background: var(--success-color);
            color: white;
        }

        .btn-confirm:hover {
            background: #059669;
        }

        .btn-cancel {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-cancel:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
        }

        footer {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
        }

        /* Адаптивность */
        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
                padding: 1rem;
                gap: 1rem;
            }

            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .header-actions {
                order: -1;
                width: 100%;
                justify-content: center;
            }

            .sidebar {
                padding: 1rem;
            }

            .chef-form {
                grid-template-columns: 1fr;
            }

            .chef-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .modal-actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="logo">
            <span class="material-icons" style="font-size: 2rem; color: var(--primary-color);">person</span>
            Редактировать шеф-повара
        </div>
        
        <div class="header-actions">
            <a href="performance.php" class="btn btn-icon" title="Мониторинг">
                <span class="material-icons">speed</span>
            </a>
            <button class="btn btn-icon" onclick="toggleTheme()" title="Переключить тему">
                <span class="material-icons" id="themeIcon">dark_mode</span>
            </button>
            <button class="btn btn-secondary" onclick="showLogoutModal()">
                <span class="material-icons">logout</span>
                Выход
            </button>
        </div>
    </header>

    <main class="main-content">
        <aside class="sidebar">
            <h3 class="nav-title">Управление</h3>
            <nav class="nav-list">
                <a href="index.php" class="nav-link">
                    <span class="material-icons">dashboard</span>
                    Главная
                </a>
                <a href="chef.php" class="nav-link">
                    <span class="material-icons">person</span>
                    Шеф-повара
                </a>
                <a href="dishes.php" class="nav-link">
                    <span class="material-icons">local_dining</span>
                    Блюда
                </a>
                <a href="ingredients.php" class="nav-link">
                    <span class="material-icons">kitchen</span>
                    Ингредиенты
                </a>
                <a href="orders.php" class="nav-link">
                    <span class="material-icons">shopping_cart</span>
                    Заказы
                </a>
                <a href="recipes.php" class="nav-link">
                    <span class="material-icons">menu_book</span>
                    Рецепты
                </a>
            </nav>

            <h3 class="nav-title" style="margin-top: 2rem;">Система</h3>
            <nav class="nav-list">
                <a href="#" class="nav-link" onclick="launchRemmina(); return false;">
                    <span class="material-icons">desktop_windows</span>
                    VNC Подключение
                </a>
                <a href="#" class="nav-link" onclick="showAddUserModal(); return false;">
                    <span class="material-icons">person_add</span>
                    Добавить пользователя
                </a>
                <a href="#" class="nav-link" onclick="showDeleteUserModal(); return false;">
                    <span class="material-icons">person_remove</span>
                    Удалить пользователя
                </a>
                <a href="unlock_admin.php" class="nav-link" target="_blank">
                    <span class="material-icons">security</span>
                    Разблокировать админа
                </a>
            </nav>
        </aside>

        <div class="content">
            <div class="page-header">
                <h1 class="page-title">Редактирование шеф-повара ID: <?php echo $id; ?></h1>
                <a href="chef.php" class="btn btn-secondary">
                    <span class="material-icons">arrow_back</span>
                    Назад к списку
                </a>
            </div>

            <div class="card">
                <?php if (isset($error_message)): ?>
                <div class="message message-error">
                    <span class="material-icons">error</span>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <!-- Информация о шефе -->
                <div class="chef-info">
                    <div class="chef-header">
                        <div class="chef-avatar">
                            <span class="material-icons">person</span>
                        </div>
                        <div class="chef-details">
                            <div class="chef-name"><?php echo htmlspecialchars($chef['Name'] . ' ' . $chef['Surname']); ?></div>
                            <div class="chef-id">ID: <?php echo $chef['ID']; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Форма редактирования -->
                <div class="form-section">
                    <h3 class="form-title">Редактировать данные</h3>
                    <form id="editChefForm" class="chef-form">
                        <div class="form-group">
                            <label class="form-label" for="chef-id">ID (нельзя изменить)</label>
                            <input type="number" id="chef-id" value="<?php echo $chef['ID']; ?>" readonly class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="chef-name">Имя</label>
                            <input type="text" id="chef-name" name="name" value="<?php echo htmlspecialchars($chef['Name']); ?>" required class="form-input" placeholder="Введите имя">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="chef-surname">Фамилия</label>
                            <input type="text" id="chef-surname" name="surname" value="<?php echo htmlspecialchars($chef['Surname']); ?>" required class="form-input" placeholder="Введите фамилию">
                        </div>
                        <button type="submit" class="btn-save" id="saveChefBtn">
                            <span class="material-icons">save</span>
                            Сохранить изменения
                        </button>
                    </form>
                    <div id="editChefResult"></div>
                </div>
            </div>

            <div class="info-panel">
                <h3 class="info-title">Информация о редактировании</h3>
                <p class="info-text">Редактируйте данные текущего шеф-повара:</p>
                
                <ul class="info-list">
                    <li class="info-item">
                        <strong>🆔 ID</strong>
                        <span>Уникальный идентификатор (нельзя изменить)</span>
                    </li>
                    <li class="info-item">
                        <strong>👤 Имя и Фамилия</strong>
                        <span>Введите новые данные в соответствующие поля</span>
                    </li>
                    <li class="info-item">
                        <strong>💾 Сохранение</strong>
                        <span>Нажмите "Сохранить изменения" для применения</span>
                    </li>
                    <li class="info-item">
                        <strong>↩️ Назад</strong>
                        <span>Вернитесь к списку шеф-поваров без сохранения</span>
                    </li>
                </ul>
            </div>
        </div>
    </main>

    <!-- Модальное окно подтверждения -->
    <div id="successModal" class="modal modal-success">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <span class="material-icons">check_circle</span>
                </div>
                <div>
                    <h3 class="modal-title">Успешно!</h3>
                </div>
            </div>
            <p class="modal-description">Данные шеф-повара успешно обновлены. Вы будете перенаправлены к списку через 2 секунды.</p>
            <div class="modal-actions">
                <button class="btn-modal btn-confirm" onclick="closeModal('successModal'); window.location.href='chef.php';">OK</button>
            </div>
        </div>
    </div>

    <!-- Модальные окна для пользователей (из index.php) -->
    <div id="logoutModal" class="modal modal-danger">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <span class="material-icons">logout</span>
                </div>
                <div>
                    <h3 class="modal-title">Выход из системы</h3>
                </div>
            </div>
            <p class="modal-description">Вы действительно хотите завершить сеанс работы? Все несохраненные данные будут потеряны.</p>
            <div class="modal-actions">
                <button class="btn-modal btn-confirm" id="confirmLogout">Выйти</button>
                <button class="btn-modal btn-cancel" id="cancelLogout">Остаться</button>
            </div>
        </div>
    </div>

    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon" style="background: var(--success-color);">
                    <span class="material-icons">person_add</span>
                </div>
                <div>
                    <h3 class="modal-title">Добавить пользователя</h3>
                </div>
            </div>
            <div class="user-list" id="userList">
                <div style="color: var(--text-secondary); text-align: center; padding: 1rem;">
                    Загрузка существующих пользователей...
                </div>
            </div>
            <form id="addUserForm" class="sql-form">
                <div class="form-group">
                    <label class="form-label" for="username">Имя пользователя</label>
                    <input type="text" id="username" name="username" class="form-input" required 
                           placeholder="Введите имя пользователя" maxlength="32">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Пароль</label>
                    <input type="password" id="password" name="password" class="form-input" required 
                           placeholder="Введите пароль" minlength="6">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-cancel" onclick="closeModal('addUserModal')">Отмена</button>
                    <button type="submit" class="btn-modal btn-confirm">Создать</button>
                </div>
            </form>
            <div id="addUserResult"></div>
        </div>
    </div>

    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon" style="background: var(--danger-color);">
                    <span class="material-icons">person_remove</span>
                </div>
                <div>
                    <h3 class="modal-title">Удалить пользователя</h3>
                </div>
            </div>
            <div class="user-list" id="deleteUserList">
                <div style="color: var(--text-secondary); text-align: center; padding: 1rem;">
                    Загрузка существующих пользователей...
                </div>
            </div>
            <form id="deleteUserForm" class="sql-form">
                <div class="form-group">
                    <label class="form-label" for="delete-username">Имя пользователя</label>
                    <input type="text" id="delete-username" name="username" class="form-input" required 
                           placeholder="Введите имя пользователя для удаления" maxlength="32">
                </div>
                <div style="color: var(--danger-color); font-size: 0.875rem; margin-top: 0.5rem;">
                    ⚠️ Это действие необратимо и удалит пользователя с сервера
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-cancel" onclick="closeModal('deleteUserModal')">Отмена</button>
                    <button type="submit" class="btn-modal btn-confirm">Удалить</button>
                </div>
            </form>
            <div id="deleteUserResult"></div>
        </div>
    </div>

    <footer>
        <p>© 2025 Семкин Иван и Щегольков Максим. Все права защищены.</p>
    </footer>

    <script>
        // Инициализация темы
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        document.getElementById('themeIcon').textContent = savedTheme === 'dark' ? 'light_mode' : 'dark_mode';

        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            document.getElementById('themeIcon').textContent = newTheme === 'dark' ? 'light_mode' : 'dark_mode';
        }

        // Автоматический выход при бездействии
        let inactivityTimer;
        const INACTIVITY_TIMEOUT = 30 * 60 * 1000; // 30 минут

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(() => {
                if (confirm('Сессия истекает из-за бездействия. Выйти из системы?')) {
                    window.location.href = '?action=logout&confirm=yes';
                }
            }, INACTIVITY_TIMEOUT);
        }

        ['load', 'mousemove', 'mousedown', 'click', 'scroll', 'keypress'].forEach(event => {
            document.addEventListener(event, resetInactivityTimer, true);
        });

        // Форма редактирования шеф-повара
        const editForm = document.getElementById('editChefForm');
        const resultDiv = document.getElementById('editChefResult');
        const saveBtn = document.getElementById('saveChefBtn');

        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'update_chef');
            
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Сохранение...';

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    resultDiv.innerHTML = `<div class="message message-success"><span class="material-icons">check_circle</span>${data.message}</div>`;
                    // Показываем модальное окно успеха
                    document.getElementById('successModal').style.display = 'flex';
                    setTimeout(() => {
                        window.location.href = 'chef.php';
                    }, 2000);
                } else {
                    resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.message}</div>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка: ${error.message}</div>`;
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<span class="material-icons">save</span> Сохранить изменения';
            }
        });

        // Модальные окна
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showLogoutModal() {
            document.getElementById('logoutModal').style.display = 'flex';
        }

        document.getElementById('confirmLogout').addEventListener('click', () => {
            window.location.href = '?action=logout&confirm=yes';
        });

        document.getElementById('cancelLogout').addEventListener('click', () => {
            closeModal('logoutModal');
        });

        // Закрытие модалок по клику вне
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });

        // VNC подключение
        function launchRemmina() {
            window.open('vnc://172.17.0.250', '_blank');
            setTimeout(() => {
                alert('Если VNC не запустился автоматически:\n\n1. Установите Remmina: sudo apt install remmina remmina-plugin-vnc\n2. Или используйте TightVNC Viewer\n3. Адрес сервера: 172.17.0.250:5900');
            }, 500);
        }

        // Управление пользователями (из index.php)
        async function fetchUserList(containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;
            
            container.innerHTML = '<div style="color: var(--text-secondary); text-align: center; padding: 1rem;"><span class="material-icons">hourglass_empty</span> Загрузка...</div>';

            try {
                const formData = new FormData();
                formData.append('action', 'get_users');

                const response = await fetch('../index.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.error) {
                    container.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.error}</div>`;
                } else if (data.users && data.users.length > 0) {
                    container.innerHTML = data.users.map(user => 
                        `<div class="user-item"><span class="material-icons" style="font-size: 16px; opacity: 0.5;">person</span>${user}</div>`
                    ).join('');
                } else {
                    container.innerHTML = '<div style="color: var(--text-secondary); text-align: center; padding: 1rem;">Пользователи не найдены</div>';
                }
            } catch (error) {
                container.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка загрузки: ${error.message}</div>`;
            }
        }

        function showAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
            fetchUserList('userList');
        }

        function showDeleteUserModal() {
            document.getElementById('deleteUserModal').style.display = 'flex';
            fetchUserList('deleteUserList');
        }

        // Формы пользователей
        document.addEventListener('DOMContentLoaded', function() {
            const addUserForm = document.getElementById('addUserForm');
            if (addUserForm) {
                addUserForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(e.target);
                    formData.append('action', 'add_user');
                    
                    const resultDiv = document.getElementById('addUserResult');
                    if (resultDiv) {
                        resultDiv.innerHTML = '<div style="color: var(--text-secondary); text-align: center;"><span class="material-icons">hourglass_empty</span> Создание...</div>';
                    }

                    try {
                        const response = await fetch('../index.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();
                        
                        if (data.status === 'success') {
                            if (resultDiv) {
                                resultDiv.innerHTML = `<div class="message message-success"><span class="material-icons">check_circle</span>${data.message}</div>`;
                            }
                            setTimeout(() => {
                                closeModal('addUserModal');
                                e.target.reset();
                                fetchUserList('userList');
                            }, 2000);
                        } else {
                            if (resultDiv) {
                                resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.message}</div>`;
                            }
                        }
                    } catch (error) {
                        if (resultDiv) {
                            resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка: ${error.message}</div>`;
                        }
                    }
                });
            }

            const deleteUserForm = document.getElementById('deleteUserForm');
            if (deleteUserForm) {
                deleteUserForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(e.target);
                    formData.append('action', 'delete_user');
                    
                    if (!confirm('Вы уверены? Это действие необратимо!')) return;
                    
                    const resultDiv = document.getElementById('deleteUserResult');
                    if (resultDiv) {
                        resultDiv.innerHTML = '<div style="color: var(--text-secondary); text-align: center;"><span class="material-icons">hourglass_empty</span> Удаление...</div>';
                    }

                    try {
                        const response = await fetch('../index.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();
                        
                        if (data.status === 'success') {
                            if (resultDiv) {
                                resultDiv.innerHTML = `<div class="message message-success"><span class="material-icons">check_circle</span>${data.message}</div>`;
                            }
                            setTimeout(() => {
                                closeModal('deleteUserModal');
                                e.target.reset();
                                fetchUserList('deleteUserList');
                            }, 2000);
                        } else {
                            if (resultDiv) {
                                resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.message}</div>`;
                            }
                        }
                    } catch (error) {
                        if (resultDiv) {
                            resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка: ${error.message}</div>`;
                        }
                    }
                });
            }

            resetInactivityTimer();
        });
    </script>
</body>
</html>