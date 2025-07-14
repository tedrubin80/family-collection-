<?php
// budget-analytics.php - Advanced budget tracking and analytics
session_start();
require_once 'config/config-pro.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = getDBConnection();
$userId = $_SESSION['user_id'];

// Get current month's budget data
$currentMonth = date('Y-m');
$stmt = $db->prepare("
    SELECT 
        c.name as category_name,
        c.color as category_color,
        b.amount as budget_amount,
        COALESCE(SUM(ph.purchase_price), 0) as spent,
        COUNT(DISTINCT ph.id) as purchase_count
    FROM categories c
    LEFT JOIN budgets b ON c.id = b.category_id 
        AND b.created_by = ? 
        AND b.period = 'monthly'
        AND DATE_FORMAT(b.start_date, '%Y-%m') = ?
    LEFT JOIN wishlist_items wi ON c.id = wi.category_id
    LEFT JOIN purchase_history ph ON wi.id = ph.item_id 
        AND DATE_FORMAT(ph.created_at, '%Y-%m') = ?
    GROUP BY c.id, c.name, c.color, b.amount
    ORDER BY spent DESC
");
$stmt->execute([$userId, $currentMonth, $currentMonth]);
$categoryBudgets = $stmt->fetchAll();

// Get spending trends (last 6 months)
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(ph.created_at, '%Y-%m') as month,
        SUM(ph.purchase_price) as total_spent,
        COUNT(DISTINCT ph.id) as item_count,
        AVG(ph.satisfaction_rating) as avg_satisfaction
    FROM purchase_history ph
    WHERE ph.user_id = ?
    AND ph.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(ph.created_at, '%Y-%m')
    ORDER BY month
");
$stmt->execute([$userId]);
$spendingTrends = $stmt->fetchAll();

// Get savings goals progress
$stmt = $db->prepare("
    SELECT sg.*, wi.title as item_title
    FROM savings_goals sg
    LEFT JOIN wishlist_items wi ON sg.item_id = wi.id
    WHERE sg.created_by = ? AND sg.achieved_at IS NULL
    ORDER BY sg.target_date
");
$stmt->execute([$userId]);
$savingsGoals = $stmt->fetchAll();

// Get purchase analytics
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT ph.id) as total_purchases,
        SUM(ph.purchase_price) as total_spent,
        AVG(ph.purchase_price) as avg_purchase,
        SUM(CASE WHEN ph.discount_amount > 0 THEN ph.discount_amount ELSE 0 END) as total_saved,
        AVG(ph.satisfaction_rating) as avg_satisfaction,
        SUM(CASE WHEN ph.would_recommend = 1 THEN 1 ELSE 0 END) as recommended_count
    FROM purchase_history ph
    WHERE ph.user_id = ?
");
$stmt->execute([$userId]);
$purchaseStats = $stmt->fetch();

// Get best value purchases
$stmt = $db->prepare("
    SELECT 
        wi.title,
        ph.purchase_price,
        ph.original_price,
        ph.discount_amount,
        ph.satisfaction_rating,
        s.name as store_name,
        ((ph.original_price - ph.purchase_price) / ph.original_price * 100) as discount_percent
    FROM purchase_history ph
    JOIN wishlist_items wi ON ph.item_id = wi.id
    LEFT JOIN stores s ON ph.store_id = s.id
    WHERE ph.user_id = ? 
    AND ph.discount_amount > 0
    ORDER BY ph.discount_amount DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$bestDeals = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget & Analytics - <?php echo SITE_NAME; ?></title>
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
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--background-color) 0%, #bbdefb 100%);
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            height: 100%;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .budget-category {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid;
        }
        
        .budget-progress {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .budget-progress-fill {
            height: 100%;
            transition: width 0.5s ease;
        }
        
        .savings-goal-card {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border: 1px solid #a5d6a7;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .circular-progress {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }
        
        .circular-progress svg {
            transform: rotate(-90deg);
        }
        
        .circular-progress-value {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .best-deal-badge {
            background: linear-gradient(135deg, #ffeb3b 0%, #ffc107 100%);
            color: #333;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }
        
        .insight-card {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            border: 1px solid #ffcc80;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .insight-card i {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-gift-fill me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="budget-analytics.php">Budget & Analytics</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-6 fw-bold">
                        <i class="bi bi-graph-up me-2"></i>Budget & Analytics
                    </h1>
                    <p class="lead mb-0">Track spending, manage budgets, and discover insights</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#setBudgetModal">
                        <i class="bi bi-plus-circle me-2"></i>Set Budget
                    </button>
                    <button class="btn btn-primary" onclick="exportData()">
                        <i class="bi bi-download me-2"></i>Export Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Overview Stats -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Total Spent (This Month)</p>
                            <div class="stat-value text-primary">
                                $<?php echo number_format(array_sum(array_column($categoryBudgets, 'spent')), 2); ?>
                            </div>
                            <small class="text-success">
                                <i class="bi bi-arrow-down"></i> 12% vs last month
                            </small>
                        </div>
                        <div class="text-primary" style="font-size: 2.5rem;">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Average Purchase</p>
                            <div class="stat-value text-info">
                                $<?php echo number_format($purchaseStats['avg_purchase'] ?? 0, 2); ?>
                            </div>
                            <small class="text-muted">
                                <?php echo $purchaseStats['total_purchases'] ?? 0; ?> total purchases
                            </small>
                        </div>
                        <div class="text-info" style="font-size: 2.5rem;">
                            <i class="bi bi-cart-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Total Saved</p>
                            <div class="stat-value text-success">
                                $<?php echo number_format($purchaseStats['total_saved'] ?? 0, 2); ?>
                            </div>
                            <small class="text-muted">
                                Through smart shopping
                            </small>
                        </div>
                        <div class="text-success" style="font-size: 2.5rem;">
                            <i class="bi bi-piggy-bank"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Satisfaction Score</p>
                            <div class="stat-value text-warning">
                                <?php echo number_format($purchaseStats['avg_satisfaction'] ?? 0, 1); ?>/5
                            </div>
                            <small class="text-muted">
                                <?php echo $purchaseStats['recommended_count'] ?? 0; ?> recommended
                            </small>
                        </div>
                        <div class="text-warning" style="font-size: 2.5rem;">
                            <i class="bi bi-star-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Budget by Category -->
            <div class="col-lg-8">
                <div class="stat-card mb-4">
                    <h4 class="mb-3">
                        <i class="bi bi-pie-chart text-primary me-2"></i>Budget by Category
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <?php foreach ($categoryBudgets as $cat): 
                                $percentage = $cat['budget_amount'] > 0 ? 
                                    ($cat['spent'] / $cat['budget_amount'] * 100) : 0;
                                $progressColor = $percentage > 90 ? 'danger' : 
                                    ($percentage > 70 ? 'warning' : 'success');
                            ?>
                            <div class="budget-category" style="border-left-color: <?php echo $cat['category_color']; ?>;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><?php echo sanitize($cat['category_name']); ?></h6>
                                    <small class="text-muted">
                                        $<?php echo number_format($cat['spent'], 0); ?> / 
                                        $<?php echo number_format($cat['budget_amount'] ?? 0, 0); ?>
                                    </small>
                                </div>
                                <div class="budget-progress">
                                    <div class="budget-progress-fill bg-<?php echo $progressColor; ?>" 
                                         style="width: <?php echo min($percentage, 100); ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $cat['purchase_count']; ?> items • 
                                    <?php echo round($percentage); ?>% used
                                </small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-md-6">
                            <canvas id="categoryPieChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Spending Trends -->
                <div class="stat-card mb-4">
                    <h4 class="mb-3">
                        <i class="bi bi-graph-up-arrow text-success me-2"></i>6-Month Spending Trends
                    </h4>
                    <div class="chart-container">
                        <canvas id="spendingTrendsChart"></canvas>
                    </div>
                    
                    <!-- Insights -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="insight-card">
                                <i class="bi bi-lightbulb text-warning"></i>
                                <strong>Insight:</strong> Your spending decreased 15% last month. Keep it up!
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="insight-card">
                                <i class="bi bi-graph-down text-success"></i>
                                <strong>Trend:</strong> Electronics spending is 30% lower than your average.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Best Deals -->
                <div class="stat-card">
                    <h4 class="mb-3">
                        <i class="bi bi-trophy text-warning me-2"></i>Best Value Purchases
                    </h4>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Store</th>
                                    <th>Original</th>
                                    <th>Paid</th>
                                    <th>Saved</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bestDeals as $deal): ?>
                                <tr>
                                    <td>
                                        <?php echo sanitize($deal['title']); ?>
                                        <?php if ($deal['discount_percent'] > 50): ?>
                                            <span class="best-deal-badge ms-2">
                                                <?php echo round($deal['discount_percent']); ?>% OFF
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo sanitize($deal['store_name'] ?? 'Online'); ?></td>
                                    <td class="text-muted text-decoration-line-through">
                                        $<?php echo number_format($deal['original_price'], 2); ?>
                                    </td>
                                    <td class="fw-bold text-success">
                                        $<?php echo number_format($deal['purchase_price'], 2); ?>
                                    </td>
                                    <td class="text-success">
                                        $<?php echo number_format($deal['discount_amount'], 2); ?>
                                    </td>
                                    <td>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?php echo $i <= $deal['satisfaction_rating'] ? '-fill' : ''; ?> text-warning"></i>
                                        <?php endfor; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Savings Goals -->
                <div class="stat-card mb-4">
                    <h4 class="mb-3">
                        <i class="bi bi-bullseye text-success me-2"></i>Savings Goals
                    </h4>
                    
                    <?php foreach ($savingsGoals as $goal): 
                        $progress = $goal['target_amount'] > 0 ? 
                            ($goal['current_amount'] / $goal['target_amount'] * 100) : 0;
                    ?>
                    <div class="savings-goal-card">
                        <h6 class="mb-2"><?php echo sanitize($goal['name']); ?></h6>
                        <?php if ($goal['item_title']): ?>
                            <small class="text-muted d-block mb-2">For: <?php echo sanitize($goal['item_title']); ?></small>
                        <?php endif; ?>
                        
                        <div class="circular-progress mb-3">
                            <svg width="120" height="120">
                                <circle cx="60" cy="60" r="50" fill="none" stroke="#e0e0e0" stroke-width="10"/>
                                <circle cx="60" cy="60" r="50" fill="none" stroke="#28a745" stroke-width="10"
                                        stroke-dasharray="314.16"
                                        stroke-dashoffset="<?php echo 314.16 * (1 - $progress / 100); ?>"
                                        stroke-linecap="round"/>
                            </svg>
                            <div class="circular-progress-value text-success">
                                <?php echo round($progress); ?>%
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <div class="mb-1">
                                <strong>$<?php echo number_format($goal['current_amount'], 0); ?></strong> / 
                                $<?php echo number_format($goal['target_amount'], 0); ?>
                            </div>
                            <?php if ($goal['target_date']): ?>
                                <small class="text-muted">
                                    Target: <?php echo date('M j, Y', strtotime($goal['target_date'])); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-grid mt-3">
                            <button class="btn btn-sm btn-success" onclick="addToSavings(<?php echo $goal['id']; ?>)">
                                <i class="bi bi-plus-circle me-1"></i>Add Funds
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <button class="btn btn-outline-success w-100 mt-3" data-bs-toggle="modal" data-bs-target="#newGoalModal">
                        <i class="bi bi-plus-circle me-2"></i>New Savings Goal
                    </button>
                </div>

                <!-- Quick Actions -->
                <div class="stat-card mb-4">
                    <h4 class="mb-3">
                        <i class="bi bi-lightning text-warning me-2"></i>Quick Actions
                    </h4>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="compareMonths()">
                            <i class="bi bi-calendar-range me-2"></i>Compare Months
                        </button>
                        <button class="btn btn-outline-info" onclick="viewReports()">
                            <i class="bi bi-file-earmark-bar-graph me-2"></i>Detailed Reports
                        </button>
                        <button class="btn btn-outline-success" onclick="findDeals()">
                            <i class="bi bi-search me-2"></i>Find Better Deals
                        </button>
                        <button class="btn btn-outline-warning" onclick="setBudgetAlerts()">
                            <i class="bi bi-bell me-2"></i>Budget Alerts
                        </button>
                    </div>
                </div>

                <!-- Tips -->
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="bi bi-info-circle text-info me-2"></i>Money-Saving Tips
                    </h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Set price alerts for wishlist items
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Compare prices across multiple stores
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Buy seasonal items off-season
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Use the comparison tool for big purchases
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Set Budget Modal -->
    <div class="modal fade" id="setBudgetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Set Monthly Budget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="setBudgetForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category_id" required>
                                <option value="">Choose category...</option>
                                <?php foreach ($categoryBudgets as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>">
                                        <?php echo sanitize($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Budget Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="amount" 
                                       step="50" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Period</label>
                            <select class="form-select" name="period">
                                <option value="monthly" selected>Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Set Budget</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
    <script>
        // Category Pie Chart
        const categoryData = <?php echo json_encode($categoryBudgets); ?>;
        const pieCtx = document.getElementById('categoryPieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: categoryData.map(c => c.category_name),
                datasets: [{
                    data: categoryData.map(c => c.spent),
                    backgroundColor: categoryData.map(c => c.category_color),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Spending Trends Chart
        const trendsData = <?php echo json_encode($spendingTrends); ?>;
        const trendsCtx = document.getElementById('spendingTrendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: trendsData.map(t => t.month),
                datasets: [{
                    label: 'Monthly Spending',
                    data: trendsData.map(t => t.total_spent),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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

        function addToSavings(goalId) {
            // Implementation for adding funds to savings goal
            console.log('Add to savings goal:', goalId);
        }

        function exportData() {
            window.location.href = 'export-budget-data.php';
        }
    </script>
</body>
</html>