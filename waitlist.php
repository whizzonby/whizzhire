<?php
/**
 * Whizz Hire — Waitlist API Endpoint
 * 
 * Handles candidate and business waitlist signups.
 * Saves to MySQL database with duplicate detection.
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ─── Database Configuration ──────────────────────────────────
// Update these values with your actual database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'whizzhire_db');
define('DB_USER', 'whizzhire_user');
define('DB_PASS', 'Wh!zz0nby0869!');
define('DB_CHARSET', 'utf8mb4');

// ─── Read & Validate Input ───────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['email']) || empty($input['type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and type are required.']);
    exit;
}

$email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
$type  = trim($input['type']);

if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

if (!in_array($type, ['candidate', 'business'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid waitlist type.']);
    exit;
}

// ─── Database Connection ─────────────────────────────────────
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;
}

// ─── Check for Duplicate ─────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT id FROM waitlist WHERE email = :email AND type = :type LIMIT 1");
    $stmt->execute(['email' => $email, 'type' => $type]);

    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => "You're already on the {$type} waitlist!"
        ]);
        exit;
    }
} catch (PDOException $e) {
    error_log("DB Query Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;
}

// ─── Insert New Signup ───────────────────────────────────────
try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO waitlist (email, type, ip_address, user_agent, created_at)
        VALUES (:email, :type, :ip, :ua, NOW())
    ");
    $stmt->execute([
        'email' => $email,
        'type'  => $type,
        'ip'    => $ip,
        'ua'    => $userAgent,
    ]);

    echo json_encode([
        'success' => true,
        'message' => "Welcome to the {$type} waitlist!"
    ]);

} catch (PDOException $e) {
    error_log("DB Insert Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;
}
