<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Проверка авторизации
$user = getCurrentUser();
if (!$user) {
    $_SESSION['message'] = 'Доступ запрещен.';
    $_SESSION['message_type'] = 'danger';
    header('Location: login.php');
    exit();
}
$employee_id = $user['employee_id'];
$access_type = $user['access_type'];

// Ограничение доступа
$allowedRoles = ['Administrator'];
restrictAccess($allowedRoles);

// Обработка действий администратора
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'delete_contact') {
            $contact_id = (int)($_POST['contact_id'] ?? 0);
            
            // Проверка существования контакта и его принадлежности сотруднику
            $stmt = $pdo->prepare("SELECT contact_id, entity_type, entity_id, contact_type, contact_value 
                                   FROM contact_info 
                                   WHERE contact_id = ? AND entity_type = 'employee'");
            $stmt->execute([$contact_id]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contact) {
                throw new Exception('Контактная информация не найдена или не относится к сотруднику.');
            }
            
            // Удаление контакта
            $stmt = $pdo->prepare("DELETE FROM contact_info WHERE contact_id = ?");
            $stmt->execute([$contact_id]);
            
            // Логирование действия
            $stmt = $pdo->prepare("INSERT INTO audit_log (entity_type, entity_id, changed_field, old_value, new_value, changed_by, changed_at) 
                                   VALUES ('contact_info', ?, 'deleted', ?, NULL, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$contact_id, "{$contact['contact_type']}: {$contact['contact_value']}", $employee_id]);
            
            $_SESSION['message'] = "Контактная информация (ID $contact_id) успешно удалена.";
            $_SESSION['message_type'] = 'success';
        } elseif (isset($_POST['action']) && $_POST['action'] === 'edit_contact') {
            $contact_id = (int)($_POST['contact_id'] ?? 0);
            $contact_type = trim($_POST['contact_type'] ?? '');
            $contact_value = trim($_POST['contact_value'] ?? '');
            $is_primary = isset($_POST['is_primary']) ? 1 : 0;
            
            // Валидация
            if (!in_array($contact_type, ['phone', 'email', 'mobile', 'other'])) {
                throw new Exception('Недопустимый тип контакта.');
            }
            if (empty($contact_value) || strlen($contact_value) > 100) {
                throw new Exception('Значение контакта должно быть от 1 до 100 символов.');
            }
            
            // Проверка существования контакта
            $stmt = $pdo->prepare("SELECT contact_id, entity_type, contact_type, contact_value, is_primary 
                                   FROM contact_info 
                                   WHERE contact_id = ? AND entity_type = 'employee'");
            $stmt->execute([$contact_id]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contact) {
                throw new Exception('Контактная информация не найдена или не относится к сотруднику.');
            }
            
            // Сохранение старых значений для лога
            $old_contact_type = $contact['contact_type'];
            $old_contact_value = $contact['contact_value'];
            $old_is_primary = $contact['is_primary'];
            
            // Обновление контакта
            $stmt = $pdo->prepare("UPDATE contact_info 
                                   SET contact_type = ?, contact_value = ?, is_primary = ? 
                                   WHERE contact_id = ?");
            $stmt->execute([$contact_type, $contact_value, $is_primary, $contact_id]);
            
            // Логирование изменений
            if ($old_contact_type !== $contact_type) {
                $stmt = $pdo->prepare("INSERT INTO audit_log (entity_type, entity_id, changed_field, old_value, new_value, changed_by, changed_at) 
                                       VALUES ('contact_info', ?, 'contact_type', ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$contact_id, $old_contact_type, $contact_type, $employee_id]);
            }
            if ($old_contact_value !== $contact_value) {
                $stmt = $pdo->prepare("INSERT INTO audit_log (entity_type, entity_id, changed_field, old_value, new_value, changed_by, changed_at) 
                                       VALUES ('contact_info', ?, 'contact_value', ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$contact_id, $old_contact_value, $contact_value, $employee_id]);
            }
            if ($old_is_primary != $is_primary) {
                $stmt = $pdo->prepare("INSERT INTO audit_log (entity_type, entity_id, changed_field, old_value, new_value, changed_by, changed_at) 
                                       VALUES ('contact_info', ?, 'is_primary', ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$contact_id, $old_is_primary, $is_primary, $employee_id]);
            }
            
            $_SESSION['message'] = "Контактная информация (ID $contact_id) успешно обновлена.";
            $_SESSION['message_type'] = 'success';
        } elseif (isset($_POST['action']) && $_POST['action'] === 'edit_employee') {
            $edit_employee_id = (int)($_POST['employee_id'] ?? 0);
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $position_id = (int)($_POST['position_id'] ?? 0);
            $access_type = trim($_POST['access_type'] ?? '');
            $salary = !empty($_POST['salary']) ? (float)$_POST['salary'] : null;
            $hourly_rate = !empty($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : null;
            $notification_preference = trim($_POST['notification_preference'] ?? 'none');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Валидация
            if (empty($first_name) || empty($last_name)) {
                throw new Exception('Имя и фамилия обязательны.');
            }
            if (!in_array($access_type, ['Administrator', 'Project Manager', 'Network Engineer', 'Installer', 'Inventory Manager', 'Analyst'])) {
                throw new Exception('Недопустимый тип доступа.');
            }
            if ($salary !== null && $salary < 0) {
                throw new Exception('Зарплата не может быть отрицательной.');
            }
            if ($hourly_rate !== null && $hourly_rate < 0) {
                throw new Exception('Часовая ставка не может быть отрицательной.');
            }
            if (!in_array($notification_preference, ['email', 'sms', 'none'])) {
                throw new Exception('Недопустимое значение для предпочтений уведомлений.');
            }

            // Проверка существования сотрудника
            $stmt = $pdo->prepare("SELECT first_name, last_name, position_id, access_type, salary, hourly_rate, notification_preference, is_active 
                                   FROM employees WHERE employee_id = ?");
            $stmt->execute([$edit_employee_id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$employee) {
                throw new Exception('Сотрудник не найден.');
            }

            // Проверка существования должности
            $stmt = $pdo->prepare("SELECT position_id FROM positions WHERE position_id = ?");
            $stmt->execute([$position_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Недопустимая должность.');
            }

            // Сохранение старых значений для лога
            $old_values = $employee;

            // Обновление сотрудника
            $stmt = $pdo->prepare("UPDATE employees 
                                   SET first_name = ?, last_name = ?, position_id = ?, access_type = ?, 
                                       salary = ?, hourly_rate = ?, notification_preference = ?, is_active = ? 
                                   WHERE employee_id = ?");
            $stmt->execute([$first_name, $last_name, $position_id, $access_type, $salary, $hourly_rate, $notification_preference, $is_active, $edit_employee_id]);

            // Логирование изменений
            $fields = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'position_id' => $position_id,
                'access_type' => $access_type,
                'salary' => $salary,
                'hourly_rate' => $hourly_rate,
                'notification_preference' => $notification_preference,
                'is_active' => $is_active
            ];
            foreach ($fields as $field => $new_value) {
                $old_value = $old_values[$field];
                if ($new_value != $old_value) {
                    $stmt = $pdo->prepare("INSERT INTO audit_log (entity_type, entity_id, changed_field, old_value, new_value, changed_by, changed_at) 
                                           VALUES ('employee', ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                    $stmt->execute([$edit_employee_id, $field, $old_value, $new_value, $employee_id]);
                }
            }

            $_SESSION['message'] = "Данные сотрудника (ID $edit_employee_id) успешно обновлены.";
            $_SESSION['message_type'] = 'success';
        }
    } catch (Exception $e) {
        error_log("Ошибка при выполнении действия: " . $e->getMessage());
        $_SESSION['message'] = "Ошибка: " . htmlspecialchars($e->getMessage());
        $_SESSION['message_type'] = 'danger';
    }
    header("Location: settings.php");
    exit();
}

try {
    // Получение списка сотрудников и их контактной информации
    $stmt = $pdo->query("
        SELECT e.employee_id, e.first_name, e.last_name, e.access_type, e.position_id, e.salary, e.hourly_rate, 
               e.notification_preference, e.is_active, p.position_name,
               ci.contact_id, ci.contact_type, ci.contact_value, ci.is_primary
        FROM employees e
        LEFT JOIN positions p ON e.position_id = p.position_id
        LEFT JOIN contact_info ci ON e.employee_id = ci.entity_id AND ci.entity_type = 'employee'
        ORDER BY e.last_name, e.first_name
    ");
    $employee_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Группировка данных по сотрудникам
    $employees = [];
    foreach ($employee_contacts as $row) {
        $emp_id = $row['employee_id'];
        if (!isset($employees[$emp_id])) {
            $employees[$emp_id] = [
                'employee_id' => $emp_id,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'access_type' => $row['access_type'],
                'position_id' => $row['position_id'],
                'position_name' => $row['position_name'],
                'salary' => $row['salary'],
                'hourly_rate' => $row['hourly_rate'],
                'notification_preference' => $row['notification_preference'],
                'is_active' => $row['is_active'],
                'contacts' => []
            ];
        }
        if ($row['contact_id']) {
            $employees[$emp_id]['contacts'][] = [
                'contact_id' => $row['contact_id'],
                'contact_type' => $row['contact_type'],
                'contact_value' => $row['contact_value'],
                'is_primary' => $row['is_primary']
            ];
        }
    }

    // Получение списка должностей
    $stmt = $pdo->query("SELECT position_id, position_name FROM positions ORDER BY position_name");
    $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка подключения к базе данных: " . $e->getMessage());
    $_SESSION['message'] = "Ошибка при загрузке данных сотрудников.";
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
    <title>Настройки сотрудников</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <h2 class="mt-4">Настройки сотрудников</h2>
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                        <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-striped" id="contactsTable">
                        <thead>
                            <tr>
                                <th>ID сотрудника</th>
                                <th>Имя</th>
                                <th>Фамилия</th>
                                <th>Роль</th>
                                <th>Должность</th>
                                <th>Тип контакта</th>
                                <th>Значение</th>
                                <th>Первичный</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr><td colspan="9" class="text-center">Нет сотрудников.</td></tr>
                            <?php else: ?>
                                <?php foreach ($employees as $employee): ?>
                                    <?php if (empty($employee['contacts'])): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['first_name']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['access_type']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['position_name'] ?? 'Не указана'); ?></td>
                                            <td colspan="3" class="text-center">Нет контактной информации.</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                        data-bs-target="#editEmployeeModal<?php echo htmlspecialchars($employee['employee_id']); ?>">
                                                    Изменить данные
                                                </button>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($employee['contacts'] as $contact): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['first_name']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['access_type']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['position_name'] ?? 'Не указана'); ?></td>
                                                <td><?php echo htmlspecialchars($contact['contact_type']); ?></td>
                                                <td><?php echo htmlspecialchars($contact['contact_value']); ?></td>
                                                <td><?php echo $contact['is_primary'] ? 'Да' : 'Нет'; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                            data-bs-target="#editContactModal<?php echo htmlspecialchars($contact['contact_id']); ?>">
                                                        Изменить контакт
                                                    </button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите удалить эту контактную информацию?');">
                                                        <input type="hidden" name="contact_id" value="<?php echo htmlspecialchars($contact['contact_id']); ?>">
                                                        <input type="hidden" name="action" value="delete_contact">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            Удалить
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-primary mt-1" data-bs-toggle="modal" 
                                                            data-bs-target="#editEmployeeModal<?php echo htmlspecialchars($employee['employee_id']); ?>">
                                                        Изменить данные
                                                    </button>
                                                </td>
                                            </tr>
                                            <!-- Модальное окно для редактирования контакта -->
                                            <div class="modal fade" id="editContactModal<?php echo htmlspecialchars($contact['contact_id']); ?>" 
                                                 tabindex="-1" aria-labelledby="editContactModalLabel<?php echo htmlspecialchars($contact['contact_id']); ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editContactModalLabel<?php echo htmlspecialchars($contact['contact_id']); ?>">
                                                                Редактирование контакта для <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="POST">
                                                                <input type="hidden" name="contact_id" value="<?php echo htmlspecialchars($contact['contact_id']); ?>">
                                                                <input type="hidden" name="action" value="edit_contact">
                                                                <div class="mb-3">
                                                                    <label for="contact_type<?php echo htmlspecialchars($contact['contact_id']); ?>" class="form-label">Тип контакта</label>
                                                                    <select name="contact_type" id="contact_type<?php echo htmlspecialchars($contact['contact_id']); ?>" 
                                                                            class="form-select" required>
                                                                        <option value="phone" <?php echo $contact['contact_type'] === 'phone' ? 'selected' : ''; ?>>Телефон</option>
                                                                        <option value="email" <?php echo $contact['contact_type'] === 'email' ? 'selected' : ''; ?>>Email</option>
                                                                        <option value="mobile" <?php echo $contact['contact_type'] === 'mobile' ? 'selected' : ''; ?>>Мобильный</option>
                                                                        <option value="other" <?php echo $contact['contact_type'] === 'other' ? 'selected' : ''; ?>>Другое</option>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label for="contact_value<?php echo htmlspecialchars($contact['contact_id']); ?>" class="form-label">Значение</label>
                                                                    <input type="text" name="contact_value" 
                                                                           id="contact_value<?php echo htmlspecialchars($contact['contact_id']); ?>" 
                                                                           class="form-control" 
                                                                           value="<?php echo htmlspecialchars($contact['contact_value']); ?>" 
                                                                           required maxlength="100">
                                                                </div>
                                                                <div class="mb-3 form-check">
                                                                    <input type="checkbox" name="is_primary" 
                                                                           id="is_primary<?php echo htmlspecialchars($contact['contact_id']); ?>" 
                                                                           class="form-check-input" 
                                                                           <?php echo $contact['is_primary'] ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="is_primary<?php echo htmlspecialchars($contact['contact_id']); ?>">
                                                                        Первичный контакт
                                                                    </label>
                                                                </div>
                                                                <button type="submit" class="btn btn-primary">Сохранить</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <!-- Модальное окно для редактирования данных сотрудника -->
                                    <div class="modal fade" id="editEmployeeModal<?php echo htmlspecialchars($employee['employee_id']); ?>" 
                                         tabindex="-1" aria-labelledby="editEmployeeModalLabel<?php echo htmlspecialchars($employee['employee_id']); ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editEmployeeModalLabel<?php echo htmlspecialchars($employee['employee_id']); ?>">
                                                        Редактирование данных сотрудника <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form method="POST">
                                                        <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee['employee_id']); ?>">
                                                        <input type="hidden" name="action" value="edit_employee">
                                                        <div class="mb-3">
                                                            <label for="first_name<?php echo htmlspecialchars($employee['employee_id']); ?>" class="form-label">Имя</label>
                                                            <input type="text" name="first_name" 
                                                                   id="first_name<?php echo htmlspecialchars($employee['employee_id']); ?>" 
                                                                   class="form-control" 
                                                                   value="<?php echo htmlspecialchars($employee['first_name']); ?>" 
                                                                   required maxlength="50">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="last_name<?php echo htmlspecialchars($employee['employee_id']); ?>" class="form-label">Фамилия</label>
                                                            <input type="text" name="last_name" 
                                                                   id="last_name<?php echo htmlspecialchars($employee['employee_id']); ?>" 
                                                                   class="form-control" 
                                                                   value="<?php echo htmlspecialchars($employee['last_name']); ?>" 
                                                                   required maxlength="50">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="position_id<?php echo htmlspecialchars($employee['employee_id']); ?>" class="form-label">Должность</label>
                                                            <select name="position_id" id="position_id<?php echo htmlspecialchars($employee['employee_id']); ?>" 
                                                                    class="form-select" required>
                                                                <?php foreach ($positions as $position): ?>
                                                                    <option value="<?php echo $position['position_id']; ?>" 
                                                                            <?php echo $employee['position_id'] == $position['position_id'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($position['position_name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="access_type<?php echo htmlspecialchars($employee['employee_id']); ?>" class="form-label">Тип доступа</label>
                                                            <select name="access_type" id="access_type<?php echo htmlspecialchars($employee['employee_id']); ?>" 
                                                                    class="form-select" required>
                                                                <option value="Administrator" <?php echo $employee['access_type'] === 'Administrator' ? 'selected' : ''; ?>>Администратор</option>
                                                                <option value="Project Manager" <?php echo $employee['access_type'] === 'Project Manager' ? 'selected' : ''; ?>>Менеджер проектов</option>
                                                                <option value="Network Engineer" <?php echo $employee['access_type'] === 'Network Engineer' ? 'selected' : ''; ?>>Сетевой инженер</option>
                                                                <option value="Installer" <?php echo $employee['access_type'] === 'Installer' ? 'selected' : ''; ?>>Монтажник</option>
                                                                <option value="Inventory Manager" <?php echo $employee['access_type'] === 'Inventory Manager' ? 'selected' : ''; ?>>Менеджер склада</option>
                                                                <option value="Analyst" <?php echo $employee['access_type'] === 'Analyst' ? 'selected' : ''; ?>>Аналитик</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="salary<?php echo htmlspecialchars($employee['employee_id']); ?>" class="form-label">Зарплата</label>
                                                            <input type="number" name="salary" 
                                                                   id="salary<?php echo htmlspecialchars($employee['employee_id']); ?>" 
                                                                   class="form-control" 
                                                                   value="<?php echo htmlspecialchars($employee['salary']); ?>" 
                                                                   step="0.01" min="0">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="hourly_rate<?php echo htmlspecialchars($employee['employee_id']); ?>" class="form-label">Часовая ставка</label>
                                                            <input type="number" name="hourly_rate" 
                                                                   id="hourly_rate<?php echo htmlspecialchars($employee['employee_id']); ?>" 
                                                                   class="form-control" 
                                                                   value="<?php echo htmlspecialchars($employee['hourly_rate']); ?>" 
                                                                   step="0.01" min="0">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="notification_preference<?php echo htmlspecialchars($employee['employee_id']); ?>" class="form-label">Предпочтения уведомлений</label>
                                                            <select name="notification_preference" id="notification_preference<?php echo htmlspecialchars($employee['employee_id']); ?>" 
                                                                    class="form-select" required>
                                                                <option value="email" <?php echo $employee['notification_preference'] === 'email' ? 'selected' : ''; ?>>Email</option>
                                                                <option value="sms" <?php echo $employee['notification_preference'] === 'sms' ? 'selected' : ''; ?>>SMS</option>
                                                                <option value="none" <?php echo $employee['notification_preference'] === 'none' ? 'selected' : ''; ?>>Нет</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3 form-check">
                                                            <input type="checkbox" name="is_active" 
                                                                   id="is_active<?php echo htmlspecialchars($employee['employee_id']); ?>" 
                                                                   class="form-check-input" 
                                                                   <?php echo $employee['is_active'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="is_active<?php echo htmlspecialchars($employee['employee_id']); ?>">
                                                                Активен
                                                            </label>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary">Сохранить</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
            $('#contactsTable').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ru.json' },
                pageLength: 10,
                lengthMenu: [10, 25, 50],
                columnDefs: [
                    { orderable: false, targets: 8 } // Отключение сортировки для столбца "Действия"
                ]
            });
        });
    </script>
</body>
</html>