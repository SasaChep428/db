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

$allowedRoles = ['Administrator'];
restrictAccess($allowedRoles);

try {
    global $pdo; // Используем глобальную переменную $pdo
    $sql = "SELECT log_id, entity_type, entity_id, changed_field, old_value, new_value, 
                   CONCAT(e.first_name, ' ', e.last_name) AS employee_name, 
                   changed_at
            FROM audit_log al
            LEFT JOIN employees e ON al.changed_by = e.username
            ORDER BY changed_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['message'] = "Ошибка базы данных: " . htmlspecialchars($e->getMessage());
    $_SESSION['message_type'] = 'danger';
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Журнал аудита</title>
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
                <h2 class="mt-4">Журнал аудита</h2>
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                        <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table id="auditTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Тип сущности</th>
                                <th>ID записи</th>
                                <th>Измененное поле</th>
                                <th>Старое значение</th>
                                <th>Новое значение</th>
                                <th>Сотрудник</th>
                                <th>Время</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($audit_logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['log_id']); ?></td>
                                    <td><?php echo htmlspecialchars($log['entity_type']); ?></td>
                                    <td><?php echo htmlspecialchars($log['entity_id']); ?></td>
                                    <td><?php echo htmlspecialchars($log['changed_field']); ?></td>
                                    <td><?php echo htmlspecialchars($log['old_value'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($log['new_value'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($log['employee_name'] ?? (isset($log['changed_by']) ? $log['changed_by'] : 'Неизвестно')); ?></td>
                                    <td><?php echo htmlspecialchars($log['changed_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
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
            $('#auditTable').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ru.json' },
                pageLength: 10,
                lengthMenu: [10, 25, 50]
            });
        });
    </script>
</body>
</html>