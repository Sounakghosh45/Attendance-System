<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; 

// CHANGED: $toEmail can now be an array or string
function sendOTP($toEmail, $username, $otp) {
    $mail = new PHPMailer(true);

    // --- CONFIG ---
    $brandColor = "#6a11cb";
    $accentColor = "#2575fc";
    $companyName = "Upasthita";

    try {
        // Server settings
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                       
        $mail->SMTPAuth   = true;                                   
        $mail->Username   = $_ENV['SMTP_USER'];         // <--- UPDATE THIS
        $mail->Password   = $_ENV['SMTP_PASS'];                 // <--- UPDATE THIS (App Password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;         
        $mail->Port       = 465;                                   

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom($_ENV['SMTP_USER'], 'Upasthita Services');

        // --- NEW LOGIC: HANDLE MULTIPLE EMAILS ---
        if (is_array($toEmail)) {
            // If it's a list of admins, add them as BCC so they don't see each other's emails
            foreach ($toEmail as $email) {
                $mail->addBCC($email);
            }
            // Add the main sender (you) as "To" just so the field isn't empty
            $mail->addAddress($_ENV['SMTP_USER'], 'Admin Notification');
        } else {
            // If it's a single user (like login), send normally
            $mail->addAddress($toEmail, $username);
        }
        // -----------------------------------------

        // Content
        $mail->isHTML(true);                                  
        $mail->Subject = "Login Code: $otp - Upasthita";
        
        // --- MODERN HTML EMAIL TEMPLATE ---
        $emailTemplate = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { background-color: #eceff1; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
                .wrapper { width: 100%; table-layout: fixed; background-color: #eceff1; padding-bottom: 40px; }
                .main-table { background-color: #ffffff; margin: 0 auto; width: 100%; max-width: 600px; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, $brandColor, $accentColor); padding: 40px; text-align: center; }
                .header h1 { color: #ffffff; margin: 0; font-size: 28px; font-weight: 300; letter-spacing: 2px; text-transform: uppercase; }
                .content { padding: 40px; text-align: center; color: #455a64; }
                .welcome-text { font-size: 18px; margin-bottom: 20px; font-weight: 500; }
                
                /* The OTP Card */
                .code-container { margin: 30px 0; position: relative; }
                .otp-code { 
                    background: #f3f4f6; 
                    color: $brandColor; 
                    font-size: 32px; 
                    font-weight: 800; 
                    letter-spacing: 4px; 
                    padding: 15px 20px; 
                    border-radius: 12px; 
                    border: 2px dashed #cfd8dc;
                    display: inline-block;
                    font-family: 'Courier New', monospace;
                    white-space: nowrap;
                    max-width: 100%;
                    overflow: hidden;
                }
                
                .info-text { font-size: 14px; color: #90a4ae; line-height: 1.6; margin-top: 30px; }
                .footer { background-color: #cfd8dc; padding: 20px; text-align: center; font-size: 12px; color: #546e7a; }
                .secure-badge { display: inline-block; margin-top: 10px; padding: 5px 10px; background: #e0e0e0; border-radius: 4px; font-size: 10px; font-weight: bold; color: #555; }
            </style>
        </head>
        <body>
            <div class='wrapper'>
                <br>
                <div class='main-table'>
                    <div class='header'>
                        <h1>$companyName</h1>
                    </div>
                    
                    <div class='content'>
                        <p class='welcome-text'>Authentication Required</p>
                        <p>Hello $username, we received a request to access your dashboard.</p>
                        
                        <div class='code-container'>
                            <div class='otp-code'>$otp</div>
                        </div>
                        
                        <p>Use this alphanumeric code to complete the login process.</p>
                        
                        <div class='info-text'>
                            &bull; This code expires in 10 minutes.<br>
                            &bull; If you didn't request this, change your password immediately.
                        </div>
                    </div>
                    
                    <div class='footer'>
                        &copy; " . date('Y') . " Upasthita Project.<br>
                        <p><strong>Developed by Sounak & Suman</strong></p>
                        <div class='secure-badge'>SECURE ENCRYPTED EMAIL</div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->Body    = $emailTemplate;
        $mail->AltBody = "Your Login Code is: $otp"; 

        $mail->send();
        return true;
   } catch (Exception $e) {
    // echo "<b>MAIL ERROR:</b> " . $mail->ErrorInfo; // <--- This prints the actual error
    // exit; // Stop the script so you can read it
    return false;
}
}
?>