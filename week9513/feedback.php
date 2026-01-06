<?php
// ===== DATABASE & MAIL CONFIG =====
$host = 'sql210.infinityfree.com';
$dbname = 'if0_40378146_wp579';
$username = 'if0_40378146';
$password = 'nQuyY3nfXVA';
$vendor_email = "vendor@example.com";
$support_email = "support@ncc-ecommerce.com";
$site_name = "NCC ICT 60220 E-COMMERCE";

// ===== WORDPRESS MAIL HELPERS =====
$wordpress_path = '/home/vol6_7/infinityfree.com/if0_40378146/htdocs/123';

function send_wordpress_email($to_email, $to_name, $subject, $message_content, $feedback_id, $site_name, $user_email, $user_name, $is_html = false) {
    global $wordpress_path;
    
    if (!file_exists($wordpress_path . '/wp-load.php')) {
        return ['success' => false, 'message' => 'WordPress not found at specified path'];
    }
    
    define('WP_USE_THEMES', false);
    require_once($wordpress_path . '/wp-load.php');
    
    $site_email = get_bloginfo('admin_email');
    if (empty($site_email) || !filter_var($site_email, FILTER_VALIDATE_EMAIL)) {
        $site_email = 'support@ncc-ecommerce.com';
    }
    
    $site_from_name = get_bloginfo('name') . ' Support';
    if (empty($site_from_name) || $site_from_name == ' Support') {
        $site_from_name = $site_name . ' Support';
    }
    
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_from_name . ' <' . $site_email . '>',
        'Reply-To: ' . $user_name . ' <' . $user_email . '>'
    ];
    
    add_filter('wp_mail_content_type', fn() => 'text/html');
    $result = wp_mail($to_email, $subject, $message_content, $headers);
    remove_filter('wp_mail_content_type', 'set_html_content_type');
    
    return $result ? ['success' => true, 'message' => 'Email sent successfully via WordPress'] : ['success' => false, 'message' => 'WordPress mail failed'];
}

function send_email_backup($to_email, $to_name, $subject, $message_content, $feedback_id, $site_name, $user_email, $user_name, $is_html = false) {
    $phpmailer_path = __DIR__ . '/PHPMailer/src/';
    
    if (!file_exists($phpmailer_path . 'PHPMailer.php')) {
        return ['success' => false, 'message' => 'PHPMailer not found'];
    }
    
    require_once $phpmailer_path . 'Exception.php';
    require_once $phpmailer_path . 'PHPMailer.php';
    require_once $phpmailer_path . 'SMTP.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.qq.com';
        $mail->SMTPAuth = true;
        $mail->Username = '3533993504@qq.com';
        $mail->Password = 'txuziwznlszsdafa';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('support@ncc-ecommerce.com', $site_name . ' Support');
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo($user_email, $user_name);
        $mail->isHTML(true);
        $mail->Subject = "[Support Ticket #$feedback_id] $subject";
        $mail->Body = $is_html ? $message_content : nl2br(htmlspecialchars($message_content));
        $mail->send();
        
        return ['success' => true, 'message' => 'Email sent via PHPMailer backup'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'PHPMailer exception: ' . $e->getMessage()];
    }
}

// ===== FORM PROCESSING =====
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    
    if (empty($subject)) {
        $errors[] = "Subject is required.";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required.";
    }
    
    // 1. FORMAT CHECK
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    } else {
        // 2. DOMAIN CHECK (MX record)
        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, "MX")) {
            $errors[] = "Email domain '$domain' does not accept mail (MX record missing).";
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("INSERT INTO wpej_feedback (name, email, subject, message, status) VALUES (:name, :email, :subject, :message, 'pending')");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':subject' => $subject,
                ':message' => $message
            ]);
            
            $feedback_id = $pdo->lastInsertId();
            
            // mail to vendor
            $vendor_result = send_wordpress_email($vendor_email, 'Support Team', "[Support Ticket #$feedback_id] $subject", $message, $feedback_id, $site_name, $email, $name);
            
            if (!$vendor_result['success']) {
                $vendor_result = send_email_backup($vendor_email, 'Support Team', "[Support Ticket #$feedback_id] $subject", $message, $feedback_id, $site_name, $email, $name);
            }
            
            // confirmation to customer
            $customer_html = '<html>
<head>
<style>
body {
    font-family: Arial, sans-serif;
    line-height: 1.6;
    color: #333;
}
.container {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
}
.header {
    background: #3498db;
    color: white;
    padding: 20px;
    text-align: center;
    border-radius: 5px 5px 0 0;
}
.content {
    background: #f9f9f9;
    padding: 30px;
    border-radius: 0 0 5px 5px;
}
.ticket-details {
    background: white;
    padding: 20px;
    border-radius: 5px;
    border-left: 4px solid #2ecc71;
    margin-bottom: 20px;
}
.footer {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
    font-size: 12px;
    color: #666;
}
.ticket-id {
    font-size: 18px;
    font-weight: bold;
    color: #e74c3c;
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Thank You for Contacting ' . $site_name . '</h1>
        <p>Your support ticket has been received</p>
    </div>
    <div class="content">
        <div class="ticket-details">
            <h3>Your Support Ticket Details</h3>
            <p><strong>Ticket ID:</strong> <span class="ticket-id">#' . $feedback_id . '</span></p>
            <p><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>
            <p><strong>Date Submitted:</strong> ' . date('F j, Y, H:i:s') . '</p>
            <p><strong>Status:</strong> <span style="color:#3498db;font-weight:bold;">Pending Review</span></p>
        </div>
        <div style="background:white;padding:20px;border-radius:5px;border-left:4px solid #f39c12;margin-bottom:20px">
            <h3>What Happens Next?</h3>
            <ol>
                <li>Our support team will review your inquiry</li>
                <li>You\'ll receive a response within 24 hours</li>
                <li>We\'ll work with you to resolve your issue</li>
            </ol>
            <p>If you have additional information to add, simply reply to this email.</p>
        </div>
        <div class="footer">
            <p>Thank you for choosing ' . $site_name . '.</p>
            <p><strong>Best regards,</strong><br>' . $site_name . ' Support Team<br>' . $support_email . '</p>
            <p style="font-size:11px;color:#999;margin-top:15px;">This is an automated message from ' . $site_name . ' Support System.</p>
        </div>
    </div>
</div>
</body>
</html>';
            
            $customer_result = send_wordpress_email($email, $name, "Thank you for contacting $site_name Support", $customer_html, $feedback_id, $site_name, $support_email, "$site_name Support", true);
            
            if (!$customer_result['success']) {
                $customer_result = send_email_backup($email, $name, "Thank you for contacting $site_name Support", $customer_html, $feedback_id, $site_name, $support_email, "$site_name Support", true);
            }
            
            // legacy plain-text fall-back
            @mail($vendor_email, "[Support Ticket #$feedback_id] $subject", "Ticket ID: #$feedback_id\nCustomer: $name ($email)\n\n$message", "From: $support_email\r\nReply-To: $email\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8");
            @mail($email, "Thank you for contacting $site_name Support", "Dear $name,\n\nWe have received your message and will respond within 24 hours.\n\nTicket ID: #$feedback_id\n\nBest regards,\n$site_name Support", "From: $support_email\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8");
            
            $success_message = "✅ Thank you! Your feedback has been submitted. Ticket ID: #$feedback_id";
            $name = $email = $subject = $message_content = '';
            
        } catch (PDOException $e) {
            $error_message = "❌ Database error: " . $e->getMessage();
        }
    } else {
        $error_message = "❌ " . implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Support - <?php echo htmlspecialchars($site_name); ?></title>
    <style>
        .message-container {
            max-width: 800px;
            margin: 20px auto 30px auto;
        }
        .message {
            padding: 15px 20px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .success {
            color: #155724;
            background: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .error {
            color: #721c24;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .feedback-section {
            background: #fff;
            padding: 3rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,.1);
            margin: 0 auto 3rem auto;
            max-width: 800px;
        }
        .feedback-form {
            width: 100%;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: .5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: .75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52,152,219,.2);
        }
        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: #3498db;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background-color .3s;
            font-weight: 600;
        }
        .submit-btn:hover {
            background: #2980b9;
        }
        .wp-email-indicator {
            display: inline-block;
            margin-left: 10px;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 10px;
            background: #4CAF50;
            color: #fff;
            font-weight: normal;
        }
        .email-info {
            margin-top: 10px;
            padding: 10px;
            background: #e8f4fd;
            border-radius: 5px;
            border-left: 4px solid #3498db;
            font-size: 14px;
            color: #2c3e50;
        }
        @media (max-width: 768px) {
            .feedback-section {
                padding: 2rem 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main style="max-width: 1200px; margin: 0 auto; padding: 2rem 20px;">
        <div class="message-container">
            <?php if (!empty($success_message)): ?>
                <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center; margin-bottom: 3rem;">
            <h1 style="color: #2c3e50; margin-bottom: 1rem;">Customer Support Feedback</h1>
            <p style="color: #666; max-width: 700px; margin: 0 auto;">Have questions or need assistance? Fill out the form below. We'll respond within 24 hours and send emails to both you and our support team.</p>
        </div>
        
        <section class="feedback-section">
            <form class="feedback-form" method="POST" action="">
                <div class="form-group">
                    <label for="name">Your Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required placeholder="Enter your full name">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required placeholder="Enter your email address">
                </div>
                
                <div class="form-group">
                    <label for="subject">Subject *</label>
                    <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($subject ?? ''); ?>" required placeholder="Brief description of your issue">
                </div>
                
                <div class="form-group">
                    <label for="message">Your Message *</label>
                    <textarea id="message" name="message" required placeholder="Please provide details about your question or issue..."><?php echo htmlspecialchars($message_content ?? ''); ?></textarea>
                </div>
                
                <button type="submit" name="submit_feedback" class="submit-btn">Send Feedback to Support Team</button>
                
                <p style="margin-top: 1rem; color: #666; font-size: 14px; text-align: center;">* After submitting, you will receive a confirmation email with your Ticket ID and next steps. Our support team will also be notified.</p>
            </form>
        </section>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>