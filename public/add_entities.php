<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Проверка авторизации
$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit();
}
$employee_id = $user['employee_id'];
$access_type = $user['access_type'];

// Ограничение доступа
$allowed_roles = ['Administrator'];
restrictAccess($allowed_roles);

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if (isset($_POST['add_employee'])) {
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $position_id = (int)$_POST['position_id'];
            $access_type = trim($_POST['access_type']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $salary = !empty($_POST['salary']) ? (float)$_POST['salary'] : null;
            $hourly_rate = !empty($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : null;
            $notification_preference = trim($_POST['notification_preference']);
            $hire_date = trim($_POST['hire_date']);

            // Валидация
            if (empty($first_name) || empty($last_name) || empty($username) || empty($password) || empty($hire_date)) {
                throw new Exception('Все обязательные поля должны быть заполнены.');
            }
            if (strlen($password) < 6) {
                throw new Exception('Пароль должен содержать минимум 6 символов.');
            }
            if (!in_array($access_type, ['Administrator', 'Project Manager', 'Network Engineer', 'Installer', 'Inventory Manager', 'Analyst'])) {
                throw new Exception('Недопустимый тип доступа.');
            }
            if (!in_array($notification_preference, ['email', 'sms', 'none'])) {
                throw new Exception('Недопустимое предпочтение уведомлений.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hire_date)) {
                throw new Exception('Недопустимый формат даты найма.');
            }

            // Проверка уникальности имени пользователя
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Имя пользователя уже занято.');
            }

            // Добавление сотрудника
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO employees (first_name, last_name, username, password, position_id, hire_date, access_type, salary, hourly_rate, notification_preference, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$first_name, $last_name, $username, $hashed_password, $position_id, $hire_date, $access_type, $salary, $hourly_rate, $notification_preference]);
            $new_employee_id = $pdo->lastInsertId();

            // Добавление контактной информации
            if (!empty($email)) {
                $stmt = $pdo->prepare("INSERT INTO contact_info (entity_type, entity_id, contact_type, contact_value, is_primary) VALUES ('employee', ?, 'email', ?, 1)");
                $stmt->execute([$new_employee_id, $email]);
            }
            if (!empty($phone)) {
                $stmt = $pdo->prepare("INSERT INTO contact_info (entity_type, entity_id, contact_type, contact_value, is_primary) VALUES ('employee', ?, 'phone', ?, 0)");
                $stmt->execute([$new_employee_id, $phone]);
            }

            // Логирование
            $stmt = $pdo->prepare("INSERT INTO audit_log (entity_type, entity_id, changed_field, old_value, new_value, changed_by, changed_at) VALUES ('employee', ?, 'created', NULL, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$new_employee_id, "Сотрудник: $first_name $last_name", $employee_id]);

            $_SESSION['message'] = 'Сотрудник успешно добавлен.';
            $_SESSION['message_type'] = 'success';
        } elseif (isset($_POST['add_customer'])) {
            // ... (код для добавления заказчика остаётся без изменений)
        } elseif (isset($_POST['add_customer_contact'])) {
            // ... (код для добавления контактного лица остаётся без изменений)
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = 'Ошибка: ' . htmlspecialchars($e->getMessage());
        $_SESSION['message_type'] = 'danger';
    }
    header('Location: add_entities.php');
    exit();
}

// Получение данных для выпадающих списков
try {
    $positions = $pdo->query("SELECT position_id, position_name FROM positions ORDER BY position_name")->fetchAll(PDO::FETCH_ASSOC);
    $customers = $pdo->query("SELECT customer_id, customer_name FROM customers ORDER BY customer_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['message'] = 'Ошибка загрузки данных: ' . htmlspecialchars($e->getMessage());
    $_SESSION['message_type'] = 'danger';
}
?>
<!-- Вкладка добавления сотрудника -->
<div class="tab-pane fade show active" id="employee" role="tabpanel">
    <form method="POST" class="row g-3">
        <input type="hidden" name="add_employee" value="1">
        <div class="col-md-4">
            <label for="first_name" class="form-label">Имя *</label>
            <input type="text" name="first_name" id="first_name" class="form-control" required maxlength="50">
        </div>
        <div class="col-md-4">
            <label for="last_name" class="form-label">Фамилия *</label>
            <input type="text" name="last_name" id="last_name" class="form-control" required maxlength="50">
        </div>
        <div class="col-md-4">
            <label for="username" class="form-label">Имя пользователя *</label>
            <input type="text" name="username" id="username" class="form-control" required maxlength="50">
        </div>
        <div class="col-md-4">
            <label for="password" class="form-label">Пароль *</label>
            <input type="password" name="password" id="password" class="form-control" required minlength="6">
        </div>
        <div class="col-md-4">
            <label for="position_id" class="form-label">Должность *</label>
            <select name="position_id" id="position_id" class="form-select" required>
                <option value="">Выберите должность</option>
                <?php foreach ($positions as $position): ?>
                    <option value="<?php echo $position['position_id']; ?>"><?php echo htmlspecialchars($position['position_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="access_type" class="form-label">Тип доступа *</label>
            <select name="access_type" id="access_type" class="form-select" required>
                <option value="Administrator">Администратор</option>
                <option value="Project Manager">Менеджер проектов</option>
                <option value="Network Engineer">Сетевой инженер</option>
                <option value="Installer">Монтажник</option>
                <option value="Inventory Manager">Менеджер склада</option>
                <option value="Analyst">Аналитик</option>
            </select>
        </div>
        <div class="col-md-4">
            <label for="hire_date" class="form-label">Дата найма *</label>
            <input type="date" name="hire_date" id="hire_date" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" class="form-control" maxlength="100">
        </div>
        <div class="col-md-4">
            <label for="phone" class="form-label">Телефон</label>
            <input type="text" name="phone" id="phone" class="form-control" maxlength="20">
        </div>
        <div class="col-md-4">
            <label for="salary" class="form-label">Зарплата</label>
            <input type="number" name="salary" id="salary" class="form-control" step="0.01" min="0">
        </div>
        <div class="col-md-4">
            <label for="hourly_rate" class="form-label">Часовая ставка</label>
            <input type="number" name="hourly_rate" id="hourly_rate" class="form-control" step="0.01" min="0">
        </div>
        <div class="col-md-4">
            <label for="notification_preference" class="form-label">Уведомления *</label>
            <select name="notification_preference" id="notification_preference" class="form-select" required>
                <option value="email">Email</option>
                <option value="sms">SMS</option>
                <option value="none">Нет</option>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Добавить сотрудника</button>
        </div>
    </form>
</div>