<?php
/**
 * LaneShare API - Simple Database Functions
 * Swimming Pool Lane Booking System
 * Group 2: Marc Jyuan G. Gumana & Kram Ashi S. Udani
 * 
 * Simple functions for each table - easy to read and modify
 */

require_once "Config.php";

// ============================================================
// USERS
// ============================================================

function register_user($conn, $data) {
    // Check required fields
    if (empty($data['full_name']) || empty($data['mobile_number'])) {
        return ["success" => false, "error" => "Full name and mobile number are required"];
    }

    // Check if mobile already exists
    $stmt = $conn->prepare("SELECT user_id FROM Users WHERE mobile_number = ?");
    $stmt->execute([$data['mobile_number']]);
    if ($stmt->fetch()) {
        return ["success" => false, "error" => "Mobile number already registered"];
    }

    $user_id = generate_uuid();
    $full_name = clean($data['full_name']);
    $mobile = clean($data['mobile_number']);
    $email = !empty($data['email']) ? clean($data['email']) : null;
    $password_hash = !empty($data['password']) ? hash_password($data['password']) : null;
    $photo = !empty($data['profile_photo_url']) ? clean($data['profile_photo_url']) : null;
    $status = 'active';
    $wallet = 0.00;
    $now = date('Y-m-d H:i:s');

    $sql = "INSERT INTO Users (user_id, full_name, mobile_number, email, password_hash, profile_photo_url, account_status, wallet_balance, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt->execute([$user_id, $full_name, $mobile, $email, $password_hash, $photo, $status, $wallet, $now, $now])) {
        return ["success" => true, "user_id" => $user_id];
    }
    return ["success" => false, "error" => "Registration failed"];
}

function login_user($conn, $data) {
    // Mobile + OTP login
    if (!empty($data['mobile_number'])) {
        $stmt = $conn->prepare("SELECT * FROM Users WHERE mobile_number = ? AND account_status = 'active'");
        $stmt->execute([$data['mobile_number']]);
        $user = $stmt->fetch();
        if ($user) {
            return ["success" => true, "user" => $user];
        }
        return ["success" => false, "error" => "User not found"];
    }

    // Email + Password login
    if (!empty($data['email']) && !empty($data['password'])) {
        $stmt = $conn->prepare("SELECT * FROM Users WHERE email = ? AND account_status = 'active'");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();
        if ($user && check_password($data['password'], $user['password_hash'])) {
            return ["success" => true, "user" => $user];
        }
        return ["success" => false, "error" => "Invalid email or password"];
    }

    return ["success" => false, "error" => "Please provide mobile_number or email+password"];
}

function get_user($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM Users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function update_user($conn, $user_id, $data) {
    $fields = [];
    $values = [];

    if (!empty($data['full_name'])) { $fields[] = "full_name = ?"; $values[] = clean($data['full_name']); }
    if (!empty($data['email'])) { $fields[] = "email = ?"; $values[] = clean($data['email']); }
    if (!empty($data['profile_photo_url'])) { $fields[] = "profile_photo_url = ?"; $values[] = clean($data['profile_photo_url']); }

    if (empty($fields)) return false;

    $fields[] = "updated_at = ?";
    $values[] = date('Y-m-d H:i:s');
    $values[] = $user_id;

    $sql = "UPDATE Users SET " . implode(", ", $fields) . " WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute($values);
}

function get_user_bookings($conn, $user_id, $status = null) {
    if ($status) {
        $stmt = $conn->prepare("SELECT * FROM Booking_Reservations WHERE user_id = ? AND status = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id, $status]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM Booking_Reservations WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
    }
    return $stmt->fetchAll();
}

// ============================================================
// INSTRUCTORS
// ============================================================

function register_instructor($conn, $data) {
    if (empty($data['full_name']) || empty($data['mobile_number']) || empty($data['certification_type']) || empty($data['certification_number'])) {
        return ["success" => false, "error" => "All required fields must be provided"];
    }

    $id = generate_uuid();
    $now = date('Y-m-d H:i:s');

    $sql = "INSERT INTO Instructors (instructor_id, full_name, mobile_number, certification_type, certification_number, specialization, verification_status, availability_status, total_sessions_completed, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', 'offline', 0, ?)";
    $stmt = $conn->prepare($sql);

    $spec = !empty($data['specialization']) ? clean($data['specialization']) : null;

    if ($stmt->execute([$id, clean($data['full_name']), clean($data['mobile_number']), clean($data['certification_type']), clean($data['certification_number']), $spec, $now])) {
        return ["success" => true, "instructor_id" => $id];
    }
    return ["success" => false, "error" => "Registration failed"];
}

function get_instructor($conn, $instructor_id) {
    $stmt = $conn->prepare("SELECT * FROM Instructors WHERE instructor_id = ?");
    $stmt->execute([$instructor_id]);
    return $stmt->fetch();
}

function get_all_instructors($conn) {
    $stmt = $conn->query("SELECT * FROM Instructors");
    return $stmt->fetchAll();
}

function update_instructor_location($conn, $instructor_id, $lat, $lng) {
    $stmt = $conn->prepare("UPDATE Instructors SET current_latitude = ?, current_longitude = ? WHERE instructor_id = ?");
    return $stmt->execute([$lat, $lng, $instructor_id]);
}

function set_instructor_availability($conn, $instructor_id, $status) {
    $stmt = $conn->prepare("UPDATE Instructors SET availability_status = ? WHERE instructor_id = ?");
    return $stmt->execute([$status, $instructor_id]);
}

// ============================================================
// POOLS
// ============================================================

function get_all_pools($conn) {
    $stmt = $conn->query("SELECT * FROM Pools ORDER BY pool_name");
    return $stmt->fetchAll();
}

function get_pool($conn, $pool_id) {
    $stmt = $conn->prepare("SELECT * FROM Pools WHERE pool_id = ?");
    $stmt->execute([$pool_id]);
    return $stmt->fetch();
}

function search_pools($conn, $search, $pool_type = null) {
    if ($pool_type) {
        $stmt = $conn->prepare("SELECT * FROM Pools WHERE pool_type = ? AND (pool_name LIKE ? OR address LIKE ?) ORDER BY pool_name");
        $stmt->execute([$pool_type, "%$search%", "%$search%"]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM Pools WHERE pool_name LIKE ? OR address LIKE ? ORDER BY pool_name");
        $stmt->execute(["%$search%", "%$search%"]);
    }
    return $stmt->fetchAll();
}

function create_pool($conn, $data) {
    $id = generate_uuid();
    $now = date('Y-m-d H:i:s');

    $sql = "INSERT INTO Pools (pool_id, pool_name, address, pool_type, total_lane_count, facility_document_url, max_capacity, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    $doc = !empty($data['facility_document_url']) ? clean($data['facility_document_url']) : null;
    $max = !empty($data['max_capacity']) ? (int)$data['max_capacity'] : null;

    if ($stmt->execute([$id, clean($data['pool_name']), clean($data['address']), clean($data['pool_type']), (int)$data['total_lane_count'], $doc, $max, $now])) {
        return ["success" => true, "pool_id" => $id];
    }
    return ["success" => false, "error" => "Failed to create pool"];
}

// ============================================================
// LANES
// ============================================================

function get_pool_lanes($conn, $pool_id) {
    $stmt = $conn->prepare("SELECT * FROM Lanes WHERE pool_id = ? ORDER BY lane_number");
    $stmt->execute([$pool_id]);
    return $stmt->fetchAll();
}

function get_lane($conn, $lane_id) {
    $stmt = $conn->prepare("SELECT * FROM Lanes WHERE lane_id = ?");
    $stmt->execute([$lane_id]);
    return $stmt->fetch();
}

function get_available_lanes($conn, $pool_id, $date, $start_time, $end_time) {
    $sql = "SELECT l.* FROM Lanes l
            WHERE l.pool_id = ?
            AND l.lane_id NOT IN (
                SELECT lane_id FROM Booking_Reservations
                WHERE pool_id = ?
                AND booking_date = ?
                AND status NOT IN ('cancelled', 'completed')
                AND (start_time < ? AND end_time > ?)
            )
            ORDER BY l.lane_number";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$pool_id, $pool_id, $date, $end_time, $start_time]);
    return $stmt->fetchAll();
}

// ============================================================
// BOOKINGS
// ============================================================

function create_booking($conn, $data) {
    // Check lane availability first
    $available = get_available_lanes($conn, $data['pool_id'], $data['booking_date'], $data['start_time'], $data['end_time']);
    $lane_ok = false;
    foreach ($available as $lane) {
        if ($lane['lane_id'] == $data['lane_id']) {
            $lane_ok = true;
            break;
        }
    }
    if (!$lane_ok) {
        return ["success" => false, "error" => "Lane is no longer available"];
    }

    $booking_id = generate_uuid();
    $now = date('Y-m-d H:i:s');
    $base_fare = !empty($data['base_fare']) ? (float)$data['base_fare'] : 0;
    $surge = !empty($data['surge_multiplier']) ? (float)$data['surge_multiplier'] : 1.00;
    $total = $base_fare * $surge;
    $instructor_id = !empty($data['instructor_id']) ? $data['instructor_id'] : null;
    $special = !empty($data['special_requests']) ? clean($data['special_requests']) : null;

    $sql = "INSERT INTO Booking_Reservations 
            (booking_id, user_id, instructor_id, pool_id, lane_id, booking_date, start_time, end_time, number_of_participants, booking_type, special_requests, base_fare, surge_multiplier, total_fare, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt->execute([$booking_id, $data['user_id'], $instructor_id, $data['pool_id'], $data['lane_id'], 
                        $data['booking_date'], $data['start_time'], $data['end_time'], 
                        (int)$data['number_of_participants'], $data['booking_type'], $special, 
                        $base_fare, $surge, $total, $now])) {
        return ["success" => false, "error" => "Booking creation failed"];
    }

    // Create payment record
    $payment_id = generate_uuid();
    $payment_sql = "INSERT INTO Payments (payment_id, booking_id, payment_method_id, amount, currency, gateway_provider, status) 
                    VALUES (?, ?, ?, ?, 'PHP', ?, 'pending')";
    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->execute([$payment_id, $booking_id, $data['payment_method_id'], $total, $data['gateway_provider'] ?? 'default']);

    // Create notification
    $notif_id = generate_uuid();
    $msg = "Your booking is confirmed for " . $data['booking_date'] . " at " . $data['start_time'];
    $notif_sql = "INSERT INTO Notifications (notification_id, recipient_id, recipient_type, booking_id, notification_type, message, is_read, sent_at) 
                 VALUES (?, ?, 'user', ?, 'booking_confirmed', ?, 0, ?)";
    $notif_stmt = $conn->prepare($notif_sql);
    $notif_stmt->execute([$notif_id, $data['user_id'], $booking_id, $msg, $now]);

    return ["success" => true, "booking_id" => $booking_id, "total_fare" => $total];
}

function get_booking($conn, $booking_id) {
    $stmt = $conn->prepare("SELECT * FROM Booking_Reservations WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    return $stmt->fetch();
}

function get_all_bookings($conn) {
    $stmt = $conn->query("SELECT * FROM Booking_Reservations ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function update_booking_status($conn, $booking_id, $status, $reason = null) {
    if ($status == 'completed') {
        $stmt = $conn->prepare("UPDATE Booking_Reservations SET status = ?, completed_at = ? WHERE booking_id = ?");
        return $stmt->execute([$status, date('Y-m-d H:i:s'), $booking_id]);
    }
    if ($status == 'cancelled' && $reason) {
        $stmt = $conn->prepare("UPDATE Booking_Reservations SET status = ?, cancellation_reason = ? WHERE booking_id = ?");
        return $stmt->execute([$status, $reason, $booking_id]);
    }
    $stmt = $conn->prepare("UPDATE Booking_Reservations SET status = ? WHERE booking_id = ?");
    return $stmt->execute([$status, $booking_id]);
}

function checkin_booking($conn, $booking_id, $url) {
    $stmt = $conn->prepare("UPDATE Booking_Reservations SET proof_of_checkin_url = ?, status = 'checked_in' WHERE booking_id = ?");
    return $stmt->execute([$url, $booking_id]);
}

// ============================================================
// PAYMENTS
// ============================================================

function get_payment($conn, $payment_id) {
    $stmt = $conn->prepare("SELECT * FROM Payments WHERE payment_id = ?");
    $stmt->execute([$payment_id]);
    return $stmt->fetch();
}

function get_booking_payment($conn, $booking_id) {
    $stmt = $conn->prepare("SELECT * FROM Payments WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    return $stmt->fetch();
}

function process_payment($conn, $payment_id, $gateway_ref) {
    $stmt = $conn->prepare("UPDATE Payments SET gateway_transaction_ref = ?, status = 'completed', paid_at = ? WHERE payment_id = ?");
    return $stmt->execute([$gateway_ref, date('Y-m-d H:i:s'), $payment_id]);
}

// ============================================================
// PAYMENT METHODS
// ============================================================

function get_user_payment_methods($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM Payment_Methods WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function add_payment_method($conn, $data) {
    // If setting as default, remove other defaults first
    if (!empty($data['is_default']) && $data['is_default']) {
        $stmt = $conn->prepare("UPDATE Payment_Methods SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$data['user_id']]);
    }

    $id = generate_uuid();
    $sql = "INSERT INTO Payment_Methods (payment_method_id, user_id, method_type, token_reference, display_label, is_default) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $is_default = !empty($data['is_default']) ? 1 : 0;

    if ($stmt->execute([$id, $data['user_id'], $data['method_type'], $data['token_reference'], $data['display_label'], $is_default])) {
        return ["success" => true, "payment_method_id" => $id];
    }
    return ["success" => false, "error" => "Failed to add payment method"];
}

// ============================================================
// RATINGS
// ============================================================

function get_ratings($conn, $entity_id, $entity_type) {
    $stmt = $conn->prepare("SELECT * FROM Ratings WHERE rated_entity_id = ? AND rated_entity_type = ? ORDER BY created_at DESC");
    $stmt->execute([$entity_id, $entity_type]);
    return $stmt->fetchAll();
}

function submit_rating($conn, $data) {
    $id = generate_uuid();
    $now = date('Y-m-d H:i:s');

    $sql = "INSERT INTO Ratings (rating_id, booking_id, rated_by, rated_entity_type, rated_entity_id, score, comment, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $comment = !empty($data['comment']) ? clean($data['comment']) : null;

    if ($stmt->execute([$id, $data['booking_id'], $data['rated_by'], $data['rated_entity_type'], $data['rated_entity_id'], (int)$data['score'], $comment, $now])) {
        // Update average rating
        if ($data['rated_entity_type'] == 'INSTRUCTOR') {
            $avg_stmt = $conn->prepare("UPDATE Instructors SET average_rating = (SELECT AVG(score) FROM Ratings WHERE rated_entity_id = ? AND rated_entity_type = 'INSTRUCTOR') WHERE instructor_id = ?");
            $avg_stmt->execute([$data['rated_entity_id'], $data['rated_entity_id']]);
        } elseif ($data['rated_entity_type'] == 'POOL') {
            $avg_stmt = $conn->prepare("UPDATE Pools SET average_rating = (SELECT AVG(score) FROM Ratings WHERE rated_entity_id = ? AND rated_entity_type = 'POOL') WHERE pool_id = ?");
            $avg_stmt->execute([$data['rated_entity_id'], $data['rated_entity_id']]);
        }
        return ["success" => true, "rating_id" => $id];
    }
    return ["success" => false, "error" => "Rating submission failed"];
}

// ============================================================
// NOTIFICATIONS
// ============================================================

function get_user_notifications($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM Notifications WHERE recipient_id = ? AND recipient_type = 'user' AND is_read = 0 ORDER BY sent_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function mark_notification_read($conn, $notification_id) {
    $stmt = $conn->prepare("UPDATE Notifications SET is_read = 1 WHERE notification_id = ?");
    return $stmt->execute([$notification_id]);
}

// ============================================================
// SAVED LOCATIONS
// ============================================================

function get_user_locations($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM Saved_Locations WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function add_saved_location($conn, $data) {
    if (!empty($data['is_default']) && $data['is_default']) {
        $stmt = $conn->prepare("UPDATE Saved_Locations SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$data['user_id']]);
    }

    $id = generate_uuid();
    $sql = "INSERT INTO Saved_Locations (location_id, user_id, label, full_address, latitude, longitude, is_default) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $is_default = !empty($data['is_default']) ? 1 : 0;

    if ($stmt->execute([$id, $data['user_id'], clean($data['label']), clean($data['full_address']), $data['latitude'], $data['longitude'], $is_default])) {
        return ["success" => true, "location_id" => $id];
    }
    return ["success" => false, "error" => "Failed to save location"];
}

function delete_saved_location($conn, $location_id) {
    $stmt = $conn->prepare("DELETE FROM Saved_Locations WHERE location_id = ?");
    return $stmt->execute([$location_id]);
}
?>

