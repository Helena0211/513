<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    session_start();
}

$base_url = 'https://helena1201.free.nf/513/week9513';
include 'includes/header.php';

// ===== DATABASE & UPLOAD CONFIG =====
$servername = "sql210.infinityfree.com";
$username   = "if0_40378146";
$dbname     = "if0_40378146_wp579";
$password   = "nQuyY3nfXVA";
$uploadDir  = __DIR__ . "/Recruitment/";

$full_name = $email = $phone = $position = $cover_message = '';
$message = $messageType = '';

// ===== FORM HANDLING =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_recruitment'])) {
    $full_name      = trim($_POST['full_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $rawPhone       = trim($_POST['phone'] ?? '');
    $position       = trim($_POST['position'] ?? '');
    $cover_message  = trim($_POST['cover_message'] ?? '');
    $files          = $_FILES['files'] ?? [];

    $errors = [];

    // 1. BASIC EMPTY CHECKS
    if (empty($full_name)) $errors[] = 'Full name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($position)) $errors[] = 'Position is required.';

    // 2. PHONE: strip non-digits + length gate
    $phoneDigits = preg_replace('/\D+/', '', $rawPhone);
    if ($phoneDigits === '' || strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
        $errors[] = 'Please enter a valid phone number (10-15 digits).';
    }

    // 3. FILE UPLOAD (unchanged)
    if (empty($errors) && !empty($files['name'][0])) {
        $max_files = 5;
        $allowed_types = ['jpg','jpeg','png','pdf','doc','docx','txt'];
        $count = min(count($files['name']), $max_files);

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

            $filename = $files['name'][$i];
            $tmp_name = $files['tmp_name'][$i];
            $file_size = $files['size'][$i];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if ($file_size > 5 * 1024 * 1024 || !in_array($ext, $allowed_types)) {
                $errors[] = "File '$filename' must be ≤ 5MB and one of: " . implode(', ', $allowed_types);
                continue;
            }

            $new_filename = uniqid() . '_' . time() . '.' . $ext;
            $destination  = $uploadDir . $new_filename;
            if (move_uploaded_file($tmp_name, $destination)) {
                $uploaded_files[] = ['original_name' => $filename, 'saved_name' => $new_filename];
            } else {
                $errors[] = "Failed to upload '$filename'.";
            }
        }
    }

    // 4. INSERT INTO DB (only if no errors)
    if (empty($errors)) {
        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $fileNamesJson = json_encode($uploaded_files ?? []);
            $sql = "INSERT INTO wpej_recruitment (full_name, email, phone, position, cover_message, file_names) 
                    VALUES (:full_name, :email, :phone, :position, :cover_message, :file_names)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':full_name'     => $full_name,
                ':email'         => $email,
                ':phone'         => $phoneDigits,   // cleaned digits
                ':position'      => $position,
                ':cover_message' => $cover_message,
                ':file_names'    => $fileNamesJson
            ]);

            $message     = 'Application submitted successfully! Thank you for your interest.';
            $messageType = 'success';
            // reset form fields
            $full_name = $email = $phone = $position = $cover_message = '';
            $conn = null;

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    // 5. DISPLAY ERRORS (red banner)
    if (!empty($errors)) {
        $message = implode('<br>', array_map('htmlspecialchars', $errors));
        $messageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Application - Recruitment</title>
    <style>
        /* 重置样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* 容器样式 */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* 主要内容区域 */
        .main-content {
            flex: 1;
            padding: 40px 0;
        }
        
        /* 招聘表单样式 */
        .recruitment-form-wrapper {
            max-width: 800px;
            margin: 30px auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #1a6fc4 0%, #0d4d8c 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .form-container {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        textarea,
        select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #1a6fc4;
            box-shadow: 0 0 0 2px rgba(26, 111, 196, 0.2);
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .file-upload {
            border: 2px dashed #ccc;
            border-radius: 6px;
            padding: 25px;
            text-align: center;
            background-color: #f9f9f9;
            transition: all 0.3s;
        }
        
        .file-upload:hover {
            border-color: #1a6fc4;
            background-color: #f0f7ff;
        }
        
        .file-upload label {
            display: inline-block;
            padding: 10px 20px;
            background-color: #1a6fc4;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-bottom: 10px;
        }
        
        .file-upload label:hover {
            background-color: #155a9c;
        }
        
        .file-info {
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }
        
        #fileList {
            margin-top: 15px;
            text-align: left;
        }
        
        .file-item {
            background-color: #f0f7ff;
            padding: 8px 12px;
            margin-bottom: 8px;
            border-radius: 4px;
            border-left: 4px solid #1a6fc4;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .file-item span {
            font-size: 14px;
        }
        
        .remove-file {
            color: #e74c3c;
            cursor: pointer;
            font-weight: bold;
        }
        
        .submit-btn {
            display: block;
            width: 100%;
            padding: 15px;
            background-color: #1a6fc4;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .submit-btn:hover {
            background-color: #155a9c;
        }
        
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .required {
            color: #e74c3c;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .header {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div style="display:none;">
        Session Status: <?php echo session_status(); ?><br>
        Session ID: <?php echo session_id(); ?><br>
        Session Variables: <?php print_r($_SESSION); ?>
    </div>

    <main class="main-content">
        <div class="container">
            <div class="recruitment-form-wrapper">
                <div class="header"><h1>Job Application</h1><p>Join our team and grow with us</p></div>
                
                <div class="form-container">
                    <?php
                    if (!empty($message)) {
                        echo '<div class="message ' . $messageType . '">' . htmlspecialchars($message) . '</div>';
                    }
                    ?>

                    <form method="post" enctype="multipart/form-data" id="recruitmentForm">
                        <div class="form-group">
                            <label for="full_name">Full Name <span class="required">*</span></label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required placeholder="Enter your full name">
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="Enter your email address">
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="Enter your phone number">
                        </div>

                        <div class="form-group">
                            <label for="position">Position Applying For <span class="required">*</span></label>
                            <select id="position" name="position" required>
                                <option value="">Select a position</option>
                                <option value="Software Developer" <?php echo ($position == 'Software Developer') ? 'selected' : ''; ?>>Software Developer</option>
                                <option value="Web Designer" <?php echo ($position == 'Web Designer') ? 'selected' : ''; ?>>Web Designer</option>
                                <option value="Project Manager" <?php echo ($position == 'Project Manager') ? 'selected' : ''; ?>>Project Manager</option>
                                <option value="Marketing Specialist" <?php echo ($position == 'Marketing Specialist') ? 'selected' : ''; ?>>Marketing Specialist</option>
                                <option value="Sales Executive" <?php echo ($position == 'Sales Executive') ? 'selected' : ''; ?>>Sales Executive</option>
                                <option value="Other" <?php echo ($position == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cover_message">Cover Letter / Message</label>
                            <textarea id="cover_message" name="cover_message" placeholder="Tell us why you're a good fit for this position..."><?php echo htmlspecialchars($cover_message); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Upload Your Documents (CV, Portfolio, etc.)</label>
                            <div class="file-upload">
                                <label for="files">Choose files</label>
                                <input type="file" id="files" name="files[]" multiple style="display: none;" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt">
                                <div class="file-info">
                                    <p>Upload up to 5 files (Max 5MB each)</p>
                                    <p>Allowed formats: PDF, DOC, DOCX, JPG, PNG, TXT</p>
                                </div>
                                <div id="fileList"></div>
                            </div>
                        </div>

                        <button type="submit" name="submit_recruitment" class="submit-btn">Submit Application</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        // ===== FILE LIST + CLIENT VALIDATION =====
        document.getElementById('files').addEventListener('change', function(e) {
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';
            const files = e.target.files;
            if (files.length > 5) {
                alert('You can only upload up to 5 files. Only the first 5 will be selected.');
                this.files = Array.from(files).slice(0, 5);
            }
            for (let i = 0; i < Math.min(files.length, 5); i++) {
                const file = files[i];
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `<span>${file.name} (${formatFileSize(file.size)})</span>
                                      <span class="remove-file" data-index="${i}">×</span>`;
                fileList.appendChild(fileItem);
            }
            document.querySelectorAll('.remove-file').forEach(btn => btn.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                removeFile(index);
            }));
        });

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function removeFile(index) {
            const input = document.getElementById('files');
            const dt = new DataTransfer();
            const files = Array.from(input.files);
            files.splice(index, 1);
            files.forEach(file => dt.items.add(file));
            input.files = dt.files;
            input.dispatchEvent(new Event('change'));
        }

        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    </script>
</body>
</html>