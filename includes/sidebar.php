<?php
// sidebar.php
// Боковая панель навигации

require_once '../includes/auth.php';

// Проверка авторизации
$user = getCurrentUser();
if (!$user) {
    header('Location: ../public/login.php');
    exit;
}
$employee_id = $user['employee_id'];
$access_type = $user['access_type'];

// Определение текущей страницы для подсветки активного пункта меню
$current_page = basename($_SERVER['PHP_SELF']);

// Получение количества непрочитанных уведомлений
global $pdo;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE created_at > (
            SELECT MAX(changed_at) 
            FROM audit_log 
            WHERE entity_type = 'employee' 
            AND entity_id = ? 
            AND changed_field = 'login'
        )
    ");
    $stmt->execute([$employee_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
} catch (PDOException $e) {
    error_log("Ошибка получения уведомлений: " . $e->getMessage());
    $unread_notifications = 0;
}

// Определение пунктов меню по ролям
$menu_items = [
    'all' => [
        ['page' => 'index.php', 'icon' => 'bi-house-door', 'label' => 'Панель управления'],
        ['page' => 'notifications.php', 'icon' => 'bi-bell', 'label' => 'Уведомления', 'badge' => $unread_notifications],
        ['page' => 'about.php', 'icon' => 'bi-info-circle', 'label' => 'О системе']
    ],
    'Administrator' => [
        'Администрирование' => [
            ['page' => 'employees.php', 'icon' => 'bi-people', 'label' => 'Управление сотрудниками'],
            ['page' => 'add_entities.php', 'icon' => 'bi-person-plus', 'label' => 'Добавить сущности'],
            ['page' => 'customer_contacts.php', 'icon' => 'bi-person-lines-fill', 'label' => 'Контакты клиентов'],
            ['page' => 'audit.php', 'icon' => 'bi-journal-text', 'label' => 'Журнал аудита']
        ],
        'Проекты' => [
            ['page' => 'projects.php', 'icon' => 'bi-briefcase', 'label' => 'Проекты'],
            ['page' => 'create_task.php', 'icon' => 'bi-list-task', 'label' => 'Создать задачу'],
            ['page' => 'upload_document.php', 'icon' => 'bi-file-earmark-arrow-up', 'label' => 'Загрузить документ']
        ],
        'Инвентарь' => [
            ['page' => 'inventory.php', 'icon' => 'bi-box', 'label' => 'Инвентарь'],
            ['page' => 'add_inventory.php', 'icon' => 'bi-box-arrow-in-up', 'label' => 'Добавить материал']
        ],
        'Отчеты' => [
            ['page' => 'reports.php', 'icon' => 'bi-bar-chart', 'label' => 'Отчеты']
        ]
    ],
    'Project Manager' => [
        'Проекты' => [
            ['page' => 'projects.php', 'icon' => 'bi-briefcase', 'label' => 'Проекты'],
            ['page' => 'create_task.php', 'icon' => 'bi-list-task', 'label' => 'Создать задачу'],
            ['page' => 'upload_document.php', 'icon' => 'bi-file-earmark-arrow-up', 'label' => 'Загрузить документ'],
            ['page' => 'resource_conflicts.php', 'icon' => 'bi-exclamation-triangle', 'label' => 'Конфликты ресурсов']
        ],
        'Отчеты' => [
            ['page' => 'reports.php', 'icon' => 'bi-bar-chart', 'label' => 'Отчеты']
        ]
    ],
    'Network Engineer' => [
        'Проекты' => [
            ['page' => 'projects.php', 'icon' => 'bi-briefcase', 'label' => 'Проекты'],
            ['page' => 'create_task.php', 'icon' => 'bi-list-task', 'label' => 'Создать задачу'],
            ['page' => 'cable_runs.php', 'icon' => 'bi-diagram-3', 'label' => 'Кабельные трассы']
        ]
    ],
    'Installer' => [
        'Задачи' => [
            ['page' => 'installer_page.php', 'icon' => 'bi-tools', 'label' => 'Задачи монтажника'],
            ['page' => 'work_log.php', 'icon' => 'bi-clock-history', 'label' => 'Журнал работ']
        ]
    ],
    'Inventory Manager' => [
        'Инвентарь' => [
            ['page' => 'inventory.php', 'icon' => 'bi-box', 'label' => 'Инвентарь'],
            ['page' => 'add_inventory.php', 'icon' => 'bi-box-arrow-in-up', 'label' => 'Добавить материал'],
            ['page' => 'purchase_orders.php', 'icon' => 'bi-cart', 'label' => 'Заказы на закупку']
        ]
    ],
    'Analyst' => [
        'Отчеты' => [
            ['page' => 'reports.php', 'icon' => 'bi-bar-chart', 'label' => 'Отчеты']
        ]
    ]
];
?>

<!-- Боковая панель навигации -->
<aside class="offcanvas offcanvas-start bg-light border-end" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarLabel">Меню</h5>
        <button type="button" class="btn btn-primary toggle-sidebar d-none d-lg-block" id="toggleSidebar">
            <i class="bi bi-chevron-left"></i>
        </button>
        <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <nav class="nav flex-column flex-grow-1">
            <!-- Общие пункты меню -->
            <div class="accordion" id="sidebarAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#generalMenu" aria-expanded="false" aria-controls="generalMenu">
                            Общее
                        </button>
                    </h2>
                    <div id="generalMenu" class="accordion-collapse collapse" data-bs-parent="#sidebarAccordion">
                        <div class="accordion-body p-0">
                            <?php foreach ($menu_items['all'] as $item): ?>
                                <a class="nav-link <?php echo $current_page === $item['page'] ? 'active' : ''; ?>" href="<?php echo $item['page']; ?>">
                                    <i class="bi <?php echo $item['icon']; ?> me-2"></i>
                                    <?php echo htmlspecialchars($item['label']); ?>
                                    <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                                        <span class="badge bg-danger ms-2"><?php echo $item['badge']; ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <!-- Пункты меню для конкретной роли -->
                <?php if (isset($menu_items[$access_type])): ?>
                    <?php foreach ($menu_items[$access_type] as $category => $items): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo str_replace(' ', '', $category); ?>Menu" aria-expanded="false" aria-controls="<?php echo str_replace(' ', '', $category); ?>Menu">
                                    <?php echo htmlspecialchars($category); ?>
                                </button>
                            </h2>
                            <div id="<?php echo str_replace(' ', '', $category); ?>Menu" class="accordion-collapse collapse" data-bs-parent="#sidebarAccordion">
                                <div class="accordion-body p-0">
                                    <?php foreach ($items as $item): ?>
                                        <a class="nav-link <?php echo $current_page === $item['page'] ? 'active' : ''; ?>" href="<?php echo $item['page']; ?>">
                                            <i class="bi <?php echo $item['icon']; ?> me-2"></i>
                                            <?php echo htmlspecialchars($item['label']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</aside>