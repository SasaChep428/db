<?php
// db_functions.php
// Функции для работы с базой данных

require_once '../includes/auth.php';

function getActiveProjects($pdo, $employee_id, $access_type) {
    $status_map = [
        'planning' => 'Планирование',
        'in_progress' => 'В процессе',
        'completed' => 'Завершён',
        'on_hold' => 'На паузе',
        'cancelled' => 'Отменён'
    ];

    try {
        $project_base_query = "
            SELECT p.project_id, p.project_name, c.customer_name,
                   CONCAT(l.address_city, ', ', l.address_street) AS location,
                   p.start_date, p.planned_end_date,
                   DATEDIFF(p.planned_end_date, CURDATE()) AS days_remaining,
                   p.status, p.completion_percentage
            FROM projects p
            JOIN customers c ON p.customer_id = c.customer_id
            JOIN locations l ON p.location_id = l.location_id
            WHERE p.status IN ('planning', 'in_progress')
        ";
        if ($access_type === 'Project Manager') {
            $project_query = "$project_base_query AND p.manager_id = ?";
            $stmt = $pdo->prepare($project_query);
            $stmt->execute([$employee_id]);
        } else {
            $project_query = "
                SELECT DISTINCT p.project_id, p.project_name, c.customer_name,
                                CONCAT(l.address_city, ', ', l.address_street) AS location,
                                p.start_date, p.planned_end_date,
                                DATEDIFF(p.planned_end_date, CURDATE()) AS days_remaining,
                                p.status, p.completion_percentage
                FROM projects p
                JOIN customers c ON p.customer_id = c.customer_id
                JOIN locations l ON p.location_id = l.location_id
                LEFT JOIN work_log wl ON p.project_id = wl.project_id
                LEFT JOIN tasks t ON p.project_id = t.project_id
                WHERE p.status IN ('planning', 'in_progress')
                  AND (wl.employee_id = ? OR t.employee_id = ? OR ? IN ('Administrator', 'Analyst'))
            ";
            $stmt = $pdo->prepare($project_query);
            $stmt->execute([$employee_id, $employee_id, $access_type]);
        }
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($projects as &$project) {
            $project['status'] = $status_map[$project['status']] ?? $project['status'];
        }
        return $projects;
    } catch (PDOException $e) {
        error_log("Ошибка получения проектов: " . $e->getMessage());
        return [];
    }
}

function getUserTasks($pdo, $employee_id) {
    $task_status_map = [
        'pending' => 'Ожидает',
        'in_progress' => 'В процессе',
        'completed' => 'Завершена'
    ];

    try {
        $task_query = "
            SELECT p.project_name, t.task_name, t.task_id, t.status, t.due_date
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.project_id
            WHERE t.employee_id = ? AND t.status IN ('pending', 'in_progress')
            ORDER BY t.due_date ASC
        ";
        $stmt = $pdo->prepare($task_query);
        $stmt->execute([$employee_id]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tasks as &$task) {
            $task['status'] = $task_status_map[$task['status']] ?? $task['status'];
        }
        return $tasks;
    } catch (PDOException $e) {
        error_log("Ошибка получения задач: " . $e->getMessage());
        return [];
    }
}

function getRecentNotifications($pdo, $employee_id) {
    try {
        $notification_query = "
            SELECT n.notification_id, n.notification_type, n.message, n.created_at,
                   CASE WHEN n.created_at > (
                       SELECT MAX(changed_at)
                       FROM audit_log
                       WHERE entity_type = 'employee' AND entity_id = ? AND changed_field = 'login'
                   ) THEN 1 ELSE 0 END AS is_unread
            FROM notifications n
            WHERE n.created_at > (
                SELECT MAX(changed_at)
                FROM audit_log
                WHERE entity_type = 'employee' AND entity_id = ? AND changed_field = 'login'
            )
            ORDER BY n.created_at DESC LIMIT 5
        ";
        $stmt = $pdo->prepare($notification_query);
        $stmt->execute([$employee_id, $employee_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Ошибка получения уведомлений: " . $e->getMessage());
        return [];
    }
}
?>