<?php
// compare.php - Smart comparison tool for decision making
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = getDBConnection();
$userId = $_SESSION['user_id'];

// Get comparison ID if editing existing
$comparisonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$comparison = null;
$items = [];
$criteria = [];

if ($comparisonId) {
    // Load existing comparison
    $stmt = $db->prepare("SELECT * FROM comparisons WHERE id = ? AND created_by = ?");
    $stmt->execute([$comparisonId, $userId]);
    $comparison = $stmt->fetch();
    
    if ($comparison) {
        // Load items
        $stmt = $db->prepare("
            SELECT wi.*, ci.position 
            FROM comparison_items ci
            JOIN wishlist_items wi ON ci.item_id = wi.id
            WHERE ci.comparison_id = ?
            ORDER BY ci.position
        ");
        $stmt->execute([$comparisonId]);
        $items = $stmt->fetchAll();
        
        // Load criteria
        $stmt = $db->prepare("SELECT * FROM comparison_criteria WHERE comparison_id = ? ORDER BY id");
        $stmt->execute([$comparisonId]);
        $criteria = $stmt->fetchAll();
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
        }
        
        .comparison-header {
            background: linear-gradient(135deg, var(--background-color) 0%, #bbdefb 100%);
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }
        
        .comparison-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .item-column {
            border-right: 2px solid #e9ecef;
            position: relative;
            min-width: 250px;
        }
        
        .item-column:last-child {
            border-right: none;
        }
        
        .item-header {
            background: #f8f9fa;
            padding: 1.5rem;
            text-align: center;
            border-bottom: 2px solid #e9ecef;
        }
        
        .winner-badge {
            position: absolute;
            top: -10px;
            right: 10px;
            background: gold;
            color: #333;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .criteria-row {
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }
        
        .criteria-row:hover {
            background-color: #f8f9fa;
        }
        
        .rating-stars {
            color: #ffc107;
            cursor: pointer;
        }
        
        .rating-stars .bi-star-fill {
            color: #ffc107;
        }
        
        .rating-stars .bi-star {
            color: #dee2e6;
        }
        
        .score-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .score-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
            transition: width 0.5s ease;
        }
        
        .decision-matrix {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .pro-con-list {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .add-item-btn {
            width: 100%;
            padding: 2rem;
            border: 2px dashed #dee2e6;
            background: #fafbfc;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .add-item-btn:hover {
            border-color: var(--primary-color);
            background: #e3f2fd;
        }
        
        .criteria-weight {
            display: inline-block;
            background: #e9ecef;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        
        .final-scores {
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            padding: 1rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
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
                        <a class="nav-link active" href="compare.php">
                            <i class="bi bi-columns-gap me-1"></i>Compare
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

    <!-- Comparison Header -->
    <div class="comparison-header">
        <div class="container">
            <h1 class="display-6 fw-bold">
                <i class="bi bi-columns-gap me-2"></i>Smart Comparison Tool
            </h1