<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include 'db_connect.php';

// Функция для записи логов с дополнительной информацией
function logAction($conn, $operation, $details = '') {
    $current_time = date('Y-m-d H:i:s');
    $user = $_SESSION['username'] ?? 'неизвестно';
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'], 0, 100);
    $full_details = "Пользователь: $user | IP: $ip | Детали: $details | UA: $user_agent";
    
    $stmt = $conn->prepare("INSERT INTO db_logs (operation, details, timestamp) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $operation, $full_details, $current_time);
    $stmt->execute();
    $stmt->close();
}

// Функция для создания бэкапа БД
function createDatabaseBackup($conn, $db_name) {
    try {
        // Получаем список всех таблиц
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        if (empty($tables)) {
            return false;
        }
        
        // Создаем папку для бэкапов
        $backup_dir = __DIR__ . '/backups/';
        if (!is_dir($backup_dir)) {
            if (!mkdir($backup_dir, 0755, true)) {
                return false;
            }
        }
        
        // Имя файла
        $filename = 'backup_' . $db_name . '_' . date('Y-m-d_His') . '.sql';
        $filepath = $backup_dir . $filename;
        
        // Открываем файл для записи
        $handle = fopen($filepath, 'w');
        if (!$handle) {
            return false;
        }
        
        // Заголовок бэкапа
        $header = "-- Kitchen Admin Database Backup\n";
        $header .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $header .= "-- Database: " . $db_name . "\n";
        $header .= "-- Host: " . DB_HOST . "\n";
        $header .= "-- User: " . (DB_USER ?? 'hidden') . "\n\n";
        $header .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $header .= "SET AUTOCOMMIT = 0;\n";
        $header .= "START TRANSACTION;\n";
        $header .= "SET time_zone = \"+00:00\";\n\n";
        
        fwrite($handle, $header);
        
        // Создаем дамп для каждой таблицы
        foreach ($tables as $table) {
            // Структура таблицы
            $result = $conn->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch_row();
            
            fwrite($handle, "\n--\n-- Table structure for table `$table`\n--\n\n");
            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($handle, $row[1] . ";\n\n");
            
            // Данные таблицы
            $result = $conn->query("SELECT * FROM `$table`");
            if ($result && $result->num_rows > 0) {
                fwrite($handle, "--\n-- Dumping data for table `$table`\n--\n\n");
                fwrite($handle, "LOCK TABLES `$table` WRITE;\n");
                fwrite($handle, "INSERT INTO `$table` VALUES\n");
                
                $first = true;
                while ($row = $result->fetch_row()) {
                    if (!$first) {
                        fwrite($handle, ",\n");
                    }
                    fwrite($handle, "(");
                    foreach ($row as $key => $value) {
                        if ($key > 0) fwrite($handle, ",");
                        if ($value === null) {
                            fwrite($handle, "NULL");
                        } else {
                            fwrite($handle, "'" . $conn->real_escape_string($value) . "'");
                        }
                    }
                    fwrite($handle, ")");
                    $first = false;
                }
                
                fwrite($handle, ";\n");
                fwrite($handle, "UNLOCK TABLES;\n\n");
            }
        }
        
        // Завершение бэкапа
        fwrite($handle, "COMMIT;\n");
        fclose($handle);
        
        return $filepath;
    } catch (Exception $e) {
        error_log("Backup error: " . $e->getMessage());
        return false;
    }
}

// Логируем вход только при первом посещении страницы
if (!isset($_SESSION['performance_page_visited'])) {
    logAction($conn, "Вход на страницу производительности");
    $_SESSION['performance_page_visited'] = true;
}

// Обработка бэкапа БД
if (isset($_GET['backup_db'])) {
    $backup_path = createDatabaseBackup($conn, DB_NAME);
    
    if ($backup_path && file_exists($backup_path)) {
        logAction($conn, "Создание бэкапа БД", "Файл: " . basename($backup_path) . " (Размер: " . round(filesize($backup_path) / 1024 / 1024, 2) . " MB)");
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($backup_path) . '"');
        header('Content-Length: ' . filesize($backup_path));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        readfile($backup_path);
        
        // Удаляем файл через 1 час
        $delete_time = time() + 3600;
        touch($backup_path, $delete_time);
        
        exit;
    } else {
        logAction($conn, "Ошибка бэкапа БД", "Не удалось создать файл бэкапа");
        header('Location: performance.php?backup_error=1');
        exit;
    }
}

// Обработка скачивания логов
if (isset($_GET['download_logs'])) {
    logAction($conn, "Скачивание логов", "Файл: system_logs_" . date('Y-m-d_His') . ".txt");
    $result = $conn->query("SELECT * FROM db_logs ORDER BY timestamp DESC");
    
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d_His') . '.txt"');
    header('Content-Transfer-Encoding: binary');
    
    echo "=== ЛОГИ СИСТЕМЫ КУХНИ ===\n";
    echo "Дата создания: " . date('Y-m-d H:i:s') . "\n";
    echo "================================\n\n";
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "[{$row['timestamp']}] {$row['operation']}\n";
            echo str_repeat(' ', 2) . trim($row['details']) . "\n\n";
        }
    } else {
        echo "Логи пусты.\n";
    }
    exit;
}

// Обработка очистки логов
if (isset($_GET['clear_logs']) && $_GET['confirm'] === 'yes') {
    // Получаем количество записей перед очисткой
    $count_result = $conn->query("SELECT COUNT(*) as count FROM db_logs");
    $count_row = $count_result->fetch_assoc();
    $affected_rows = (int)$count_row['count'];
    
    $truncate_result = $conn->query("TRUNCATE TABLE db_logs");
    logAction($conn, "Очистка логов", "Удалено записей: $affected_rows");
    header('Location: performance.php?cleared=1');
    exit;
}

// Обработка AJAX-запроса для метрик
if (isset($_GET['get_metrics'])) {
    $metrics = [];
    $start_time = microtime(true);
    
    // Время отклика
    $metrics['response_time'] = number_format((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2);

    // Нагрузка на CPU
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $metrics['cpu_load'] = 'N/A';
        if (class_exists('COM')) {
            try {
                $wmi = new COM('WinMgmts://');
                $cpus = $wmi->ExecQuery("SELECT LoadPercentage FROM Win32_Processor");
                $total_load = 0;
                $cpu_count = 0;
                foreach ($cpus as $cpu) {
                    $total_load += $cpu->LoadPercentage;
                    $cpu_count++;
                }
                $metrics['cpu_load'] = $cpu_count > 0 ? round($total_load / $cpu_count, 1) : 'N/A';
            } catch (Exception $e) {
                $metrics['cpu_load'] = 'N/A';
            }
        }
    } else {
        $load = sys_getloadavg();
        $metrics['cpu_load'] = isset($load[0]) ? round($load[0] * 100 / 4, 1) : 'N/A'; // Для 4-ядерного CPU
    }

    // Использование памяти
    $memory_usage = memory_get_usage();
    $metrics['memory_usage'] = round($memory_usage / 1024 / 1024, 1);
    $metrics['memory_percent'] = round($memory_usage / (1024 * 1024 * 128) * 100, 1); // 128MB limit
    
    // Активные соединения к БД
    $result = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
    $row = $result->fetch_assoc();
    $metrics['db_connections'] = $row['Value'] ?? 0;
    
    // Размер БД
    $db_size_result = $conn->query("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size_mb FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
    $db_size_row = $db_size_result->fetch_assoc();
    $metrics['database_size'] = round($db_size_row['size_mb'] ?? 0, 1);
    
    // Количество записей в логах
    $log_count_result = $conn->query("SELECT COUNT(*) as count FROM db_logs");
    $log_count_row = $log_count_result->fetch_assoc();
    $metrics['log_count'] = (int)$log_count_row['count'];
    
    // Количество запросов за сегодня
    $today_result = $conn->query("SELECT COUNT(*) as count FROM db_logs WHERE DATE(timestamp) = CURDATE()");
    $today_row = $today_result->fetch_assoc();
    $metrics['queries_today'] = (int)$today_row['count'];
    
    // Время выполнения
    $metrics['execution_time'] = number_format((microtime(true) - $start_time) * 1000, 2);
    
    header('Content-Type: application/json');
    echo json_encode($metrics);
    exit;
}

// Получение последних логов для отображения
$logs = [];
$log_result = $conn->query("SELECT * FROM db_logs ORDER BY timestamp DESC LIMIT 20");
while ($row = $log_result->fetch_assoc()) {
    $logs[] = $row;
}

// Получение статистики логов
$log_stats = $conn->query("SELECT COUNT(*) as total, DATE(timestamp) as date FROM db_logs GROUP BY DATE(timestamp) ORDER BY date DESC LIMIT 7")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Производительность | Kitchen Admin</title>
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <link rel="stylesheet" href="css/styles_perfomance.css">
    <script src="js/scripts_perfomance.js" defer></script>
</head>

<body data-theme="light">
    <header class="header">
        <div class="header-title">
            <span class="material-icons" style="font-size: 1.5rem; color: var(--primary-color);">speed</span>
            Мониторинг производительности
        </div>
        
        <div class="header-actions">
            <a href="index.php" class="btn btn-icon" title="На главную" aria-label="Вернуться на главную">
                <span class="material-icons">arrow_back</span>
            </a>
            <a href="?backup_db=1" class="btn btn-success" title="Создать бэкап БД">
                <span class="material-icons">backup</span>
                Бэкап БД
            </a>
        </div>
    </header>

    <main class="main-content">
        <section class="metrics-panel">
            <div class="panel-header">
                <h2 class="panel-title">
                    <span class="material-icons">dashboard</span>
                    Метрики системы
                </h2>
                <div class="metric-status" id="overallStatus">
                    <span class="material-icons" style="font-size: 1.5rem; color: var(--success-color);">check_circle</span>
                    <span style="font-size: 0.875rem; color: var(--text-secondary);">Система стабильна</span>
                </div>
            </div>

            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-label">Время отклика</div>
                    <div class="metric-value" id="responseTime">--</div>
                    <div class="metric-unit">мс</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Нагрузка CPU</div>
                    <div class="metric-value" id="cpuLoad">--</div>
                    <div class="metric-unit">%</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Память</div>
                    <div class="metric-value" id="memoryUsage">--</div>
                    <div class="metric-unit">MB</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Соединения БД</div>
                    <div class="metric-value" id="dbConnections">--</div>
                    <div class="metric-unit">активных</div>
                </div>
            </div>

            <div class="backup-section">
                <div class="backup-header">
                    <span class="material-icons" style="color: var(--success-color); font-size: 1.5rem;">storage</span>
                    <h3 class="backup-title">База данных</h3>
                </div>
                
                <div class="backup-info">
                    Текущий размер базы данных и статистика активности
                </div>

                <div class="backup-stats">
                    <div class="stat-card">
                        <div class="stat-value" id="dbSize">--</div>
                        <div class="stat-label">Размер БД</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="logCount">--</div>
                        <div class="stat-label">Записей в логах</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="queriesToday">--</div>
                        <div class="stat-label">Запросов сегодня</div>
                    </div>
                </div>

                <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                    <a href="?backup_db=1" class="btn btn-success">
                        <span class="material-icons">file_download</span>
                        Создать бэкап
                    </a>
                    <button onclick="showLogStats()" class="btn btn-secondary">
                        <span class="material-icons">analytics</span>
                        Статистика
                    </button>
                </div>
            </div>

            <div class="chart-container">
                <div class="panel-header" style="margin-bottom: 1rem;">
                    <h3 class="panel-title">
                        <span class="material-icons">trending_up</span>
                        Нагрузка CPU
                    </h3>
                    <div style="font-size: 0.875rem; color: var(--text-secondary);">За последние 60 секунд</div>
                </div>
                <canvas id="cpuChart"></canvas>
            </div>

            <div class="chart-container">
                <div class="panel-header" style="margin-bottom: 1rem;">
                    <h3 class="panel-title">
                        <span class="material-icons">memory</span>
                        Использование памяти
                    </h3>
                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Динамика за минуту</div>
                </div>
                <canvas id="memoryChart"></canvas>
            </div>
        </section>

        <aside class="logs-panel">
            <div class="panel-header">
                <h2 class="panel-title">
                    <span class="material-icons">history</span>
                    Системные логи
                </h2>
                <div class="logs-actions">
                    <a href="?download_logs=1" class="btn btn-primary" title="Скачать все логи">
                        <span class="material-icons">download</span>
                    </a>
                    <button onclick="confirmClearLogs()" class="btn btn-danger" title="Очистить логи">
                        <span class="material-icons">delete</span>
                    </button>
                </div>
            </div>

            <?php if (isset($_GET['cleared'])): ?>
                <div class="notification success" style="display: block;">
                    <span class="material-icons">check_circle</span>
                    Логи успешно очищены (<?= $affected_rows ?? 'неизвестно' ?> записей)
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['backup_error'])): ?>
                <div class="notification error" style="display: block;">
                    <span class="material-icons">error</span>
                    Ошибка создания бэкапа. Проверьте права доступа к папке backups/
                </div>
            <?php endif; ?>

            <div class="logs-list" id="logsList">
                <?php if (empty($logs)): ?>
                    <div style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                        <span class="material-icons" style="font-size: 3rem; opacity: 0.3;">description</span>
                        <p style="margin-top: 1rem;">Логи пусты</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($logs as $index => $log): ?>
                        <div class="log-entry" style="animation-delay: <?= $index * 0.05 ?>s;">
                            <div class="log-header">
                                <span class="log-operation"><?= htmlspecialchars($log['operation']) ?></span>
                                <span class="log-timestamp"><?= date('H:i:s', strtotime($log['timestamp'])) ?></span>
                            </div>
                            <div class="log-details"><?= htmlspecialchars($log['details']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>
    </main>

    <footer>
        <p>© 2025 Семкин Иван и Щегольков Максим. Все права защищены.</p>
    </footer>
</body>
</html>