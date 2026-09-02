<?php
/**
 * LaneShare API - Simple Database Config
 * Swimming Pool Lane Booking System
 * Group 2: Marc Jyuan G. Gumana & Kram Ashi S. Udani
 * 
 * Just update the 4 lines below with your GoDaddy details
 */

// ========== UPDATE THESE 4 LINES ==========
$DB_HOST     = "localhost";           // GoDaddy: usually "localhost"
$DB_NAME     = "LaneShare_db";        // Your database name
$DB_USER     = "your_username";       // Your MySQL username
$DB_PASS     = "your_password";       // Your MySQL password
// =========================================

// Allow requests from your mobile app
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Connect to database
 * Returns: PDO connection or false
 */
function db_connect() {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    try {
        $conn = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Send success response
 */
function send_success($data, $message = "Success") {
    echo json_encode([
        "status" => "success",
        "message" => $message,
        "data" => $data
    ]);
}

/**
 * Send error response
 */
function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        "status" => "error",
        "message" => $message
    ]);
}

/**
 * Get JSON input from request body
 */
function get_input() {
    $json = file_get_contents("php://input");
    return json_decode($json, true);
}

/**
 * Generate a UUID
 */
function generate_uuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Hash a password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Check a password
 */
function check_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Clean user input
 */
function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
?>

