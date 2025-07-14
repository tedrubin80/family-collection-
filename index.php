<?php
// index.php - Main entry point
session_start();
require_once 'config/config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = "Invalid username or password";
        }
    } else {
        $error = "Invalid request";
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo PRIMARY_COLOR; ?>;
            --secondary-color: <?php echo SECONDARY_COLOR; ?>;
            --background-color: <?php echo BACKGROUND_COLOR; ?>;
        }
        
        body {
            background: linear-gradient(135deg, var(--background-color) 0%, #bbdefb 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .brand-header h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .brand-header p {
            color: var(--secondary-color);
            font-size: 1.1rem;
        }
        
        .feature-list {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .feature-item i {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .floating-shapes {
            position: fixed;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            animation: float 20s infinite ease-in-out;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-50px) rotate(180deg); }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape" style="width: 200px; height: 200px; background: #007bff; top: 10%; left: 10%;"></div>
        <div class="shape" style="width: 150px; height: 150px; background: #28a745; top: 60%; right: 10%; animation-delay: -5s;"></div>
        <div class="shape" style="width: 100px; height: 100px; background: #ffc107; bottom: 10%; left: 30%; animation-delay: -10s;"></div>
    </div>

    <div class="container">
        <?php if (!$isLoggedIn): ?>
        <div class="login-container">
            <div class="brand-header">
                <i class="bi bi-gift-fill" style="font-size: 3rem; color: var(--primary-color);"></i>
                <h1>Family Wishlist</h1>
                <p>Share your wishes with loved ones</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo sanitize($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="login" value="1">
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>
            
            <div class="feature-list">
                <h5 class="mb-3">Features:</h5>
                <div class="feature-item">
                    <i class="bi bi-tags"></i>
                    <span>Unlimited categories & lists</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-people"></i>
                    <span>Share wishlists with family</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-star"></i>
                    <span>Set priorities for items</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-envelope"></i>
                    <span>Email notifications</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-shield-check"></i>
                    <span>Secure & private</span>
                </div>
            </div>
        </div>
        <?php else: ?>
            <script>window.location.href = 'dashboard.php';</script>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>