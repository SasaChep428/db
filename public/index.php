<?php
// Главная страница дашборда

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db_functions.php';

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

try {
    $projects = getActiveProjects($pdo, $employee_id, $access_type);
    $notifications = getRecentNotifications($pdo, $employee_id);

    // Получение задач
    $task_query = "
        SELECT t.task_id, p.project_name, t.task_name, t.status, t.due_date
        FROM tasks t
        JOIN projects p ON t.project_id = p.project_id
        WHERE 1=1
    ";
    $params = [];
    if ($access_type === 'Project Manager') {
        $task_query .= " AND p.manager_id = ?";
        $params[] = $employee_id;
    } elseif (!in_array($access_type, ['Administrator', 'Analyst'])) {
        $task_query .= " AND t.employee_id = ?";
        $params[] = $employee_id;
    }
    $task_query .= " ORDER BY t.due_date";
    $stmt = $pdo->prepare($task_query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка подключения: " . $e->getMessage());
    $error_message = "Ошибка подключения к базе данных.";
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления</title>
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
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <h2 class="mt-4">Панель управления</h2>
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                        <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                <!-- Карточки-виджеты -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Активные проекты</h5>
                                <p class="display-6"><?php echo count($projects); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Мои задачи</h5>
                                <p class="display-6"><?php echo count($tasks); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Уведомления</h5>
                                <p class="display-6"><?php echo count($notifications); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Активные проекты -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Активные проекты</h5>
                        <?php if (in_array($access_type, ['Administrator', 'Project Manager'])): ?>
                            <a href="projects.php" class="btn btn-primary mb-3">Создать проект</a>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table id="projectsTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Название проекта</th>
                                        <th>Заказчик</th>
                                        <th>Местоположение</th>
                                        <th>Дата начала</th>
                                        <th>Плановая дата завершения</th>
                                        <th>Осталось дней</th>
                                        <th>Статус</th>
                                        <th>Прогресс</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($projects)): ?>
                                        <tr><td colspan="9" class="text-center">Нет активных проектов.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($projects as $project): ?>
                                            <tr <?php echo $project['days_remaining'] < 0 ? 'class="table-danger"' : ''; ?>>
                                                <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                                <td><?php echo htmlspecialchars($project['customer_name']); ?></td>
                                                <td><?php echo htmlspecialchars($project['location']); ?></td>
                                                <td><?php echo htmlspecialchars($project['start_date']); ?></td>
                                                <td><?php echo htmlspecialchars($project['planned_end_date']); ?></td>
                                                <td><?php echo htmlspecialchars($project['days_remaining']); ?></td>
                                                <td><?php echo htmlspecialchars($project['status']); ?></td>
                                                <td><?php echo htmlspecialchars($project['completion_percentage']); ?>%</td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewEmployeesModal"
                                                            data-project-id="<?php echo htmlspecialchars($project['project_id']); ?>"
                                                            data-project-name="<?php echo htmlspecialchars($project['project_name']); ?>">
                                                        Просмотр сотрудников
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Уведомления -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Уведомления</h5>
                        <div class="table-responsive">
                            <table id="notificationsTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Тип</th>
                                        <th>Сообщение</th>
                                        <th>Дата</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($notifications)): ?>
                                        <tr><td colspan="3" class="text-center">Нет уведомлений.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($notifications as $notification): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($notification['notification_type']); ?></td>
                                                <td><?php echo htmlspecialchars($notification['message']); ?></td>
                                                <td><?php echo htmlspecialchars($notification['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Мои задачи -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Мои задачи</h5>
                        <div class="table-responsive">
                            <table id="tasksTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Проект</th>
                                        <th>Задача</th>
                                        <th>Статус</th>
                                        <th>Дата завершения</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tasks)): ?>
                                        <tr><td colspan="4" class="text-center">Нет задач.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($tasks as $task): ?>
                                            <tr <?php echo !empty($task['due_date']) && strtotime($task['due_date']) < time() ? 'class="table-danger"' : ''; ?>>
                                                <td><?php echo htmlspecialchars($task['project_name']); ?></td>
                                                <td><?php echo htmlspecialchars($task['task_name']); ?></td>
                                                <td><?php echo htmlspecialchars($task['status']); ?></td>
                                                <td><?php echo htmlspecialchars($task['due_date'] ?? 'Не указано'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Модальное окно для просмотра сотрудников -->
                <div class="modal fade" id="viewEmployeesModal" tabindex="-1" aria-labelledby="viewEmployeesModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="viewEmployeesModalLabel">Сотрудники проекта</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive">
                                    <table id="employeesTable" class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Имя</th>
                                                <th>Фамилия</th>
                                                <th>Должность</th>
                                                <th>Задача</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#projectsTable, #notificationsTable, #tasksTable').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ru.json' },
                pageLength: 5,
                lengthMenu: [5, 10, 25],
                searching: true,
                lengthChange: true
            });

            const viewEmployeesModal = document.getElementById('viewEmployeesModal');
            viewEmployeesModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const projectId = button.getAttribute('data-project-id');
                const projectName = button.getAttribute('data-project-name');
                const modalTitle = viewEmployeesModal.querySelector('.modal-title');
                modalTitle.textContent = `Сотрудники проекта: ${projectName}`;

                $.ajax({
                    url: 'api/employees.php',
                    type: 'POST',
                    data: { get_employees: true, project_id: projectId },
                    dataType: 'json',
                    success: function (response) {
                        const tbody = $('#employeesTable tbody');
                        tbody.empty();
                        if (response.length === 0) {
                            tbody.append('<tr><td colspan="4" class="text-center">Нет сотрудников.</td></tr>');
                        } else {
                            response.forEach(function (employee) {
                                tbody.append(`
                                    <tr>
                                        <td>${employee.first_name}</td>
                                        <td>${employee.last_name}</td>
                                        <td>${employee.position_name}</td>
                                        <td>${employee.task_name}</td>
                                    </tr>
                                `);
                            });
                        }
                    },
                    error: function () {
                        $('#employeesTable tbody').html('<tr><td colspan="4" class="text-center">Ошибка загрузки данных.</td></tr>');
                    }
                });
            });
        });
    </script>
</body>
</html>
