<?php
// ============================================================
// config/config.php — App Configuration & Global Helpers
// ============================================================

define('APP_NAME',    'Zaidi Bakery');
define('APP_URL',     'http://localhost/zaidi_bakery');
define('APP_VERSION', '1.0.0');

define('JWT_SECRET',        'zaidi_bakery_super_secret_key_2025');
define('JWT_EXPIRY_HOURS',  24);

define('DELIVERY_FEE',      100.00);
define('LOW_STOCK_THRESHOLD', 10);

define('UPLOAD_PATH', __DIR__ . '/../../assets/images/products/');
define('UPLOAD_URL',  APP_URL . '/assets/images/products/');

define('ALLOWED_ROLES', ['customer' => 1, 'admin' => 2, 'delivery' => 3]);

// ─── Response Helper ──────────────────────────────────────
function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function success(mixed $data = null, string $msg = 'Success', int $code = 200): void {
    json_response(['success' => true, 'message' => $msg, 'data' => $data], $code);
}

function error(string $msg, int $code = 400): void {
    json_response(['success' => false, 'message' => $msg], $code);
}

// ─── Sanitize Input ──────────────────────────────────────
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function get_body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? $_POST;
}

// ─── JWT ─────────────────────────────────────────────────
function jwt_encode(array $payload): string {
    $header  = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload['iat'] = time();
    $payload['exp'] = time() + (JWT_EXPIRY_HOURS * 3600);
    $pay     = base64url_encode(json_encode($payload));
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$pay", JWT_SECRET, true));
    return "$header.$pay.$sig";
}

function jwt_decode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payload, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $data = json_decode(base64url_decode($payload), true);
    if (!$data || $data['exp'] < time()) return null;
    return $data;
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

// ─── Get Auth User ───────────────────────────────────────
function get_auth_user(): ?array {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!str_starts_with($auth, 'Bearer ')) return null;
    return jwt_decode(substr($auth, 7));
}

function require_auth(array $roles = []): array {
    $user = get_auth_user();
    if (!$user) error('Unauthorized. Please log in.', 401);
    if (!empty($roles) && !in_array($user['role'], $roles)) error('Access forbidden.', 403);
    return $user;
}

// ─── Password ────────────────────────────────────────────
function hash_password(string $plain): string {
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 10]);
}
function verify_password(string $plain, string $hash): bool {
    return password_verify($plain, $hash);
}
