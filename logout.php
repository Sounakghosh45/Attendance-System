
<?php
session_start();
include 'db.php'; // Needed to clear the token from DB

// 1. Clear the Token from Database (Security Step)
// This ensures that even if someone stole the cookie, it won't work anymore.
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    if ($conn) {
        $conn->query("UPDATE users SET remember_token = NULL WHERE id = '$uid'");
    }
}

// 2. Kill the Browser Cookie
// Important: Must use the same Path "/" as when you created it
if (isset($_COOKIE['remember_me'])) {
    unset($_COOKIE['remember_me']); 
    setcookie('remember_me', '', time() - 3600, '/'); 
}

// 3. Destroy the Session
session_unset();
session_destroy();

// 4. Go to Login
header("Location: login.php");
exit;
?>