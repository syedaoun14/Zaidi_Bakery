<?php
// ============================================================
// controllers/AdminController.php
// GET  /api/admin/dashboard       — stats overview
// GET  /api/admin/reports         — sales reports
// GET  /api/admin/employees       — list employees
// POST /api/admin/employees       — add employee
// PUT  /api/admin/employees/{id}  — update employee
// DELETE /api/admin/employees/{id}— remove employee
// GET  /api/admin/customers       — list customers
// GET  /api/admin/payments        — list payments
// GET  /api/admin/low-stock       — low stock products
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class AdminController {
    private Database $db;
    public function __construct() {
        $this->db = Database::getInstance();
        require_auth(['admin']); // All admin endpoints require admin role
    }

    // ── GET /api/admin/dashboard ─────────────────────────
    public function dashboard(): void {
        $stats = $this->db->fetchOne("SELECT * FROM vw_DashboardStats");

        $recentOrders = $this->db->fetchAll(
            "SELECT TOP 10 o.order_id, o.status, o.total_amount, o.ordered_at,
                    c.full_name AS customer_name, p.method AS payment_method
             FROM Orders o
             JOIN Customers c ON o.customer_id = c.customer_id
             LEFT JOIN Payments p ON o.order_id = p.order_id
             ORDER BY o.ordered_at DESC");

        $weeklySales = $this->db->fetchAll(
            "SELECT CAST(ordered_at AS DATE) AS sale_date,
                    COUNT(*) AS orders, SUM(total_amount) AS revenue
             FROM Orders
             WHERE ordered_at >= DATEADD(DAY,-7,GETDATE()) AND status != 'cancelled'
             GROUP BY CAST(ordered_at AS DATE)
             ORDER BY sale_date ASC");

        $topProducts = $this->db->fetchAll(
            "SELECT TOP 5 pr.name, pr.image_url,
                    SUM(oi.quantity) AS units_sold,
                    SUM(oi.subtotal) AS revenue
             FROM Order_Items oi
             JOIN Products pr ON oi.product_id = pr.product_id
             JOIN Orders o ON oi.order_id = o.order_id
             WHERE o.status != 'cancelled'
             GROUP BY pr.product_id, pr.name, pr.image_url
             ORDER BY revenue DESC");

        $categoryBreakdown = $this->db->fetchAll(
            "SELECT c.name AS category,
                    COUNT(DISTINCT o.order_id) AS orders,
                    SUM(oi.subtotal) AS revenue
             FROM Order_Items oi
             JOIN Products pr ON oi.product_id = pr.product_id
             JOIN Categories c ON pr.category_id = c.category_id
             JOIN Orders o ON oi.order_id = o.order_id
             WHERE o.status != 'cancelled'
             GROUP BY c.category_id, c.name
             ORDER BY revenue DESC");

        success(compact('stats','recentOrders','weeklySales','topProducts','categoryBreakdown'));
    }

    // ── GET /api/admin/reports ───────────────────────────
    public function reports(): void {
        $period = $_GET['period'] ?? 'daily';
        if (!in_array($period, ['daily','weekly','monthly'])) error('Invalid period.');

        $groupBy = match($period) {
            'daily'   => "CAST(ordered_at AS DATE)",
            'weekly'  => "DATEPART(WEEK, ordered_at), YEAR(ordered_at)",
            'monthly' => "MONTH(ordered_at), YEAR(ordered_at)",
        };
        $label = match($period) {
            'daily'   => "CONVERT(VARCHAR, ordered_at, 23)",
            'weekly'  => "CONCAT('Week ', DATEPART(WEEK, ordered_at), ' ', YEAR(ordered_at))",
            'monthly' => "CONCAT(DATENAME(MONTH, ordered_at), ' ', YEAR(ordered_at))",
        };
        $since = match($period) {
            'daily'   => "DATEADD(DAY,-30,GETDATE())",
            'weekly'  => "DATEADD(WEEK,-12,GETDATE())",
            'monthly' => "DATEADD(MONTH,-12,GETDATE())",
        };

        $reports = $this->db->fetchAll(
            "SELECT $label AS period,
                    COUNT(*) AS total_orders,
                    SUM(total_amount) AS total_revenue,
                    AVG(total_amount) AS avg_order_value,
                    SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled
             FROM Orders
             WHERE ordered_at >= $since
             GROUP BY $groupBy
             ORDER BY MIN(ordered_at) ASC");

        success($reports);
    }

    // ── GET /api/admin/employees ─────────────────────────
    public function listEmployees(): void {
        $employees = $this->db->fetchAll(
            "SELECT e.employee_id, e.full_name, e.email, e.phone, e.salary, e.hire_date, e.is_active,
                    r.role_name
             FROM Employees e JOIN Roles r ON e.role_id = r.role_id
             ORDER BY e.hire_date DESC");
        success($employees);
    }

    // ── POST /api/admin/employees ────────────────────────
    public function createEmployee(): void {
        $body   = get_body();
        $name   = sanitize($body['full_name'] ?? '');
        $email  = strtolower(trim($body['email'] ?? ''));
        $phone  = sanitize($body['phone'] ?? '');
        $salary = (float)($body['salary'] ?? 0);
        $role_id= (int)($body['role_id'] ?? 3);
        $pass   = $body['password'] ?? '';

        if (!$name || !$email || !$pass) error('Name, email, and password are required.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) error('Invalid email.');
        if (!in_array($role_id, [2,3])) error('Invalid role. Use 2 (admin) or 3 (delivery).');

        $exists = $this->db->fetchOne("SELECT 1 FROM Employees WHERE email=?", [$email]);
        if ($exists) error('Email already registered.', 409);

        $this->db->execute(
            "INSERT INTO Employees (full_name, role_id, email, phone, salary, password_hash) VALUES (?,?,?,?,?,?)",
            [$name, $role_id, $email, $phone, $salary, hash_password($pass)]);

        success(['employee_id' => $this->db->lastInsertId()], 'Employee added.', 201);
    }

    // ── PUT /api/admin/employees/{id} ────────────────────
    public function updateEmployee(int $id): void {
        $body = get_body();
        $sets = []; $params = [];
        foreach (['full_name','email','phone','salary','is_active'] as $f) {
            if (array_key_exists($f, $body)) {
                $sets[] = "$f = ?";
                $params[] = in_array($f, ['full_name','email','phone']) ? sanitize((string)$body[$f]) : $body[$f];
            }
        }
        if (isset($body['password'])) {
            $sets[] = "password_hash = ?";
            $params[] = hash_password($body['password']);
        }
        if (empty($sets)) error('Nothing to update.');
        $sets[] = "updated_at = GETDATE()";
        $params[] = $id;
        $rows = $this->db->execute("UPDATE Employees SET " . implode(', ',$sets) . " WHERE employee_id=?", $params);
        if (!$rows) error('Employee not found.', 404);
        success(null, 'Employee updated.');
    }

    // ── DELETE /api/admin/employees/{id} ─────────────────
    public function deleteEmployee(int $id): void {
        $rows = $this->db->execute("UPDATE Employees SET is_active=0 WHERE employee_id=?", [$id]);
        if (!$rows) error('Employee not found.', 404);
        success(null, 'Employee deactivated.');
    }

    // ── GET /api/admin/customers ─────────────────────────
    public function listCustomers(): void {
        $search = $_GET['search'] ?? '';
        $limit  = min((int)($_GET['limit']  ?? 20), 100);
        $offset = (int)($_GET['offset'] ?? 0);

        $where = $search ? "WHERE full_name LIKE ? OR email LIKE ?" : '';
        $params = $search ? ["%$search%", "%$search%"] : [];

        $customers = $this->db->fetchAll(
            "SELECT c.customer_id, c.full_name, c.email, c.phone, c.address, c.is_active, c.created_at,
                    COUNT(o.order_id) AS total_orders,
                    ISNULL(SUM(o.total_amount), 0) AS lifetime_value
             FROM Customers c
             LEFT JOIN Orders o ON c.customer_id = o.customer_id
             $where
             GROUP BY c.customer_id, c.full_name, c.email, c.phone, c.address, c.is_active, c.created_at
             ORDER BY c.created_at DESC
             OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY",
            $params);
        success($customers);
    }

    // ── GET /api/admin/payments ──────────────────────────
    public function listPayments(): void {
        $status = $_GET['status'] ?? null;
        $where  = $status ? "WHERE p.status = ?" : '';
        $params = $status ? [$status] : [];
        $payments = $this->db->fetchAll(
            "SELECT p.payment_id, p.order_id, p.amount, p.method, p.status, p.paid_at,
                    c.full_name AS customer_name, c.email
             FROM Payments p
             JOIN Orders o ON p.order_id = o.order_id
             JOIN Customers c ON o.customer_id = c.customer_id
             $where
             ORDER BY p.created_at DESC",
            $params);
        success($payments);
    }

    // ── GET /api/admin/low-stock ─────────────────────────
    public function lowStock(): void {
        $threshold = (int)($_GET['threshold'] ?? LOW_STOCK_THRESHOLD);
        $products  = $this->db->fetchAll(
            "SELECT p.product_id, p.name, p.stock, p.image_url, c.name AS category
             FROM Products p
             JOIN Categories c ON p.category_id = c.category_id
             WHERE p.is_active = 1 AND p.stock < ?
             ORDER BY p.stock ASC",
            [$threshold]);
        success($products);
    }
}

// ── Router ───────────────────────────────────────────────
$ctrl   = new AdminController();
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts  = array_values(array_filter(explode('/', trim($uri, '/'))));
$section = $parts[2] ?? '';
$id      = isset($parts[3]) ? (int)$parts[3] : null;

match(true) {
    $section === 'dashboard'  && $method === 'GET'    => $ctrl->dashboard(),
    $section === 'reports'    && $method === 'GET'    => $ctrl->reports(),
    $section === 'employees'  && $method === 'GET'    => $ctrl->listEmployees(),
    $section === 'employees'  && $method === 'POST'   => $ctrl->createEmployee(),
    $section === 'employees'  && $method === 'PUT'    => $ctrl->updateEmployee($id),
    $section === 'employees'  && $method === 'DELETE' => $ctrl->deleteEmployee($id),
    $section === 'customers'  && $method === 'GET'    => $ctrl->listCustomers(),
    $section === 'payments'   && $method === 'GET'    => $ctrl->listPayments(),
    $section === 'low-stock'  && $method === 'GET'    => $ctrl->lowStock(),
    default                                           => error('Not found.', 404),
};
