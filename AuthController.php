<?php
// ============================================================
// controllers/AuthController.php
// POST /api/auth/register
// POST /api/auth/login
// POST /api/auth/logout
// GET  /api/auth/me
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class AuthController {
    private Database $db;

    public function __construct() { $this->db = Database::getInstance(); }

    // ── POST /api/auth/register ─────────────────────────
    public function register(): void {
        $body = get_body();

        $name  = sanitize($body['full_name'] ?? '');
        $email = strtolower(trim($body['email'] ?? ''));
        $phone = sanitize($body['phone'] ?? '');
        $addr  = sanitize($body['address'] ?? '');
        $pass  = $body['password'] ?? '';

        if (!$name || !$email || !$pass)     error('Name, email and password are required.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) error('Invalid email format.');
        if (strlen($pass) < 8)               error('Password must be at least 8 characters.');

        // Check duplicate
        $exists = $this->db->fetchOne("SELECT customer_id FROM Customers WHERE email = ?", [$email]);
        if ($exists) error('An account with this email already exists.', 409);

        $hash = hash_password($pass);
        $this->db->execute(
            "INSERT INTO Customers (full_name, email, phone, address, password_hash, role_id) VALUES (?,?,?,?,?,1)",
            [$name, $email, $phone, $addr, $hash]
        );
        $id = $this->db->lastInsertId();

        $token = jwt_encode(['user_id' => $id, 'email' => $email, 'role' => 'customer', 'name' => $name]);
        success(['token' => $token, 'user' => compact('id','name','email','phone'), 'role' => 'customer'],
                'Registration successful! Welcome to Zaidi Bakery.', 201);
    }

    // ── POST /api/auth/login ────────────────────────────
    public function login(): void {
        $body  = get_body();
        $email = strtolower(trim($body['email'] ?? ''));
        $pass  = $body['password'] ?? '';
        $role  = $body['role'] ?? 'customer';  // customer | admin | delivery

        if (!$email || !$pass) error('Email and password are required.');

        if ($role === 'customer') {
            $user = $this->db->fetchOne(
                "SELECT c.customer_id AS id, c.full_name AS name, c.email, c.phone, c.password_hash, r.role_name AS role
                 FROM Customers c JOIN Roles r ON c.role_id = r.role_id
                 WHERE c.email = ? AND c.is_active = 1", [$email]);
        } else {
            // admin or delivery staff
            $user = $this->db->fetchOne(
                "SELECT e.employee_id AS id, e.full_name AS name, e.email, e.phone, e.password_hash, r.role_name AS role
                 FROM Employees e JOIN Roles r ON e.role_id = r.role_id
                 WHERE e.email = ? AND e.is_active = 1 AND r.role_name = ?", [$email, $role]);
        }

        if (!$user || !verify_password($pass, $user['password_hash'])) {
            error('Invalid email or password.', 401);
        }

        unset($user['password_hash']);
        $token = jwt_encode(['user_id' => $user['id'], 'email' => $user['email'], 'role' => $user['role'], 'name' => $user['name']]);
        success(['token' => $token, 'user' => $user], 'Login successful!');
    }

    // ── GET /api/auth/me ─────────────────────────────────
    public function me(): void {
        $auth = require_auth();
        $role = $auth['role'];

        if ($role === 'customer') {
            $user = $this->db->fetchOne(
                "SELECT customer_id AS id, full_name AS name, email, phone, address FROM Customers WHERE customer_id = ?",
                [$auth['user_id']]);
        } else {
            $user = $this->db->fetchOne(
                "SELECT employee_id AS id, full_name AS name, email, phone FROM Employees WHERE employee_id = ?",
                [$auth['user_id']]);
        }

        if (!$user) error('User not found.', 404);
        $user['role'] = $role;
        success($user);
    }
}

// ── Router ───────────────────────────────────────────────
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$ctrl   = new AuthController();
$action = basename($_SERVER['REQUEST_URI']);

match(true) {
    $action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST' => $ctrl->register(),
    $action === 'login'    && $_SERVER['REQUEST_METHOD'] === 'POST' => $ctrl->login(),
    $action === 'me'       && $_SERVER['REQUEST_METHOD'] === 'GET'  => $ctrl->me(),
    default => error('Endpoint not found.', 404),
};
