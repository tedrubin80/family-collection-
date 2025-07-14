<?php
// wishlist.php - View wishlist details and items
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = getDBConnection();
$userId = $_SESSION['user_id'];
$wishlistId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if user has access to this wishlist
$stmt = $db->prepare("
    SELECT w.*, u.username as owner_name,
           CASE 
               WHEN w.user_id = ? THEN 'owner'
               WHEN sw.shared_with_user_id = ? THEN IF(sw.can_edit, 'editor', 'viewer')
               ELSE 'none'
           END as access_level
    FROM wishlists w
    JOIN users u ON w.user_id = u.id
    LEFT JOIN shared_wishlists sw ON w.id = sw.wishlist_id AND sw.shared_with_user_id = ?
    WHERE w.id = ? AND (w.user_id = ? OR sw.shared_with_user_id = ? OR w.is_public = 1)
");
$stmt->execute([$userId, $userId, $userId, $wishlistId, $userId, $userId]);
$wishlist = $stmt->fetch();

if (!$wishlist) {
    header('Location: dashboard.php');
    exit;
}

// Fetch wishlist items
$stmt = $db->prepare("
    SELECT wi.*, c.name as category_name, c.color as category_color, u.username as purchased_by_name
    FROM wishlist_items wi
    LEFT JOIN categories c ON wi.category_id = c.id
    LEFT JOIN users u ON wi.purchased_by = u.id
    WHERE wi.wishlist_id = ?
    ORDER BY 
        CASE wi.priority 
            WHEN 'high' THEN 1 
            WHEN 'medium' THEN 2 
            WHEN 'low' THEN 3 
        END,
        wi.created_at DESC
");
$stmt->execute([$wishlistId]);
$items = $stmt->fetchAll();

// Fetch categories for add item modal
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
$canEdit = in_array($wishlist['access_level'], ['owner', 'editor']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($wishlist['title']); ?> - <?php echo SITE_NAME; ?></title>
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
        
        .wishlist-header {
            background: linear-gradient(135deg, var(--background-color) 0%, #bbdefb 100%);
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }
        
        .item-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }
        
        .item-card.purchased {
            opacity: 0.7;
            background: #f8f9fa;
        }
        
        .item-card.purchased::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(0, 0, 0, 0.03) 10px,
                rgba(0, 0, 0, 0.03) 20px
            );
            pointer-events: none;
        }
        
        .priority-indicator {
            width: 4px;
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
        }
        
        .priority-high .priority-indicator { background: #dc3545; }
        .priority-medium .priority-indicator { background: #ffc107; }
        .priority-low .priority-indicator { background: #28a745; }
        
        .category-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
            display: inline-block;
            color: white;
            font-weight: 500;
        }
        
        .item-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .item-card:hover .item-actions {
            opacity: 1;
        }
        
        .price-tag {
            background: #28a745;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .filter-tabs {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
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
                        <a class="nav-link" href="categories.php">
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

    <!-- Wishlist Header -->
    <div class="wishlist-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-6 fw-bold mb-2"><?php echo sanitize($wishlist['title']); ?></h1>
                    <?php if ($wishlist['description']): ?>
                        <p class="lead mb-2"><?php echo sanitize($wishlist['description']); ?></p>
                    <?php endif; ?>
                    <div class="text-muted">
                        <i class="bi bi-person me-1"></i> By <?php echo sanitize($wishlist['owner_name']); ?>
                        <span class="mx-2">•</span>
                        <i class="bi bi-box me-1"></i> <?php echo count($items); ?> items
                        <?php if ($wishlist['is_public']): ?>
                            <span class="mx-2">•</span>
                            <span class="badge bg-info">Public</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <?php if ($canEdit): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                            <i class="bi bi-plus-circle me-2"></i>Add Item
                        </button>
                        <a href="edit-wishlist.php?id=<?php echo $wishlistId; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-gear me-2"></i>Settings
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <ul class="nav nav-pills" id="filterTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-tab" data-bs-toggle="pill" data-bs-target="#all" type="button">
                        All Items <span class="badge bg-secondary ms-1"><?php echo count($items); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="available-tab" data-bs-toggle="pill" data-bs-target="#available" type="button">
                        Available <span class="badge bg-success ms-1"><?php echo count(array_filter($items, function($i) { return !$i['is_purchased']; })); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="purchased-tab" data-bs-toggle="pill" data-bs-target="#purchased" type="button">
                        Purchased <span class="badge bg-info ms-1"><?php echo count(array_filter($items, function($i) { return $i['is_purchased']; })); ?></span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Items List -->
        <div class="tab-content" id="filterTabsContent">
            <div class="tab-pane fade show active" id="all" role="tabpanel">
                <?php if (empty($items)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3>No items yet</h3>
                        <p>Start adding items to this wishlist!</p>
                        <?php if ($canEdit): ?>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                <i class="bi bi-plus-circle me-2"></i>Add First Item
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <div class="item-card priority-<?php echo $item['priority']; ?> <?php echo $item['is_purchased'] ? 'purchased' : ''; ?>">
                            <div class="priority-indicator"></div>
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4 class="mb-2">
                                        <?php if ($item['url']): ?>
                                            <a href="<?php echo sanitize($item['url']); ?>" target="_blank" class="text-decoration-none">
                                                <?php echo sanitize($item['title']); ?>
                                                <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                                            </a>
                                        <?php else: ?>
                                            <?php echo sanitize($item['title']); ?>
                                        <?php endif; ?>
                                    </h4>
                                    
                                    <?php if ($item['description']): ?>
                                        <p class="text-muted mb-2"><?php echo sanitize($item['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if ($item['category_name']): ?>
                                            <span class="category-badge" style="background-color: <?php echo $item['category_color']; ?>">
                                                <i class="bi bi-tag me-1"></i><?php echo sanitize($item['category_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <span class="text-muted small">
                                            <i class="bi bi-flag me-1"></i>
                                            Priority: <strong class="text-<?php echo $item['priority'] == 'high' ? 'danger' : ($item['priority'] == 'medium' ? 'warning' : 'success'); ?>">
                                                <?php echo ucfirst($item['priority']); ?>
                                            </strong>
                                        </span>
                                        
                                        <?php if ($item['is_purchased']): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i>
                                                Purchased by <?php echo sanitize($item['purchased_by_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 text-md-end">
                                    <?php if ($item['price'] && $item['show_price']): ?>
                                        <div class="price-tag mb-2">
                                            $<?php echo number_format($item['price'], 2); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="item-actions">
                                        <?php if (!$item['is_purchased'] && $wishlist['access_level'] != 'owner'): ?>
                                            <button class="btn btn-sm btn-success" onclick="markPurchased(<?php echo $item['id']; ?>)">
                                                <i class="bi bi-check-circle me-1"></i>Mark Purchased
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($canEdit): ?>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="editItem(<?php echo $item['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <?php if ($canEdit): ?>
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Item to Wishlist</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="add-item.php">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="wishlist_id" value="<?php echo $wishlistId; ?>">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Item Name</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="">No category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo sanitize($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="url" class="form-label">Product URL (Optional)</label>
                            <input type="url" class="form-control" id="url" name="url" placeholder="https://...">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="priority" class="form-label">Priority</label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Price (Optional)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="price" name="price" step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="show_price" name="show_price" checked>
                                        <label class="form-check-label" for="show_price">
                                            Show price to others
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Add Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markPurchased(itemId) {
            if (confirm('Mark this item as purchased?')) {
                window.location.href = 'mark-purchased.php?id=' + itemId + '&csrf_token=<?php echo $csrfToken; ?>';
            }
        }
        
        function editItem(itemId) {
            // Would open edit modal
            window.location.href = 'edit-item.php?id=' + itemId;
        }
        
        function deleteItem(itemId) {
            if (confirm('Are you sure you want to delete this item?')) {
                window.location.href = 'delete-item.php?id=' + itemId + '&csrf_token=<?php echo $csrfToken; ?>';
            }
        }
        
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const availableTab = document.getElementById('available-tab');
            const purchasedTab = document.getElementById('purchased-tab');
            const allTab = document.getElementById('all-tab');
            const items = document.querySelectorAll('.item-card');
            
            availableTab.addEventListener('click', function() {
                items.forEach(item => {
                    if (item.classList.contains('purchased')) {
                        item.style.display = 'none';
                    } else {
                        item.style.display = 'block';
                    }
                });
            });
            
            purchasedTab.addEventListener('click', function() {
                items.forEach(item => {
                    if (item.classList.contains('purchased')) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
            
            allTab.addEventListener('click', function() {
                items.forEach(item => {
                    item.style.display = 'block';
                });
            });
        });
    </script>
</body>
</html>