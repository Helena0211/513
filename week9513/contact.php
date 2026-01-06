<?php
require_once 'config/config.php';

$success = '';
$error = '';

if ($_POST) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // In a real application, you would send an email here
        // For demo purposes, we'll just show a success message
        
        // You could use: mail('hello@skillcraft.com', $subject, $message, "From: $email");
        
        $success = 'Thank you for your message! We will get back to you within 24 hours.';
        $_POST = array(); // Clear form
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="mb-0">Get In Touch</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">Have questions about our workshops? Need help with booking? Want to become an instructor? We would love to hear from you!</p>

                    <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject *</label>
                            <select class="form-select" id="subject" name="subject" required>
                                <option value="">Select a subject</option>
                                <option value="General Inquiry" <?php echo ($_POST['subject'] ?? '') == 'General Inquiry' ? 'selected' : ''; ?>>General Inquiry</option>
                                <option value="Workshop Information" <?php echo ($_POST['subject'] ?? '') == 'Workshop Information' ? 'selected' : ''; ?>>Workshop Information</option>
                                <option value="Booking Assistance" <?php echo ($_POST['subject'] ?? '') == 'Booking Assistance' ? 'selected' : ''; ?>>Booking Assistance</option>
                                <option value="Become an Instructor" <?php echo ($_POST['subject'] ?? '') == 'Become an Instructor' ? 'selected' : ''; ?>>Become an Instructor</option>
                                <option value="Technical Support" <?php echo ($_POST['subject'] ?? '') == 'Technical Support' ? 'selected' : ''; ?>>Technical Support</option>
                                <option value="Partnership" <?php echo ($_POST['subject'] ?? '') == 'Partnership' ? 'selected' : ''; ?>>Partnership Opportunities</option>
                                <option value="Other" <?php echo ($_POST['subject'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="6" 
                                      placeholder="Please provide details about your inquiry..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg">Send Message</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Contact Information -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Contact Information</h5>
                    <div class="contact-info">
                        <div class="d-flex align-items-center mb-3">
                            <div class="contact-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <strong>Visit Our Studio</strong><br>
                                <span class="text-muted">123 Creative Street<br>Art District, AC 12345</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center mb-3">
                            <div class="contact-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <strong>Call Us</strong><br>
                                <span class="text-muted">+1 (555) 123-4567</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center mb-3">
                            <div class="contact-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <strong>Email Us</strong><br>
                                <span class="text-muted">hello@skillcraft.com</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center">
                            <div class="contact-icon bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <strong>Business Hours</strong><br>
                                <span class="text-muted">Mon-Fri: 9AM-6PM<br>Sat: 10AM-4PM</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ Quick Links -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Quick Help</h5>
                    <div class="list-group list-group-flush">
                        <a href="products/index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-graduation-cap me-2 text-primary"></i>
                            Browse Workshops
                        </a>
                        <a href="auth/register.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user-plus me-2 text-success"></i>
                            Create Account
                        </a>
                        <a href="auth/login.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-sign-in-alt me-2 text-info"></i>
                            Login Help
                        </a>
                        <a href="about.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-info-circle me-2 text-warning"></i>
                            About SkillCraft
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<style>
.contact-icon {
    flex-shrink: 0;
}
</style>