<?php
session_start();
include 'db.php'; // Include your database connection

// Protect this page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("You must be logged in to do this.");
}

$message = 'An error occurred.';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['contact_name'] ?? '';
    $email = $_POST['contact_email'] ?? '';
    $subject = $_POST['contact_subject'] ?? '';
    $message_body = $_POST['contact_message'] ?? '';

    // Prepare statement to insert into the new table
    $stmt = $conn->prepare("INSERT INTO contact_submissions (name, email, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $subject, $message_body);

    if ($stmt->execute()) {
        $message = "Thank you! Your message has been submitted successfully.";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}
$conn->close();

// Redirect back to the contact page
echo "<script>
    alert('" . addslashes($message) . "');
    window.location.href = 'index.php#contact';
</script>";
?>