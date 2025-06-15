<?php
// api/employees.php
// Обработка AJAX-запроса для получения сотрудников проекта

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db_functions.php';

// Проверка авторизации
$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Неавторизован']);
    exit;
}
$employee_id = $user['employee_id'];
$access_type = $user['access_type'];

// Ограничение доступа
$allowedRoles = ['Administrator', 'Project Manager', 'Network Engineer', 'Installer', 'Analyst'];
restrictAccess($allowedRoles);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['get_employees']) || !isset($_POST['project_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный запрос']);
    exit;
}

$project_id = (int)$_POST['project_id'];
if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный ID проекта']);
    exit;
}

try {
    $query = "
        SELECT DISTINCT e.first_name, e.last_name, COALESCE(p.position_name, e.access_type) AS position_name, 
               COALESCE(t.task_name, 'Нет задачи') AS task_name
        FROM employees e
        LEFT JOIN tasks t ON e.employee_id = t.employee_id AND t.project_id = ?
        LEFT JOIN positions p ON e.position_id = p.position_id
        LEFT JOIN projects pr ON t.project_id = pr.project_id
        LEFT JOIN work_log wl ON pr.project_id = wl.project_id
        WHERE t.project_id = ?
          AND (pr.manager_id = ? OR wl.employee_id = ? OR t.employee_id = ? OR ? IN ('Administrator', 'Analyst'))
    ";
    $params = [$project_id, $project_id, $employee_id, $employee_id, $employee_id, $access_type];
    
    // Для IN используем явные заполнители
    if (in_array($access_type, ['Administrator', 'Analyst'])) {
        $query = str_replace("OR ? IN ('Administrator', 'Analyst')", "OR :access_type IN ('Administrator', 'Analyst')", $query);
        $params = [$project_id, $project_id, $employee_id, $employee_id, $employee_id];
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':access_type', $access_type, PDO::PARAM_STR);
    } else {
        $stmt = $pdo->prepare($query);
    }
    
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($employees ?: []);
} catch (PDOException $e) {
    error_log("Ошибка получения сотрудников: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера']);
}
exit;
?>