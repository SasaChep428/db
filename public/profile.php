<?php
// public/profile.php
// Страница профиля пользователя

require_once '../includes/auth.php';

// Проверка авторизации
$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}
$employee_id = $user['employee_id'];
$username = $user['username'];

restrictAccess(['Administrator', 'Project Manager', 'Network Engineer', 'Installer', 'Inventory Manager', 'Analyst']);

$success_message = '';
$error_message = '';

global $pdo;
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);

        if (empty($first_name) || empty($last_name)) {
            $error_message = 'Имя и фамилия обязательны.';
        } else {
            $stmt = $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ? WHERE employee_id = ?");
            $stmt->execute([$first_name, $last_name, $employee_id]);

            $_SESSION['user']['first_name'] = $first_name;
            $_SESSION['user']['last_name'] = $last_name;

            $stmt = $pdo->prepare("INSERT INTO audit_log (entity_type, entity_id, changed_field, old_value, new_value, changed_by, changed_at) 
                                  VALUES ('employee', ?, 'profile_updated', ?, ?, ?, CURRENT_TIMESTAMP)");
            $new_value = "Updated: $first_name $last_name";
            $stmt->execute([$employee_id, '', $new_value, $username]);

            $success_message = 'Профиль успешно обновлён.';
        }
    }

    $stmt = $pdo->prepare("
        SELECT e.employee_id, e.first_name, e.last_name, e.access_type, p.position_name,
               (SELECT contact_value FROM contact_info WHERE entity_type = 'employee' AND entity_id = e.employee_id AND contact_type = 'email' LIMIT 1) AS email,
               (SELECT contact_value FROM contact_info WHERE entity_type = 'employee' AND entity_id = e.employee_id AND contact_type = 'phone' LIMIT 1) AS phone
        FROM employees e
        LEFT JOIN positions p ON e.position_id = p.position_id
        WHERE e.employee_id = ?
    ");
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $stmt->execute([$employee_id]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        throw new Exception('Пользователь не найден.');
    }
} catch (PDOException $e) {
    error_log("Ошибка подключения: " . $e->getMessage());
    $error_message = "Ошибка подключения к базе данных.";
} catch (Exception $e) {
    error_log("Ошибка: " . $e->getMessage());
    $error_message = "Произошла ошибка: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль пользователя</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <h2 class="mt-4">Профиль пользователя</h2>
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-6">
                        <h3>Личная информация</h3>
                        <p><strong>Должность:</strong> <?php echo htmlspecialchars($user_data['position_name'] ?? 'Не указана'); ?></p>
                        <p><strong>Тип доступа:</strong> <?php echo htmlspecialchars($user_data['access_type']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email'] ?? 'Не указан'); ?></p>
                        <p><strong>Телефон:</strong> <?php echo htmlspecialchars($user_data['phone'] ?? 'Не указан'); ?></p>
                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">Имя</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Фамилия</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Обновить</button>
                            <a href="settings.php" class="btn btn-secondary ms-2">Изменить контакты или пароль</a>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Управление сворачиванием/разворачиванием боковой панели
        document.getElementById('toggleSidebar')?.addEventListener('click', function () {
            const sidebar = document.getElementById('sidebarOffcanvas');
            const mainContent = document.querySelector('.main-content');
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        });
    </script>
</body>
</html>