<?php
// email-helper.php - Email handling functions
require_once 'config.php';

// For production, use PHPMailer or similar library
// This is a simplified version for demonstration

class EmailHelper {
    private $db;
    
    public function __construct() {
        $this->db = getDBConnection();
    }
    
    /**
     * Send email notification
     */
    public function sendEmail($to, $subject, $body, $isHtml = true) {
        // In production, use PHPMailer with SMTP
        // This is a basic implementation
        
        $headers = [
            'From' => EMAIL_FROM_NAME . ' <' . EMAIL_FROM . '>',
            'Reply-To' => EMAIL_FROM,
            'X-Mailer' => 'PHP/' . phpversion(),
            'MIME-Version' => '1.0'
        ];
        
        if ($isHtml) {
            $headers['Content-Type'] = 'text/html; charset=UTF-8';
        } else {
            $headers['Content-Type'] = 'text/plain; charset=UTF-8';
        }
        
        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= $key . ': ' . $value . "\r\n";
        }
        
        // For development/testing
        if (DEBUG_MODE) {
            error_log("Email to: $to");
            error_log("Subject: $subject");
            error_log("Body: $body");
            return true;
        }
        
        // Send email
        return mail($to, $subject, $body, $headerString);
    }
    
    /**
     * Queue email for sending
     */
    public function queueEmail($userId, $type, $subject, $body) {
        $stmt = $this->db->prepare("
            INSERT INTO email_notifications (user_id, type, subject, body) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$userId, $type, $subject, $body]);
    }
    
    /**
     * Process email queue
     */
    public function processEmailQueue($limit = 10) {
        // Get pending emails
        $stmt = $this->db->prepare("
            SELECT en.*, u.email 
            FROM email_notifications en
            JOIN users u ON en.user_id = u.id
            WHERE en.is_sent = 0
            ORDER BY en.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $emails = $stmt->fetchAll();
        
        $sent = 0;
        foreach ($emails as $email) {
            if ($this->sendEmail($email['email'], $email['subject'], $email['body'])) {
                // Mark as sent
                $updateStmt = $this->db->prepare("
                    UPDATE email_notifications 
                    SET is_sent = 1, sent_at = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$email['id']]);
                $sent++;
            }
        }
        
        return $sent;
    }
    
    /**
     * Send wishlist shared notification
     */
    public function sendWishlistSharedNotification($wishlistId, $sharedWithUserId, $sharedByUsername) {
        // Get recipient info
        $stmt = $this->db->prepare("
            SELECT u.email, u.username, w.title 
            FROM users u, wishlists w 
            WHERE u.id = ? AND w.id = ?
        ");
        $stmt->execute([$sharedWithUserId, $wishlistId]);
        $data = $stmt->fetch();
        
        if (!$data) return false;
        
        $subject = "Wishlist Shared: " . $data['title'];
        $body = $this->getEmailTemplate('wishlist_shared', [
            'recipient_name' => $data['username'],
            'shared_by' => $sharedByUsername,
            'wishlist_title' => $data['title'],
            'wishlist_url' => SITE_URL . '/wishlist.php?id=' . $wishlistId
        ]);
        
        return $this->queueEmail($sharedWithUserId, 'wishlist_shared', $subject, $body);
    }
    
    /**
     * Send item purchased notification
     */
    public function sendItemPurchasedNotification($itemId, $purchasedByUsername) {
        // Get item and wishlist owner info
        $stmt = $this->db->prepare("
            SELECT wi.title as item_title, w.title as wishlist_title, 
                   w.user_id, u.email, u.username
            FROM wishlist_items wi
            JOIN wishlists w ON wi.wishlist_id = w.id
            JOIN users u ON w.user_id = u.id
            WHERE wi.id = ?
        ");
        $stmt->execute([$itemId]);
        $data = $stmt->fetch();
        
        if (!$data) return false;
        
        $subject = "Item Purchased: " . $data['item_title'];
        $body = $this->getEmailTemplate('item_purchased', [
            'recipient_name' => $data['username'],
            'purchased_by' => $purchasedByUsername,
            'item_title' => $data['item_title'],
            'wishlist_title' => $data['wishlist_title'],
            'wishlist_url' => SITE_URL . '/wishlist.php?id=' . $itemId
        ]);
        
        return $this->queueEmail($data['user_id'], 'item_purchased', $subject, $body);
    }
    
    /**
     * Get email template
     */
    private function getEmailTemplate($template, $variables = []) {
        $templates = [
            'wishlist_shared' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                        <h2 style="color: #007bff;">Wishlist Shared With You!</h2>
                        <p>Hi {recipient_name},</p>
                        <p>{shared_by} has shared their wishlist "{wishlist_title}" with you.</p>
                        <p>You can view the wishlist by clicking the link below:</p>
                        <p style="text-align: center;">
                            <a href="{wishlist_url}" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">View Wishlist</a>
                        </p>
                        <p>Happy shopping!</p>
                        <hr style="border: 1px solid #eee;">
                        <p style="font-size: 12px; color: #666;">This is an automated message from ' . SITE_NAME . '</p>
                    </div>
                </body>
                </html>
            ',
            
            'item_purchased' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                        <h2 style="color: #28a745;">Item Purchased!</h2>
                        <p>Hi {recipient_name},</p>
                        <p>Good news! {purchased_by} has purchased "{item_title}" from your wishlist "{wishlist_title}".</p>
                        <p>You can view your wishlist by clicking the link below:</p>
                        <p style="text-align: center;">
                            <a href="{wishlist_url}" style="display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;">View Wishlist</a>
                        </p>
                        <hr style="border: 1px solid #eee;">
                        <p style="font-size: 12px; color: #666;">This is an automated message from ' . SITE_NAME . '</p>
                    </div>
                </body>
                </html>
            ',
            
            'welcome' => '
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                        <h2 style="color: #007bff;">Welcome to ' . SITE_NAME . '!</h2>
                        <p>Hi {username},</p>
                        <p>Your account has been created successfully. You can now start creating wishlists and sharing them with your family!</p>
                        <p>Here are some things you can do:</p>
                        <ul>
                            <li>Create unlimited wishlists</li>
                            <li>Organize items by categories</li>
                            <li>Share wishlists with family members</li>
                            <li>Mark items as purchased</li>
                            <li>Set priorities and prices</li>
                        </ul>
                        <p style="text-align: center;">
                            <a href="{site_url}" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">Get Started</a>
                        </p>
                        <hr style="border: 1px solid #eee;">
                        <p style="font-size: 12px; color: #666;">This is an automated message from ' . SITE_NAME . '</p>
                    </div>
                </body>
                </html>
            '
        ];
        
        $html = $templates[$template] ?? '';
        
        // Replace variables
        foreach ($variables as $key => $value) {
            $html = str_replace('{' . $key . '}', htmlspecialchars($value), $html);
        }
        
        return $html;
    }
}

// Function to process email queue (can be called by cron job)
function processEmailQueue() {
    $emailHelper = new EmailHelper();
    $sent = $emailHelper->processEmailQueue();
    echo "Sent $sent emails\n";
}

// If called directly from command line
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    processEmailQueue();
}