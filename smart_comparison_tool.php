<?php
// compare-pro.php - Advanced comparison tool for couples
session_start();
require_once 'config-pro.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = getDBConnection();
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get partner info
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

// Handle comparison ID or create new
$comparisonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$items = isset($_GET['items']) ? explode(',', $_GET['items']) : [];

// Load or create comparison
if ($comparisonId) {
    $stmt = $db->prepare("SELECT * FROM comparisons WHERE id = ? AND created_by IN (?, ?)");
    $stmt->execute([$comparisonId, $userId, $partner['partner_id'] ?? 0]);
    $comparison = $stmt->fetch();
} else if (!empty($items)) {
    // Create new comparison with items
    $stmt = $db->prepare("INSERT INTO comparisons (name, created_by, comparison_type) VALUES (?, ?, 'weighted')");
    $stmt->execute(['New Comparison - ' . date('M j'), $userId]);
    $comparisonId = $db->lastInsertId();
    
    // Add items
    foreach ($items as $itemId) {
        $stmt = $db->prepare("INSERT INTO comparison_items (comparison_id, item_id, added_by) VALUES (?, ?, ?)");
        $stmt->execute([$comparisonId, $itemId, $userId]);
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Comparison Tool - <?php echo SITE_NAME; ?></title>
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
        
        .comparison-header {
            background: linear-gradient(135deg, var(--background-color) 0%, #bbdefb 100%);
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }
        
        .comparison-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        
        .item-column {
            border-right: 1px solid #e0e0e0;
            min-height: 600px;
            position: relative;
        }
        
        .item-column:last-child {
            border-right: none;
        }
        
        .item-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 2px solid #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .criteria-section {
            padding: 1.5rem;
        }
        
        .criteria-row {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }
        
        .criteria-row:hover {
            background-color: #f8f9fa;
        }
        
        .rating-input {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        
        .rating-star {
            font-size: 1.5rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .rating-star:hover,
        .rating-star.active {
            color: #ffc107;
        }
        
        .pro-con-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin: 0.5rem;
        }
        
        .pro-item {
            background: #d4edda;
            border-left: 3px solid #28a745;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .con-item {
            background: #f8d7da;
            border-left: 3px solid #dc3545;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .comparison-score {
            font-size: 3rem;
            font-weight: bold;
        }
        
        .score-bar {
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        .score-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color) 0%, var(--info-color) 100%);
            transition: width 0.5s ease;
        }
        
        .winner-badge {
            position: absolute;
            top: -10px;
            right: 10px;
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #333;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transform: rotate(-5deg);
            z-index: 20;
        }
        
        .add-item-card {
            border: 2px dashed #dee2e6;
            background: #fafbfc;
            padding: 3rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .add-item-card:hover {
            border-color: var(--primary-color);
            background: var(--background-color);
        }
        
        .criteria-weight {
            display: inline-block;
            background: #e9ecef;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            margin-left: 0.5rem;
        }
        
        .decision-section {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 2rem;
            border-radius: 12px;
            margin-top: 2rem;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        
        .discussion-bubble {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .comparison-chart {
            height: 300px;
            margin: 2rem 0;
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="compare.php">
                            <i class="bi bi-columns-gap me-1"></i>Compare
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Comparison Header -->
    <div class="comparison-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-6 fw-bold">
                        <i class="bi bi-columns-gap me-2"></i>Smart Comparison Tool
                    </h1>
                    <p class="lead mb-0">Compare items together with <?php echo sanitize($partner['partner_name'] ?? 'your partner'); ?> to make the best decision</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-light" onclick="saveComparison()">
                        <i class="bi bi-save me-2"></i>Save Progress
                    </button>
                    <button class="btn btn-primary" onclick="showDecision()">
                        <i class="bi bi-trophy me-2"></i>View Results
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Comparison Area -->
    <div class="container-fluid">
        <!-- Comparison Setup -->
        <?php if (!$comparisonId): ?>
        <div class="row mb-4">
            <div class="col-lg-8 mx-auto">
                <div class="comparison-container p-4">
                    <h3 class="mb-4">Start a New Comparison</h3>
                    <form id="startComparisonForm">
                        <div class="mb-3">
                            <label class="form-label">Comparison Name</label>
                            <input type="text" class="form-control" name="name" placeholder="e.g., New Couch Comparison" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">What are you comparing?</label>
                            <select class="form-select" name="comparison_type">
                                <option value="simple">Simple Comparison (Basic pros/cons)</option>
                                <option value="weighted">Weighted Criteria (Rate multiple factors)</option>
                                <option value="pro_con">Detailed Pro/Con Analysis</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Select items to compare (2-5 items)</label>
                            <div class="item-selector">
                                <!-- Dynamic item selector would go here -->
                                <p class="text-muted">Search and select items from your wishlists...</p>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-play-circle me-2"></i>Start Comparison
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Active Comparison -->
        <div class="comparison-container mb-4">
            <div class="row g-0">
                <!-- Item 1 -->
                <div class="col-md-4 item-column">
                    <div class="item-header">
                        <div class="winner-badge">
                            <i class="bi bi-trophy-fill"></i> Winner!
                        </div>
                        <h5 class="mb-2">Premium Leather Sofa</h5>
                        <div class="text-muted mb-2">West Elm</div>
                        <div class="fs-4 fw-bold text-primary mb-3">$1,299</div>
                        <div class="text-center">
                            <div class="comparison-score text-success">8.5</div>
                            <div class="score-bar">
                                <div class="score-fill" style="width: 85%"></div>
                            </div>
                            <small class="text-muted">Overall Score</small>
                        </div>
                    </div>
                    
                    <div class="criteria-section">
                        <!-- Comfort Rating -->
                        <div class="criteria-row">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Comfort</span>
                                <span class="criteria-weight">Weight: 3x</span>
                            </div>
                            <div class="rating-input" data-criteria="comfort" data-item="1">
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star rating-star"></i>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted">
                                    <span class="user-avatar">C</span>Carole: 4/5
                                </small>
                                <small class="text-muted">
                                    <span class="user-avatar">T</span>Ted: 4/5
                                </small>
                            </div>
                        </div>
                        
                        <!-- Style Rating -->
                        <div class="criteria-row">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Style</span>
                                <span class="criteria-weight">Weight: 2x</span>
                            </div>
                            <div class="rating-input" data-criteria="style" data-item="1">
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted">
                                    <span class="user-avatar">C</span>Carole: 5/5
                                </small>
                                <small class="text-muted">
                                    <span class="user-avatar">T</span>Ted: 5/5
                                </small>
                            </div>
                        </div>
                        
                        <!-- Durability Rating -->
                        <div class="criteria-row">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Durability</span>
                                <span class="criteria-weight">Weight: 2x</span>
                            </div>
                            <div class="rating-input" data-criteria="durability" data-item="1">
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star rating-star"></i>
                            </div>
                        </div>
                        
                        <!-- Pros/Cons -->
                        <div class="pro-con-section mt-3">
                            <h6 class="text-success mb-2"><i class="bi bi-plus-circle"></i> Pros</h6>
                            <div class="pro-item">Premium leather quality</div>
                            <div class="pro-item">5-year warranty included</div>
                            <div class="pro-item">Perfect size for our space</div>
                            
                            <h6 class="text-danger mb-2 mt-3"><i class="bi bi-dash-circle"></i> Cons</h6>
                            <div class="con-item">Higher price point</div>
                            <div class="con-item">6-week delivery time</div>
                        </div>
                    </div>
                </div>
                
                <!-- Item 2 -->
                <div class="col-md-4 item-column">
                    <div class="item-header">
                        <h5 class="mb-2">Modern Fabric Sectional</h5>
                        <div class="text-muted mb-2">Article</div>
                        <div class="fs-4 fw-bold text-primary mb-3">$999</div>
                        <div class="text-center">
                            <div class="comparison-score text-warning">7.8</div>
                            <div class="score-bar">
                                <div class="score-fill" style="width: 78%"></div>
                            </div>
                            <small class="text-muted">Overall Score</small>
                        </div>
                    </div>
                    
                    <div class="criteria-section">
                        <!-- Similar rating sections for Item 2 -->
                        <div class="criteria-row">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Comfort</span>
                                <span class="criteria-weight">Weight: 3x</span>
                            </div>
                            <div class="rating-input" data-criteria="comfort" data-item="2">
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                                <i class="bi bi-star-fill rating-star active"></i>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted">
                                    <span class="user-avatar">C</span>Carole: 5/5
                                </small>
                                <small class="text-muted">
                                    <span class="user-avatar">T</span>Ted: 4/5
                                </small>
                            </div>
                        </div>
                        
                        <!-- Pros/Cons -->
                        <div class="pro-con-section mt-3">
                            <h6 class="text-success mb-2"><i class="bi bi-plus-circle"></i> Pros</h6>
                            <div class="pro-item">Great reviews for comfort</div>
                            <div class="pro-item">Modern design we both love</div>
                            <div class="pro-item">Quick 2-week delivery</div>
                            
                            <h6 class="text-danger mb-2 mt-3"><i class="bi bi-dash-circle"></i> Cons</h6>
                            <div class="con-item">Fabric may show wear faster</div>
                            <div class="con-item">Limited color options</div>
                        </div>
                    </div>
                </div>
                
                <!-- Add New Item -->
                <div class="col-md-4 item-column">
                    <div class="add-item-card h-100" onclick="addItemToComparison()">
                        <i class="bi bi-plus-circle" style="font-size: 3rem; color: var(--primary-color);"></i>
                        <h5 class="mt-3">Add Another Item</h5>
                        <p class="text-muted">Compare up to 5 items</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Comparison Analysis -->
        <div class="row">
            <div class="col-lg-8">
                <div class="comparison-container p-4 mb-4">
                    <h4 class="mb-3">
                        <i class="bi bi-graph-up text-primary me-2"></i>Comparison Analysis
                    </h4>
                    <canvas id="comparisonChart" class="comparison-chart"></canvas>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Discussion Section -->
                <div class="comparison-container p-4 mb-4">
                    <h4 class="mb-3">
                        <i class="bi bi-chat-dots text-info me-2"></i>Discussion
                    </h4>
                    
                    <div class="discussion-bubble">
                        <div class="d-flex align-items-start">
                            <span class="user-avatar">C</span>
                            <div class="flex-grow-1">
                                <strong>Carole</strong>
                                <small class="text-muted ms-2">2 hours ago</small>
                                <p class="mb-0 mt-1">I really love the style of the West Elm sofa, and the leather will age beautifully. Worth the extra cost?</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="discussion-bubble">
                        <div class="d-flex align-items-start">
                            <span class="user-avatar">T</span>
                            <div class="flex-grow-1">
                                <strong>Ted</strong>
                                <small class="text-muted ms-2">1 hour ago</small>
                                <p class="mb-0 mt-1">The Article sectional is super comfortable and we'd save $300. But you're right about the leather quality...</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <textarea class="form-control" rows="2" placeholder="Add your thoughts..."></textarea>
                        <button class="btn btn-primary btn-sm mt-2">
                            <i class="bi bi-send me-1"></i>Send
                        </button>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="comparison-container p-4">
                    <h5 class="mb-3">Quick Stats</h5>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Average Rating</span>
                            <strong>8.2/10</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Price Range</span>
                            <strong>$999 - $1,299</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Agreement Level</span>
                            <strong class="text-success">85%</strong>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button class="btn btn-success" onclick="makeDecision()">
                            <i class="bi bi-check-circle me-2"></i>Make Final Decision
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>

    <!-- Decision Modal -->
    <div class="modal fade" id="decisionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-trophy-fill me-2"></i>Comparison Results
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="winner-badge d-inline-block mb-3" style="position: static; transform: none;">
                            <i class="bi bi-trophy-fill"></i> Winner: Premium Leather Sofa
                        </div>
                        <h4>Based on your ratings, the Premium Leather Sofa is the best choice!</h4>
                        <p class="text-muted">It scored highest in the criteria that matter most to both of you.</p>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h5>Overall Score</h5>
                            <div class="display-4 text-success">8.5/10</div>
                        </div>
                        <div class="col-md-4">
                            <h5>Agreement</h5>
                            <div class="display-4 text-primary">92%</div>
                        </div>
                        <div class="col-md-4">
                            <h5>Value Score</h5>
                            <div class="display-4 text-warning">Good</div>
                        </div>
                    </div>
                    
                    <div class="decision-section mt-4">
                        <h5 class="mb-3">Why this choice?</h5>
                        <ul>
                            <li>Highest combined ratings from both of you</li>
                            <li>Excellent scores in your top priorities (Comfort & Style)</li>
                            <li>Long-term value with 5-year warranty</li>
                            <li>Both partners rated it 4+ stars in all categories</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Comparing</button>
                    <button type="button" class="btn btn-success" onclick="confirmDecision()">
                        <i class="bi bi-check-circle me-2"></i>Confirm Decision
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
    <script>
        // Initialize comparison chart
        const ctx = document.getElementById('comparisonChart').getContext('2d');
        const comparisonChart = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: ['Comfort', 'Style', 'Durability', 'Value', 'Delivery Time', 'Warranty'],
                datasets: [{
                    label: 'Premium Leather Sofa',
                    data: [4, 5, 4, 3, 2, 5],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                }, {
                    label: 'Modern Fabric Sectional',
                    data: [4.5, 4, 3, 4, 5, 3],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 5
                    }
                }
            }
        });

        // Rating stars interaction
        document.querySelectorAll('.rating-star').forEach(star => {
            star.addEventListener('click', function() {
                const parent = this.parentElement;
                const stars = parent.querySelectorAll('.rating-star');
                const rating = Array.from(stars).indexOf(this) + 1;
                
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.remove('bi-star');
                        s.classList.add('bi-star-fill', 'active');
                    } else {
                        s.classList.remove('bi-star-fill', 'active');
                        s.classList.add('bi-star');
                    }
                });
                
                // Save rating via AJAX
                saveRating(parent.dataset.criteria, parent.dataset.item, rating);
            });
        });

        function saveRating(criteria, itemId, rating) {
            // AJAX call to save rating
            console.log(`Saving: ${criteria} for item ${itemId} = ${rating}`);
            
            // Update chart dynamically
            updateComparisonChart();
        }

        function updateComparisonChart() {
            // Recalculate and update chart data
            // This would pull fresh data from the server
        }

        function makeDecision() {
            const modal = new bootstrap.Modal(document.getElementById('decisionModal'));
            modal.show();
        }

        function confirmDecision() {
            // Save the decision and redirect
            window.location.href = 'dashboard.php?decision=confirmed';
        }

        function addItemToComparison() {
            // Open item selector modal
            console.log('Add item to comparison');
        }

        function saveComparison() {
            // Save current state
            alert('Comparison saved!');
        }

        function showDecision() {
            makeDecision();
        }
    </script>
</body>
</html>