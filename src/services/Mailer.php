<?php
namespace App\Services;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    protected $mailer;
    
    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        
        // Setup Gmail SMTP configuration
        $this->mailer->isSMTP();
        $this->mailer->Host = 'smtp.gmail.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['MAIL_USERNAME']; // your Gmail address
        $this->mailer->Password = $_ENV['MAIL_PASSWORD']; // your Gmail App Password (not regular password)
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = 587;
        
        // Gmail SMTP options
        $this->mailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set sender info using Gmail
        $this->mailer->setFrom($_ENV['MAIL_USERNAME'], 'Farm App');
    }
    
    public function sendOTP($toEmail, $otp): bool
    {
        try {
            // Clear any previous addresses to avoid conflicts
            $this->mailer->clearAddresses();
            
            $this->mailer->addAddress($toEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Your OTP Code - Farm App';
            $this->mailer->Body = "
                <html>
                <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;'>
                    <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>
                        <h2 style='color: #333; text-align: center; margin-bottom: 30px;'>Farm App - OTP Verification</h2>
                        <div style='text-align: center; padding: 20px; background-color: #f8f9fa; border-radius: 5px; margin: 20px 0;'>
                            <h3 style='color: #28a745; margin: 0;'>Your OTP Code:</h3>
                            <div style='font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 3px; margin: 15px 0;'>$otp</div>
                        </div>
                        <p style='color: #666; text-align: center; margin: 20px 0;'>
                            This code will expire in <strong>10 minutes</strong>.
                        </p>
                        <p style='color: #666; text-align: center; font-size: 14px;'>
                            If you didn't request this code, please ignore this email.
                        </p>
                        <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                        <p style='color: #999; text-align: center; font-size: 12px;'>
                            This is an automated message from Farm App. Please do not reply to this email.
                        </p>
                    </div>
                </body>
                </html>
            ";
            
            // Plain text alternative for email clients that don't support HTML
            $this->mailer->AltBody = "Your OTP Code: $otp\n\nThis code will expire in 10 minutes.\nIf you didn't request this code, please ignore this email.\n\n--\nFarm App Team";
            
            $this->mailer->send();
            return true;
            
        } catch (Exception $e) {
            error_log('Gmail Mailer Error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Method to test Gmail connection
    public function testGmailConnection(): array
    {
        $results = [];
        
        try {
            $testMailer = new PHPMailer(true);
            $testMailer->isSMTP();
            $testMailer->Host = 'smtp.gmail.com';
            $testMailer->SMTPAuth = true;
            $testMailer->Username = $_ENV['GMAIL_USERNAME'];
            $testMailer->Password = $_ENV['GMAIL_APP_PASSWORD'];
            $testMailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $testMailer->Port = 587;
            
            $testMailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            if ($testMailer->smtpConnect()) {
                $results[] = [
                    'config' => 'Gmail SMTP (smtp.gmail.com:587)',
                    'status' => 'SUCCESS',
                    'message' => 'Connection successful'
                ];
                $testMailer->smtpClose();
            } else {
                $results[] = [
                    'config' => 'Gmail SMTP (smtp.gmail.com:587)',
                    'status' => 'FAILED',
                    'message' => 'Could not connect to SMTP server'
                ];
            }
            
        } catch (Exception $e) {
            $results[] = [
                'config' => 'Gmail SMTP (smtp.gmail.com:587)',
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ];
        }
        
        return $results;
    }
    
    // Alternative method using Gmail SMTP on port 465 (SSL)
    public function sendOTPSecure($toEmail, $otp): bool
    {
        try {
            $secureMailer = new PHPMailer(true);
            
            // Gmail SMTP with SSL (port 465)
            $secureMailer->isSMTP();
            $secureMailer->Host = 'smtp.gmail.com';
            $secureMailer->SMTPAuth = true;
            $secureMailer->Username = $_ENV['GMAIL_USERNAME'];
            $secureMailer->Password = $_ENV['GMAIL_APP_PASSWORD'];
            $secureMailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL encryption
            $secureMailer->Port = 465;
            
            $secureMailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            $secureMailer->setFrom($_ENV['GMAIL_USERNAME'], 'Farm App');
            $secureMailer->addAddress($toEmail);
            $secureMailer->isHTML(true);
            $secureMailer->Subject = 'Your OTP Code - Farm App';
            $secureMailer->Body = "
                <html>
                <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;'>
                    <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>
                        <h2 style='color: #333; text-align: center; margin-bottom: 30px;'>Farm App - OTP Verification</h2>
                        <div style='text-align: center; padding: 20px; background-color: #f8f9fa; border-radius: 5px; margin: 20px 0;'>
                            <h3 style='color: #28a745; margin: 0;'>Your OTP Code:</h3>
                            <div style='font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 3px; margin: 15px 0;'>$otp</div>
                        </div>
                        <p style='color: #666; text-align: center; margin: 20px 0;'>
                            This code will expire in <strong>10 minutes</strong>.
                        </p>
                        <p style='color: #666; text-align: center; font-size: 14px;'>
                            If you didn't request this code, please ignore this email.
                        </p>
                    </div>
                </body>
                </html>
            ";
            
            $secureMailer->AltBody = "Your OTP Code: $otp\n\nThis code will expire in 10 minutes.\nIf you didn't request this code, please ignore this email.";
            
            $secureMailer->send();
            return true;
            
        } catch (Exception $e) {
            error_log('Gmail Secure Mailer Error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Enhanced sendOTP with fallback (tries STARTTLS first, then SSL)
    public function sendOTPWithFallback($toEmail, $otp): bool
    {
        // Try STARTTLS (port 587) first
        if ($this->sendOTP($toEmail, $otp)) {
            return true;
        }
        
        // If STARTTLS fails, try SSL (port 465)
        error_log('Gmail STARTTLS failed, trying SSL connection');
        return $this->sendOTPSecure($toEmail, $otp);
    }
    
    // Method to send test email
    public function sendTestEmail($toEmail): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($toEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Test Email from Farm App';
            $this->mailer->Body = "
                <html>
                <body style='font-family: Arial, sans-serif; padding: 20px;'>
                    <h2>Gmail Configuration Test</h2>
                    <p>If you're reading this, your Gmail SMTP configuration is working correctly!</p>
                    <p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>
                </body>
                </html>
            ";
            
            $this->mailer->send();
            return true;
            
        } catch (Exception $e) {
            error_log('Test Email Error: ' . $e->getMessage());
            return false;
        }
    }
}