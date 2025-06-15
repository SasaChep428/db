<?php
// public/inventory.php
// Подключение файлов
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db_functions.php';

// Проверка авторизации
$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit();
}
$employee_id = $user['employee_id'];
$access_type = $user['access_type'];

// Ограничение доступа
$allowed_roles = ['Administrator', 'Inventory Manager'];
restrictAccess($allowed_roles);

// Инициализация данных
$inventory_data = [];

// Обработка выдачи материалов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_material'])) {
    $material_id = (int)$_POST['material_id'];
    $quantity = (int)$_POST['quantity'];
    $recipient_id = (int)$_POST['recipient_id'];
    $project_id = (int)$_POST['project_id'];

    if ($quantity > 0 && $recipient_id > 0 && $project_id > 0) {
        try {
            $pdo->beginTransaction();

            // Проверка остатка материалов
            $check_stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE inventory_id = ? FOR UPDATE");
            $check_stmt->execute([$material_id]);
            $check_result = $check_stmt->fetch();

            if (!$check_result || $check_result['quantity'] < $quantity) {
                $_SESSION['message'] = 'Недостаточно материалов на складе.';
                $_SESSION['message_type'] = 'danger';
                $pdo->rollBack();
            } else {
                // Обновление инвентаря
                $update_stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE inventory_id = ?");
                $update_stmt->execute([$quantity, $material_id]);

                // Запись в material_usage
                $usage_stmt = $pdo->prepare("INSERT INTO material_usage (project_id, inventory_id, quantity_used, usage_date, employee_id, recipient_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $usage_stmt->execute([$project_id, $material_id, $quantity, date('Y-m-d'), $employee_id, $recipient_id, 'issued']);

                // Логирование в историю для аудита (дополнительная таблица или существующая)
                $audit_stmt = $pdo->prepare("INSERT INTO audit_log (entity_type, entity_id, changed_field, old_value, new_value, changed_by, changed_at) 
                                            VALUES ('inventory', ?, 'quantity_issued', ?, ?, ?, CURRENT_TIMESTAMP)");
                $old_quantity = $check_result['quantity'];
                $new_quantity = $old_quantity - $quantity;
                $audit_stmt->execute([$material_id, $old_quantity, $new_quantity, $username]);

                $pdo->commit();
                $_SESSION['message'] = 'Материал успешно выдан.';
                $_SESSION['message_type'] = 'success';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Ошибка при выдаче материала: " . $e->getMessage());
            $_SESSION['message'] = "Ошибка при выдаче материала.";
            $_SESSION['message_type'] = 'danger';
        }
    } else {
        $_SESSION['message'] = 'Некорректные данные.';
        $_SESSION['message_type'] = 'danger';
    }
    header("Location: inventory.php");
    exit();
}

// Фильтры
$conditions = [];
$params = [];
if (isset($_GET['status']) && $_GET['status'] !== 'all') {
    $status = htmlspecialchars($_GET['status']);
    if ($status === 'low') {
        $conditions[] = "quantity <= min_stock_level";
    } elseif ($status === 'sufficient') {
        $conditions[] = "quantity > min_stock_level";
    }
}
if (isset($_GET['type']) && !empty($_GET['type'])) {
    $type = htmlspecialchars($_GET['type']);
    $conditions[] = "unit = :type";
    $params[':type'] = $type;
}

$query = "SELECT inventory_id, description, unit, quantity, unit_price, min_stock_level FROM inventory WHERE 1=1";
if (!empty($conditions)) {
    $query .= " AND " . implode(" AND ", $conditions);
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $inventory_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка получения инвентаря: " . $e->getMessage());
    $_SESSION['message'] = "Ошибка загрузки данных.";
    $_SESSION['message_type'] = 'danger';
    $inventory_data = [];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Инвентарь</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <h2 class="mt-4">Управление инвентарем</h2>
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                        <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                    </div>
                <?php endif; ?>

                <!-- Форма выдачи материалов -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5>Выдача материалов</h5>
                        <form method="POST" class="row g-3">
                            <div class="col-md-3">
                                <label for="project_id" class="form-label">Проект</label>
                                <select name="project_id" id="project_id" class="form-select" required>
                                    <?php
                                    $project_stmt = $pdo->query("SELECT project_id, project_name FROM projects WHERE status IN ('planning', 'in_progress')");
                                    while ($row = $project_stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='{$row['project_id']}'>" . htmlspecialchars($row['project_name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="material_id" class="form-label">Материал</label>
                                <select name="material_id" id="material_id" class="form-select" required>
                                    <?php
                                    $material_stmt = $pdo->query("SELECT inventory_id, description FROM inventory");
                                    while ($row = $material_stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='{$row['inventory_id']}'>" . htmlspecialchars($row['description']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="quantity" class="form-label">Количество</label>
                                <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                            </div>
                            <div class="col-md-3">
                                <label for="recipient_id" class="form-label">Получатель</label>
                                <select name="recipient_id" id="recipient_id" class="form-select" required>
                                    <?php
                                    $recipient_stmt = $pdo->query("SELECT employee_id, CONCAT(first_name, ' ', last_name) AS name FROM employees WHERE access_type = 'Installer'");
                                    while ($row = $recipient_stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='{$row['employee_id']}'>" . htmlspecialchars($row['name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" name="issue_material" class="btn btn-primary mt-4">Выдать</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Фильтры -->
                <form method="GET" class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Статус</label>
                        <select name="status" id="status" class="form-select">
                            <option value="all">Все</option>
                            <option value="low" <?php echo isset($_GET['status']) && $_GET['status'] === 'low' ? 'selected' : ''; ?>>Низкий уровень</option>
                            <option value="sufficient" <?php echo isset($_GET['status']) && $_GET['status'] === 'sufficient' ? 'selected' : ''; ?>>Достаточно</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="type" class="form-label">Тип</label>
                        <select name="type" id="type" class="form-select">
                            <option value="">Все типы</option>
                            <?php
                            $type_stmt = $pdo->query("SELECT DISTINCT unit FROM inventory");
                            while ($row = $type_stmt->fetch(PDO::FETCH_ASSOC)) {
                                $selected = isset($_GET['type']) && $_GET['type'] === $row['unit'] ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($row['unit']) . "' $selected>" . htmlspecialchars($row['unit']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary mt-4">Фильтр</button>
                    </div>
                </form>

                <!-- Таблица инвентаря -->
                <div class="table-responsive">
                    <table id="inventoryTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Описание</th>
                                <th>Ед. изм.</th>
                                <th>Количество</th>
                                <th>Цена</th>
                                <th>Мин. уровень</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inventory_data)): ?>
                                <tr><td colspan="6" class="text-center">Нет данных</td></tr>
                            <?php else: ?>
                                <?php foreach ($inventory_data as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['inventory_id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                                        <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                        <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                                        <td><?php echo htmlspecialchars($row['unit_price']); ?></td>
                                        <td><?php echo htmlspecialchars($row['min_stock_level']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Экспорт -->
                <a href="export_inventory.php" class="btn btn-secondary mt-3">Экспорт в CSV</a>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#inventoryTable').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/Russian.json' },
                pageLength: 10
            });
        });
    </script>
</body>
</html>