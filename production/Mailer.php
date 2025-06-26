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
        
        // Setup mail config for cPanel
        $this->mailer->isSMTP();
        $this->mailer->Host = 'localhost'; // or 'mail.yourdomain.com' if localhost doesn't work
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['MAIL_USERNAME']; // your cPanel email
        $this->mailer->Password = $_ENV['MAIL_PASSWORD']; // your cPanel email password
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = 587;
        
        // Additional options for shared hosting
        $this->mailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set sender info using the cPanel email
        $this->mailer->setFrom($_ENV['MAIL_USERNAME'], 'Farm App');
    }
    
    public function sendOTP($toEmail, $otp): bool
    {
        try {
            // Clear any previous addresses to avoid conflicts
            $this->mailer->clearAddresses();
            
            $this->mailer->addAddress($toEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Your OTP Code';
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
                    </div>
                </body>
                </html>
            ";
            
            // Plain text alternative for email clients that don't support HTML
            $this->mailer->AltBody = "Your OTP Code: $otp\n\nThis code will expire in 10 minutes.\nIf you didn't request this code, please ignore this email.";
            
            $this->mailer->send();
            return true;
            
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Method to test different configurations if the default doesn't work
    public function testConnection(): array
    {
        $configurations = [
            ['host' => 'localhost', 'port' => 587, 'encryption' => PHPMailer::ENCRYPTION_STARTTLS],
            ['host' => 'localhost', 'port' => 465, 'encryption' => PHPMailer::ENCRYPTION_SMTPS],
            ['host' => 'localhost', 'port' => 25, 'encryption' => false],
            ['host' => 'mail.' . $_SERVER['HTTP_HOST'], 'port' => 587, 'encryption' => PHPMailer::ENCRYPTION_STARTTLS],
        ];
        
        $results = [];
        
        foreach ($configurations as $config) {
            $testMailer = new PHPMailer(true);
            
            try {
                $testMailer->isSMTP();
                $testMailer->Host = $config['host'];
                $testMailer->SMTPAuth = true;
                $testMailer->Username = $_ENV['MAIL_USERNAME'];
                $testMailer->Password = $_ENV['MAIL_PASSWORD'];
                
                if ($config['encryption']) {
                    $testMailer->SMTPSecure = $config['encryption'];
                }
                
                $testMailer->Port = $config['port'];
                
                $testMailer->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                if ($testMailer->smtpConnect()) {
                    $results[] = [
                        'config' => "Host: {$config['host']}, Port: {$config['port']}",
                        'status' => 'SUCCESS'
                    ];
                    $testMailer->smtpClose();
                } else {
                    $results[] = [
                        'config' => "Host: {$config['host']}, Port: {$config['port']}",
                        'status' => 'FAILED'
                    ];
                }
                
            } catch (Exception $e) {
                $results[] = [
                    'config' => "Host: {$config['host']}, Port: {$config['port']}",
                    'status' => 'ERROR',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    // Fallback method using PHP's built-in mail function
    public function sendOTPFallback($toEmail, $otp): bool
    {
        try {
            $subject = 'Your OTP Code - Farm App';
            $message = "Your OTP Code: $otp\n\nThis code will expire in 10 minutes.\nIf you didn't request this code, please ignore this email.";
            
            $headers = array(
                'From' => $_ENV['MAIL_USERNAME'],
                'Reply-To' => $_ENV['MAIL_USERNAME'],
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Mailer' => 'PHP/' . phpversion()
            );
            
            $headerString = '';
            foreach ($headers as $key => $value) {
                $headerString .= "$key: $value\r\n";
            }
            
            return mail($toEmail, $subject, $message, $headerString);
            
        } catch (Exception $e) {
            error_log('Fallback Mailer Error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Enhanced sendOTP with fallback
    public function sendOTPWithFallback($toEmail, $otp): bool
    {
        // Try SMTP first
        if ($this->sendOTP($toEmail, $otp)) {
            return true;
        }
        
        // If SMTP fails, try built-in mail function
        error_log('SMTP failed, trying fallback mail function');
        return $this->sendOTPFallback($toEmail, $otp);
    }
}