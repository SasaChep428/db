<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Проверка авторизации
$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}
$employee_id = $user['employee_id'];
$access_type = $user['access_type'];

// Ограничение доступа
$allowedRoles = ['Administrator', 'Project Manager', 'Network Engineer', 'Installer', 'Inventory Manager', 'Analyst'];
restrictAccess($allowedRoles);

$notifications = []; // Инициализация пустого массива для уведомлений
$error_message = null; // Переменная для хранения сообщения об ошибке

try {
    // Проверка существования $pdo
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception("Объект PDO не инициализирован. Проверьте настройки в config.php.");
    }

    // Проверка $employee_id
    if (empty($employee_id)) {
        throw new Exception("Идентификатор сотрудника не определен.");
    }

    // Оригинальный запрос (сохраняется без изменений)
    $sql = "SELECT notification_id, notification_type, message, created_at 
            FROM notifications 
            WHERE employee_id = ? 
            ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute([$employee_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Notifications fetched (original query): " . count($notifications));
    } catch (PDOException $e) {
        // Обработка ошибки, связанной с отсутствием столбца employee_id
        if (strpos($e->getMessage(), 'Unknown column \'employee_id\'') !== false) {
            error_log("Столбец employee_id отсутствует в таблице notifications: " . $e->getMessage());
            // Загружаем все уведомления без привязки к employee_id
            $sql_all = "SELECT notification_id, notification_type, message, created_at 
                        FROM notifications 
                        ORDER BY created_at DESC";
            $stmt_all = $pdo->prepare($sql_all);
            $stmt_all->execute();
            $all_notifications = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

            // Фильтрация уведомлений по роли пользователя
            foreach ($all_notifications as $notification) {
                if ($notification['notification_type'] === 'general') {
                    // Общие уведомления доступны всем ролям
                    $notifications[] = $notification;
                } elseif ($notification['notification_type'] === 'system' && $access_type === 'Administrator') {
                    // Системные уведомления только для администраторов
                    $notifications[] = $notification;
                }
            }
            error_log("Notifications filtered by role: " . count($notifications));
            if (empty($notifications)) {
                $error_message = "Нет доступных уведомлений для вашей роли.";
            }
        } else {
            // Логирование других PDO-ошибок
            error_log("Ошибка выполнения запроса: " . $e->getMessage() . " в файле " . $e->getFile() . " на строке " . $e->getLine());
            throw $e;
        }
    }
} catch (PDOException $e) {
    error_log("Ошибка при загрузке уведомлений: " . $e->getMessage() . " в файле " . $e->getFile() . " на строке " . $e->getLine());
    $error_message = "Ошибка при загрузке уведомлений: " . htmlspecialchars($e->getMessage());
} catch (Exception $e) {
    error_log("Общая ошибка: " . $e->getMessage() . " в файле " . $e->getFile() . " на строке " . $e->getLine());
    $error_message = "Ошибка: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Уведомления</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <h2 class="mt-4">Уведомления</h2>
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                        <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-warning">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table id="notificationsTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Тип</th>
                                <th>Сообщение</th>
                                <th>Дата</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($notifications)): ?>
                                <tr><td colspan="4" class="text-center">Нет уведомлений.</td></tr>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($notification['notification_id'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($notification['notification_type'] ?? 'Неизвестно'); ?></td>
                                        <td><?php echo htmlspecialchars($notification['message'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($notification['created_at'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#notificationsTable').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ru.json' },
                pageLength: 10,
                lengthMenu: [10, 25, 50]
            });
        });
    </script>
</body>
</html>