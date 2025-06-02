<?php
class EmailConfig {
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_SSL_PORT = 465; 
    const SMTP_USERNAME = 'andreea.arama.01@gmail.com';
    const SMTP_PASSWORD = 'zvfzcltvepbibgql';
    const FROM_EMAIL = 'andreea.arama.01@gmail.com';
    const FROM_NAME = 'FitGen Support';
    const ADMIN_EMAIL = 'andreea.arama.01@gmail.com';
    

    public static function sendEmail($to, $subject, $htmlBody, $replyTo = null) {
        error_log("🚀 Starting email send to: $to");

        if (self::sendEmailCurl($to, $subject, $htmlBody, $replyTo)) {
            return true;
        }
        
        if (self::sendEmailNative($to, $subject, $htmlBody, $replyTo)) {
            return true;
        }
        
        if (self::sendEmailSocket($to, $subject, $htmlBody, $replyTo)) {
            return true;
        }
        
        error_log("All email methods failed for: $to");
        return false;
    }
    
    private static function sendEmailCurl($to, $subject, $htmlBody, $replyTo = null) {
        if (!function_exists('curl_init')) {
            error_log("cURL not available");
            return false;
        }
        
        try {
            error_log("🔄 Trying cURL SMTP method");
            
            $headers = self::buildEmailHeaders($to, $subject, $replyTo);
            $emailContent = $headers . "\r\n" . $htmlBody;
            
            $tempFile = tempnam(sys_get_temp_dir(), 'email_');
            file_put_contents($tempFile, $emailContent);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "smtps://smtp.gmail.com:465",
                CURLOPT_USE_SSL => CURLUSESSL_ALL,
                CURLOPT_USERNAME => self::SMTP_USERNAME,
                CURLOPT_PASSWORD => self::SMTP_PASSWORD,
                CURLOPT_MAIL_FROM => self::FROM_EMAIL,
                CURLOPT_MAIL_RCPT => [$to],
                CURLOPT_READDATA => fopen($tempFile, 'r'),
                CURLOPT_UPLOAD => true,
                CURLOPT_VERBOSE => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            unlink($tempFile);
            
            if ($result && empty($error)) {
                error_log("cURL SMTP success for: $to");
                return true;
            } else {
                error_log("cURL SMTP failed: $error (code: $httpCode)");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("cURL SMTP exception: " . $e->getMessage());
            return false;
        }
    }

    private static function sendEmailNative($to, $subject, $htmlBody, $replyTo = null) {
        try {
            error_log("🔄 Trying native mail() method");
            
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . self::FROM_NAME . ' <' . self::FROM_EMAIL . '>',
                'Reply-To: ' . ($replyTo ?: self::FROM_EMAIL),
                'X-Mailer: FitGen PHP Mail',
                'X-Priority: 3',
                'Date: ' . date('r'),
                'Message-ID: <' . md5(uniqid(time())) . '@fitgen.com>'
            ];
            
            $headersString = implode("\r\n", $headers);
            $additionalParams = '-f' . self::FROM_EMAIL;
            
            $cleanSubject = str_replace(["\r", "\n"], '', $subject);
            $cleanBody = str_replace(["\r\n", "\r", "\n"], "\r\n", $htmlBody);
            
            $result = @mail($to, $cleanSubject, $cleanBody, $headersString, $additionalParams);
            
            if ($result) {
                error_log("Native mail() success for: $to");
                return true;
            } else {
                $lastError = error_get_last();
                error_log("Native mail() failed for: $to - " . ($lastError['message'] ?? 'Unknown error'));
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Native mail() exception: " . $e->getMessage());
            return false;
        }
    }
   
    private static function sendEmailSocket($to, $subject, $htmlBody, $replyTo = null) {
        try {
            error_log("Trying simple socket method");
            $socket = @fsockopen('localhost', 25, $errno, $errstr, 10);
            
            if (!$socket) {
                error_log("Socket connection failed: $errstr ($errno)");
                return false;
            }
            
            $response = fgets($socket, 515);
            
            fwrite($socket, "HELO " . $_SERVER['SERVER_NAME'] . "\r\n");
            $response = fgets($socket, 515);

            fwrite($socket, "MAIL FROM: <" . self::FROM_EMAIL . ">\r\n");
            $response = fgets($socket, 515);
            
            fwrite($socket, "RCPT TO: <$to>\r\n");
            $response = fgets($socket, 515);
            
            fwrite($socket, "DATA\r\n");
            $response = fgets($socket, 515);
            
            $headers = self::buildEmailHeaders($to, $subject, $replyTo);
            $emailContent = $headers . "\r\n" . $htmlBody . "\r\n.\r\n";
            fwrite($socket, $emailContent);
            $response = fgets($socket, 515);
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            
            error_log("Socket method success for: $to");
            return true;
            
        } catch (Exception $e) {
            error_log("Socket method exception: " . $e->getMessage());
            return false;
        }
    }
    public static function sendEmailSMTP($to, $subject, $htmlBody, $replyTo = null) {
        return self::sendEmail($to, $subject, $htmlBody, $replyTo);
    }

    private static function buildEmailHeaders($to, $subject, $replyTo = null) {
        $headers = "From: " . self::FROM_NAME . " <" . self::FROM_EMAIL . ">\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Reply-To: " . ($replyTo ?: self::FROM_EMAIL) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $headers .= "X-Mailer: FitGen Multi-Method\r\n";
        $headers .= "X-Priority: 3\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . md5(uniqid(time())) . "@fitgen.com>\r\n";
        
        return $headers;
    }

    public static function sendAutoConfirmation($userEmail, $userName, $message) {
        $subject = "✅ We received your message - FitGen Support";
        $htmlBody = self::buildConfirmationTemplate($userName, $message);
        return self::sendEmail($userEmail, $subject, $htmlBody);
    }
    
    public static function notifyAdmin($userName, $userEmail, $message) {
        $subject = "🔔 New Contact Message - FitGen Admin";
        $htmlBody = self::buildAdminNotificationTemplate($userName, $userEmail, $message);
        return self::sendEmail(self::ADMIN_EMAIL, $subject, $htmlBody, $userEmail);
    }

    private static function buildConfirmationTemplate($userName, $message) {
        $shortMessage = strlen($message) > 200 ? substr($message, 0, 200) . "..." : $message;
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                .header { background: #18D259; color: white; padding: 30px 20px; text-align: center; }
                .content { padding: 30px; }
                .message-box { background: #f8f9fa; padding: 20px; border-left: 4px solid #18D259; margin: 20px 0; border-radius: 5px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; }
                .highlight { color: #18D259; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0; font-size: 24px;'>💪 FitGen</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Message Received Successfully!</p>
                </div>
                <div class='content'>
                    <p>Dear <span class='highlight'>" . htmlspecialchars($userName) . "</span>,</p>
                    
                    <p>Thank you for contacting FitGen! We have successfully received your message and our support team will review it shortly.</p>
                    
                    <div class='message-box'>
                        <strong>📝 Your message:</strong><br><br>
                        " . nl2br(htmlspecialchars($shortMessage)) . "
                    </div>
                    
                    <h3 style='color: #18D259;'>⏰ What happens next?</h3>
                    <ul>
                        <li><strong>Response time:</strong> Within 24 hours</li>
                        <li><strong>Personal response:</strong> Detailed reply via email</li>
                        <li><strong>Support:</strong> We're here to help with your fitness journey!</li>
                    </ul>
                    
                    <p><strong>Thank you for choosing FitGen! 🚀</strong></p>
                </div>
                <div class='footer'>
                    <p><strong>© 2025 FitGen - Your Fitness Partner</strong></p>
                    <p>📧 support@fitgen.com | 🌐 www.fitgen.com</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private static function buildAdminNotificationTemplate($userName, $userEmail, $message) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .info-box { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .message-box { background: #f8f9fa; padding: 15px; border-left: 4px solid #18D259; margin: 15px 0; border-radius: 5px; }
                .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 14px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin: 0;'>🔔 New Contact Message</h2>
                    <p style='margin: 5px 0 0 0;'>FitGen Admin Alert</p>
                </div>
                <div class='content'>
                    <p><strong>⚡ Action Required:</strong> New message received!</p>
                    
                    <div class='info-box'>
                        <h3 style='margin-top: 0; color: #1976d2;'>👤 Customer Details:</h3>
                        <strong>Name:</strong> " . htmlspecialchars($userName) . "<br>
                        <strong>Email:</strong> " . htmlspecialchars($userEmail) . "<br>
                        <strong>Time:</strong> " . date('Y-m-d H:i:s') . "<br>
                        <strong>IP:</strong> " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "
                    </div>
                    
                    <div class='message-box'>
                        <h3 style='margin-top: 0; color: #18D259;'>📝 Message:</h3>
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>
                    
                    <p><strong>💡 Next Steps:</strong></p>
                    <ul>
                        <li>Review message in admin panel</li>
                        <li>Send personalized response</li>
                        <li>Mark as resolved when done</li>
                    </ul>
                </div>
                <div class='footer'>
                    <p><strong>FitGen Admin System</strong></p>
                </div>
            </div>
        </body>
        </html>";
    }
}