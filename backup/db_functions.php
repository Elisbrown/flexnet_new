<?php
/**
 * Database Utility Functions
 * Provides helper functions for common database operations
 */

if (defined('DB_FUNCTIONS_INCLUDED')) {
    return;
}
define('DB_FUNCTIONS_INCLUDED', true);

require_once __DIR__ . '/db_connection.php';

/**
 * Execute a prepared statement
 * @param string $query SQL query with ? placeholders
 * @param array $params Parameters to bind
 * @param string $types Parameter types (i=int, s=string, d=double, b=blob)
 * @return mysqli_result|false
 */
function executeQuery($query, $params = [], $types = '') {
    $conn = getDBConnection();
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Query preparation failed: " . $conn->error);
        return false;
    }
    
    if (!empty($params) && !empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        return false;
    }
    
    return $stmt->get_result();
}

/**
 * Get a single row from database
 * @param string $query SQL query
 * @param array $params Parameters to bind
 * @param string $types Parameter types
 * @return array|null
 */
function fetchOne($query, $params = [], $types = '') {
    $result = executeQuery($query, $params, $types);
    
    if ($result && $row = $result->fetch_assoc()) {
        return $row;
    }
    
    return null;
}

/**
 * Get all rows from database
 * @param string $query SQL query
 * @param array $params Parameters to bind
 * @param string $types Parameter types
 * @return array
 */
function fetchAll($query, $params = [], $types = '') {
    $result = executeQuery($query, $params, $types);
    $rows = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    
    return $rows;
}

/**
 * Insert a record into database
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @return int|false Last insert ID or false on failure
 */
function insert($table, $data) {
    $conn = getDBConnection();
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Insert preparation failed: " . $conn->error);
        return false;
    }
    
    // Build types string
    $types = '';
    $values = [];
    foreach ($data as $value) {
        $values[] = $value;
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    
    $stmt->bind_param($types, ...$values);
    
    if (!$stmt->execute()) {
        error_log("Insert execution failed: " . $stmt->error);
        return false;
    }
    
    return $conn->insert_id;
}

/**
 * Update records in database
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @param array $where Associative array of where conditions
 * @return int|false Number of affected rows or false on failure
 */
function update($table, $data, $where) {
    $conn = getDBConnection();
    $setClause = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
    $whereClause = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));
    $query = "UPDATE $table SET $setClause WHERE $whereClause";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Update preparation failed: " . $conn->error);
        return false;
    }
    
    // Build types and values
    $types = '';
    $values = [];
    
    foreach ($data as $value) {
        $values[] = $value;
        $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
    }
    
    foreach ($where as $value) {
        $values[] = $value;
        $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
    }
    
    $stmt->bind_param($types, ...$values);
    
    if (!$stmt->execute()) {
        error_log("Update execution failed: " . $stmt->error);
        return false;
    }
    
    return $stmt->affected_rows;
}

/**
 * Delete records from database
 * @param string $table Table name
 * @param array $where Associative array of where conditions
 * @return int|false Number of affected rows or false on failure
 */
function delete($table, $where) {
    $conn = getDBConnection();
    $whereClause = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));
    $query = "DELETE FROM $table WHERE $whereClause";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Delete preparation failed: " . $conn->error);
        return false;
    }
    
    // Build types and values
    $types = '';
    $values = [];
    
    foreach ($where as $value) {
        $values[] = $value;
        $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
    }
    
    $stmt->bind_param($types, ...$values);
    
    if (!$stmt->execute()) {
        error_log("Delete execution failed: " . $stmt->error);
        return false;
    }
    
    return $stmt->affected_rows;
}

/**
 * Count records in table
 * @param string $table Table name
 * @param array $where Optional where conditions
 * @return int
 */
function countRecords($table, $where = []) {
    $query = "SELECT COUNT(*) as count FROM $table";
    $params = [];
    $types = '';
    
    if (!empty($where)) {
        $whereClause = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));
        $query .= " WHERE $whereClause";
        
        foreach ($where as $value) {
            $params[] = $value;
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }
    }
    
    $result = executeQuery($query, $params, $types);
    
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }
    
    return 0;
}

// ============================================================================
// ENTITY-SPECIFIC CRUD HELPERS
// ============================================================================

/**
 * Search locations by name or code
 * @param string $search Search term
 * @return array
 */
function searchLocations($search) {
    $term = "%$search%";
    return fetchAll(
        "SELECT l.*, COUNT(h.id) as household_count 
         FROM locations l 
         LEFT JOIN households h ON l.id = h.location_id 
         WHERE l.name LIKE ? OR l.code LIKE ?
         GROUP BY l.id 
         ORDER BY l.name ASC",
        [$term, $term],
        'ss'
    );
}

/**
 * Filter locations by active status
 * @param int $is_active 0 or 1
 * @return array
 */
function filterLocationsByStatus($is_active) {
    return fetchAll(
        "SELECT l.*, COUNT(h.id) as household_count 
         FROM locations l 
         LEFT JOIN households h ON l.id = h.location_id 
         WHERE l.is_active = ?
         GROUP BY l.id 
         ORDER BY l.created_at DESC",
        [$is_active],
        'i'
    );
}

/**
 * Get location by ID
 * @param int $id
 * @return array|null
 */
function getLocationById($id) {
    return fetchOne(
        "SELECT l.*, COUNT(h.id) as household_count 
         FROM locations l 
         LEFT JOIN households h ON l.id = h.location_id 
         WHERE l.id = ?
         GROUP BY l.id",
        [$id],
        'i'
    );
}

/**
 * Create a new location
 * @param array $data {name, code, address_line1, address_line2, city, region, is_active}
 * @return int|false Location ID or false
 */
function createLocation($data) {
    $required = ['name', 'code'];
    foreach ($required as $field) {
        if (empty($data[$field])) return false;
    }
    
    return insert('locations', [
        'name' => $data['name'],
        'code' => $data['code'],
        'address_line1' => $data['address_line1'] ?? null,
        'address_line2' => $data['address_line2'] ?? null,
        'city' => $data['city'] ?? null,
        'region' => $data['region'] ?? null,
        'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1
    ]);
}

/**
 * Update an existing location
 * @param int $id
 * @param array $data
 * @return int|false Affected rows or false
 */
function updateLocation($id, $data) {
    $allowed_fields = ['name', 'code', 'address_line1', 'address_line2', 'city', 'region', 'is_active'];
    $update_data = [];
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_data[$field] = $data[$field];
        }
    }
    
    if (empty($update_data)) return false;
    
    return update('locations', $update_data, ['id' => $id]);
}

/**
 * Delete a location
 * @param int $id
 * @return int|false Affected rows or false
 */
function deleteLocation($id) {
    return delete('locations', ['id' => $id]);
}

/**
 * Search admins by name or email
 * @param string $search
 * @return array
 */
function searchAdmins($search) {
    $term = "%$search%";
    return fetchAll(
        "SELECT a.id, a.full_name, a.email, a.is_active, a.last_login_at, a.created_at
         FROM admins a 
         WHERE a.full_name LIKE ? OR a.email LIKE ?
         ORDER BY a.full_name ASC",
        [$term, $term],
        'ss'
    );
}

/**
 * Filter admins by active status
 * @param int $is_active
 * @return array
 */
function filterAdminsByStatus($is_active) {
    return fetchAll(
        "SELECT a.id, a.full_name, a.email, a.is_active, a.last_login_at, a.created_at
         FROM admins a 
         WHERE a.is_active = ?
         ORDER BY a.created_at DESC",
        [$is_active],
        'i'
    );
}

/**
 * Get admin by ID
 * @param int $id
 * @return array|null
 */
function getAdminById($id) {
    return fetchOne(
        "SELECT id, full_name, email, is_active, last_login_at, created_at FROM admins WHERE id = ?",
        [$id],
        'i'
    );
}

/**
 * Create a new admin
 * @param array $data {full_name, email, password, roles}
 * @return int|false Admin ID or false
 */
function createAdmin($data) {
    $required = ['full_name', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) return false;
    }
    
    $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
    
    $id = insert('admins', [
        'full_name' => $data['full_name'],
        'email' => $data['email'],
        'password_hash' => $password_hash,
        'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1
    ]);
    
    // Assign roles if provided
    if ($id && isset($data['roles']) && is_array($data['roles'])) {
        foreach ($data['roles'] as $role_id) {
            insert('admin_roles', ['admin_id' => $id, 'role_id' => $role_id]);
        }
    }
    
    return $id;
}

/**
 * Update an existing admin
 * @param int $id
 * @param array $data
 * @return int|false Affected rows or false
 */
function updateAdmin($id, $data) {
    $allowed_fields = ['full_name', 'email', 'is_active'];
    $update_data = [];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_data[$field] = $data[$field];
        }
    }
    
    // Handle password update separately
    if (!empty($data['password'])) {
        $update_data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
    }
    
    if (empty($update_data)) return false;
    
    return update('admins', $update_data, ['id' => $id]);
}

/**
 * Delete an admin (including their role assignments)
 * @param int $id
 * @return int|false Affected rows or false
 */
function deleteAdmin($id) {
    // Delete role assignments first
    delete('admin_roles', ['admin_id' => $id]);
    // Then delete admin
    return delete('admins', ['id' => $id]);
}

/**
 * Assign role to admin
 * @param int $admin_id
 * @param int $role_id
 * @return int|false
 */
function assignRoleToAdmin($admin_id, $role_id) {
    // Check if already assigned
    $exists = fetchOne(
        "SELECT 1 FROM admin_roles WHERE admin_id = ? AND role_id = ?",
        [$admin_id, $role_id],
        'ii'
    );
    
    if ($exists) return false;
    
    return insert('admin_roles', ['admin_id' => $admin_id, 'role_id' => $role_id]);
}

/**
 * Remove role from admin
 * @param int $admin_id
 * @param int $role_id
 * @return int|false
 */
function removeRoleFromAdmin($admin_id, $role_id) {
    return delete('admin_roles', ['admin_id' => $admin_id, 'role_id' => $role_id]);
}

/**
 * Get all roles
 * @return array
 */
function getAllRoles() {
    return fetchAll("SELECT * FROM roles ORDER BY name ASC", [], '');
}

/**
 * Search FAQs by question text
 * @param string $search
 * @return array
 */
function searchFaqs($search) {
    $term = "%$search%";
    return fetchAll(
        "SELECT * FROM faqs 
         WHERE question_en LIKE ? OR question_fr LIKE ? OR answer_en LIKE ? OR answer_fr LIKE ?
         ORDER BY sort_order ASC, created_at DESC",
        [$term, $term, $term, $term],
        'ssss'
    );
}

/**
 * Filter FAQs by publish status
 * @param int $is_published
 * @return array
 */
function filterFaqsByStatus($is_published) {
    return fetchAll(
        "SELECT * FROM faqs 
         WHERE is_published = ?
         ORDER BY sort_order ASC, created_at DESC",
        [$is_published],
        'i'
    );
}

/**
 * Get FAQ by ID
 * @param int $id
 * @return array|null
 */
function getFaqById($id) {
    return fetchOne("SELECT * FROM faqs WHERE id = ?", [$id], 'i');
}

/**
 * Create a new FAQ
 * @param array $data {slug, question_en, answer_en, question_fr, answer_fr, is_published, sort_order}
 * @return int|false FAQ ID or false
 */
function createFaq($data) {
    $required = ['question_en', 'answer_en'];
    foreach ($required as $field) {
        if (empty($data[$field])) return false;
    }
    
    // Generate slug if not provided
    $slug = $data['slug'] ?? strtolower(preg_replace('/[^a-z0-9-]+/', '-', trim($data['question_en'])));
    
    return insert('faqs', [
        'slug' => $slug,
        'question_en' => $data['question_en'],
        'answer_en' => $data['answer_en'],
        'question_fr' => $data['question_fr'] ?? null,
        'answer_fr' => $data['answer_fr'] ?? null,
        'is_published' => isset($data['is_published']) ? (int)$data['is_published'] : 1,
        'sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0
    ]);
}

/**
 * Update an existing FAQ
 * @param int $id
 * @param array $data
 * @return int|false Affected rows or false
 */
function updateFaq($id, $data) {
    $allowed_fields = ['question_en', 'answer_en', 'question_fr', 'answer_fr', 'is_published', 'sort_order'];
    $update_data = [];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_data[$field] = $data[$field];
        }
    }
    
    if (empty($update_data)) return false;
    
    return update('faqs', $update_data, ['id' => $id]);
}

/**
 * Delete a FAQ
 * @param int $id
 * @return int|false Affected rows or false
 */
function deleteFaq($id) {
    return delete('faqs', ['id' => $id]);
}

/**
 * Search support tickets by customer name or subject
 * @param string $search
 * @return array
 */
function searchTickets($search) {
    $term = "%$search%";
    return fetchAll(
        "SELECT * FROM support_tickets 
         WHERE customer_name LIKE ? OR subject LIKE ? OR LOWER(status) LIKE LOWER(?)
         ORDER BY created_at DESC",
        [$term, $term, $term],
        'sss'
    );
}

/**
 * Filter tickets by status
 * @param string $status
 * @return array
 */
function filterTicketsByStatus($status) {
    return fetchAll(
        "SELECT * FROM support_tickets 
         WHERE status = ?
         ORDER BY created_at DESC",
        [$status],
        's'
    );
}

/**
 * Get ticket by ID
 * @param int $id
 * @return array|null
 */
function getTicketById($id) {
    return fetchOne("SELECT * FROM support_tickets WHERE id = ?", [$id], 'i');
}

/**
 * Update support ticket status
 * @param int $id
 * @param string $status (open, in_progress, closed, resolved)
 * @return int|false Affected rows or false
 */
function updateTicketStatus($id, $status) {
    $valid_statuses = ['open', 'in_progress', 'closed', 'resolved'];
    if (!in_array($status, $valid_statuses)) return false;
    
    return update('support_tickets', ['status' => $status], ['id' => $id]);
}

/**
 * Search payments by reference or subscriber
 * @param string $search
 * @return array
 */
function searchPayments($search) {
    $term = "%$search%";
    return fetchAll(
        "SELECT p.*, h.primary_full_name, h.apartment_label, l.name as location_name
         FROM payments p
         JOIN households h ON p.household_id = h.id
         JOIN locations l ON h.location_id = l.id
         WHERE p.external_reference LIKE ? OR h.primary_full_name LIKE ?
         ORDER BY p.created_at DESC
         LIMIT 100",
        [$term, $term],
        'ss'
    );
}

/**
 * Filter payments by status and optional date range
 * @param string $status (SUCCESS, PENDING, FAILED)
 * @param string|null $start_date (Y-m-d)
 * @param string|null $end_date (Y-m-d)
 * @return array
 */
function filterPaymentsByStatus($status, $start_date = null, $end_date = null) {
    $query = "SELECT p.*, h.primary_full_name, h.apartment_label, l.name as location_name
              FROM payments p
              JOIN households h ON p.household_id = h.id
              JOIN locations l ON h.location_id = l.id
              WHERE p.status = ?";
    
    $params = [$status];
    $types = 's';
    
    if ($start_date) {
        $query .= " AND DATE(p.created_at) >= ?";
        $params[] = $start_date;
        $types .= 's';
    }
    
    if ($end_date) {
        $query .= " AND DATE(p.created_at) <= ?";
        $params[] = $end_date;
        $types .= 's';
    }
    
    $query .= " ORDER BY p.created_at DESC LIMIT 100";
    
    return fetchAll($query, $params, $types);
}

/**
 * Get all households for a location
 * @param int $location_id
 * @return array
 */
function getHouseholdsByLocation($location_id) {
    return fetchAll(
        "SELECT * FROM households WHERE location_id = ? ORDER BY apartment_label ASC",
        [$location_id],
        'i'
    );
}

/**
 * Get a single household by ID
 * @param int $household_id
 * @return array|null
 */
function getHouseholdById($household_id) {
    return fetchOne(
        "SELECT * FROM households WHERE id = ?",
        [$household_id],
        'i'
    );
}

/**
 * Search households by apartment label or subscriber name
 * @param string $search Search term
 * @param int $location_id Optional location filter
 * @return array
 */
function searchHouseholds($search, $location_id = null) {
    $query = "SELECT * FROM households WHERE (apartment_label LIKE ? OR primary_full_name LIKE ?)";
    $params = ["%$search%", "%$search%"];
    $types = 'ss';
    
    if ($location_id) {
        $query .= " AND location_id = ?";
        $params[] = $location_id;
        $types .= 'i';
    }
    
    $query .= " ORDER BY apartment_label ASC";
    return fetchAll($query, $params, $types);
}

/**
 * Filter households by subscription status
 * @param string $status (active, pending, expired, paused)
 * @param int $location_id Optional location filter
 * @return array
 */
function filterHouseholdsByStatus($status, $location_id = null) {
    $query = "SELECT * FROM households WHERE subscription_status = ?";
    $params = [$status];
    $types = 's';
    
    if ($location_id) {
        $query .= " AND location_id = ?";
        $params[] = $location_id;
        $types .= 'i';
    }
    
    $query .= " ORDER BY apartment_label ASC";
    return fetchAll($query, $params, $types);
}

/**
 * Create a new household
 * @param array $data (location_id, apartment_label, primary_full_name, primary_phone_number, primary_email, preferred_language, notes, subscription_status)
 * @return int|false Household ID or false on error
 */
function createHousehold($data) {
    $query = "INSERT INTO households 
              (location_id, apartment_label, primary_full_name, primary_phone_number, primary_email, preferred_language, notes, subscription_status, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $params = [
        $data['location_id'] ?? null,
        $data['apartment_label'] ?? null,
        $data['primary_full_name'] ?? null,
        $data['primary_phone_number'] ?? null,
        $data['primary_email'] ?? '',
        $data['preferred_language'] ?? 'en',
        $data['notes'] ?? '',
        $data['subscription_status'] ?? 'pending'
    ];
    
    $types = 'isssssss';
    
    return executeInsert($query, $params, $types);
}

/**
 * Update an existing household
 * @param int $household_id
 * @param array $data Fields to update
 * @return int|false Number of affected rows or false on error
 */
function updateHousehold($household_id, $data) {
    $conn = getDBConnection();
    $updates = [];
    $params = [];
    $types = '';
    
    $allowed_fields = ['apartment_label', 'primary_full_name', 'primary_phone_number', 'primary_email', 'preferred_language', 'notes', 'subscription_status'];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
            $types .= (in_array($field, ['primary_phone_number']) ? 's' : 's');
        }
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $params[] = $household_id;
    $types .= 'i';
    
    $query = "UPDATE households SET " . implode(", ", $updates) . ", updated_at = NOW() WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Update preparation failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        error_log("Update execution failed: " . $stmt->error);
        return false;
    }
    
    return $stmt->affected_rows;
}

/**
 * Delete a household
 * @param int $household_id
 * @return int|false Number of affected rows or false on error
 */
function deleteHousehold($household_id) {
    $conn = getDBConnection();
    $query = "DELETE FROM households WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Delete preparation failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param('i', $household_id);
    
    if (!$stmt->execute()) {
        error_log("Delete execution failed: " . $stmt->error);
        return false;
    }
    
    return $stmt->affected_rows;
}

/**
 * Reset household PIN (user account)
 * @param int $household_id
 * @return int|false Number of affected rows or false on error
 */
function resetHouseholdPin($household_id) {
    $default_pin_hash = password_hash('1234', PASSWORD_BCRYPT);
    
    $conn = getDBConnection();
    $query = "UPDATE users SET pin_code = ?, has_changed_default_pin = 0, updated_at = NOW() WHERE household_id = ? AND role = 'HOUSEHOLD'";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("PIN reset preparation failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param('si', $default_pin_hash, $household_id);
    
    if (!$stmt->execute()) {
        error_log("PIN reset execution failed: " . $stmt->error);
        return false;
    }
    
    return $stmt->affected_rows;
}

/**
 * Get user account for a household (if exists)
 * @param int $household_id
 * @return array|null
 */
function getHouseholdUser($household_id) {
    return fetchOne(
        "SELECT id, email, has_changed_default_pin FROM users WHERE household_id = ? AND role = 'HOUSEHOLD'",
        [$household_id],
        'i'
    );
}

?>
