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

// Функция для записи логов
function logAction($conn, $operation, $details = '') {
    $current_time = date('Y-m-d H:i:s');
    $user = $_SESSION['username'] ?? 'неизвестно';
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $full_details = "Пользователь: $user | IP: $ip | Детали: $details | User-Agent: $user_agent";
    
    $stmt = $conn->prepare("INSERT INTO db_logs (operation, details, timestamp) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $operation, $full_details, $current_time);
    $stmt->execute();
    $stmt->close();
}

// Логируем вход на страницу только при первом посещении
if (!isset($_SESSION['orders_page_visited'])) {
    logAction($conn, "Вход на страницу заказов");
    $_SESSION['orders_page_visited'] = true;
}

// Обработка AJAX-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'get_orders':
            $per_page = 5;
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $offset = ($page - 1) * $per_page;
            $table_filter = trim($_POST['table_filter'] ?? '');
            
            $sql = "SELECT * FROM Orders";
            $params = [];
            $types = "";
            
            if (!empty($table_filter)) {
                $sql .= " WHERE Table_Number = ?";
                $params[] = $table_filter;
                $types .= "i";
            }
            
            $sql .= " ORDER BY ID ASC LIMIT ?, ?";
            $params[] = $offset;
            $params[] = $per_page;
            $types .= "ii";
            
            $orders = [];
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $orders[] = $row;
            }
            
            // Общее количество
            $count_sql = "SELECT COUNT(*) as total FROM Orders";
            if (!empty($table_filter)) {
                $count_sql .= " WHERE Table_Number = ?";
                $count_stmt = $conn->prepare($count_sql);
                $count_stmt->bind_param("i", $table_filter);
                $count_stmt->execute();
                $total_result = $count_stmt->get_result();
                $total = $total_result->fetch_assoc()['total'];
                $count_stmt->close();
            } else {
                $total_result = $conn->query($count_sql);
                $total = $total_result->fetch_row()[0];
            }
            
            $pages = ceil($total / $per_page);
            
            echo json_encode([
                'orders' => $orders,
                'total' => $total,
                'pages' => $pages,
                'current_page' => $page,
                'table_filter' => $table_filter
            ]);
            exit;
            
        case 'get_tables':
            $tables = [];
            $result = $conn->query("SELECT DISTINCT Table_Number FROM Orders ORDER BY Table_Number");
            while ($row = $result->fetch_assoc()) {
                $tables[] = $row['Table_Number'];
            }
            echo json_encode($tables);
            exit;
            
        case 'add_order':
            $date = trim($_POST['date'] ?? '');
            $time = trim($_POST['time'] ?? '');
            $table_number = (int)$_POST['table_number'];
            $response = ['status' => 'error', 'message' => 'Неверные данные'];
            
            if (!empty($date) && !empty($time) && $table_number > 0) {
                $sql = "INSERT INTO Orders (Date, Time, Table_Number) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $date, $time, $table_number);
                
                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    logAction($conn, "Добавление заказа", "ID: $new_id, Дата: $date, Время: $time, Номер стола: $table_number");
                    $response = ['status' => 'success', 'message' => "Заказ успешно добавлен", 'new_id' => $new_id];
                } else {
                    $response['message'] = 'Ошибка: Не удалось добавить заказ. ' . $stmt->error;
                }
            } else {
                $response['message'] = 'Заполните все поля корректно';
            }
            
            echo json_encode($response);
            exit;
            
        case 'delete_order':
            $id = (int)$_POST['id'];
            $response = ['status' => 'error', 'message' => 'Неверные данные'];
            
            if ($id > 0) {
                $sql = "DELETE FROM Orders WHERE ID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    logAction($conn, "Удаление заказа", "ID: $id");
                    $response = ['status' => 'success', 'message' => "Заказ успешно удален"];
                } else {
                    $response['message'] = 'Ошибка: Не удалось удалить заказ. ' . $stmt->error;
                }
            }
            
            echo json_encode($response);
            exit;
    }
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
    <title>Заказы | Панель админа</title>
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

        .nav-link:hover, .nav-link.active {
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

        .nav-link:hover i, .nav-link.active i {
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

        .order-form {
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

        .btn-add {
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

        .btn-add:hover:not(:disabled) {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-add:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .filter-section {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            min-width: 200px;
        }

        .btn-filter {
            background: var(--primary-color);
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

        .btn-filter:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-clear {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
        }

        .btn-clear:hover {
            background: var(--bg-secondary);
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .table-container {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-card);
        }

        .orders-table th,
        .orders-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .orders-table th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .orders-table tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }

        .orders-table tbody tr:last-child td {
            border-bottom: none;
        }

        .datetime-cell {
            font-weight: 500;
        }

        .table-cell {
            background: var(--warning-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            padding: 0.5rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
        }

        .btn-edit {
            background: var(--warning-color);
            color: white;
        }

        .btn-edit:hover {
            background: #d97706;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: var(--danger-color);
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .pagination-btn:hover, .pagination-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .message {
            padding: 1rem;
            border-radius: var(--radius);
            margin-top: 1rem;
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

        .modal-danger .modal-icon {
            background: var(--danger-color);
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
            background: var(--danger-color);
            color: white;
        }

        .btn-confirm:hover {
            background: #dc2626;
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

            .order-form {
                grid-template-columns: 1fr;
            }

            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                min-width: auto;
            }

            .modal-actions {
                flex-direction: column;
            }

            .pagination {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="logo">
            <span class="material-icons" style="font-size: 2rem; color: var(--primary-color);">shopping_cart</span>
            Заказы
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
                <a href="orders.php" class="nav-link active">
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
                <h1 class="page-title">Управление заказами</h1>
            </div>

            <div class="card">
                <div class="form-section">
                    <h3 class="form-title">Добавить новый заказ</h3>
                    <form id="addOrderForm" class="order-form">
                        <div class="form-group">
                            <label class="form-label" for="order-date">Дата</label>
                            <input type="date" id="order-date" name="date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="order-time">Время</label>
                            <input type="time" id="order-time" name="time" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="order-table">Номер стола</label>
                            <input type="number" id="order-table" name="table_number" class="form-input" required min="1" placeholder="Например: 5">
                        </div>
                        <button type="submit" class="btn-add" id="addOrderBtn">
                            <span class="material-icons">add</span>
                            Добавить
                        </button>
                    </form>
                    <div id="addOrderResult"></div>
                </div>

                <div class="filter-section">
                    <div class="filter-group">
                        <label class="form-label" for="table-filter">Фильтр по номеру стола</label>
                        <select id="table-filter" class="form-select">
                            <option value="">Все столы</option>
                        </select>
                    </div>
                    <button class="btn-filter" onclick="applyFilter()">
                        <span class="material-icons">filter_list</span>
                        Применить
                    </button>
                    <button class="btn-clear" onclick="clearFilter()">
                        <span class="material-icons">clear</span>
                        Очистить
                    </button>
                </div>

                <h3 class="form-title">Список заказов</h3>
                <div class="table-container">
                    <table class="orders-table" id="ordersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Дата и время</th>
                                <th>Номер стола</th>
                                <th style="width: 120px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-secondary);">
                                    Загрузка данных...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination" id="pagination"></div>
                
                <div id="deleteOrderResult"></div>
            </div>

            <div class="info-panel">
                <h3 class="info-title">Информация об управлении</h3>
                <p class="info-text">Этот раздел позволяет полностью управлять системой заказов в ресторане.</p>
                
                <ul class="info-list">
                    <li class="info-item">
                        <strong>➕ Добавить</strong>
                        <span>Создавайте новые заказы с датой, временем и номером стола</span>
                    </li>
                    <li class="info-item">
                        <strong>🔍 Фильтрация</strong>
                        <span>Быстро находите заказы по номеру стола</span>
                    </li>
                    <li class="info-item">
                        <strong>📋 Список</strong>
                        <span>Удобный обзор всех активных и завершенных заказов</span>
                    </li>
                    <li class="info-item">
                        <strong>✏️ Редактировать</strong>
                        <span>Изменяйте детали заказов через форму редактирования</span>
                    </li>
                    <li class="info-item">
                        <strong>🗑️ Удалить</strong>
                        <span>Удаляйте заказы с подтверждением действия</span>
                    </li>
                </ul>
            </div>
        </div>
    </main>

    <!-- Модальное окно удаления -->
    <div id="deleteModal" class="modal modal-danger">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <span class="material-icons">delete</span>
                </div>
                <div>
                    <h3 class="modal-title">Удалить заказ</h3>
                </div>
            </div>
            <p class="modal-description" id="deleteModalText">Вы действительно хотите удалить этот заказ? Это действие необратимо.</p>
            <div class="modal-actions">
                <button class="btn-modal btn-cancel" onclick="closeModal('deleteModal')">Отмена</button>
                <button class="btn-modal btn-confirm" id="confirmDeleteBtn">Удалить</button>
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

        // Переменные для модального окна удаления
        let currentDeleteId = null;
        let currentTableFilter = '';

        // Инициализация номеров столов
        async function loadTables() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_tables');

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const tables = await response.json();
                const tableSelect = document.getElementById('table-filter');
                tableSelect.innerHTML = '<option value="">Все столы</option>';
                tables.forEach(table => {
                    const option = document.createElement('option');
                    option.value = table;
                    option.textContent = `Стол №${table}`;
                    tableSelect.appendChild(option);
                });
            } catch (error) {
                console.error('Ошибка загрузки номеров столов:', error);
            }
        }

        // Загрузка списка заказов
        async function loadOrders(page = 1, tableFilter = '') {
            const tableBody = document.getElementById('ordersTableBody');
            const pagination = document.getElementById('pagination');
            
            tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-secondary);"><span class="material-icons">hourglass_empty</span> Загрузка...</td></tr>';

            try {
                const formData = new FormData();
                formData.append('action', 'get_orders');
                formData.append('page', page);
                formData.append('table_filter', tableFilter);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                currentTableFilter = data.table_filter;

                if (data.orders && data.orders.length > 0) {
                    tableBody.innerHTML = data.orders.map(order => `
                        <tr>
                            <td>${order.ID}</td>
                            <td class="datetime-cell">${order.Date} ${order.Time}</td>
                            <td><span class="table-cell">Стол №${order.Table_Number}</span></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_order.php?id=${order.ID}" class="btn-action btn-edit" title="Редактировать">
                                        <span class="material-icons">edit</span>
                                    </a>
                                    <button class="btn-action btn-delete" onclick="showDeleteModal(${order.ID})" title="Удалить">
                                        <span class="material-icons">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-secondary);">Заказы не найдены</td></tr>';
                }

                // Генерация пагинации
                let paginationHtml = '';
                const range = 2;
                const start = Math.max(1, data.current_page - range);
                const end = Math.min(data.pages, data.current_page + range);

                if (start > 1) {
                    paginationHtml += `<a href="#" class="pagination-btn" onclick="loadOrders(1, '${data.table_filter}'); return false;">1</a>`;
                    if (start > 2) {
                        paginationHtml += `<span class="pagination-ellipsis">...</span>`;
                    }
                }

                for (let i = start; i <= end; i++) {
                    paginationHtml += `<a href="#" class="pagination-btn ${i == data.current_page ? 'active' : ''}" onclick="loadOrders(${i}, '${data.table_filter}'); return false;">${i}</a>`;
                }

                if (end < data.pages) {
                    if (end < data.pages - 1) {
                        paginationHtml += `<span class="pagination-ellipsis">...</span>`;
                    }
                    paginationHtml += `<a href="#" class="pagination-btn" onclick="loadOrders(${data.pages}, '${data.table_filter}'); return false;">${data.pages}</a>`;
                }

                pagination.innerHTML = paginationHtml;

            } catch (error) {
                tableBody.innerHTML = `<tr><td colspan="4" style="text-align: center;"><div class="message message-error"><span class="material-icons">error</span>Ошибка загрузки: ${error.message}</div></td></tr>`;
            }
        }

        // Форма добавления заказа
        document.getElementById('addOrderForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'add_order');
            
            const addBtn = document.getElementById('addOrderBtn');
            const resultDiv = document.getElementById('addOrderResult');
            
            addBtn.disabled = true;
            addBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Добавление...';

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    resultDiv.innerHTML = `<div class="message message-success"><span class="material-icons">check_circle</span>${data.message}</div>`;
                    e.target.reset();
                    // Устанавливаем текущую дату и время по умолчанию
                    document.getElementById('order-date').value = new Date().toISOString().split('T')[0];
                    document.getElementById('order-time').value = new Date().toTimeString().split(' ')[0].substring(0, 5);
                    loadOrders(1, currentTableFilter);
                    setTimeout(() => {
                        resultDiv.innerHTML = '';
                    }, 3000);
                } else {
                    resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.message}</div>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка: ${error.message}</div>`;
            } finally {
                addBtn.disabled = false;
                addBtn.innerHTML = '<span class="material-icons">add</span> Добавить';
            }
        });

        // Фильтрация
        function applyFilter() {
            const tableFilter = document.getElementById('table-filter').value;
            loadOrders(1, tableFilter);
        }

        function clearFilter() {
            document.getElementById('table-filter').value = '';
            loadOrders(1, '');
        }

        // Модальное окно удаления
        function showDeleteModal(id) {
            currentDeleteId = id;
            document.getElementById('deleteModalText').textContent = `Вы действительно хотите удалить заказ с ID ${id}? Это действие необратимо.`;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
            if (!currentDeleteId) return;

            const formData = new FormData();
            formData.append('action', 'delete_order');
            formData.append('id', currentDeleteId);

            const resultDiv = document.getElementById('deleteOrderResult');
            resultDiv.innerHTML = '<div style="color: var(--text-secondary); text-align: center;"><span class="material-icons">hourglass_empty</span> Удаление...</div>';

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    resultDiv.innerHTML = `<div class="message message-success"><span class="material-icons">check_circle</span>${data.message}</div>`;
                    loadOrders(1, currentTableFilter);
                    closeModal('deleteModal');
                    setTimeout(() => {
                        resultDiv.innerHTML = '';
                    }, 3000);
                } else {
                    resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.message}</div>`;
                    closeModal('deleteModal');
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка: ${error.message}</div>`;
                closeModal('deleteModal');
            }
            
            currentDeleteId = null;
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
            container.innerHTML = '<div style="color: var(--text-secondary); text-align: center; padding: 1rem;"><span class="material-icons">hourglass_empty</span> Загрузка...</div>';

            try {
                const formData = new FormData();
                formData.append('action', 'get_users');

                const response = await fetch('index.php', {
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
        document.getElementById('addUserForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'add_user');
            
            const resultDiv = document.getElementById('addUserResult');
            resultDiv.innerHTML = '<div style="color: var(--text-secondary); text-align: center;"><span class="material-icons">hourglass_empty</span> Создание...</div>';

            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.status === 'success') {
                    resultDiv.innerHTML = `<div class="message message-success"><span class="material-icons">check_circle</span>${data.message}</div>`;
                    setTimeout(() => {
                        closeModal('addUserModal');
                        e.target.reset();
                        fetchUserList('userList');
                    }, 2000);
                } else {
                    resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.message}</div>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка: ${error.message}</div>`;
            }
        });

        document.getElementById('deleteUserForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'delete_user');
            
            if (!confirm('Вы уверены? Это действие необратимо!')) return;
            
            const resultDiv = document.getElementById('deleteUserResult');
            resultDiv.innerHTML = '<div style="color: var(--text-secondary); text-align: center;"><span class="material-icons">hourglass_empty</span> Удаление...</div>';

            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.status === 'success') {
                    resultDiv.innerHTML = `<div class="message message-success"><span class="material-icons">check_circle</span>${data.message}</div>`;
                    setTimeout(() => {
                        closeModal('deleteUserModal');
                        e.target.reset();
                        fetchUserList('deleteUserList');
                    }, 2000);
                } else {
                    resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>${data.message}</div>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="message message-error"><span class="material-icons">error</span>Ошибка: ${error.message}</div>`;
            }
        });

        // Инициализация
        document.addEventListener('DOMContentLoaded', async () => {
            await loadTables();
            loadOrders(1, '');
            // Устанавливаем текущую дату и время по умолчанию
            document.getElementById('order-date').value = new Date().toISOString().split('T')[0];
            document.getElementById('order-time').value = new Date().toTimeString().split(' ')[0].substring(0, 5);
            resetInactivityTimer();
        });
    </script>
</body>
</html>