<?php
// dashboard.php - Main dashboard after login
session_start();
require_once 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = getDBConnection();
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch user's wishlists
$stmt = $db->prepare("
    SELECT w.*, COUNT(wi.id) as item_count 
    FROM wishlists w 
    LEFT JOIN wishlist_items wi ON w.id = wi.wishlist_id 
    WHERE w.user_id = ? 
    GROUP BY w.id 
    ORDER BY w.updated_at DESC
");
$stmt->execute([$userId]);
$wishlists = $stmt->fetchAll();

// Fetch shared wishlists
$stmt = $db->prepare("
    SELECT w.*, u.username as owner_name, sw.can_edit, COUNT(wi.id) as item_count 
    FROM shared_wishlists sw 
    JOIN wishlists w ON sw.wishlist_id = w.id 
    JOIN users u ON w.user_id = u.id 
    LEFT JOIN wishlist_items wi ON w.id = wi.wishlist_id 
    WHERE sw.shared_with_user_id = ? 
    GROUP BY w.id 
    ORDER BY sw.shared_at DESC
");
$stmt->execute([$userId]);
$sharedWishlists = $stmt->fetchAll();

// Fetch categories
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .main-header {
            background: linear-gradient(135deg, var(--background-color) 0%, #bbdefb 100%);
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }
        
        .wishlist-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .wishlist-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }
        
        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            height: 100%;
        }
        
        .stats-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .quick-actions {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }
        
        .btn-fab {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
            color: white;
        }
        
        .priority-high { color: #dc3545; }
        .priority-medium { color: #ffc107; }
        .priority-low { color: #28a745; }
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
                            <i class="bi bi-person me-1"></i><?php echo sanitize($username); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="?logout=1">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Header -->
    <div class="main-header">
        <div class="container">
            <h1 class="display-5 fw-bold">Welcome back, <?php echo sanitize($username); ?>!</h1>
            <p class="lead mb-0">Manage your wishlists and discover what your family wishes for</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <i class="bi bi-list-ul text-primary"></i>
                    <h3><?php echo count($wishlists); ?></h3>
                    <p class="text-muted mb-0">My Wishlists</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <i class="bi bi-people text-success"></i>
                    <h3><?php echo count($sharedWishlists); ?></h3>
                    <p class="text-muted mb-0">Shared With Me</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <i class="bi bi-tags text-warning"></i>
                    <h3><?php echo count($categories); ?></h3>
                    <p class="text-muted mb-0">Categories</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <i class="bi bi-gift text-danger"></i>
                    <h3><?php 
                        $totalItems = 0;
                        foreach ($wishlists as $w) $totalItems += $w['item_count'];
                        echo $totalItems;
                    ?></h3>
                    <p class="text-muted mb-0">Total Items</p>
                </div>
            </div>
        </div>

        <!-- My Wishlists -->
        <div class="row">
            <div class="col-lg-8">
                <h2 class="mb-4">
                    <i class="bi bi-list-stars me-2"></i>My Wishlists
                </h2>
                
                <?php if (empty($wishlists)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        You haven't created any wishlists yet. Click the + button to create your first wishlist!
                    </div>
                <?php else: ?>
                    <?php foreach ($wishlists as $wishlist): ?>
                        <div class="wishlist-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h4 class="mb-2">
                                        <a href="wishlist.php?id=<?php echo $wishlist['id']; ?>" class="text-decoration-none">
                                            <?php echo sanitize($wishlist['title']); ?>
                                        </a>
                                    </h4>
                                    <?php if ($wishlist['description']): ?>
                                        <p class="text-muted mb-2"><?php echo sanitize($wishlist['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="d-flex align-items-center text-muted small">
                                        <i class="bi bi-box me-1"></i>
                                        <span class="me-3"><?php echo $wishlist['item_count']; ?> items</span>
                                        <i class="bi bi-clock me-1"></i>
                                        <span>Updated <?php echo date('M d, Y', strtotime($wishlist['updated_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="btn-group">
                                    <a href="wishlist.php?id=<?php echo $wishlist['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit-wishlist.php?id=<?php echo $wishlist['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-info" onclick="shareWishlist(<?php echo $wishlist['id']; ?>)">
                                        <i class="bi bi-share"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Shared Wishlists -->
            <div class="col-lg-4">
                <h2 class="mb-4">
                    <i class="bi bi-people-fill me-2"></i>Shared With Me
                </h2>
                
                <?php if (empty($sharedWishlists)): ?>
                    <div class="alert alert-light">
                        <i class="bi bi-info-circle me-2"></i>
                        No wishlists have been shared with you yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($sharedWishlists as $shared): ?>
                        <div class="wishlist-card">
                            <h5 class="mb-2">
                                <a href="wishlist.php?id=<?php echo $shared['id']; ?>" class="text-decoration-none">
                                    <?php echo sanitize($shared['title']); ?>
                                </a>
                            </h5>
                            <p class="text-muted small mb-2">
                                <i class="bi bi-person me-1"></i>
                                By <?php echo sanitize($shared['owner_name']); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-secondary">
                                    <?php echo $shared['item_count']; ?> items
                                </span>
                                <?php if ($shared['can_edit']): ?>
                                    <span class="badge bg-success">Can Edit</span>
                                <?php else: ?>
                                    <span class="badge bg-info">View Only</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <div class="quick-actions">
        <button class="btn btn-primary btn-fab" data-bs-toggle="modal" data-bs-target="#createWishlistModal">
            <i class="bi bi-plus"></i>
        </button>
    </div>

    <!-- Create Wishlist Modal -->
    <div class="modal fade" id="createWishlistModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Wishlist</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="create-wishlist.php">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Wishlist Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_public" name="is_public">
                            <label class="form-check-label" for="is_public">
                                Make this wishlist public
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Create Wishlist
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function shareWishlist(wishlistId) {
            // This would open a modal to share the wishlist
            alert('Share functionality would be implemented here for wishlist ID: ' + wishlistId);
        }
    </script>
</body>
</html>