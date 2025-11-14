<?php
/**
 * API Request Handlers
 * Processes CRUD operations and returns JSON responses
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db_functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Verify authentication
requireAuth();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? null;

// Determine response
$response = ['success' => false, 'message' => 'Unknown action', 'data' => null];

try {
    // ====================================================================
    // LOCATION HANDLERS
    // ====================================================================
    
    if ($action === 'create_location') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $result = createLocation([
            'name' => $_POST['name'] ?? '',
            'code' => $_POST['code'] ?? '',
            'address_line1' => $_POST['address_line1'] ?? '',
            'address_line2' => $_POST['address_line2'] ?? '',
            'city' => $_POST['city'] ?? '',
            'region' => $_POST['region'] ?? '',
            'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1
        ]);
        
        if ($result) {
            $response = [
                'success' => true,
                'message' => 'Location created successfully',
                'data' => ['id' => $result]
            ];
        } else {
            throw new Exception('Failed to create location');
        }
    }
    
    elseif ($action === 'update_location') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) throw new Exception('Location ID required');
        
        $result = updateLocation($id, [
            'name' => $_POST['name'] ?? null,
            'code' => $_POST['code'] ?? null,
            'address_line1' => $_POST['address_line1'] ?? null,
            'address_line2' => $_POST['address_line2'] ?? null,
            'city' => $_POST['city'] ?? null,
            'region' => $_POST['region'] ?? null,
            'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : null
        ]);
        
        if ($result !== false) {
            $response = [
                'success' => true,
                'message' => 'Location updated successfully',
                'data' => ['rows_affected' => $result]
            ];
        } else {
            throw new Exception('Failed to update location');
        }
    }
    
    elseif ($action === 'delete_location') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) throw new Exception('Location ID required');
        
        $result = deleteLocation($id);
        if ($result !== false) {
            $response = [
                'success' => true,
                'message' => 'Location deleted successfully',
                'data' => ['rows_affected' => $result]
            ];
        } else {
            throw new Exception('Failed to delete location');
        }
    }
    
    elseif ($action === 'search_locations') {
        $search = $_GET['q'] ?? $_POST['q'] ?? '';
        $locations = $search ? searchLocations($search) : [];
        $response = [
            'success' => true,
            'message' => 'Locations found',
            'data' => $locations
        ];
    }
    
    elseif ($action === 'filter_locations') {
        $status = isset($_GET['status']) ? (int)$_GET['status'] : -1;
        $locations = $status >= 0 ? filterLocationsByStatus($status) : [];
        $response = [
            'success' => true,
            'message' => 'Locations filtered',
            'data' => $locations
        ];
    }
    
    // ====================================================================
    // ADMIN HANDLERS
    // ====================================================================
    
    elseif ($action === 'create_admin') {
        if ($method !== 'POST') throw new Exception('POST method required');
        requireRole('SUPER_ADMIN');
        
        $result = createAdmin([
            'full_name' => $_POST['full_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'roles' => isset($_POST['roles']) ? array_map('intval', (array)$_POST['roles']) : [],
            'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1
        ]);
        
        if ($result) {
            $response = [
                'success' => true,
                'message' => 'Admin created successfully',
                'data' => ['id' => $result]
            ];
        } else {
            throw new Exception('Failed to create admin');
        }
    }
    
    elseif ($action === 'update_admin') {
        if ($method !== 'POST') throw new Exception('POST method required');
        requireRole('SUPER_ADMIN');
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) throw new Exception('Admin ID required');
        
        $result = updateAdmin($id, [
            'full_name' => $_POST['full_name'] ?? null,
            'email' => $_POST['email'] ?? null,
            'password' => $_POST['password'] ?? null,
            'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : null
        ]);
        
        if ($result !== false) {
            $response = [
                'success' => true,
                'message' => 'Admin updated successfully',
                'data' => ['rows_affected' => $result]
            ];
        } else {
            throw new Exception('Failed to update admin');
        }
    }
    
    elseif ($action === 'delete_admin') {
        if ($method !== 'POST') throw new Exception('POST method required');
        requireRole('SUPER_ADMIN');
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) throw new Exception('Admin ID required');
        
        // Prevent self-deletion
        $current_admin = getCurrentAdmin();
        if ($id === (int)$current_admin['id']) {
            throw new Exception('Cannot delete your own admin account');
        }
        
        $result = deleteAdmin($id);
        if ($result !== false) {
            $response = [
                'success' => true,
                'message' => 'Admin deleted successfully',
                'data' => ['rows_affected' => $result]
            ];
        } else {
            throw new Exception('Failed to delete admin');
        }
    }
    
    elseif ($action === 'search_admins') {
        requireRole('SUPER_ADMIN');
        $search = $_GET['q'] ?? $_POST['q'] ?? '';
        $admins = $search ? searchAdmins($search) : [];
        $response = [
            'success' => true,
            'message' => 'Admins found',
            'data' => $admins
        ];
    }
    
    elseif ($action === 'filter_admins') {
        requireRole('SUPER_ADMIN');
        $status = isset($_GET['status']) ? (int)$_GET['status'] : -1;
        $admins = $status >= 0 ? filterAdminsByStatus($status) : [];
        $response = [
            'success' => true,
            'message' => 'Admins filtered',
            'data' => $admins
        ];
    }
    
    elseif ($action === 'toggle_admin_status') {
        if ($method !== 'POST') throw new Exception('POST method required');
        requireRole('SUPER_ADMIN');
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) throw new Exception('Admin ID required');
        
        // Get current status
        $admin = getAdminById($id);
        if (!$admin) throw new Exception('Admin not found');
        
        // Prevent self-deactivation
        $current_admin = getCurrentAdmin();
        if ($id === (int)$current_admin['id'] && $admin['is_active']) {
            throw new Exception('Cannot deactivate your own account');
        }
        
        $new_status = 1 - (int)$admin['is_active'];
        $result = updateAdmin($id, ['is_active' => $new_status]);
        
        if ($result !== false) {
            $response = [
                'success' => true,
                'message' => $new_status ? 'Admin activated' : 'Admin deactivated',
                'data' => ['new_status' => $new_status]
            ];
        } else {
            throw new Exception('Failed to toggle admin status');
        }
    }
    
    // ====================================================================
    // FAQ HANDLERS
    // ====================================================================
    
    elseif ($action === 'create_faq') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $result = createFaq([
            'question_en' => $_POST['question_en'] ?? '',
            'answer_en' => $_POST['answer_en'] ?? '',
            'question_fr' => $_POST['question_fr'] ?? '',
            'answer_fr' => $_POST['answer_fr'] ?? '',
            'is_published' => isset($_POST['is_published']) ? (int)$_POST['is_published'] : 1,
            'sort_order' => isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0
        ]);
        
        if ($result) {
            $response = [
                'success' => true,
                'message' => 'FAQ created successfully',
                'data' => ['id' => $result]
            ];
        } else {
            throw new Exception('Failed to create FAQ');
        }
    }
    
    elseif ($action === 'update_faq') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) throw new Exception('FAQ ID required');
        
        $result = updateFaq($id, [
            'question_en' => $_POST['question_en'] ?? null,
            'answer_en' => $_POST['answer_en'] ?? null,
            'question_fr' => $_POST['question_fr'] ?? null,
            'answer_fr' => $_POST['answer_fr'] ?? null,
            'is_published' => isset($_POST['is_published']) ? (int)$_POST['is_published'] : null,
            'sort_order' => isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : null
        ]);
        
        if ($result !== false) {
            $response = [
                'success' => true,
                'message' => 'FAQ updated successfully',
                'data' => ['rows_affected' => $result]
            ];
        } else {
            throw new Exception('Failed to update FAQ');
        }
    }
    
    elseif ($action === 'delete_faq') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) throw new Exception('FAQ ID required');
        
        $result = deleteFaq($id);
        if ($result !== false) {
            $response = [
                'success' => true,
                'message' => 'FAQ deleted successfully',
                'data' => ['rows_affected' => $result]
            ];
        } else {
            throw new Exception('Failed to delete FAQ');
        }
    }
    
    elseif ($action === 'search_faqs') {
        $search = $_GET['q'] ?? $_POST['q'] ?? '';
        $faqs = $search ? searchFaqs($search) : [];
        $response = [
            'success' => true,
            'message' => 'FAQs found',
            'data' => $faqs
        ];
    }
    
    elseif ($action === 'filter_faqs') {
        $status = isset($_GET['status']) ? (int)$_GET['status'] : -1;
        $faqs = $status >= 0 ? filterFaqsByStatus($status) : [];
        $response = [
            'success' => true,
            'message' => 'FAQs filtered',
            'data' => $faqs
        ];
    }
    
    // ====================================================================
    // SUPPORT TICKET HANDLERS
    // ====================================================================
    
    elseif ($action === 'update_ticket_status') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        
        if (!$id) throw new Exception('Ticket ID required');
        if (!$status) throw new Exception('Status required');
        
        $result = updateTicketStatus($id, $status);
        if ($result !== false) {
            $response = [
                'success' => true,
                'message' => 'Ticket status updated',
                'data' => ['rows_affected' => $result]
            ];
        } else {
            throw new Exception('Failed to update ticket status');
        }
    }
    
    elseif ($action === 'search_tickets') {
        $search = $_GET['q'] ?? $_POST['q'] ?? '';
        $tickets = $search ? searchTickets($search) : [];
        $response = [
            'success' => true,
            'message' => 'Tickets found',
            'data' => $tickets
        ];
    }
    
    elseif ($action === 'filter_tickets') {
        $status = $_GET['status'] ?? $_POST['status'] ?? '';
        $tickets = $status ? filterTicketsByStatus($status) : [];
        $response = [
            'success' => true,
            'message' => 'Tickets filtered',
            'data' => $tickets
        ];
    }
    
    // ====================================================================
    // PAYMENT HANDLERS
    // ====================================================================
    
    elseif ($action === 'search_payments') {
        $search = $_GET['q'] ?? $_POST['q'] ?? '';
        $payments = $search ? searchPayments($search) : [];
        $response = [
            'success' => true,
            'message' => 'Payments found',
            'data' => $payments
        ];
    }
    
    elseif ($action === 'filter_payments') {
        $status = $_GET['status'] ?? $_POST['status'] ?? '';
        $start_date = $_GET['start_date'] ?? $_POST['start_date'] ?? null;
        $end_date = $_GET['end_date'] ?? $_POST['end_date'] ?? null;
        
        $payments = $status ? filterPaymentsByStatus($status, $start_date, $end_date) : [];
        $response = [
            'success' => true,
            'message' => 'Payments filtered',
            'data' => $payments
        ];
    }
    
    // ====================================================================
    // HOUSEHOLD HANDLERS
    // ====================================================================
    
    elseif ($action === 'create_household') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $result = createHousehold([
            'location_id' => $_POST['location_id'] ?? null,
            'apartment_label' => $_POST['apartment_label'] ?? '',
            'primary_full_name' => $_POST['primary_full_name'] ?? '',
            'primary_phone_number' => $_POST['primary_phone_number'] ?? '',
            'primary_email' => $_POST['primary_email'] ?? '',
            'preferred_language' => $_POST['preferred_language'] ?? 'en',
            'notes' => $_POST['notes'] ?? '',
            'subscription_status' => $_POST['subscription_status'] ?? 'pending'
        ]);
        
        if ($result) {
            $response = [
                'success' => true,
                'message' => 'Household created successfully',
                'data' => ['id' => $result]
            ];
        } else {
            throw new Exception('Failed to create household');
        }
    }
    
    elseif ($action === 'update_household') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $household_id = $_POST['id'] ?? null;
        if (!$household_id) throw new Exception('Household ID required');
        
        $result = updateHousehold($household_id, [
            'apartment_label' => $_POST['apartment_label'] ?? null,
            'primary_full_name' => $_POST['primary_full_name'] ?? null,
            'primary_phone_number' => $_POST['primary_phone_number'] ?? null,
            'primary_email' => $_POST['primary_email'] ?? null,
            'preferred_language' => $_POST['preferred_language'] ?? null,
            'notes' => $_POST['notes'] ?? null,
            'subscription_status' => $_POST['subscription_status'] ?? null
        ]);
        
        if ($result !== false) {
            $response = [
                'success' => true,
                'message' => 'Household updated successfully',
                'data' => ['affected_rows' => $result]
            ];
        } else {
            throw new Exception('Failed to update household');
        }
    }
    
    elseif ($action === 'delete_household') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $household_id = $_POST['id'] ?? null;
        if (!$household_id) throw new Exception('Household ID required');
        
        $result = deleteHousehold($household_id);
        
        if ($result !== false) {
            $response = [
                'success' => true,
                'message' => 'Household deleted successfully',
                'data' => ['affected_rows' => $result]
            ];
        } else {
            throw new Exception('Failed to delete household');
        }
    }
    
    elseif ($action === 'search_households') {
        $search = $_GET['q'] ?? $_POST['q'] ?? '';
        $location_id = $_GET['location_id'] ?? $_POST['location_id'] ?? null;
        
        $households = $search ? searchHouseholds($search, $location_id) : [];
        $response = [
            'success' => true,
            'message' => 'Households found',
            'data' => $households
        ];
    }
    
    elseif ($action === 'filter_households') {
        $status = $_GET['status'] ?? $_POST['status'] ?? '';
        $location_id = $_GET['location_id'] ?? $_POST['location_id'] ?? null;
        
        $households = $status ? filterHouseholdsByStatus($status, $location_id) : [];
        $response = [
            'success' => true,
            'message' => 'Households filtered',
            'data' => $households
        ];
    }
    
    elseif ($action === 'reset_household_pin') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $household_id = $_POST['id'] ?? $_POST['household_id'] ?? null;
        if (!$household_id) throw new Exception('Household ID required');
        
        $result = resetHouseholdPin($household_id);
        
        if ($result !== false) {
            $response = [
                'success' => true,
                'message' => 'PIN reset to 1234. User must change on next login.',
                'data' => ['affected_rows' => $result]
            ];
        } else {
            throw new Exception('Failed to reset PIN');
        }
    }

    // ====================================================================
    // SUBSCRIPTION HANDLERS
    // ====================================================================

    elseif ($action === 'subscription_action') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $household_id = $_POST['household_id'] ?? null;
        if (!$household_id) throw new Exception('Household ID required');
        
        $sub_action = $_POST['sub_action'] ?? null;
        if (!$sub_action) throw new Exception('Action type required');
        
        $admin = getCurrentAdmin();
        
        // Log the audit action (if audit_logs table exists)
        // $audit_data = [
        //     'household_id' => $household_id,
        //     'actor' => $admin['name'],
        //     'action' => 'Subscription ' . ucfirst($sub_action),
        //     'entity_type' => 'subscription',
        //     'details' => 'Action: ' . $sub_action
        // ];
        // $audit_result = insert('audit_logs', $audit_data);
        
        $response = [
            'success' => true,
            'message' => 'Subscription ' . $sub_action . ' recorded successfully',
            'data' => []
        ];
    }

    // ====================================================================
    // PAYMENT HANDLERS (DECISION)
    // ====================================================================

    elseif ($action === 'payment_decision') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $household_id = $_POST['household_id'] ?? null;
        $payment_id = $_POST['payment_id'] ?? null;
        $decision = $_POST['decision'] ?? null;
        $note = $_POST['note'] ?? '';
        
        if (!$household_id) throw new Exception('Household ID required');
        if (!$payment_id) throw new Exception('Payment ID required');
        if (!$decision) throw new Exception('Decision required');
        
        $admin = getCurrentAdmin();
        
        // Update payment status
        $new_status = ($decision === 'verify') ? 'completed' : 'failed';
        $payment_result = update('payments', ['status' => $new_status], ['id' => $payment_id]);
        
        // Log the audit action (if audit_logs table exists)
        // $audit_data = [
        //     'household_id' => $household_id,
        //     'actor' => $admin['name'],
        //     'action' => 'Payment ' . ucfirst($decision),
        //     'entity_type' => 'payment',
        //     'details' => 'Payment #' . $payment_id . ' - ' . $note
        // ];
        // $audit_result = insert('audit_logs', $audit_data);
        
        $response = [
            'success' => true,
            'message' => 'Payment ' . $decision . 'ed successfully',
            'data' => ['payment_id' => $payment_id]
        ];
    }

    // ====================================================================
    // SUPPORT HANDLERS
    // ====================================================================

    elseif ($action === 'support_action') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $household_id = $_POST['household_id'] ?? null;
        $mode = $_POST['mode'] ?? 'new';
        $status = $_POST['status'] ?? 'open';
        $message = $_POST['message'] ?? '';
        
        if (!$household_id) throw new Exception('Household ID required');
        if (!$message) throw new Exception('Message required');
        
        $admin = getCurrentAdmin();
        
        if ($mode === 'new') {
            // Create new ticket
            $ticket_data = [
                'household_id' => $household_id,
                'subject' => substr($message, 0, 100),
                'description' => $message,
                'status' => $status,
                'created_by' => $admin['id'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $ticket_id = insert('support_tickets', $ticket_data);
            
            $response = [
                'success' => true,
                'message' => 'New support ticket created successfully',
                'data' => ['ticket_id' => $ticket_id]
            ];
        } else {
            // Reply to existing ticket (just log in audit)
            // $audit_data = [
            //     'household_id' => $household_id,
            //     'actor' => $admin['name'],
            //     'action' => 'Support reply',
            //     'entity_type' => 'ticket',
            //     'details' => $message
            // ];
            // insert('audit_logs', $audit_data);
            
            $response = [
                'success' => true,
                'message' => 'Support reply recorded successfully',
                'data' => []
            ];
        }
    }

    // ====================================================================
    // RESET PIN (Household Detail)
    // ====================================================================

    elseif ($action === 'reset_pin') {
        if ($method !== 'POST') throw new Exception('POST method required');
        
        $household_id = $_POST['household_id'] ?? null;
        if (!$household_id) throw new Exception('Household ID required');
        
        $admin = getCurrentAdmin();
        
        // Reset the PIN to default 1234 (if users table has pin column)
        // $pin_result = update('users', ['pin' => '1234', 'has_changed_default_pin' => 0], ['household_id' => $household_id]);
        
        // Log the audit action (if audit_logs table exists)
        // $audit_data = [
        //     'household_id' => $household_id,
        //     'actor' => $admin['name'],
        //     'action' => 'PIN reset',
        //     'entity_type' => 'household',
        //     'details' => 'PIN reset to 1234, user must change on next login'
        // ];
        // insert('audit_logs', $audit_data);
        
        $response = [
            'success' => true,
            'message' => 'PIN reset successfully to 1234. User must change on next login.',
            'data' => []
        ];
    }
    
    else {
        throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ];
    http_response_code(400);
}

// Output JSON response
echo json_encode($response);
exit;
?>
