<?php
session_start();
include 'db.php';
include 'send_mail.php'; 

$app_name = "Upasthita";
$message = '';
$msg_type = '';
$step = isset($_SESSION['reg_step']) ? $_SESSION['reg_step'] : 'form';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // --- PHASE 1: REQUEST CODE ---
    if (isset($_POST['action']) && $_POST['action'] === 'request_register') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // Check DB
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Username or Email already taken.";
            $msg_type = "danger";
        } else {
            // Generate OTP
            $raw_otp = strtoupper(bin2hex(random_bytes(3))); 
            $approval_code = substr($raw_otp, 0, 3) . '-' . substr($raw_otp, 3, 3);

            $_SESSION['temp_reg_user'] = $username;
            $_SESSION['temp_reg_email'] = $email;
            $_SESSION['temp_reg_pass'] = $password; 
            $_SESSION['admin_approval_code'] = $approval_code;

            // Send Email to Super-Admin
            // --- PHASE 1: SUBMIT DETAILS & REQUEST CODE ---
    if (isset($_POST['action']) && $_POST['action'] === 'request_register') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // 1. Check if Username/Email Exists in DB
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Username or Email already taken.";
            $msg_type = "danger";
        } else {
            // 2. Generate Approval Code
            $raw_otp = strtoupper(bin2hex(random_bytes(3))); 
            $approval_code = substr($raw_otp, 0, 3) . '-' . substr($raw_otp, 3, 3);

            // 3. Save Request to Session
            $_SESSION['temp_reg_user'] = $username;
            $_SESSION['temp_reg_email'] = $email;
            $_SESSION['temp_reg_pass'] = $password; 
            $_SESSION['admin_approval_code'] = $approval_code;

            // =========================================================
            // 4. [FIXED] Send Code ONLY to Super Admin
            // =========================================================
            $super_admin_email = $_ENV['SUPER_ADMIN_EMAIL']; 

            // We pass the email as a STRING (not array) so it goes directly to the 'To' field
            if (sendOTP($super_admin_email, "Super Admin", $approval_code)) {
                $_SESSION['reg_step'] = 'verify_code';
                header("Location: register.php");
                exit;
            } else {
                $message = "Error: Could not send email to Super Admin.";
                $msg_type = "danger";
            }
            // =========================================================
        }
        $check->close();
    }
        }
        $check->close();
    }
    
    // --- PHASE 2: VERIFY & INSERT ---
    if (isset($_POST['action']) && $_POST['action'] === 'verify_code') {
        $submitted_code = strtoupper(trim($_POST['otp_code']));

        if ($submitted_code === $_SESSION['admin_approval_code']) {
            $u = $_SESSION['temp_reg_user'];
            $e = $_SESSION['temp_reg_email'];
            $p = $_SESSION['temp_reg_pass']; 
            
            $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $u, $e, $p);

            if ($stmt->execute()) {
                // Cleanup
                unset($_SESSION['temp_reg_user'], $_SESSION['temp_reg_email'], $_SESSION['temp_reg_pass'], $_SESSION['admin_approval_code'], $_SESSION['reg_step']);
                
                // SUCCESS ALERT & REDIRECT
                echo "<script>
                        alert('Registration Successful! You can now login as Admin.');
                        window.location.href = 'login.php';
                      </script>";
                exit;
            } else {
                $message = "Database Error: " . $conn->error;
                $msg_type = "danger";
            }
        } else {
            $message = "Invalid Code.";
            $msg_type = "danger";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - <?php echo $app_name; ?></title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>

    <div class="register-wrapper animate__animated animate__fadeInUp">
        <div class="glass-card">
            
            <div class="text-center mb-4 w-100">
                <h2 class="brand-title"><?php echo $app_name; ?></h2>
                <p class="text-muted small">Join the Administration</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $msg_type ?> text-center w-100 animate__animated animate__pulse" role="alert">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 'form'): ?>
            <form method="POST" action="register.php" class="w-100">
                <input type="hidden" name="action" value="request_register">
                
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary small">USERNAME</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-person-badge text-secondary"></i></span>
                        <input type="text" class="form-control" name="username" placeholder="Enter your name" required autocomplete="off">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary small">EMAIL</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-envelope text-secondary"></i></span>
                        <input type="email" class="form-control" name="email" placeholder="name@example.com" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-secondary small">PASSWORD</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-key text-secondary"></i></span>
                        <input type="password" class="form-control" name="password" placeholder="Create a password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-custom mb-3">Request Account</button>
                
                <div class="text-center">
                    <span class="text-muted small">Already have an ID?</span>
                    <a href="login.php" class="login-link small ms-1">Login Here</a>
                </div>
            </form>
            <?php endif; ?>

            <?php if ($step === 'verify_code'): ?>
            <form method="POST" action="register.php" class="w-100 animate__animated animate__fadeIn">
                <input type="hidden" name="action" value="verify_code">
                
                <div class="text-center mb-4">
                    <div class="verification-icon mx-auto animate__animated animate__zoomIn">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h5 class="mt-3">Approval Required</h5>
                    <p class="text-muted small">
                        Code sent to <strong>Super-Admin</strong>.<br>
                        Please enter the verification code to continue.
                    </p>
                </div>

                <div class="d-flex justify-content-center mb-4">
                    <input type="text" id="otpInput" class="form-control otp-box" name="otp_code" placeholder="XXX-XXX" maxlength="7" required autofocus>
                </div>

                <button type="submit" class="btn btn-custom mb-3">Complete Registration</button>
                
                <div class="text-center">
                    <a href="register.php?cancel=1" class="text-muted small text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Cancel Request
                    </a>
                </div>
            </form>
            <?php
                if (isset($_GET['cancel'])) {
                    unset($_SESSION['reg_step'], $_SESSION['temp_reg_user']);
                    echo "<script>window.location.href='register.php';</script>";
                    exit;
                }
            ?>
            <?php endif; ?>

        </div>
    </div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const otpInput = document.getElementById("otpInput");
    if (otpInput) {
        otpInput.addEventListener("input", function(e) {
            let value = e.target.value.replace(/[^a-zA-Z0-9]/g, "").toUpperCase();
            if (value.length > 6) value = value.slice(0, 6);
            if (value.length > 3) value = value.slice(0, 3) + "-" + value.slice(3);
            e.target.value = value;
        });
        otpInput.addEventListener("keydown", function(e) {
            if (e.key === "Backspace" && e.target.value.endsWith("-")) {
                e.target.value = e.target.value.slice(0, -1);
            }
        });
    }
});
</script>
</body>
</html>