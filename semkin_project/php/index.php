<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include 'db_connect.php';

// Функция проверки на опасные команды
function isDangerousQuery($sql) {
    $sql = trim(strtoupper($sql));
    if (preg_match('/\bDROP\b/i', $sql)) return true;
    if (preg_match('/\bDELETE\b/i', $sql) && !preg_match('/\bWHERE\b/i', $sql)) return true;
    return false;
}

// Функция для получения списка пользователей из базы данных
function getUserListFromDB($conn) {
    $sql = "SELECT username, role, last_login FROM admins";
    $result = $conn->query($sql);
    $users = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        return ['users' => $users];
    } else {
        return ['error' => "Ошибка при получении списка пользователей: " . $conn->error];
    }
}

// Обработка AJAX-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_users':
            $response = getUserListFromDB($conn);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
            
        case 'add_user':
            // Restrict to Administrators
            if ($_SESSION['role'] !== 'Administrator') {
                $response = ['status' => 'error', 'message' => 'Доступ запрещен: требуется роль администратора'];
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
            
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $role = trim($_POST['role'] ?? 'User');
            $response = ['status' => 'error', 'message' => 'Неверные данные'];
            
            if (!empty($username) && !empty($password) && preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                // Проверяем, существует ли пользователь
                if (getAdminByUsername($conn, $username)) {
                    $response['message'] = "Пользователь $username уже существует";
                } else {
                    // Добавляем пользователя в локальную БД
                    $response = addAdminUser($conn, $username, $password, $role);
                }
            } else {
                $response['message'] = 'Имя пользователя может содержать только буквы, цифры и подчеркивания, пароль не должен быть пустым';
            }
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
            
        case 'delete_user':
            // Restrict to Administrators
            if ($_SESSION['role'] !== 'Administrator') {
                $response = ['status' => 'error', 'message' => 'Доступ запрещен: требуется роль администратора'];
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
            
            $username = trim($_POST['username'] ?? '');
            $response = ['status' => 'error', 'message' => 'Неверные данные'];
            
            if (!empty($username) && preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                // Проверяем, чтобы пользователь не удалял самого себя
                if ($username === $_SESSION['username']) {
                    $response['message'] = 'Нельзя удалить собственную учетную запись';
                } else {
                    // Удаляем пользователя из локальной БД
                    $sql = "DELETE FROM admins WHERE username = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $username);
                    
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $response = ['status' => 'success', 'message' => "Пользователь $username успешно удален из базы данных"];
                        } else {
                            $response['message'] = "Пользователь $username не найден в базе данных";
                        }
                    } else {
                        $response['message'] = "Ошибка при удалении пользователя: " . $conn->error;
                    }
                }
            } else {
                $response['message'] = 'Имя пользователя может содержать только буквы, цифры и подчеркивания';
            }
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
    }
}

// Обработка скачивания файла
if (isset($_GET['download'])) {
    // Restrict to Administrators
    if ($_SESSION['role'] !== 'Administrator') {
        header('HTTP/1.1 403 Forbidden');
        echo "Доступ запрещен: требуется роль администратора";
        exit;
    }
    
    $sql = $_SESSION['last_sql_query'] ?? '';
    if ($sql && ($result = $conn->query($sql)) && $result !== true) {
        $fields = [];
        while ($field = $result->fetch_field()) {
            $fields[] = $field->name;
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        if ($_GET['download'] === 'csv') {
            $csv_content = implode(",", array_map(fn($field) => '"' . str_replace('"', '""', $field) . '"', $fields)) . "\n";
            foreach ($rows as $row) {
                $csv_content .= implode(",", array_map(fn($cell) => '"' . str_replace('"', '""', preg_replace('/\s*\n\s*/', '; ', trim($cell ?? ''))) . '"', $row)) . "\n";
            }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="query_result_' . date('Y-m-d_H-i-s') . '.csv"');
            echo "\xEF\xBB\xBF"; // BOM для UTF-8
            echo $csv_content;
        } elseif ($_GET['download'] === 'xlsx') {
            $html_content = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Query Result</title></head><body><table border='1' style='border-collapse:collapse;'>";
            $html_content .= "<tr>";
            foreach ($fields as $field) {
                $html_content .= "<th style='background:#f0f0f0;padding:8px;border:1px solid #ddd;'>" . htmlspecialchars($field) . "</th>";
            }
            $html_content .= "</tr>";
            foreach ($rows as $row) {
                $html_content .= "<tr>";
                foreach ($row as $cell) {
                    $html_content .= "<td style='padding:8px;border:1px solid #ddd;'>" . htmlspecialchars($cell ?? '') . "</td>";
                }
                $html_content .= "</tr>";
            }
            $html_content .= "</table></body></html>";
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="query_result_' . date('Y-m-d_H-i-s') . '.xls"');
            header('Cache-Control: max-age=0');
            echo $html_content;
        }
    }
    exit;
}

// Обработка выхода
if (isset($_GET['action']) && $_GET['action'] === 'logout' && $_GET['confirm'] === 'yes') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Обработка SQL-запроса
if (isset($_POST['sql_query'])) {
    // Restrict to Administrators
    if ($_SESSION['role'] !== 'Administrator') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Доступ запрещен: требуется роль администратора']);
        exit;
    }
    
    $sql = trim($_POST['sql_query']);
    $is_confirmed = $_POST['confirmed'] === 'true';
    $_SESSION['last_sql_query'] = $sql;
    
    header('Content-Type: application/json');
    
    if (empty($sql)) {
        echo json_encode(['status' => 'error', 'message' => 'Пожалуйста, введите SQL-запрос']);
        exit;
    }
    
    if (isDangerousQuery($sql) && !$is_confirmed) {
        echo json_encode(['status' => 'warning', 'message' => 'Внимание: запрос содержит потенциально опасные команды. Подтвердите выполнение.']);
        exit;
    }
    
    if (preg_match('/^\s*CREATE\s+TABLE\s+\w+\s*(?:\(|$)/i', $sql) && !preg_match('/\(/', $sql)) {
        echo json_encode(['status' => 'error', 'message' => 'Некорректный синтаксис CREATE TABLE. Укажите структуру таблицы.']);
        exit;
    }
    
    try {
        if ($result = $conn->query($sql)) {
            if ($result === true) {
                echo json_encode(['status' => 'success', 'message' => 'Запрос успешно выполнен']);
            } else {
                ob_start();
                echo '<div class="result-table">';
                echo '<table><thead><tr>';
                $fields = [];
                while ($field = $result->fetch_field()) {
                    $fields[] = $field->name;
                    echo '<th>' . htmlspecialchars($field->name) . '</th>';
                }
                echo '</tr></thead><tbody>';
                
                $row_count = 0;
                while ($row = $result->fetch_assoc()) {
                    echo '<tr>';
                    foreach ($row as $cell) {
                        echo '<td>' . htmlspecialchars($cell ?? '') . '</td>';
                    }
                    echo '</tr>';
                    $row_count++;
                    if ($row_count > 1000) break; // Ограничение для производительности
                }
                echo '</tbody></table>';
                
                if ($row_count > 1000) {
                    echo '<p class="table-note">Показаны первые 1000 строк. <a href="?download=csv">Скачать все данные</a></p>';
                } else {
                    echo '<div class="download-links">';
                    echo '<a href="?download=csv" class="download-btn">📥 CSV</a>';
                    echo '<a href="?download=xlsx" class="download-btn">📊 Excel</a>';
                    echo '</div>';
                }
                echo '</div>';
                
                $message = ob_get_clean();
                echo json_encode(['status' => 'success', 'message' => $message]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Ошибка SQL: ' . $conn->error]);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Ошибка выполнения: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель админа | Кухня</title>
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="css/styles_index.css">
    <script src="js/scripts_index.js" defer></script>
</head>

<body>
    <header class="header">
        <div class="logo">
            <span class="material-icons" style="font-size: 2rem; color: var(--primary-color);">restaurant</span>
            Панель админа
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
                    Разблокировать пользователей
                </a>
            </nav>

            <div class="console-card">
                <div class="console-header">
                    <h3 class="console-title">SQL Консоль</h3>
                    <button class="console-toggle" onclick="toggleConsole()">
                        <span class="material-icons" id="consoleIcon">expand_more</span>
                        <span id="consoleText">Открыть</span>
                    </button>
                </div>
                <div class="console-content" id="consoleContent">
                    <form class="sql-form" id="sqlForm">
                        <textarea 
                            class="sql-textarea" 
                            name="sql_query" 
                            placeholder="SELECT * FROM admins; -- Введите SQL запрос"
                            rows="4"
                        ></textarea>
                        <div class="sql-buttons">
                            <button type="submit" class="btn-execute" id="executeBtn">
                                <span class="material-icons">play_arrow</span>
                                Выполнить
                            </button>
                        </div>
                    </form>
                    <div class="result-container" id="resultContainer">
                        <div style="color: var(--text-secondary);">Введите SQL запрос и нажмите "Выполнить"</div>
                    </div>
                </div>
            </div>
        </aside>

        <div class="content">
            <div class="info-panel">
                <h2 class="info-title">Добро пожаловать в панель управления</h2>
                <p class="info-text">Централизованное управление кухонной инфраструктурой и системными ресурсами.</p>
                
                <ul class="info-list">
                    <li class="info-item">
                        <strong>👨‍🍳 Шеф-повара</strong>
                        <span>Управление профилем и доступом поваров</span>
                    </li>
                    <li class="info-item">
                        <strong>🍽️ Блюда</strong>
                        <span>Каталог блюд и их характеристик</span>
                    </li>
                    <li class="info-item">
                        <strong>🥬 Ингредиенты</strong>
                        <span>Учет запасов и поставок</span>
                    </li>
                    <li class="info-item">
                        <strong>📋 Заказы</strong>
                        <span>Обработка и аналитика заказов</span>
                    </li>
                    <li class="info-item">
                        <strong>📖 Рецепты</strong>
                        <span>База рецептов и инструкций</span>
                    </li>
                    <li class="info-item">
                        <strong>🖥️ VNC</strong>
                        <span>Удаленное подключение к серверу</span>
                    </li>
                    <li class="info-item">
                        <strong>👥 Пользователи</strong>
                        <span>Управление системными учетными записями</span>
                    </li>
                    <li class="info-item">
                        <strong>🔐 Безопасность</strong>
                        <span>Разблокировка административных аккаунтов</span>
                    </li>
                </ul>
            </div>
        </div>
    </main>

    <!-- Модальные окна -->
    <div id="warningModal" class="modal modal-warning">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <span class="material-icons">warning</span>
                </div>
                <div>
                    <h3 class="modal-title">Внимание!</h3>
                </div>
            </div>
            <p class="modal-description">Этот SQL-запрос содержит потенциально опасные команды (DROP, DELETE без WHERE). Вы уверены, что хотите продолжить выполнение?</p>
            <div class="modal-actions">
                <button class="btn-modal btn-confirm" id="confirmQuery">Выполнить</button>
                <button class="btn-modal btn-cancel" id="cancelQuery">Отмена</button>
            </div>
        </div>
    </div>

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
                    <p class="modal-subtitle">Создание нового пользователя</p>
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
                <div class="form-group">
                    <label class="form-label" for="role">Роль</label>
                    <select id="role" name="role" class="form-input">
                        <option value="Administrator">Администратор</option>
                        <option value="User" selected>Пользователь</option>
                        <option value="Moderator">Модератор</option>
                    </select>
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
                    ⚠️ Это действие необратимо и удалит пользователя из базы данных
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
</body>
</html>