<?php
// categories.php - Manage categories
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = getDBConnection();
$userId = $_SESSION['user_id'];

// Handle category creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $color = sanitize($_POST['color']);
        
        $stmt = $db->prepare("INSERT INTO categories (name, description, color, created_by) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $description, $color, $userId])) {
            $success = "Category created successfully!";
        } else {
            $error = "Failed to create category.";
        }
    }
}

// Handle category deletion
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    if (verifyCSRFToken($_GET['csrf_token'])) {
        $categoryId = (int)$_GET['delete'];
        
        // Check if user created this category
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ? AND created_by = ?");
        if ($stmt->execute([$categoryId, $userId])) {
            $success = "Category deleted successfully!";
        } else {
            $error = "Cannot delete this category.";
        }
    }
}

// Fetch all categories with usage count
$stmt = $db->query("
    SELECT c.*, u.username as created_by_name, 
           COUNT(DISTINCT wi.id) as item_count
    FROM categories c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN wishlist_items wi ON c.id = wi.category_id
    GROUP BY c.id
    ORDER BY c.name
");
$categories = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo PRIMARY_COLOR; ?>;
            --secondary-color: <?php echo SECONDARY_COLOR; ?>;
            --background-color: <?php echo BACKGROUND_COLOR; ?>;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--background-color) 0%, #bbdefb 100%);
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }
        
        .category-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }
        
        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-block;
            vertical-align: middle;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .create-form {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .color-picker {
            width: 60px;
            height: 40px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-gift-fill me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-house-door me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="categories.php">
                            <i class="bi bi-tags me-1"></i>Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person me-1"></i><?php echo sanitize($_SESSION['username']); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php?logout=1">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1 class="display-6 fw-bold">Categories</h1>
            <p class="lead mb-0">Organize your wishlist items with custom categories</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Create Category Form -->
            <div class="col-lg-4">
                <div class="create-form">
                    <h4 class="mb-4">
                        <i class="bi bi-plus-circle me-2"></i>Create Category
                    </h4>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="create_category" value="1">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="name" name="name" required maxlength="100">
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="color" class="form-label">Color</label>
                            <input type="color" class="form-control color-picker" id="color" name="color" value="#007bff">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle me-2"></i>Create Category
                        </button>
                    </form>
                </div>
                
                <div class="alert alert-info">
                    <h6 class="alert-heading">
                        <i class="bi bi-info-circle me-2"></i>Tips
                    </h6>
                    <ul class="mb-0 small">
                        <li>Categories help organize your wishlist items</li>
                        <li>Choose colors that make categories easy to identify</li>
                        <li>You can create unlimited categories</li>
                        <li>Categories are shared across all your wishlists</li>
                    </ul>
                </div>
            </div>

            <!-- Categories List -->
            <div class="col-lg-8">
                <h4 class="mb-4">
                    <i class="bi bi-tags me-2"></i>All Categories
                    <span class="badge bg-secondary ms-2"><?php echo count($categories); ?></span>
                </h4>
                
                <?php if (empty($categories)): ?>
                    <div class="alert alert-light text-center py-5">
                        <i class="bi bi-tags" style="font-size: 3rem; opacity: 0.5;"></i>
                        <p class="mt-3 mb-0">No categories yet. Create your first category!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card" style="border-left-color: <?php echo $category['color']; ?>;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <div class="color-preview me-3" style="background-color: <?php echo $category['color']; ?>;"></div>
                                    <div>
                                        <h5 class="mb-1"><?php echo sanitize($category['name']); ?></h5>
                                        <?php if ($category['description']): ?>
                                            <p class="text-muted mb-1 small"><?php echo sanitize($category['description']); ?></p>
                                        <?php endif; ?>
                                        <div class="text-muted small">
                                            <i class="bi bi-box me-1"></i><?php echo $category['item_count']; ?> items
                                            <?php if ($category['created_by_name']): ?>
                                                <span class="mx-2">•</span>
                                                <i class="bi bi-person me-1"></i>Created by <?php echo sanitize($category['created_by_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($category['created_by'] == $userId && $category['item_count'] == 0): ?>
                                        <a href="?delete=<?php echo $category['id']; ?>&csrf_token=<?php echo $csrfToken; ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Delete this category?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php elseif ($category['item_count'] > 0): ?>
                                        <span class="text-muted small">In use</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>