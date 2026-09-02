<?php
/**
 * LaneShare API - Main Entry Point (Simple Version)
 * Swimming Pool Lane Booking System
 * Group 2: Marc Jyuan G. Gumana & Kram Ashi S. Udani
 * 
 * How to use:
 * 1. Update Config.php with your GoDaddy database details
 * 2. Upload all files to your server
 * 3. Test: your-domain.com/api/index.php?route=health
 */

require_once "Config.php";
require_once "Product.php";

// Connect to database
$conn = db_connect();
if (!$conn) {
    send_error("Cannot connect to database. Check Config.php settings.", 500);
    exit();
}

// Get the route from URL
$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = get_input();

// Get ID from URL if present (e.g., ?route=users&id=123)
$id = $_GET['id'] ?? '';

// ========== ROUTING ==========
switch ($route) {

    // ==================== HEALTH CHECK ====================
    case 'health':
        send_success([
            "service" => "LaneShare API",
            "version" => "1.0",
            "status" => "running",
            "database" => "connected"
        ], "API is running");
        break;

    // ==================== AUTH ====================
    case 'register':
        if ($method != 'POST') { send_error("Use POST", 405); break; }
        $type = $input['type'] ?? 'user';
        if ($type == 'instructor') {
            $result = register_instructor($conn, $input);
        } else {
            $result = register_user($conn, $input);
        }
        if ($result['success']) {
            send_success(["id" => $result['user_id'] ?? $result['instructor_id']], "Registered successfully");
        } else {
            send_error($result['error']);
        }
        break;

    case 'login':
        if ($method != 'POST') { send_error("Use POST", 405); break; }
        $type = $input['type'] ?? 'user';
        if ($type == 'instructor') {
            // Simple instructor login by mobile
            $stmt = $conn->prepare("SELECT * FROM Instructors WHERE mobile_number = ?");
            $stmt->execute([$input['mobile_number'] ?? '']);
            $user = $stmt->fetch();
            if ($user) {
                send_success($user, "Login successful");
            } else {
                send_error("Instructor not found", 401);
            }
        } else {
            $result = login_user($conn, $input);
            if ($result['success']) {
                send_success($result['user'], "Login successful");
            } else {
                send_error($result['error'], 401);
            }
        }
        break;

    // ==================== USERS ====================
    case 'users':
        if ($method == 'GET') {
            if ($id) {
                $user = get_user($conn, $id);
                if ($user) {
                    send_success($user, "User found");
                } else {
                    send_error("User not found", 404);
                }
            } else {
                $stmt = $conn->query("SELECT * FROM Users");
                send_success($stmt->fetchAll(), "All users");
            }
        } elseif ($method == 'PUT') {
            if (!$id) { send_error("User ID required"); break; }
            if (update_user($conn, $id, $input)) {
                send_success(get_user($conn, $id), "User updated");
            } else {
                send_error("Update failed");
            }
        } else {
            send_error("Use GET or PUT", 405);
        }
        break;

    case 'user-bookings':
        if ($method != 'GET') { send_error("Use GET", 405); break; }
        $user_id = $_GET['user_id'] ?? $id;
        $status = $_GET['status'] ?? null;
        if (!$user_id) { send_error("user_id required"); break; }
        send_success(get_user_bookings($conn, $user_id, $status), "User bookings");
        break;

    // ==================== INSTRUCTORS ====================
    case 'instructors':
        if ($method == 'GET') {
            if ($id) {
                $inst = get_instructor($conn, $id);
                if ($inst) {
                    send_success($inst, "Instructor found");
                } else {
                    send_error("Instructor not found", 404);
                }
            } else {
                send_success(get_all_instructors($conn), "All instructors");
            }
        } elseif ($method == 'PUT') {
            if (!$id) { send_error("Instructor ID required"); break; }
            // Update instructor fields
            $fields = [];
            $values = [];
            if (!empty($input['full_name'])) { $fields[] = "full_name = ?"; $values[] = clean($input['full_name']); }
            if (!empty($input['specialization'])) { $fields[] = "specialization = ?"; $values[] = clean($input['specialization']); }
            if (!empty($input['availability_status'])) { $fields[] = "availability_status = ?"; $values[] = $input['availability_status']; }
            if (empty($fields)) { send_error("No fields to update"); break; }
            $values[] = $id;
            $sql = "UPDATE Instructors SET " . implode(", ", $fields) . " WHERE instructor_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute($values)) {
                send_success(get_instructor($conn, $id), "Instructor updated");
            } else {
                send_error("Update failed");
            }
        } else {
            send_error("Use GET or PUT", 405);
        }
        break;

    case 'instructor-location':
        if ($method != 'POST') { send_error("Use POST", 405); break; }
        if (!$id) { send_error("instructor_id required"); break; }
        if (update_instructor_location($conn, $id, $input['latitude'] ?? 0, $input['longitude'] ?? 0)) {
            send_success(null, "Location updated");
        } else {
            send_error("Update failed");
        }
        break;

    case 'instructor-availability':
        if ($method != 'POST') { send_error("Use POST", 405); break; }
        if (!$id) { send_error("instructor_id required"); break; }
        if (set_instructor_availability($conn, $id, $input['status'] ?? 'offline')) {
            send_success(null, "Availability updated");
        } else {
            send_error("Update failed");
        }
        break;

    // ==================== POOLS ====================
    case 'pools':
        if ($method == 'GET') {
            $search = $_GET['search'] ?? '';
            $pool_type = $_GET['pool_type'] ?? '';
            if ($id) {
                $pool = get_pool($conn, $id);
                if ($pool) {
                    // Get lanes for this pool
                    $pool['lanes'] = get_pool_lanes($conn, $id);
                    send_success($pool, "Pool found");
                } else {
                    send_error("Pool not found", 404);
                }
            } elseif ($search || $pool_type) {
                send_success(search_pools($conn, $search, $pool_type), "Search results");
            } else {
                send_success(get_all_pools($conn), "All pools");
            }
        } elseif ($method == 'POST') {
            $result = create_pool($conn, $input);
            if ($result['success']) {
                send_success(["pool_id" => $result['pool_id']], "Pool created", 201);
            } else {
                send_error($result['error']);
            }
        } elseif ($method == 'PUT') {
            if (!$id) { send_error("Pool ID required"); break; }
            $fields = [];
            $values = [];
            if (!empty($input['pool_name'])) { $fields[] = "pool_name = ?"; $values[] = clean($input['pool_name']); }
            if (!empty($input['address'])) { $fields[] = "address = ?"; $values[] = clean($input['address']); }
            if (!empty($input['pool_type'])) { $fields[] = "pool_type = ?"; $values[] = $input['pool_type']; }
            if (!empty($input['total_lane_count'])) { $fields[] = "total_lane_count = ?"; $values[] = (int)$input['total_lane_count']; }
            if (empty($fields)) { send_error("No fields to update"); break; }
            $values[] = $id;
            $sql = "UPDATE Pools SET " . implode(", ", $fields) . " WHERE pool_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute($values)) {
                send_success(get_pool($conn, $id), "Pool updated");
            } else {
                send_error("Update failed");
            }
        } else {
            send_error("Method not allowed", 405);
        }
        break;

    // ==================== LANES ====================
    case 'lanes':
        if ($method != 'GET') { send_error("Use GET", 405); break; }
        $pool_id = $_GET['pool_id'] ?? '';
        if ($id) {
            $lane = get_lane($conn, $id);
            if ($lane) {
                send_success($lane, "Lane found");
            } else {
                send_error("Lane not found", 404);
            }
        } elseif ($pool_id) {
            send_success(get_pool_lanes($conn, $pool_id), "Pool lanes");
        } else {
            $stmt = $conn->query("SELECT * FROM Lanes ORDER BY lane_number");
            send_success($stmt->fetchAll(), "All lanes");
        }
        break;

    case 'available-lanes':
        if ($method != 'GET') { send_error("Use GET", 405); break; }
        $pool_id = $_GET['pool_id'] ?? '';
        $date = $_GET['date'] ?? '';
        $start = $_GET['start_time'] ?? '';
        $end = $_GET['end_time'] ?? '';
        if (!$pool_id || !$date || !$start || !$end) {
            send_error("pool_id, date, start_time, end_time are required");
            break;
        }
        send_success(get_available_lanes($conn, $pool_id, $date, $start, $end), "Available lanes");
        break;

    // ==================== BOOKINGS ====================
    case 'bookings':
        if ($method == 'POST') {
            $result = create_booking($conn, $input);
            if ($result['success']) {
                send_success(["booking_id" => $result['booking_id'], "total_fare" => $result['total_fare']], "Booking created", 201);
            } else {
                send_error($result['error']);
            }
        } elseif ($method == 'GET') {
            if ($id) {
                $booking = get_booking($conn, $id);
                if ($booking) {
                    send_success($booking, "Booking found");
                } else {
                    send_error("Booking not found", 404);
                }
            } else {
                send_success(get_all_bookings($conn), "All bookings");
            }
        } elseif ($method == 'PUT') {
            if (!$id) { send_error("Booking ID required"); break; }
            $status = $input['status'] ?? '';
            $reason = $input['cancellation_reason'] ?? null;
            if ($status && update_booking_status($conn, $id, $status, $reason)) {
                send_success(get_booking($conn, $id), "Booking updated");
            } else {
                send_error("Update failed");
            }
        } else {
            send_error("Method not allowed", 405);
        }
        break;

    case 'booking-checkin':
        if ($method != 'POST') { send_error("Use POST", 405); break; }
        if (!$id) { send_error("booking_id required"); break; }
        if (checkin_booking($conn, $id, $input['proof_of_checkin_url'] ?? '')) {
            send_success(null, "Check-in recorded");
        } else {
            send_error("Check-in failed");
        }
        break;

    // ==================== PAYMENTS ====================
    case 'payments':
        if ($method == 'GET') {
            if ($id) {
                $payment = get_payment($conn, $id);
                if ($payment) {
                    send_success($payment, "Payment found");
                } else {
                    send_error("Payment not found", 404);
                }
            } else {
                $booking_id = $_GET['booking_id'] ?? '';
                if ($booking_id) {
                    send_success(get_booking_payment($conn, $booking_id), "Booking payment");
                } else {
                    $stmt = $conn->query("SELECT * FROM Payments");
                    send_success($stmt->fetchAll(), "All payments");
                }
            }
        } elseif ($method == 'POST') {
            if (!$id) { send_error("payment_id required"); break; }
            if (process_payment($conn, $id, $input['gateway_transaction_ref'] ?? '')) {
                send_success(null, "Payment processed");
            } else {
                send_error("Payment processing failed");
            }
        } else {
            send_error("Method not allowed", 405);
        }
        break;

    // ==================== PAYMENT METHODS ====================
    case 'payment-methods':
        if ($method == 'GET') {
            $user_id = $_GET['user_id'] ?? '';
            if ($user_id) {
                send_success(get_user_payment_methods($conn, $user_id), "Payment methods");
            } else {
                $stmt = $conn->query("SELECT * FROM Payment_Methods");
                send_success($stmt->fetchAll(), "All payment methods");
            }
        } elseif ($method == 'POST') {
            $result = add_payment_method($conn, $input);
            if ($result['success']) {
                send_success(["payment_method_id" => $result['payment_method_id']], "Payment method added", 201);
            } else {
                send_error($result['error']);
            }
        } else {
            send_error("Method not allowed", 405);
        }
        break;

    // ==================== RATINGS ====================
    case 'ratings':
        if ($method == 'GET') {
            $entity_id = $_GET['entity_id'] ?? '';
            $entity_type = $_GET['entity_type'] ?? '';
            if ($entity_id && $entity_type) {
                send_success(get_ratings($conn, $entity_id, $entity_type), "Ratings");
            } else {
                $stmt = $conn->query("SELECT * FROM Ratings ORDER BY created_at DESC");
                send_success($stmt->fetchAll(), "All ratings");
            }
        } elseif ($method == 'POST') {
            $result = submit_rating($conn, $input);
            if ($result['success']) {
                send_success(["rating_id" => $result['rating_id']], "Rating submitted", 201);
            } else {
                send_error($result['error']);
            }
        } else {
            send_error("Method not allowed", 405);
        }
        break;

    // ==================== NOTIFICATIONS ====================
    case 'notifications':
        if ($method == 'GET') {
            $user_id = $_GET['user_id'] ?? '';
            if ($user_id) {
                send_success(get_user_notifications($conn, $user_id), "Notifications");
            } else {
                $stmt = $conn->query("SELECT * FROM Notifications ORDER BY sent_at DESC");
                send_success($stmt->fetchAll(), "All notifications");
            }
        } elseif ($method == 'PUT') {
            if (!$id) { send_error("notification_id required"); break; }
            if (mark_notification_read($conn, $id)) {
                send_success(null, "Marked as read");
            } else {
                send_error("Update failed");
            }
        } else {
            send_error("Method not allowed", 405);
        }
        break;

    // ==================== SAVED LOCATIONS ====================
    case 'saved-locations':
        if ($method == 'GET') {
            $user_id = $_GET['user_id'] ?? '';
            if ($user_id) {
                send_success(get_user_locations($conn, $user_id), "Saved locations");
            } else {
                $stmt = $conn->query("SELECT * FROM Saved_Locations");
                send_success($stmt->fetchAll(), "All saved locations");
            }
        } elseif ($method == 'POST') {
            $result = add_saved_location($conn, $input);
            if ($result['success']) {
                send_success(["location_id" => $result['location_id']], "Location saved", 201);
            } else {
                send_error($result['error']);
            }
        } elseif ($method == 'DELETE') {
            if (!$id) { send_error("location_id required"); break; }
            if (delete_saved_location($conn, $id)) {
                send_success(null, "Location deleted");
            } else {
                send_error("Delete failed");
            }
        } else {
            send_error("Method not allowed", 405);
        }
        break;

    // ==================== DEFAULT ====================
    default:
        send_error("Invalid route. Available routes: health, register, login, users, user-bookings, instructors, instructor-location, instructor-availability, pools, lanes, available-lanes, bookings, booking-checkin, payments, payment-methods, ratings, notifications, saved-locations", 404);
        break;
}
?>
