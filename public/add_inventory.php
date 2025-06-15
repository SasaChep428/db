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
$allowed_roles = ['Administrator', 'Inventory Manager'];
restrictAccess($allowed_roles);

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_material'])) {
    try {
        $description = trim($_POST['description']);
        $unit = trim($_POST['unit']);
        $quantity = (int)$_POST['quantity'];
        $unit_price = (float)$_POST['unit_price'];
        $min_stock_level = (int)$_POST['min_stock_level'];

        // Валидация
        if (empty($description) || empty($unit) || $quantity < 0 || $unit_price < 0 || $min_stock_level < 0) {
            throw new Exception('Все поля должны быть корректно заполнены.');
        }

        $pdo->beginTransaction();

        // Добавление материала
        $stmt = $pdo->prepare("INSERT INTO inventory (description, unit, quantity, unit_price, min_stock_level) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$description, $unit, $quantity, $unit_price, $min_stock_level]);
        $new_inventory_id = $pdo->lastInsertId();

        // Логирование
        $stmt = $pdo->prepare("INSERT INTO audit_log (entity_type, entity_id, changed_field, old_value, new_value, changed_by, changed_at) VALUES ('inventory', ?, 'created', NULL, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$new_inventory_id, "Материал: $description", $employee_id]);

        $pdo->commit();
        $_SESSION['message'] = 'Материал успешно добавлен.';
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = 'Ошибка: ' . htmlspecialchars($e->getMessage());
        $_SESSION['message_type'] = 'danger';
    }
    header('Location: add_inventory.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавление материалов</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <h2 class="mt-4">Добавление материалов в инвентарь</h2>
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                        <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="add_material" value="1">
                    <div class="col-md-6">
                        <label for="description" class="form-label">Описание *</label>
                        <input type="text" name="description" id="description" class="form-control" required maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label for="unit" class="form-label">Единица измерения *</label>
                        <input type="text" name="unit" id="unit" class="form-control" required maxlength="50">
                    </div>
                    <div class="col-md-4">
                        <label for="quantity" class="form-label">Количество *</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" required min="0">
                    </div>
                    <div class="col-md-4">
                        <label for="unit_price" class="form-label">Цена за единицу *</label>
                        <input type="number" name="unit_price" id="unit_price" class="form-control" required step="0.01" min="0">
                    </div>
                    <div class="col-md-4">
                        <label for="min_stock_level" class="form-label">Минимальный уровень запаса *</label>
                        <input type="number" name="min_stock_level" id="min_stock_level" class="form-control" required min="0">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Добавить материал</button>
                    </div>
                </form>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>