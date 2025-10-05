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

// Функция для записи логов (взята из performance.php)
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
if (!isset($_SESSION['chef_page_visited'])) {
    logAction($conn, "Вход на страницу шеф-поваров");
    $_SESSION['chef_page_visited'] = true;
}

// Обработка AJAX-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'get_chefs':
            $per_page = 5;
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $offset = ($page - 1) * $per_page;
            
            $sql = "SELECT * FROM Chef ORDER BY ID ASC LIMIT $offset, $per_page";
            $result = $conn->query($sql);
            $chefs = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $chefs[] = $row;
                }
            }
            
            $total = $conn->query("SELECT COUNT(*) FROM Chef")->fetch_row()[0];
            $pages = ceil($total / $per_page);
            
            echo json_encode([
                'chefs' => $chefs,
                'total' => $total,
                'pages' => $pages,
                'current_page' => $page
            ]);
            exit;
            
        case 'add_chef':
            $id = (int)$_POST['id'];
            $name = trim($_POST['name'] ?? '');
            $surname = trim($_POST['surname'] ?? '');
            $response = ['status' => 'error', 'message' => 'Неверные данные'];
            
            if ($id > 0 && !empty($name) && !empty($surname)) {
                $check_sql = "SELECT ID FROM Chef WHERE ID = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $response['message'] = 'Ошибка: Шеф с таким ID уже существует.';
                } else {
                    $sql = "INSERT INTO Chef (ID, Name, Surname) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iss", $id, $name, $surname);
                    
                    if ($stmt->execute()) {
                        logAction($conn, "Добавление шеф-повара", "ID: $id, Имя: $name, Фамилия: $surname");
                        $response = ['status' => 'success', 'message' => "Шеф-повар $name $surname успешно добавлен"];
                    } else {
                        $response['message'] = 'Ошибка: Не удалось добавить шефа. ' . $stmt->error;
                    }
                }
            } else {
                $response['message'] = 'Заполните все поля корректно';
            }
            
            echo json_encode($response);
            exit;
            
        case 'delete_chef':
            $id = (int)$_POST['id'];
            $response = ['status' => 'error', 'message' => 'Неверные данные'];
            
            if ($id > 0) {
                $delete_sql = "DELETE FROM Chef WHERE ID = ?";
                $stmt = $conn->prepare($delete_sql);
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    logAction($conn, "Удаление шеф-повара", "ID: $id");
                    $response = ['status' => 'success', 'message' => "Шеф-повар успешно удален"];
                } else {
                    $response['message'] = 'Ошибка: Не удалось удалить повара. ' . $stmt->error;
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
    <title>Шеф-повара | Панель админа</title>
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="css/styles_chef.css">
    <script src="js/scripts_chef.js" defer></script>
</head>

<body>
    <header class="header">
        <div class="logo">
            <span class="material-icons" style="font-size: 2rem; color: var(--primary-color);">person</span>
            Шеф-повара
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
                <a href="chef.php" class="nav-link active">
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
                <h1 class="page-title">Управление шеф-поварами</h1>
            </div>

            <div class="card">
                <div class="form-section">
                    <h3 class="form-title">Добавить нового шеф-повара</h3>
                    <form id="addChefForm" class="chef-form">
                        <div class="form-group">
                            <label class="form-label" for="chef-id">ID</label>
                            <input type="number" id="chef-id" name="id" class="form-input" required min="1" placeholder="Уникальный ID">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="chef-name">Имя</label>
                            <input type="text" id="chef-name" name="name" class="form-input" required placeholder="Введите имя">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="chef-surname">Фамилия</label>
                            <input type="text" id="chef-surname" name="surname" class="form-input" required placeholder="Введите фамилию">
                        </div>
                        <button type="submit" class="btn-add" id="addChefBtn">
                            <span class="material-icons">person_add</span>
                            Добавить
                        </button>
                    </form>
                    <div id="addChefResult"></div>
                </div>

                <h3 class="form-title">Список шеф-поваров</h3>
                <div class="table-container">
                    <table class="chefs-table" id="chefsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Имя</th>
                                <th>Фамилия</th>
                                <th style="width: 120px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody id="chefsTableBody">
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-secondary);">
                                    Загрузка данных...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination" id="pagination"></div>
                
                <div id="deleteChefResult"></div>
            </div>

            <div class="info-panel">
                <h3 class="info-title">Информация об управлении</h3>
                <p class="info-text">Этот раздел позволяет полностью управлять списком шеф-поваров в системе.</p>
                
                <ul class="info-list">
                    <li class="info-item">
                        <strong>➕ Добавить</strong>
                        <span>Введите ID, имя и фамилию нового шеф-повара в форму выше</span>
                    </li>
                    <li class="info-item">
                        <strong>✏️ Редактировать</strong>
                        <span>Нажмите кнопку редактирования для изменения данных существующего шеф-повара</span>
                    </li>
                    <li class="info-item">
                        <strong>🗑️ Удалить</strong>
                        <span>Нажмите кнопку удаления и подтвердите действие в появившемся окне</span>
                    </li>
                    <li class="info-item">
                        <strong>📄 Пагинация</strong>
                        <span>Используйте номера страниц внизу таблицы для навигации по списку</span>
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
                    <h3 class="modal-title">Удалить шеф-повара</h3>
                </div>
            </div>
            <p class="modal-description" id="deleteModalText">Вы действительно хотите удалить этого шеф-повара? Это действие необратимо.</p>
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
</body>
</html>