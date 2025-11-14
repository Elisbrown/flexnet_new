<?php
/**
 * User App Database Helper Functions
 * Provides database access for user-related queries
 */

require_once __DIR__ . '/config.php';

/**
 * Get database connection
 * @return mysqli
 */
function getUserDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed']));
        }
        
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

/**
 * Get user by phone number
 * @param string $phone
 * @return array|null
 */
function getUserByPhone($phone) {
    $conn = getUserDBConnection();
    
    $stmt = $conn->prepare(
        "SELECT id, phone_number as phone, pin, full_name as name, 
                household_id, avatar_url, has_changed_default_pin
         FROM users WHERE phone_number = ? LIMIT 1"
    );
    
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}

/**
 * Verify user PIN
 * @param int $user_id
 * @param string $pin
 * @return bool
 */
function verifyUserPin($user_id, $pin) {
    $conn = getUserDBConnection();
    
    $stmt = $conn->prepare("SELECT pin FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $user = $result->fetch_assoc();
    return $user['pin'] === $pin;
}

/**
 * Update user PIN
 * @param int $user_id
 * @param string $new_pin
 * @return bool
 */
function updateUserPin($user_id, $new_pin) {
    $conn = getUserDBConnection();
    
    $stmt = $conn->prepare(
        "UPDATE users SET pin = ?, has_changed_default_pin = 1 
         WHERE id = ?"
    );
    
    $stmt->bind_param('si', $new_pin, $user_id);
    $result = $stmt->execute();
    
    return $result && $stmt->affected_rows > 0;
}

/**
 * Get user profile by ID
 * @param int $user_id
 * @return array|null
 */
function getUserProfile($user_id) {
    $conn = getUserDBConnection();
    
    $stmt = $conn->prepare(
        "SELECT id, phone_number as phone, full_name as name, 
                email, avatar_url, household_id, created_at
         FROM users WHERE id = ? LIMIT 1"
    );
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}

/**
 * Get household info
 * @param int $household_id
 * @return array|null
 */
function getHouseholdInfo($household_id) {
    $conn = getUserDBConnection();
    
    $stmt = $conn->prepare(
        "SELECT id, apartment_label, location_id, primary_full_name, 
                primary_phone_number, primary_email, status
         FROM households WHERE id = ? LIMIT 1"
    );
    
    $stmt->bind_param('i', $household_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}

/**
 * Get active subscription
 * @param int $household_id
 * @return array|null
 */
function getActiveSubscription($household_id) {
    $conn = getUserDBConnection();
    
    $stmt = $conn->prepare(
        "SELECT id, household_id, subscription_status, subscription_start_date, 
                subscription_end_date, payment_channel, subscription_type
         FROM subscriptions 
         WHERE household_id = ? AND subscription_status = 'ACTIVE'
         ORDER BY subscription_end_date DESC LIMIT 1"
    );
    
    $stmt->bind_param('i', $household_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}

/**
 * Get all subscriptions for household
 * @param int $household_id
 * @return array
 */
function getHouseholdSubscriptions($household_id) {
    $conn = getUserDBConnection();
    
    $stmt = $conn->prepare(
        "SELECT id, subscription_status, subscription_start_date, 
                subscription_end_date, payment_channel, subscription_type
         FROM subscriptions 
         WHERE household_id = ?
         ORDER BY subscription_start_date DESC"
    );
    
    $stmt->bind_param('i', $household_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $subscriptions = [];
    while ($row = $result->fetch_assoc()) {
        $subscriptions[] = $row;
    }
    
    return $subscriptions;
}

/**
 * Get payment history
 * @param int $household_id
 * @param int $limit
 * @return array
 */
function getPaymentHistory($household_id, $limit = 20) {
    $conn = getUserDBConnection();
    
    $stmt = $conn->prepare(
        "SELECT id, household_id, amount, payment_channel, status, 
                reference, created_at, updated_at
         FROM payments 
         WHERE household_id = ?
         ORDER BY created_at DESC
         LIMIT ?"
    );
    
    $stmt->bind_param('ii', $household_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    return $payments;
}

/**
 * Get recent payments (last 30 days)
 * @param int $household_id
 * @return array
 */
function getRecentPayments($household_id) {
    $conn = getUserDBConnection();
    
    $stmt = $conn->prepare(
        "SELECT id, household_id, amount, payment_channel, status, 
                created_at, updated_at
         FROM payments 
         WHERE household_id = ? 
         AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         ORDER BY created_at DESC"
    );
    
    $stmt->bind_param('i', $household_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    return $payments;
}

/**
 * Get support tickets for user
 * @param int $household_id
 * @param int $limit
 * @return array
 */
function getSupportTickets($household_id, $limit = 10) {
    $conn = getUserDBConnection();
    
    $stmt = $conn->prepare(
        "SELECT id, household_id, subject, status, created_at, updated_at
         FROM support_tickets 
         WHERE household_id = ?
         ORDER BY updated_at DESC
         LIMIT ?"
    );
    
    $stmt->bind_param('ii', $household_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    
    return $tickets;
}

/**
 * Create payment record
 * @param int $household_id
 * @param int $amount
 * @param string $channel
 * @param string $reference
 * @param string $status
 * @return bool
 */
function createPaymentRecord($household_id, $amount, $channel, $reference, $status = 'PENDING') {
    $conn = getUserDBConnection();
    
    $stmt = $conn->prepare(
        "INSERT INTO payments (household_id, amount, payment_channel, reference, status, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    
    $stmt->bind_param('iisss', $household_id, $amount, $channel, $reference, $status);
    $result = $stmt->execute();
    
    return $result && $stmt->affected_rows > 0;
}

/**
 * Update payment status
 * @param int $payment_id
 * @param string $status
 * @return bool
 */
function updatePaymentStatus($payment_id, $status) {
    $conn = getUserDBConnection();
    
    $stmt = $conn->prepare(
        "UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?"
    );
    
    $stmt->bind_param('si', $status, $payment_id);
    $result = $stmt->execute();
    
    return $result && $stmt->affected_rows > 0;
}

/**
 * Get payment by reference
 * @param string $reference
 * @return array|null
 */
function getPaymentByReference($reference) {
    $conn = getUserDBConnection();
    
    $stmt = $conn->prepare(
        "SELECT id, household_id, amount, status, payment_channel, reference
         FROM payments WHERE reference = ? LIMIT 1"
    );
    
    $stmt->bind_param('s', $reference);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}

?>
