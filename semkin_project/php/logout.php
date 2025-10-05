<?php
session_start();
session_unset();
session_destroy();
header('Location: login.php'); // Перенаправление на страницу входа
exit;
?>
