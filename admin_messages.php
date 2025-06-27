<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function getConnection() {
    $host = "database-1.cpak6uiam1q1.eu-north-1.rds.amazonaws.com";
    $port = "5432";
    $dbname = "postgres";
    $username = "postgres";
    $password = "postgres";
    
    $connection_string = "host=$host port=$port dbname=$dbname user=$username password=$password";
    
    $conn = @pg_connect($connection_string);
    
    if (!$conn) {
        error_log("Database connection failed: " . pg_last_error());
        return false;
    }
    
    $schemaResult = pg_query($conn, "SET search_path TO fitgen, public");
    if (!$schemaResult) {
        error_log("Failed to set search_path: " . pg_last_error($conn));
        return false;
    }
    
    return $conn;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $conn = getConnection();
        if (!$conn) {
            throw new Exception('Database connection failed');
        }

        $query = "SELECT id, full_name, email, message, created_at, is_read, response_sent, admin_notes 
                  FROM contact_messages 
                  ORDER BY created_at DESC";
        
        $result = pg_query($conn, $query);
        
        if (!$result) {
            throw new Exception('Failed to fetch messages: ' . pg_last_error($conn));
        }

        $messages = [];
        while ($row = pg_fetch_assoc($result)) {
            $messages[] = [
                'id' => (int)$row['id'],
                'fullName' => htmlspecialchars($row['full_name']),
                'email' => htmlspecialchars($row['email']),
                'message' => htmlspecialchars($row['message']),
                'createdAt' => $row['created_at'],
                'isRead' => $row['is_read'] === 't',
                'responseSent' => $row['response_sent'] === 't',
                'adminNotes' => htmlspecialchars($row['admin_notes'] ?? '')
            ];
        }

        pg_close($conn);

        echo json_encode([
            'success' => true,
            'data' => $messages
        ]);

    } elseif ($method === 'POST') {
        require_once 'email_config.php';
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        error_log("🔄 Admin response received: " . json_encode($input));
        
        if (!$input) {
            throw new Exception('Invalid JSON data');
        }

        $messageId = (int)$input['messageId'];
        $responseText = trim($input['responseText']);
        $adminEmail = trim($input['adminEmail'] ?? EmailConfig::ADMIN_EMAIL);
        
        if (!$messageId || !$responseText) {
            throw new Exception('Message ID and response text are required');
        }

        $conn = getConnection();
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        $getMessageQuery = "SELECT full_name, email, message FROM contact_messages WHERE id = $1";
        $messageResult = pg_query_params($conn, $getMessageQuery, [$messageId]);
        
        if (!$messageResult || pg_num_rows($messageResult) === 0) {
            throw new Exception('Message not found');
        }

        $originalMessage = pg_fetch_assoc($messageResult);
        error_log("📧 Found original message for user: " . $originalMessage['email']);

        $to = $originalMessage['email'];
        $subject = "Response from FitGen Support Team";
        $emailBody = buildAdminResponseEmail(
            $originalMessage['full_name'],
            $responseText,
            $originalMessage['message']
        );

        try {
            error_log("🚀 Attempting to send admin response email to: " . $to);
            
            $emailSent = EmailConfig::sendEmail($to, $subject, $emailBody, $adminEmail);
            
            if ($emailSent) {
                error_log("✅ Admin response email sent successfully to: " . $to);

                $updateQuery = "UPDATE contact_messages 
                               SET response_sent = true, is_read = true, admin_notes = $1 
                               WHERE id = $2";
                
                $adminNotes = "Admin response sent on " . date('Y-m-d H:i:s') . 
                            " from " . $adminEmail . 
                            ". Response preview: " . substr($responseText, 0, 100) . 
                            (strlen($responseText) > 100 ? "..." : "");
                
                $updateResult = pg_query_params($conn, $updateQuery, [$adminNotes, $messageId]);
                
                if (!$updateResult) {
                    error_log("Failed to update message status: " . pg_last_error($conn));
                    throw new Exception('Failed to update message status in database');
                }

                pg_close($conn);

                echo json_encode([
                    'success' => true,
                    'message' => 'Response sent successfully! The customer will receive your reply via email.',
                    'details' => [
                        'recipient' => $to,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'method' => 'Multi-Method Email System'
                    ]
                ]);
                
            } else {
                throw new Exception('All email delivery methods failed. Please check server configuration and try again.');
            }
            
        } catch (Exception $emailError) {
            error_log("Email sending failed completely: " . $emailError->getMessage());

            $updateQuery = "UPDATE contact_messages 
                           SET is_read = true, admin_notes = $1 
                           WHERE id = $2";
            
            $failureNotes = "Email send FAILED on " . date('Y-m-d H:i:s') . 
                          ". Error: " . $emailError->getMessage() . 
                          ". Response was: " . substr($responseText, 0, 200);
            
            pg_query_params($conn, $updateQuery, [$failureNotes, $messageId]);
            pg_close($conn);
            
            throw new Exception('Email delivery failed: ' . $emailError->getMessage() . 
                              '. The message has been marked as read but no email was sent. ' .
                              'Please contact the customer directly at: ' . $to);
        }

    } else {
        throw new Exception('Method not allowed');
    }

} catch (Exception $e) {
    error_log("Admin messages error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function buildAdminResponseEmail($customerName, $responseText, $originalMessage) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f5f5f5;
            }
            .email-container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: #ffffff;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                border-radius: 8px;
                overflow: hidden;
            }
            .header { 
                background: linear-gradient(135deg, #18D259 0%, #3fcb70 100%); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .logo { 
                font-size: 32px; 
                font-weight: bold; 
                margin-bottom: 10px; 
                text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            .content { 
                padding: 30px 25px; 
                background: #ffffff;
            }
            .response-box { 
                background: linear-gradient(135deg, #e6f9ed 0%, #f0fcf4 100%); 
                padding: 25px; 
                border-radius: 12px; 
                margin: 25px 0; 
                border-left: 5px solid #18D259;
                box-shadow: 0 2px 8px rgba(24, 210, 89, 0.1);
            }
            .original-message { 
                background: #f8f9fa; 
                padding: 20px; 
                border-left: 4px solid #dee2e6; 
                margin: 25px 0; 
                border-radius: 8px;
                border: 1px solid #e9ecef;
            }
            .footer { 
                background: #f8f9fa; 
                padding: 25px; 
                text-align: center; 
                font-size: 14px; 
                color: #6c757d;
                border-top: 1px solid #e9ecef;
            }
            .highlight { 
                color: #18D259; 
                font-weight: 600; 
            }
            .greeting {
                font-size: 18px;
                margin-bottom: 20px;
            }
            .signature {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #e9ecef;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <div class='logo'>💪 FitGen</div>
                <h2 style='margin: 0; font-weight: 300;'>Support Team Response</h2>
            </div>
            <div class='content'>
                <div class='greeting'>
                    Dear <strong class='highlight'>" . htmlspecialchars($customerName) . "</strong>,
                </div>
                
                <p>Thank you for reaching out to FitGen support! Our team has carefully reviewed your message and we're here to help you on your fitness journey.</p>
                
                <div class='response-box'>
                    <h3 style='color: #18D259; margin-top: 0; font-size: 20px;'>
                        💬 Our Response:
                    </h3>
                    <div style='font-size: 16px; line-height: 1.7;'>
                        " . nl2br(htmlspecialchars($responseText)) . "
                    </div>
                </div>
                
                <div class='original-message'>
                    <h4 style='margin-top: 0; color: #495057;'>
                        📝 Your original message:
                    </h4>
                    <div style='font-style: italic; color: #6c757d;'>
                        " . nl2br(htmlspecialchars($originalMessage)) . "
                    </div>
                </div>
                
                <p>We hope this response helps you achieve your fitness goals! If you have any follow-up questions or need additional assistance, please don't hesitate to contact us again.</p>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                    <strong>💡 Pro Tip:</strong> Save our email address <span class='highlight'>support@fitgen.com</span> to your contacts for faster support in the future!
                </div>
                
                <div class='signature'>
                    <p><strong>Stay strong and keep pushing! 🚀</strong></p>
                    <p style='margin: 0;'>
                        <strong>The FitGen Support Team</strong><br>
                        <em>Your partners in fitness success</em>
                    </p>
                </div>
            </div>
            <div class='footer'>
                <p><strong>© 2025 FitGen. All rights reserved.</strong></p>
                <p style='margin: 10px 0;'>
                    📧 <a href='mailto:support@fitgen.com' style='color: #18D259;'>support@fitgen.com</a> | 
                    🌐 <a href='http://www.fitgen.com' style='color: #18D259;'>www.fitgen.com</a>
                </p>
                <p style='font-size: 12px; color: #adb5bd; margin: 15px 0 0 0;'>
                    This email was sent in response to your inquiry. Please add us to your contacts to ensure delivery.
                </p>
            </div>
        </div>
    </body>
    </html>";
}
?>