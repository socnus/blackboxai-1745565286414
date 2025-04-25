<?php
ob_start(); // Start output buffering
require_once 'includes/init.php';
require_once 'includes/task_helpers.php';
// Ensure user is admin
if (!isLoggedIn() || !isAdmin(getCurrentUserId())) {
    $_SESSION['flash_message'] = $translations[$currentLang]['msg_access_denied'];
    $_SESSION['flash_type'] = 'danger';
    header('Location: tasks.php');
    exit;
}

// Get all users for assignment
$stmt = $pdo->query("SELECT id, username FROM users ORDER BY username");
$users = $stmt->fetchAll();

// Get all categories
$stmt = $pdo->query("SELECT * FROM task_categories ORDER BY name");
$categories = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $priority = filter_input(INPUT_POST, 'priority', FILTER_SANITIZE_STRING);
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $dueDate = filter_input(INPUT_POST, 'due_date', FILTER_SANITIZE_STRING);
    $assignees = $_POST['assignees'] ?? [];

    if (empty($title) || empty($description) || empty($assignees)) {
    $_SESSION['flash_message'] = $translations[$currentLang]['required_fields'];
        $_SESSION['flash_type'] = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            // Calculate due date if not provided
            if (empty($dueDate)) {
                $workingDays = getWorkingDaysByPriority($priority);
                $dueDate = addWorkingDays(date('Y-m-d'), $workingDays);
            }

            // Create task
            $stmt = $pdo->prepare("
                INSERT INTO tasks (user_id, category_id, title, description, priority, due_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                getCurrentUserId(),
                $categoryId,
                $title,
                $description,
                $priority,
                $dueDate ?: null
            ]);
            
            $taskId = $pdo->lastInsertId();

            // Assign users
            $stmt = $pdo->prepare("
                INSERT INTO task_assignments (task_id, user_id)
                VALUES (?, ?)
            ");
            foreach ($assignees as $userId) {
                $stmt->execute([$taskId, $userId]);
            }

            // Log activity
            logActivity(getCurrentUserId(), 'create_task', "Created new task: $title");

            $pdo->commit();
            
            $_SESSION['flash_message'] = $translations[$currentLang]['task_created'];
            $_SESSION['flash_type'] = 'success';
            header("Location: view_task.php?id=$taskId");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = $translations[$currentLang]['task_creation_failed'];
            $_SESSION['flash_type'] = 'danger';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="col mb-4">
        <nav aria-label="breadcrumb" class="btn btn-outline-secondary w-100 p-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php"><?php echo $translations[$currentLang]['nav_home']; ?></a></li>
                <li class="breadcrumb-item"><a href="tasks.php"><?php echo $translations[$currentLang]['tasks']; ?></a></li>
                <li class="breadcrumb-item"><?php echo $translations[$currentLang]['add_task']; ?></li>
            </ol>
        </nav>
    </div>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h1 class="h4 mb-0">
                        <i class="fas fa-plus-circle me-2"></i><?php echo $translations[$currentLang]['create_new_task']; ?>
                    </h1>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="title" class="form-label"><?php echo $translations[$currentLang]['title']; ?></label>
                            <input type="text" class="form-control" id="title" name="title" required>
                            <div class="invalid-feedback">
                                <?php echo $translations[$currentLang]['required_fields']; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label"><?php echo $translations[$currentLang]['description']; ?></label>
                            <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                            <div class="invalid-feedback">
                                <?php echo $translations[$currentLang]['required_fields']; ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="category_id" class="form-label"><?php echo $translations[$currentLang]['category']; ?></label>
                                <select class="form-select" id="category_id" name="category_id" required>
                            <option value=""><?php echo $translations[$currentLang]['select_category']; ?></option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo h($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                <?php echo $translations[$currentLang]['required_fields']; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="priority" class="form-label"><?php echo $translations[$currentLang]['priority']; ?></label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="low"><?php echo $translations[$currentLang]['priority_low']; ?></option>
                                    <option value="medium" selected><?php echo $translations[$currentLang]['priority_medium']; ?></option>
                                    <option value="high"><?php echo $translations[$currentLang]['priority_high']; ?></option>
                                    <option value="urgent"><?php echo $translations[$currentLang]['priority_urgent']; ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="due_date" class="form-label"><?php echo $translations[$currentLang]['due_date']; ?></label>
                            <input type="date" class="form-control" id="due_date" name="due_date"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="assignees" class="form-label"><?php echo $translations[$currentLang]['assign_to']; ?></label>
                            <select class="form-select" id="assignees" name="assignees[]" multiple required>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo h($user['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                <?php echo $translations[$currentLang]['required_fields']; ?>
                            </div>
                            <div class="form-text">
                                <?php echo $translations[$currentLang]['select_multiple']; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="tasks.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i><?php echo $translations[$currentLang]['back_to_tasks']; ?>
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i><?php echo $translations[$currentLang]['create_new_task']; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
