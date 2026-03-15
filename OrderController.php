<?php
// ============================================================
// controllers/OrderController.php
// GET  /api/orders              — list (admin: all, customer: own)
// POST /api/orders              — place order [customer]
// GET  /api/orders/{id}         — order detail
// PUT  /api/orders/{id}/status  — update status [admin/delivery]
// GET  /api/orders/delivery     — delivery assignments [delivery]
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class OrderController {
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }

    // ── GET /api/orders ─────────────────────────────────
    public function index(): void {
        $auth   = require_auth();
        $role   = $auth['role'];
        $limit  = min((int)($_GET['limit'] ?? 20), 100);
        $offset = (int)($_GET['offset'] ?? 0);
        $status = $_GET['status'] ?? null;

        $where = []; $params = [];

        if ($role === 'customer') {
            $where[] = "o.customer_id = ?";
            $params[] = $auth['user_id'];
        } elseif ($role === 'delivery') {
            $where[] = "o.assigned_to = ?";
            $params[] = $auth['user_id'];
        }

        if ($status) { $where[] = "o.status = ?"; $params[] = $status; }
        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT o.order_id, o.status, o.delivery_type, o.delivery_address,
                       o.total_amount, o.delivery_fee, o.ordered_at,
                       c.full_name AS customer_name, c.phone AS customer_phone,
                       p.status AS payment_status, p.method AS payment_method
                FROM Orders o
                JOIN Customers c ON o.customer_id = c.customer_id
                LEFT JOIN Payments p ON o.order_id = p.order_id
                $whereStr
                ORDER BY o.ordered_at DESC
                OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";

        $orders = $this->db->fetchAll($sql, $params);

        // Attach items to each order
        foreach ($orders as &$order) {
            $order['items'] = $this->db->fetchAll(
                "SELECT oi.quantity, oi.unit_price, oi.subtotal,
                        pr.name AS product_name, pr.image_url, pr.product_id
                 FROM Order_Items oi
                 JOIN Products pr ON oi.product_id = pr.product_id
                 WHERE oi.order_id = ?",
                [$order['order_id']]);
        }

        $countSql = "SELECT COUNT(*) AS total FROM Orders o $whereStr";
        $total = (int)$this->db->fetchOne($countSql, $params)['total'];
        success(['orders' => $orders, 'total' => $total]);
    }

    // ── POST /api/orders ─────────────────────────────────
    public function create(): void {
        $auth = require_auth(['customer']);
        $body = get_body();

        $delivery_type    = in_array($body['delivery_type'] ?? '', ['delivery','pickup']) ? $body['delivery_type'] : 'delivery';
        $delivery_address = sanitize($body['delivery_address'] ?? '');
        $payment_method   = in_array($body['payment_method'] ?? '', ['card','jazzcash','easypaisa','cod']) ? $body['payment_method'] : 'cod';
        $coupon_code      = sanitize($body['coupon_code'] ?? '');
        $notes            = sanitize($body['notes'] ?? '');
        $items            = $body['items'] ?? [];

        if ($delivery_type === 'delivery' && !$delivery_address) error('Delivery address is required.');
        if (empty($items)) error('No items in order.');

        // Validate & price items
        $lineItems = [];
        $subtotal = 0;
        foreach ($items as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['quantity'] ?? 0);
            if ($pid < 1 || $qty < 1) continue;

            $product = $this->db->fetchOne(
                "SELECT product_id, name, price, stock FROM Products WHERE product_id = ? AND is_active = 1",
                [$pid]);
            if (!$product) error("Product ID $pid not found.");
            if ($product['stock'] < $qty) error("Insufficient stock for '{$product['name']}'.");

            $lineItems[] = ['product_id' => $pid, 'quantity' => $qty, 'unit_price' => $product['price']];
            $subtotal += $product['price'] * $qty;
        }
        if (empty($lineItems)) error('No valid items in order.');

        $delivery_fee = $delivery_type === 'pickup' ? 0 : DELIVERY_FEE;
        $discount = 0;

        // Coupon validation
        if ($coupon_code) {
            $coupon = $this->db->fetchOne(
                "SELECT * FROM Coupons WHERE code=? AND is_active=1 AND (expires_at IS NULL OR expires_at>GETDATE()) AND used_count<max_uses",
                [$coupon_code]);
            if (!$coupon) error('Invalid or expired coupon code.');
            if ($subtotal < $coupon['min_order']) error("Minimum order of PKR {$coupon['min_order']} required for this coupon.");

            $discount = $coupon['discount_type'] === 'percent'
                ? ($subtotal * $coupon['discount_value'] / 100)
                : $coupon['discount_value'];

            $this->db->execute("UPDATE Coupons SET used_count = used_count + 1 WHERE code = ?", [$coupon_code]);
        }

        $total = $subtotal + $delivery_fee - $discount;

        // Create order in transaction
        $this->db->getConn()->beginTransaction();
        try {
            $this->db->execute(
                "INSERT INTO Orders (customer_id, delivery_type, delivery_address, total_amount, delivery_fee, discount_amount, coupon_code, notes)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$auth['user_id'], $delivery_type, $delivery_address ?: null, $total, $delivery_fee, $discount, $coupon_code ?: null, $notes ?: null]);

            $order_id = (int)$this->db->lastInsertId();

            foreach ($lineItems as $li) {
                $this->db->execute(
                    "INSERT INTO Order_Items (order_id, product_id, quantity, unit_price) VALUES (?,?,?,?)",
                    [$order_id, $li['product_id'], $li['quantity'], $li['unit_price']]);

                // Decrease stock
                $this->db->execute(
                    "UPDATE Products SET stock = stock - ? WHERE product_id = ?",
                    [$li['quantity'], $li['product_id']]);
            }

            // Payment
            $pay_status = $payment_method === 'cod' ? 'pending' : 'paid';
            $paid_at    = $payment_method !== 'cod' ? 'GETDATE()' : null;
            $this->db->execute(
                "INSERT INTO Payments (order_id, amount, method, status) VALUES (?,?,?,?)",
                [$order_id, $total, $payment_method, $pay_status]);

            // Notification
            $this->db->execute(
                "INSERT INTO Notifications (customer_id, title, message) VALUES (?,?,?)",
                [$auth['user_id'], 'Order Placed!', "Your order #$order_id has been received. We'll start baking shortly!"]);

            $this->db->getConn()->commit();
            success(['order_id' => $order_id, 'total' => $total], 'Order placed successfully!', 201);
        } catch (\Throwable $e) {
            $this->db->getConn()->rollBack();
            error('Failed to place order: ' . $e->getMessage(), 500);
        }
    }

    // ── GET /api/orders/{id} ─────────────────────────────
    public function show(int $id): void {
        $auth = require_auth();
        $order = $this->db->fetchOne(
            "SELECT o.*, c.full_name AS customer_name, c.email, c.phone,
                    p.status AS payment_status, p.method AS payment_method, p.paid_at,
                    e.full_name AS delivery_staff_name
             FROM Orders o
             JOIN Customers c ON o.customer_id = c.customer_id
             LEFT JOIN Payments p ON o.order_id = p.order_id
             LEFT JOIN Employees e ON o.assigned_to = e.employee_id
             WHERE o.order_id = ?",
            [$id]);

        if (!$order) error('Order not found.', 404);

        // Customers can only see own orders
        if ($auth['role'] === 'customer' && (int)$order['customer_id'] !== $auth['user_id'])
            error('Access denied.', 403);

        $order['items'] = $this->db->fetchAll(
            "SELECT oi.*, pr.name AS product_name, pr.image_url
             FROM Order_Items oi
             JOIN Products pr ON oi.product_id = pr.product_id
             WHERE oi.order_id = ?",
            [$id]);

        success($order);
    }

    // ── PUT /api/orders/{id}/status ──────────────────────
    public function updateStatus(int $id): void {
        $auth = require_auth(['admin', 'delivery']);
        $body = get_body();
        $status = $body['status'] ?? '';
        $allowed = ['confirmed','processing','out_for_delivery','delivered','cancelled'];
        if (!in_array($status, $allowed)) error('Invalid status value.');

        // Delivery staff can only set out_for_delivery or delivered
        if ($auth['role'] === 'delivery' && !in_array($status, ['out_for_delivery','delivered']))
            error('Delivery staff can only update to "out_for_delivery" or "delivered".', 403);

        $rows = $this->db->execute("UPDATE Orders SET status=?, updated_at=GETDATE() WHERE order_id=?", [$status, $id]);
        if (!$rows) error('Order not found.', 404);

        if ($status === 'delivered') {
            $this->db->execute("UPDATE Payments SET status='paid', paid_at=GETDATE() WHERE order_id=? AND method='cod'", [$id]);
        }

        // Notify customer
        $order = $this->db->fetchOne("SELECT customer_id FROM Orders WHERE order_id=?", [$id]);
        $msgs = [
            'confirmed'        => 'Your order has been confirmed!',
            'processing'       => "We're baking your order now! 🔥",
            'out_for_delivery' => 'Your order is on the way! 🚚',
            'delivered'        => 'Your order has been delivered! Enjoy! 🎉',
            'cancelled'        => 'Your order has been cancelled.',
        ];
        $this->db->execute(
            "INSERT INTO Notifications (customer_id, title, message) VALUES (?,?,?)",
            [$order['customer_id'], "Order #$id Update", $msgs[$status] ?? '']);

        success(null, "Order status updated to '$status'.");
    }

    // ── GET /api/orders/delivery ─────────────────────────
    public function deliveryAssignments(): void {
        $auth = require_auth(['delivery']);
        $orders = $this->db->fetchAll(
            "SELECT o.order_id, o.status, o.delivery_address, o.total_amount, o.ordered_at,
                    c.full_name AS customer_name, c.phone AS customer_phone,
                    STRING_AGG(CONCAT(pr.name, ' x', oi.quantity), ', ') AS items_summary
             FROM Orders o
             JOIN Customers c ON o.customer_id = c.customer_id
             JOIN Order_Items oi ON o.order_id = oi.order_id
             JOIN Products pr ON oi.product_id = pr.product_id
             WHERE o.assigned_to = ? AND o.status IN ('confirmed','processing','out_for_delivery')
             GROUP BY o.order_id, o.status, o.delivery_address, o.total_amount, o.ordered_at,
                      c.full_name, c.phone
             ORDER BY o.ordered_at DESC",
            [$auth['user_id']]);
        success($orders);
    }
}

// ── Router ───────────────────────────────────────────────
$ctrl   = new OrderController();
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts  = array_values(array_filter(explode('/', trim($uri, '/'))));

$id     = isset($parts[2]) ? (int)$parts[2] : null;
$sub    = $parts[3] ?? null;

match(true) {
    $method === 'GET'  && !$id                        => $ctrl->index(),
    $method === 'POST' && !$id                        => $ctrl->create(),
    $method === 'GET'  && $id                         => $ctrl->show($id),
    $method === 'PUT'  && $id && $sub === 'status'    => $ctrl->updateStatus($id),
    $method === 'GET'  && isset($parts[2]) && $parts[2] === 'delivery' => $ctrl->deliveryAssignments(),
    default                                           => error('Not found.', 404),
};
