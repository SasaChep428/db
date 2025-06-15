<?php
require_once 'config.php';

/**
 * Аутентификация пользователя через таблицу employees
 * @param string $username Имя пользователя
 * @param string $password Пароль
 * @return array|null Возвращает данные пользователя или null при неудачной аутентификации
 */
function authenticateUser($username, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT employee_id, username, password, access_type, first_name, last_name, is_active
            FROM employees
            WHERE username = :username
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        
        // Проверка активности учетной записи и совпадения пароля (SHA2)
        if ($user && $user['is_active'] && hash('sha256', $password) === $user['password']) {
            return [
                'employee_id' => $user['employee_id'],
                'username' => $user['username'],
                'access_type' => $user['access_type'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name']
            ];
        }
        return null;
    } catch (PDOException $e) {
        error_log("Ошибка аутентификации: " . $e->getMessage());
        return null;
    }
}

/**
 * Проверка, вошел ли пользователь в систему
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

/**
 * Получение данных текущего пользователя
 * @return array|null
 */
function getCurrentUser() {
    return isLoggedIn() ? $_SESSION['user'] : null;
}

/**
 * Проверка наличия необходимой роли у пользователя
 * @param string|array $requiredRoles Требуемые роли
 * @return bool
 */
function hasRole($requiredRoles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = $_SESSION['user']['access_type'];
    if (is_array($requiredRoles)) {
        return in_array($userRole, $requiredRoles);
    }
    return $userRole === $requiredRoles;
}

/**
 * Ограничение доступа для пользователей без необходимых ролей
 * @param string|array $requiredRoles Требуемые роли
 */
function restrictAccess($requiredRoles) {
    if (!hasRole($requiredRoles)) {
        header('Location: ../public/login.php');
        exit;
    }
}

/**
 * Выход пользователя из системы
 */
function logout() {
    session_unset();
    session_destroy();
}
?>
