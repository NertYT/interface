<?php
session_start();
require_once 'db_connect.php';

// Генерация CSRF токена для защиты
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Проверка, если пользователь согласился с условиями
if (!isset($_SESSION['accepted_terms'])) {
    $_SESSION['accepted_terms'] = false;
}

// Секретный ключ Google reCAPTCHA
$recaptcha_secret_key = '6LepmP0qAAAAADTwaSXRzXsksLpGtQHCMjaxEBwE';

// Проверка при отправке формы логина
$error = null; // Инициализируем переменную ошибки
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // Проверка CSRF токена
    if ($csrf_token !== $_SESSION['csrf_token']) {
        $error = 'Ошибка: Неверный CSRF токен';
    }
    // Проверка, заполнена ли reCAPTCHA
    elseif (empty($recaptcha_response)) {
        $error = 'Пожалуйста, подтвердите, что вы не робот';
    } else {
        // Проверка reCAPTCHA через API Google
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $recaptcha_secret_key,
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            $error = 'Не удалось проверить reCAPTCHA. Попробуйте позже.';
        } else {
            $response = json_decode($result, true);
            if ($response['success'] !== true) {
                // Очищаем счетчик попыток для reCAPTCHA ошибок, чтобы не блокировать аккаунт
                if (!empty($username)) {
                    $admin = getAdminByUsername($conn, $username);
                    if ($admin && !$admin['is_locked']) {
                        $error = 'Ошибка проверки безопасности. Попробуйте еще раз.';
                    } else {
                        $error = 'Неверная проверка reCAPTCHA';
                    }
                } else {
                    $error = 'Неверная проверка reCAPTCHA';
                }
            } else {
                // reCAPTCHA прошла успешно, проверяем логин и пароль
                $login_result = verifyAdminLogin($conn, $username, $password);
                
                if ($login_result === true) {
                    $admin = getAdminByUsername($conn, $username);
                    $_SESSION['logged_in'] = true;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $admin['role'] ?? 'User'; // Устанавливаем роль из БД
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    header('Location: index.php');
                    exit;
                } elseif (is_array($login_result) && isset($login_result['blocked'])) {
                    // Аккаунт заблокирован
                    if (isset($login_result['unlock_time'])) {
                        $unlock_time = new DateTime($login_result['unlock_time']);
                        $error = "Аккаунт заблокирован до " . $unlock_time->format('d.m.Y H:i:s') . ". Попробуйте позже.";
                    } else {
                        $error = "Аккаунт заблокирован после 3 неудачных попыток. Обратитесь к администратору.";
                    }
                } else {
                    // Проверка статуса аккаунта для отображения количества оставшихся попыток
                    $status = getAccountStatus($conn, $username);
                    if ($status && !$status['is_locked']) {
                        $error = "Неверный логин или пароль! Осталось попыток: " . (3 - $status['failed_attempts']);
                    } else {
                        $error = "Неверный логин или пароль!";
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему | Панель админа</title>
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="css/styles_login.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script src="js/scripts_login.js" defer></script>
</head>

<body data-theme="light">
    <!-- Кнопка смены темы -->
    <button class="theme-toggle" onclick="toggleTheme()" title="Переключить тему" aria-label="Переключить тему">
        <span class="material-icons" id="themeIcon">dark_mode</span>
    </button>

    <div class="page-container">
        <div class="login-card" id="loginCard">
            <div class="login-header">
                <div class="login-logo">
                    <span class="material-icons">lock</span>
                </div>
                <h1 class="login-title">Добро пожаловать</h1>
                <p class="login-subtitle">Войдите в панель администратора</p>
            </div>

            <?php if (isset($error) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <div class="message message-error">
                    <span class="material-icons">error</span>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="login-form" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="form-group">
                    <label class="form-label" for="username">Логин</label>
                    <div class="input-wrapper">
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-input" 
                            placeholder="Введите логин" 
                            required 
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            autocomplete="username"
                            aria-describedby="username-help"
                        >
                        <span class="input-icon material-icons">person</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Пароль</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Введите пароль" 
                            required 
                            autocomplete="current-password"
                            aria-describedby="password-help"
                        >
                        <button type="button" class="password-toggle material-icons" onclick="togglePassword()" title="Показать пароль" aria-label="Показать пароль">
                            visibility
                        </button>
                        <span class="input-icon material-icons">lock</span>
                    </div>
                </div>

                <div class="recaptcha-container" id="recaptcha-container"></div>

                <button type="submit" class="btn-login" id="loginBtn" disabled>
                    <span class="material-icons">login</span>
                    Войти в систему
                </button>

                <?php if (isset($_POST['username']) && !empty($_POST['username']) && !$error): ?>
                    <?php 
                    $status = getAccountStatus($conn, trim($_POST['username']));
                    if ($status && !$status['is_locked'] && $status['failed_attempts'] > 0): 
                    ?>
                        <div class="message message-warning">
                            <span class="material-icons">warning</span>
                            Осталось попыток: <strong><?= 3 - $status['failed_attempts'] ?></strong>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="security-info">
                    <div class="security-item">
                        <span class="material-icons">lock</span>
                        После 3 неудачных попыток аккаунт блокируется на 24 часа
                    </div>
                    <div class="security-item">
                        <span class="material-icons">security</span>
                        Все соединения защищены SSL и CSRF токенами
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно согласия -->
    <div id="termsModal" class="modal <?= $_SESSION['accepted_terms'] ? '' : 'active' ?>">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <span class="material-icons">gavel</span>
                </div>
                <div>
                    <h3 class="modal-title">Условия использования</h3>
                    <p class="modal-subtitle">Лицензионное соглашение</p>
                </div>
            </div>

            <div class="modal-description">
                <div class="modal-text">
                    <strong>Проект:</strong> Панель администратора для системы управления кухней<br><br>
                    <strong>Разработчики:</strong><br>
                    • Семкин Иван (@nertoff)<br>
                    • Щегольков Максим (@Oxigen4ik)<br><br>
                    <em>Вся документация и программное обеспечение защищены авторским правом 
                    и могут использоваться только с письменного разрешения разработчиков.</em>
                </div>
                
                <div class="contact-info">
                    <strong>Контакты для связи:</strong>
                    📧 35313531as@gmail.com<br>
                    📧 q_bite@mail.ru<br>
                    📱 Telegram: <a href="https://t.me/nertoff" target="_blank">@nertoff</a> (Семкин Иван)<br>
                    📱 Telegram: <a href="https://t.me/Oxigen4ik" target="_blank">@Oxigen4ik</a> (Щегольков Максим)
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-modal btn-decline" onclick="declineTerms()" aria-label="Отказаться от условий">
                    <span class="material-icons">close</span>
                    Отказаться
                </button>
                <button class="btn-modal btn-accept" onclick="acceptTerms()" aria-label="Принять условия">
                    <span class="material-icons">check</span>
                    Принять и продолжить
                </button>
            </div>
        </div>
    </div>
</body>
</html>