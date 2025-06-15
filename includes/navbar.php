<?php
// navbar.php
// Верхняя навигационная панель

if (defined('NAVBAR_INCLUDED')) {
    return;
}
define('NAVBAR_INCLUDED', true);

require_once '../includes/auth.php';

// Проверка авторизации
$user = getCurrentUser();
if (!$user) {
    header('Location: ../public/login.php');
    exit;
}
$employee_id = $user['employee_id'];
$access_type = $user['access_type'];
$first_name = $user['first_name'];
$last_name = $user['last_name'];
$username = $user['username'];

// Массив для перевода ролей пользователя
$role_map = [
    'Administrator' => 'Администратор',
    'Project Manager' => 'Менеджер проектов',
    'Network Engineer' => 'Сетевой инженер',
    'Installer' => 'Монтажник',
    'Inventory Manager' => 'Менеджер склада',
    'Analyst' => 'Аналитик'
];
$translated_role = $role_map[$access_type] ?? $access_type;

// Получение количества непрочитанных уведомлений
global $pdo;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE created_at > COALESCE((
            SELECT MAX(changed_at) 
            FROM audit_log 
            WHERE entity_type = 'employee' 
            AND entity_id = ? 
            AND changed_field = 'login'
        ), '2000-01-01 00:00:00'))
    ");
    $stmt->execute([$employee_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
} catch (PDOException $e) {
    error_log("Ошибка получения уведомлений: " . $e->getMessage());
    $unread_notifications = 0;
}
?>

<!-- Навигационная панель -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <!-- Кнопка для открытия боковой панели на мобильных устройствах -->
        <button class="btn btn-primary me-2 d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
            <i class="bi bi-list"></i>
        </button>
        <!-- Логотип и название системы -->
        <a class="navbar-brand" href="index.php">Система управления</a>
        <!-- Кнопка для мобильной навигации -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <!-- Содержимое навигационной панели -->
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item dropdown">
                    <!-- Выпадающее меню пользователя -->
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars($first_name . ' ' . $last_name); ?> (<?php echo htmlspecialchars($translated_role); ?>)
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php">Профиль</a></li>
                        <li>
                            <a class="dropdown-item" href="notifications.php">
                                Уведомления
                                <?php if ($unread_notifications > 0): ?>
                                    <span class="badge bg-danger ms-2"><?php echo $unread_notifications; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Выйти</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>