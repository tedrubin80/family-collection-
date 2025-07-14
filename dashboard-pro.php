<?php
// dashboard-pro.php - Enhanced dashboard with all features
session_start();
require_once 'config/config-pro.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = getDBConnection();
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get partner ID (for couple features)
$stmt = $db->prepare("
    SELECT u2.id as partner_id, u2.username as partner_name
    FROM family_group_members fgm1
    JOIN family_group_members fgm2 ON fgm1.group_id = fgm2.group_id
    JOIN users u2 ON fgm2.user_id = u2.id
    WHERE fgm1.user_id = ? AND fgm2.user_id != ?
    LIMIT 1
");
$stmt->execute([$userId, $userId]);
$partner = $stmt->fetch();

// Fetch dashboard statistics
$stats = [];

// Total wishlists
$stmt = $db->prepare("SELECT COUNT(*) as count FROM wishlists WHERE user_id = ?");
$stmt->execute([$userId]);
$stats['wishlists'] = $stmt->fetch()['count'];

// Items needing decision
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT wi.id) as count
    FROM wishlist_items wi
    JOIN wishlists w ON wi.wishlist_id = w.id
    LEFT JOIN couple_decisions cd ON wi.id = cd.item_id
    WHERE w.user_id = ? AND (cd.decision_status IS NULL OR cd.decision_status = 'discussing')
");
$stmt->execute([$userId]);
$stats['pending_decisions'] = $stmt->fetch()['count'];

// Active price alerts
$stmt = $db->prepare("SELECT COUNT(*) as count FROM price_alerts WHERE user_id = ? AND is_active = 1");
$stmt->execute([$userId]);
$stats['price_alerts'] = $stmt->fetch()['count'];

// Monthly budget usage
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(b.amount), 0) as total_budget,
        COALESCE(SUM(ph.purchase_price), 0) as spent
    FROM budgets b
    LEFT JOIN purchase_history ph ON 
        MONTH(ph.created_at) = MONTH(CURRENT_DATE()) AND 
        YEAR(ph.created_at) = YEAR(CURRENT_DATE())
    WHERE b.created_by = ? AND b.period = 'monthly'
");
$stmt->execute([$userId]);
$budget = $stmt->fetch();
$stats['budget_percentage'] = $budget['total_budget'] > 0 ? 
    round(($budget['spent'] / $budget['total_budget']) * 100) : 0;

// Recent activity
$stmt = $db->prepare("
    SELECT al.*, u.username
    FROM activity_log al
    JOIN users u ON al.user_id = u.id
    JOIN family_group_members fgm1 ON u.id = fgm1.user_id
    JOIN family_group_members fgm2 ON fgm1.group_id = fgm2.group_id
    WHERE fgm2.user_id = ?
    ORDER BY al.created_at DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$recentActivity = $stmt->fetchAll();

// Price drops
$stmt = $db->prepare("
    SELECT wi.*, ph1.price as current_price, ph2.price as previous_price,
           ((ph2.price - ph1.price) / ph2.price * 100) as discount_percentage
    FROM wishlist_items wi
    JOIN wishlists w ON wi.wishlist_id = w.id
    JOIN price_history ph1 ON wi.id = ph1.item_id
    JOIN price_history ph2 ON wi.id = ph2.item_id
    WHERE w.user_id = ? 
    AND ph1.id = (SELECT MAX(id) FROM price_history WHERE item_id = wi.id)
    AND ph2.id = (SELECT MAX(id) FROM price_history WHERE item_id = wi.id AND id < ph1.id)
    AND ph1.price < ph2.price
    AND wi.is_purchased = 0
    ORDER BY discount_percentage DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$priceDrops = $stmt->fetchAll();

// Upcoming reminders
$stmt = $db->prepare("
    SELECT r.*, wi.title as item_title
    FROM reminders r
    LEFT JOIN wishlist_items wi ON r.item_id = wi.id
    WHERE r.user_id = ? 
    AND r.remind_at > NOW() 
    AND r.remind_at < DATE_ADD(NOW(), INTERVAL 7 DAY)
    AND r.is_completed = 0
    ORDER BY r.remind_at
    LIMIT 5
");
$stmt->execute([$userId]);
$upcomingReminders = $stmt->fetchAll();

// Items both want (couple feature)
if ($partner) {
    $stmt = $db->prepare("
        SELECT wi.*, 
               r1.rating as my_rating, r1.vote_type as my_vote,
               r2.rating as partner_rating, r2.vote_type as partner_vote
        FROM wishlist_items wi
        JOIN wishlists w ON wi.wishlist_id = w.id
        JOIN item_ratings r1 ON wi.id = r1.item_id AND r1.user_id = ?
        JOIN item_ratings r2 ON wi.id = r2.item_id AND r2.user_id = ?
        WHERE w.user_id IN (?, ?)
        AND r1.vote_type = 'want' AND r2.vote_type = 'want'
        AND wi.is_purchased = 0
        ORDER BY (r1.rating + r2.rating) DESC
        LIMIT 5
    ");
    $stmt->execute([$userId, $partner['partner_id'], $userId, $partner['partner_id']]);
    $bothWantItems = $stmt->fetchAll();
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
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo PRIMARY_COLOR; ?>;
            --secondary-color: <?php echo SECONDARY_COLOR; ?>;
            --success-color: <?php echo SUCCESS_COLOR; ?>;
            --warning-color: <?php echo WARNING_COLOR; ?>;
            --danger-color: <?php echo DANGER_COLOR; ?>;
            --info-color: <?php echo INFO_COLOR; ?>;
            --background-color: <?php echo BACKGROUND_COLOR; ?>;
        }
        
        body {
            background-color: #f0f2f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,.08);
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--background-color) 0%, #bbdefb 100%);
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        
        .stat-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .activity-feed {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            align-items: start;
            gap: 1rem;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .price-drop-card {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe0e0 100%);
            border: 1px solid #ffcccc;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .price-drop-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(220,53,69,0.15);
        }
        
        .discount-badge {
            background: var(--danger-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .reminder-card {
            background: linear-gradient(135deg, #fff9e6 0%, #ffecb3 100%);
            border: 1px solid #ffd54f;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .both-want-item {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 1px solid #90caf9;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .rating-hearts {
            color: #e91e63;
        }
        
        .progress-ring {
            transform: rotate(-90deg);
        }
        
        .progress-ring-circle {
            stroke-dasharray: 251.2;
            stroke-dashoffset: 251.2;
            transition: stroke-dashoffset 1s ease;
        }
        
        .quick-action-btn {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }
        
        .quick-action-btn:hover {
            border-color: var(--primary-color);
            background: var(--background-color);
            transform: translateY(-2px);
            text-decoration: none;
            color: inherit;
        }
        
        .quick-action-btn i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Enhanced Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-gift-fill me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="wishlists.php">
                            <i class="bi bi-list-stars me-1"></i>Wishlists
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="compare.php">
                            <i class="bi bi-columns-gap me-1"></i>Compare
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="budget.php">
                            <i class="bi bi-piggy-bank me-1"></i>Budget
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php">
                            <i class="bi bi-graph-up me-1"></i>Analytics
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <span class="notification-badge">3</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-tag me-2 text-success"></i>Price dropped on "Kitchen Mixer"</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-chat me-2 text-primary"></i>Ted commented on "New Sofa"</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-calendar me-2 text-warning"></i>Reminder: Check seasonal items</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="notifications.php">View all</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?php echo sanitize($username); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-6 fw-bold mb-2">Welcome back, <?php echo sanitize($username); ?>!</h1>
                    <p class="lead mb-0">
                        <?php if ($partner): ?>
                            You and <?php echo sanitize($partner['partner_name']); ?> have 
                            <strong><?php echo $stats['pending_decisions']; ?></strong> items to decide on together
                        <?php else: ?>
                            Here's your wishlist overview for today
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#quickAddModal">
                        <i class="bi bi-plus-circle me-2"></i>Quick Add Item
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <!-- Statistics Row -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-list-ul"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['wishlists']; ?></h3>
                    <p class="text-muted mb-0">Active Wishlists</p>
                    <small class="text-success"><i class="bi bi-arrow-up"></i> 2 new this month</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-question-circle"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['pending_decisions']; ?></h3>
                    <p class="text-muted mb-0">Pending Decisions</p>
                    <small class="text-muted">With <?php echo sanitize($partner['partner_name'] ?? 'partner'); ?></small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-bell"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['price_alerts']; ?></h3>
                    <p class="text-muted mb-0">Active Alerts</p>
                    <small class="text-info"><i class="bi bi-tag"></i> 3 triggered today</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div class="d-flex align-items-center mb-1">
                        <h3 class="mb-0"><?php echo $stats['budget_percentage']; ?>%</h3>
                        <small class="ms-2 text-muted">of budget</small>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $stats['budget_percentage']; ?>%"></div>
                    </div>
                    <small class="text-muted">Monthly budget used</small>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content Column -->
            <div class="col-lg-8">
                <!-- Price Drops Section -->
                <?php if (!empty($priceDrops)): ?>
                <div class="mb-4">
                    <h4 class="mb-3">
                        <i class="bi bi-tag-fill text-danger me-2"></i>Price Drops on Your Items
                    </h4>
                    <?php foreach ($priceDrops as $item): ?>
                    <div class="price-drop-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?php echo sanitize($item['title']); ?></h6>
                                <p class="mb-0">
                                    <span class="text-decoration-line-through text-muted">
                                        $<?php echo number_format($item['previous_price'], 2); ?>
                                    </span>
                                    <span class="fs-5 fw-bold text-success ms-2">
                                        $<?php echo number_format($item['current_price'], 2); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="discount-badge">
                                    <?php echo abs(round($item['discount_percentage'])); ?>% OFF
                                </span>
                                <div class="mt-2">
                                    <a href="item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger">
                                        View Deal
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Items Both Want (Couple Feature) -->
                <?php if ($partner && !empty($bothWantItems)): ?>
                <div class="mb-4">
                    <h4 class="mb-3">
                        <i class="bi bi-heart-fill text-danger me-2"></i>Items You Both Want
                    </h4>
                    <?php foreach ($bothWantItems as $item): ?>
                    <div class="both-want-item">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h6 class="mb-1"><?php echo sanitize($item['title']); ?></h6>
                                <p class="text-muted mb-0 small"><?php echo sanitize($item['description'] ?? ''); ?></p>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="text-center">
                                        <small class="text-muted d-block">You</small>
                                        <div class="rating-hearts">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-heart<?php echo $i <= $item['my_rating'] ? '-fill' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <small class="text-muted d-block"><?php echo sanitize($partner['partner_name']); ?></small>
                                        <div class="rating-hearts">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-heart<?php echo $i <= $item['partner_rating'] ? '-fill' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 text-end">
                                <?php if ($item['price']): ?>
                                    <div class="fs-5 fw-bold text-primary mb-1">
                                        $<?php echo number_format($item['price'], 2); ?>
                                    </div>
                                <?php endif; ?>
                                <a href="compare.php?items=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">
                                    Compare Options
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="mb-4">
                    <h4 class="mb-3">
                        <i class="bi bi-lightning-fill text-warning me-2"></i>Quick Actions
                    </h4>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <a href="wishlists.php?create=1" class="quick-action-btn">
                                <i class="bi bi-plus-square text-primary"></i>
                                <span>New Wishlist</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="compare.php" class="quick-action-btn">
                                <i class="bi bi-columns-gap text-info"></i>
                                <span>Compare Items</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="budget.php" class="quick-action-btn">
                                <i class="bi bi-calculator text-success"></i>
                                <span>Set Budget</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="scan.php" class="quick-action-btn">
                                <i class="bi bi-upc-scan text-warning"></i>
                                <span>Scan Barcode</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Spending Analytics Chart -->
                <div class="mb-4">
                    <div class="stat-card">
                        <h4 class="mb-3">
                            <i class="bi bi-graph-up text-success me-2"></i>Monthly Spending Trend
                        </h4>
                        <canvas id="spendingChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Sidebar Column -->
            <div class="col-lg-4">
                <!-- Upcoming Reminders -->
                <div class="mb-4">
                    <h5 class="mb-3">
                        <i class="bi bi-calendar-event text-warning me-2"></i>Upcoming Reminders
                    </h5>
                    <?php if (empty($upcomingReminders)): ?>
                        <p class="text-muted">No upcoming reminders</p>
                    <?php else: ?>
                        <?php foreach ($upcomingReminders as $reminder): ?>
                        <div class="reminder-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo sanitize($reminder['title']); ?></strong>
                                    <?php if ($reminder['item_title']): ?>
                                        <br><small class="text-muted"><?php echo sanitize($reminder['item_title']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('M j', strtotime($reminder['remind_at'])); ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity Feed -->
                <div class="mb-4">
                    <h5 class="mb-3">
                        <i class="bi bi-activity text-primary me-2"></i>Recent Activity
                    </h5>
                    <div class="activity-feed">
                        <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon bg-primary bg-opacity-10 text-primary">
                                <?php
                                $icon = match($activity['action_type']) {
                                    'item_added' => 'bi-plus-circle',
                                    'item_purchased' => 'bi-check-circle',
                                    'price_alert' => 'bi-tag',
                                    'comment_added' => 'bi-chat',
                                    'list_shared' => 'bi-share',
                                    default => 'bi-dot'
                                };
                                ?>
                                <i class="bi <?php echo $icon; ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <p class="mb-0">
                                    <strong><?php echo sanitize($activity['username']); ?></strong>
                                    <?php echo sanitize($activity['description'] ?? $activity['action_type']); ?>
                                </p>
                                <small class="text-muted">
                                    <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Savings Goals -->
                <div class="mb-4">
                    <h5 class="mb-3">
                        <i class="bi bi-piggy-bank-fill text-success me-2"></i>Savings Goals
                    </h5>
                    <div class="stat-card">
                        <div class="text-center">
                            <svg width="120" height="120" class="mb-3">
                                <circle cx="60" cy="60" r="40" fill="none" stroke="#e0e0e0" stroke-width="8"/>
                                <circle cx="60" cy="60" r="40" fill="none" stroke="#28a745" stroke-width="8"
                                        class="progress-ring-circle" 
                                        style="stroke-dashoffset: <?php echo 251.2 - (251.2 * 0.65); ?>"/>
                            </svg>
                            <h5>65% Complete</h5>
                            <p class="text-muted mb-0">$650 of $1,000 saved</p>
                            <small class="text-success">For: New Camera</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Add Modal -->
    <div class="modal fade" id="quickAddModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>Quick Add Item
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="quickAddForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Add by URL or Search</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="url_or_search" 
                                       placeholder="Paste URL or search for item...">
                                <button class="btn btn-outline-secondary" type="button" id="scanBarcodeBtn">
                                    <i class="bi bi-upc-scan"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Item Name</label>
                                    <input type="text" class="form-control" name="title" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="form-select" name="priority">
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Add to Wishlist</label>
                            <select class="form-select" name="wishlist_id" required>
                                <option value="">Choose wishlist...</option>
                                <option value="new">+ Create New Wishlist</option>
                                <optgroup label="My Wishlists">
                                    <option value="1">Birthday Wishlist</option>
                                    <option value="2">Home Improvement</option>
                                    <option value="3">Tech Gadgets</option>
                                </optgroup>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tags (comma separated)</label>
                            <input type="text" class="form-control" name="tags" 
                                   placeholder="gift-idea, on-sale, researching">
                            <div class="form-text">Popular: gift-idea, both-want, urgent, eco-friendly</div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="addToComparison" name="add_to_comparison">
                            <label class="form-check-label" for="addToComparison">
                                Add to active comparison
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="quickAddForm" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Add Item
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
    <script>
        // Spending Chart
        const ctx = document.getElementById('spendingChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Actual Spending',
                    data: [450, 380, 520, 410, 490, 385],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Budget',
                    data: [500, 500, 500, 500, 500, 500],
                    borderColor: '#dc3545',
                    borderDash: [5, 5],
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return ' + value;
                            }
                        }
                    }
                }
            }
        });

        // Quick Add Form Handler
        document.getElementById('quickAddForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('api/quick-add-item.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Close modal and show success
                    bootstrap.Modal.getInstance(document.getElementById('quickAddModal')).hide();
                    
                    // Show success notification
                    showNotification('Item added successfully!', 'success');
                    
                    // Refresh relevant sections
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message || 'Error adding item', 'danger');
                }
            } catch (error) {
                showNotification('Network error. Please try again.', 'danger');
            }
        });

        // Notification helper
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            notification.style.zIndex = '9999';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => notification.remove(), 5000);
        }

        // Initialize tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    </script>
</body>
</html>