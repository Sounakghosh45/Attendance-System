<?php
session_start();
include 'db.php'; 
include 'send_mail.php'; 

$app_name = "Upasthita";

// --- 1. HANDLE LOGOUT (Clear Cookie) ---
if (isset($_GET['reset'])) {
    if (isset($_SESSION['user_id'])) {
        // Remove token from DB
        $uid = $_SESSION['user_id'];
        $conn->query("UPDATE users SET remember_token=NULL WHERE id='$uid'");
    }
    // Delete Cookie
    setcookie('remember_me', '', time() - 3600, "/");
    
    unset($_SESSION['login_step'], $_SESSION['temp_otp'], $_SESSION['temp_email'], $_SESSION['loggedin'], $_SESSION['user_id']);
    session_destroy();
    
    header('Location: login.php'); 
    exit;
}

// --- 2. CHECK REMEMBER ME COOKIE (Auto Login) ---
if (!isset($_SESSION['loggedin']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    
    // Check DB for this token
    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        
        // Auto Login Success
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_id'] = $user['id'];
        
        header('Location: index.php');
        exit;
    }
}

// Check if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';
$success_msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$step = isset($_SESSION['login_step']) ? $_SESSION['login_step'] : 'credentials'; 

// --- ANIMATION LOGIC ---
if ($step === 'verification') {
    $wrapper_class = 'flex-row-reverse'; 
    $img_anim = 'animate__slideInRight'; 
    $form_anim = 'animate__slideInLeft'; 
} else {
    $wrapper_class = 'flex-row'; 
    $img_anim = 'animate__fadeInLeft'; 
    $form_anim = 'animate__fadeInRight'; 
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // STEP 1: VALIDATE USER
    if (isset($_POST['action']) && $_POST['action'] === 'validate_user') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Store "Remember Me" choice in session to use later after OTP
        $_SESSION['temp_remember'] = isset($_POST['remember_me']); 

        $stmt = $conn->prepare("SELECT id, username, password, email FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if ($password === $user['password']) { 
                
                // Generate OTP
                $raw_otp = strtoupper(bin2hex(random_bytes(3))); 
                $otp = substr($raw_otp, 0, 3) . '-' . substr($raw_otp, 3, 3);
                
                $_SESSION['temp_user_id'] = $user['id'];
                $_SESSION['temp_username'] = $user['username'];
                $_SESSION['temp_otp'] = $otp;
                $_SESSION['temp_email'] = $user['email'];
                
                if (sendOTP($user['email'], $user['username'], $otp)) {
                    $_SESSION['login_step'] = 'verification';
                    header('Location: login.php'); 
                    exit;
                } else {
                    $error = 'Connection error. Could not send OTP.';
                }
            } else {
                $error = 'Incorrect password provided.';
            }
        } else {
            $error = 'Account not found.';
        }
        $stmt->close();
    }

    // STEP 2: VERIFY OTP
    if (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
        $submitted_otp = strtoupper($_POST['otp_code'] ?? ''); 
        
        if ($submitted_otp == $_SESSION['temp_otp']) {
            
            // --- 3. SET REMEMBER ME COOKIE (If chosen) ---
            if (isset($_SESSION['temp_remember']) && $_SESSION['temp_remember'] === true) {
                $token = bin2hex(random_bytes(32)); // Generate Secure Token
                $uid = $_SESSION['temp_user_id'];
                
                // Save Token to Database
                $stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $stmt->bind_param("si", $token, $uid);
                $stmt->execute();
                
                // Set Cookie (Expires in 30 Days)
                setcookie('remember_me', $token, time() + (86400 * 30), "/");
            }

            // LOGIN SUCCESS
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $_SESSION['temp_username'];
            $_SESSION['user_id'] = $_SESSION['temp_user_id'];
            
            unset($_SESSION['temp_otp'], $_SESSION['temp_email'], $_SESSION['login_step'], $_SESSION['temp_user_id'], $_SESSION['temp_username'], $_SESSION['temp_remember']);
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid Code. Please check your email.';
            $step = 'verification';
            $wrapper_class = 'flex-row-reverse'; 
            $img_anim = 'animate__pulse'; 
            $form_anim = 'animate__shakeX';
        }
    }
}
$conn->close();
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?php echo $app_name; ?></title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>

    <div class="login-wrapper <?php echo $wrapper_class; ?>">
        
        <div class="panel-image animate__animated <?php echo $img_anim; ?>">
            <img src="img1.webp" alt="Secure Login" class="illustration-img">
        </div>

        <div class="panel-form animate__animated <?php echo $form_anim; ?>">
            
            <div class="mb-4">
                <h2 class="brand-title" style="color: var(--primary-color); font-weight: 700;"><?php echo $app_name; ?></h2>
                <p class="text-muted">RFID Attendance Admin Panel</p>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success-custom mb-4 animate__animated animate__fadeIn">
                    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-custom mb-4 animate__animated animate__headShake">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 'credentials'): ?>
            <form method="POST" action="login.php">
                <input type="hidden" name="action" value="validate_user">
                
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary small">USERNAME</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-person text-secondary"></i></span>
                        <input type="text" class="form-control" name="username" placeholder="Username" required autocomplete="off">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-bold text-secondary small">PASSWORD</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-lock text-secondary"></i></span>
                        <input type="password" class="form-control" name="password" placeholder="Password" required>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="remember_me" id="remember">
                        <label class="form-check-label text-muted small" for="remember">Remember me</label>
                    </div>
                    <div>
                        <a href="register.php" class="text-decoration-none fw-bold small" style="color: var(--secondary-color);">Register New</a>
                    </div>
                </div>

                <button type="submit" class="btn btn-custom shadow-sm">Log In</button>
            </form>
            <?php endif; ?>

            <?php if ($step === 'verification'): ?>
            <form method="POST" action="login.php">
                <input type="hidden" name="action" value="verify_otp">
                
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <div class="verification-icon animate__animated animate__zoomIn">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                    </div>
                    <h5>Two-Factor Authentication</h5>
                    <p class="text-muted small">
                        Code sent to: 
                        <strong>
                            <?php 
                                $e = $_SESSION['temp_email']; 
                                echo substr($e, 0, 3) . '***' . substr($e, strpos($e, '@')); 
                            ?>
                        </strong>
                    </p>
                </div>

                <div class="mb-4">
                 <input type="text" id="otpInput" class="form-control otp-box" name="otp_code" placeholder="A1B-2C3" maxlength="7" required autofocus>
                </div>

                <button type="submit" class="btn btn-custom mb-3">Verify Code</button>
                
                <div class="text-center">
                    <a href="login.php?reset=1" class="text-muted small text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Back to Login
                    </a>
                </div>
            </form>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener("DOMContentLoaded", function() {
    const otpInput = document.getElementById("otpInput");

    if (otpInput) {
        otpInput.addEventListener("input", function(e) {
            // 1. Remove any existing hyphens and non-alphanumeric chars
            let value = e.target.value.replace(/[^a-zA-Z0-9]/g, "").toUpperCase();

            // 2. Limit to 6 characters (since we only want 6 alphanumeric chars total)
            if (value.length > 6) {
                value = value.slice(0, 6);
            }

            // 3. Add the hyphen if length is greater than 3
            if (value.length > 3) {
                value = value.slice(0, 3) + "-" + value.slice(3);
            }

            // 4. Update the input field
            e.target.value = value;
        });

        // Optional: Handle backspace cleanly
        otpInput.addEventListener("keydown", function(e) {
            const key = e.key;
            const value = e.target.value;
            
            // If backspacing specifically the hyphen, allow it to jump back
            if (key === "Backspace" && value.endsWith("-")) {
                e.target.value = value.slice(0, -1);
            }
        });
    }
});
</script>
</body>
</html>