<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../SDKs-main/php/Fapshi.php';

// Allow CORS for payment callbacks
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? ($_GET['action'] ?? null);

// Response helper function
function sendResponse($success, $message, $data = null) {
    http_response_code($success ? 200 : 400);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

try {
    // Initialize Fapshi SDK
    $fapshi = new Fapshi();

    switch ($action) {
        case 'initiate':
            // Require authentication
            requireUserAuth();
            
            $phoneNumber = $input['phone_number'] ?? null;
            $amount = intval($input['amount'] ?? 0);
            $paymentMethod = $input['payment_method'] ?? null;
            $accountReference = $input['account_reference'] ?? null;

            if (!$phoneNumber || $amount <= 0 || !$paymentMethod) {
                sendResponse(false, 'Missing required fields');
            }

            // Normalize phone number for Fapshi (expects 6xxxxxxxx format for XAF in Cameroon)
            // For Uganda (typically starting with 256), we need to adjust format
            $phone = str_replace(['+', ' ', '-'], '', $phoneNumber);
            if (strpos($phone, '256') === 0) {
                $phone = substr($phone, 3); // Remove country code
            }

            // Convert to Cameroon Fapshi format (if using same provider for Cameroon)
            // For different providers, you may need separate handling
            if (strlen($phone) === 9 && $phone[0] === '7') {
                $phone = '6' . $phone; // Convert to XAF format
            }

            // Log payment initiation
            $reference = 'FLX-' . time() . '-' . rand(1000, 9999);
            
            // Create payment record
            $conn = getUserDBConnection();
            $stmt = $conn->prepare(
                "INSERT INTO payments (household_id, amount, status, payment_method, reference, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $status = 'pending';
            $stmt->bind_param('iisss', $_SESSION['household_id'], $amount, $status, $paymentMethod, $reference);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create payment record');
            }

            $paymentId = $conn->insert_id;
            $stmt->close();

            // Prepare Fapshi payment data
            $paymentData = [
                'amount' => $amount,
                'phone' => $phone,
                'externalTransId' => $reference,
                'narration' => 'Flexnet subscription payment',
                'userId' => (string)$_SESSION['user_id']
            ];

            // Call Fapshi API based on payment method
            $fapshiResponse = [];
            
            if ($paymentMethod === 'mtn' || $paymentMethod === 'airtel') {
                // Use direct_pay for mobile money
                $fapshiResponse = $fapshi->direct_pay($paymentData);
            } else if ($paymentMethod === 'card') {
                // Use initiate_pay for card payments
                $fapshiResponse = $fapshi->initiate_pay($paymentData);
            }

            // Check if Fapshi response indicates success
            if ($fapshiResponse && isset($fapshiResponse['statusCode']) && $fapshiResponse['statusCode'] == 200) {
                // Store Fapshi transaction ID
                $fapshiTransId = $fapshiResponse['transId'] ?? $fapshiResponse['id'] ?? null;
                
                if ($fapshiTransId) {
                    $updateStmt = $conn->prepare(
                        "UPDATE payments SET fapshi_trans_id = ? WHERE id = ?"
                    );
                    $updateStmt->bind_param('si', $fapshiTransId, $paymentId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }

                sendResponse(true, 'Payment initiated successfully', [
                    'reference' => $reference,
                    'transId' => $fapshiTransId,
                    'amount' => $amount
                ]);
            } else {
                // Fapshi error
                $errorMsg = $fapshiResponse['message'] ?? 'Payment initiation failed';
                
                // Update payment status to failed
                $failStmt = $conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
                $failStatus = 'failed';
                $failStmt->bind_param('si', $failStatus, $paymentId);
                $failStmt->execute();
                $failStmt->close();

                sendResponse(false, $errorMsg, $fapshiResponse);
            }
            break;

        case 'status':
            // Check payment status
            requireUserAuth();
            
            $reference = $input['reference'] ?? null;
            if (!$reference) {
                sendResponse(false, 'Reference required');
            }

            // Get payment from database
            $conn = getUserDBConnection();
            $stmt = $conn->prepare(
                "SELECT id, amount, status, fapshi_trans_id FROM payments 
                 WHERE reference = ? AND household_id = ?"
            );
            $stmt->bind_param('si', $reference, $_SESSION['household_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $payment = $result->fetch_assoc();
            $stmt->close();

            if (!$payment) {
                sendResponse(false, 'Payment not found');
            }

            // Check status with Fapshi if we have transaction ID
            $status = $payment['status'];
            if ($payment['fapshi_trans_id']) {
                $fapshiStatus = $fapshi->payment_status($payment['fapshi_trans_id']);
                
                if ($fapshiStatus && isset($fapshiStatus['statusCode']) && $fapshiStatus['statusCode'] == 200) {
                    $fapshiPaymentStatus = $fapshiStatus['paymentStatus'] ?? 'unknown';
                    
                    // Update payment status if it has changed
                    if ($fapshiPaymentStatus === 'success' && $status !== 'completed') {
                        $newStatus = 'completed';
                        $updateStmt = $conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
                        $updateStmt->bind_param('si', $newStatus, $payment['id']);
                        $updateStmt->execute();
                        $updateStmt->close();
                        $status = 'completed';
                    } else if ($fapshiPaymentStatus === 'failed' && $status !== 'failed') {
                        $newStatus = 'failed';
                        $updateStmt = $conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
                        $updateStmt->bind_param('si', $newStatus, $payment['id']);
                        $updateStmt->execute();
                        $updateStmt->close();
                        $status = 'failed';
                    }
                }
            }

            sendResponse(true, 'Status retrieved', [
                'reference' => $reference,
                'status' => $status,
                'amount' => $payment['amount']
            ]);
            break;

        case 'webhook':
            // Webhook handler for Fapshi callbacks
            // Verify webhook signature (implement based on Fapshi documentation)
            
            $transId = $input['transId'] ?? null;
            $paymentStatus = $input['paymentStatus'] ?? null;
            $externalTransId = $input['externalTransId'] ?? null;

            if (!$transId || !$paymentStatus || !$externalTransId) {
                sendResponse(false, 'Invalid webhook data');
            }

            // Find payment by reference
            $conn = getUserDBConnection();
            $stmt = $conn->prepare(
                "SELECT id FROM payments WHERE reference = ? LIMIT 1"
            );
            $stmt->bind_param('s', $externalTransId);
            $stmt->execute();
            $result = $stmt->get_result();
            $payment = $result->fetch_assoc();
            $stmt->close();

            if (!$payment) {
                // Payment not found, log it
                error_log('Webhook: Payment not found for reference: ' . $externalTransId);
                sendResponse(false, 'Payment not found', ['reference' => $externalTransId]);
            }

            // Update payment status based on Fapshi response
            $newStatus = ($paymentStatus === 'success') ? 'completed' : 'failed';
            $updateStmt = $conn->prepare(
                "UPDATE payments SET status = ?, fapshi_trans_id = ?, updated_at = NOW() WHERE id = ?"
            );
            $updateStmt->bind_param('ssi', $newStatus, $transId, $payment['id']);
            
            if ($updateStmt->execute()) {
                // Log successful webhook
                error_log('Webhook: Payment ' . $externalTransId . ' updated to ' . $newStatus);
                
                // TODO: Send notification to user about payment status
                
                sendResponse(true, 'Webhook processed successfully');
            } else {
                sendResponse(false, 'Failed to update payment status');
            }
            $updateStmt->close();
            break;

        case 'balance':
            // Check Fapshi account balance (admin only - requires special permission)
            // For now, require authentication
            requireUserAuth();
            
            $balanceResponse = $fapshi->balance();
            
            if ($balanceResponse && isset($balanceResponse['statusCode']) && $balanceResponse['statusCode'] == 200) {
                sendResponse(true, 'Balance retrieved', $balanceResponse);
            } else {
                sendResponse(false, 'Failed to retrieve balance', $balanceResponse);
            }
            break;

        case 'history':
            // Get user's payment history
            requireUserAuth();
            
            $conn = getUserDBConnection();
            $limit = intval($input['limit'] ?? 20);
            
            $stmt = $conn->prepare(
                "SELECT id, amount, status, payment_method, reference, created_at 
                 FROM payments 
                 WHERE household_id = ? 
                 ORDER BY created_at DESC 
                 LIMIT ?"
            );
            $stmt->bind_param('ii', $_SESSION['household_id'], $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $payments = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            sendResponse(true, 'Payment history retrieved', $payments);
            break;

        default:
            sendResponse(false, 'Unknown action: ' . $action);
    }

} catch (Exception $e) {
    error_log('Payment API Error: ' . $e->getMessage());
    sendResponse(false, 'An error occurred: ' . $e->getMessage());
}
?>
