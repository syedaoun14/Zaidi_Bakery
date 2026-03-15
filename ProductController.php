<?php
// ============================================================
// controllers/ProductController.php
// GET    /api/products              — list all (with filters)
// GET    /api/products/{id}         — single product
// POST   /api/products              — create  [admin]
// PUT    /api/products/{id}         — update  [admin]
// DELETE /api/products/{id}         — delete  [admin]
// GET    /api/products/{id}/reviews — product reviews
// POST   /api/products/{id}/reviews — add review [customer]
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class ProductController {
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }

    // ── GET /api/products ────────────────────────────────
    public function index(): void {
        $cat     = $_GET['category'] ?? null;
        $search  = $_GET['search'] ?? null;
        $featured = $_GET['featured'] ?? null;
        $sort    = $_GET['sort'] ?? 'name';
        $order   = strtoupper($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $limit   = min((int)($_GET['limit'] ?? 20), 100);
        $offset  = (int)($_GET['offset'] ?? 0);

        $where = ["p.is_active = 1"];
        $params = [];

        if ($cat) {
            $where[] = "c.slug = ?";
            $params[] = $cat;
        }
        if ($search) {
            $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($featured) {
            $where[] = "p.is_featured = 1";
        }

        $sortMap = ['name' => 'p.name', 'price' => 'p.price', 'rating' => 'avg_rating', 'newest' => 'p.created_at'];
        $sortCol = $sortMap[$sort] ?? 'p.name';

        $whereStr = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT p.product_id, p.name, p.description, p.price, p.stock,
                       p.image_url, p.is_featured, p.badge, p.discount_pct,
                       c.name AS category, c.slug AS category_slug,
                       ISNULL(AVG(CAST(r.rating AS FLOAT)),0) AS avg_rating,
                       COUNT(DISTINCT r.rating_id) AS rating_count
                FROM Products p
                JOIN Categories c ON p.category_id = c.category_id
                LEFT JOIN Product_Ratings r ON p.product_id = r.product_id
                $whereStr
                GROUP BY p.product_id, p.name, p.description, p.price, p.stock,
                         p.image_url, p.is_featured, p.badge, p.discount_pct, c.name, c.slug
                ORDER BY $sortCol $order
                OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";

        $products = $this->db->fetchAll($sql, $params);

        // Count
        $countSql = "SELECT COUNT(DISTINCT p.product_id) AS total
                     FROM Products p
                     JOIN Categories c ON p.category_id = c.category_id
                     $whereStr";
        $total = (int)$this->db->fetchOne($countSql, $params)['total'];

        success(['products' => $products, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
    }

    // ── GET /api/products/{id} ───────────────────────────
    public function show(int $id): void {
        $product = $this->db->fetchOne(
            "SELECT p.*, c.name AS category, c.slug AS category_slug,
                    ISNULL(AVG(CAST(r.rating AS FLOAT)),0) AS avg_rating,
                    COUNT(DISTINCT r.rating_id) AS rating_count
             FROM Products p
             JOIN Categories c ON p.category_id = c.category_id
             LEFT JOIN Product_Ratings r ON p.product_id = r.product_id
             WHERE p.product_id = ? AND p.is_active = 1
             GROUP BY p.product_id, p.name, p.category_id, p.description, p.price,
                      p.stock, p.image_url, p.is_featured, p.badge, p.discount_pct,
                      p.weight_grams, p.is_active, p.created_at, p.updated_at, c.name, c.slug",
            [$id]);

        if (!$product) error('Product not found.', 404);
        success($product);
    }

    // ── POST /api/products ───────────────────────────────
    public function create(): void {
        require_auth(['admin']);
        $body = get_body();

        $name    = sanitize($body['name'] ?? '');
        $cat_id  = (int)($body['category_id'] ?? 0);
        $price   = (float)($body['price'] ?? 0);
        $stock   = (int)($body['stock'] ?? 0);
        $desc    = sanitize($body['description'] ?? '');
        $img     = sanitize($body['image_url'] ?? '');
        $badge   = sanitize($body['badge'] ?? '');
        $featured = (int)($body['is_featured'] ?? 0);

        if (!$name || !$cat_id || $price <= 0) error('Name, category, and price are required.');

        $this->db->execute(
            "INSERT INTO Products (name, category_id, description, price, stock, image_url, badge, is_featured)
             VALUES (?,?,?,?,?,?,?,?)",
            [$name, $cat_id, $desc, $price, $stock, $img, $badge ?: null, $featured]);

        $id = $this->db->lastInsertId();
        success(['product_id' => $id], 'Product created successfully.', 201);
    }

    // ── PUT /api/products/{id} ───────────────────────────
    public function update(int $id): void {
        require_auth(['admin']);
        $body = get_body();

        // Build dynamic SET
        $sets = []; $params = [];
        $fields = ['name','description','price','stock','image_url','badge','is_featured','is_active','discount_pct','category_id'];
        foreach ($fields as $f) {
            if (array_key_exists($f, $body)) {
                $sets[] = "$f = ?";
                $params[] = in_array($f, ['name','description','image_url','badge']) ? sanitize((string)$body[$f]) : $body[$f];
            }
        }
        if (empty($sets)) error('No fields to update.');
        $sets[] = "updated_at = GETDATE()";
        $params[] = $id;

        $rows = $this->db->execute("UPDATE Products SET " . implode(', ', $sets) . " WHERE product_id = ?", $params);
        if (!$rows) error('Product not found.', 404);
        success(null, 'Product updated.');
    }

    // ── DELETE /api/products/{id} ────────────────────────
    public function delete(int $id): void {
        require_auth(['admin']);
        // Soft delete
        $rows = $this->db->execute("UPDATE Products SET is_active = 0 WHERE product_id = ?", [$id]);
        if (!$rows) error('Product not found.', 404);
        success(null, 'Product removed.');
    }

    // ── GET /api/products/{id}/reviews ──────────────────
    public function reviews(int $id): void {
        $reviews = $this->db->fetchAll(
            "SELECT r.rating_id, r.rating, r.review, r.created_at,
                    c.full_name AS customer_name
             FROM Product_Ratings r
             JOIN Customers c ON r.customer_id = c.customer_id
             WHERE r.product_id = ?
             ORDER BY r.created_at DESC",
            [$id]);
        success($reviews);
    }

    // ── POST /api/products/{id}/reviews ─────────────────
    public function addReview(int $id): void {
        $auth = require_auth(['customer']);
        $body = get_body();
        $rating = (int)($body['rating'] ?? 0);
        $review = sanitize($body['review'] ?? '');

        if ($rating < 1 || $rating > 5) error('Rating must be between 1 and 5.');

        // Check purchased
        $purchased = $this->db->fetchOne(
            "SELECT 1 FROM Order_Items oi
             JOIN Orders o ON oi.order_id = o.order_id
             WHERE o.customer_id = ? AND oi.product_id = ? AND o.status = 'delivered'",
            [$auth['user_id'], $id]);
        if (!$purchased) error('You can only review products you have purchased.');

        try {
            $this->db->execute(
                "INSERT INTO Product_Ratings (product_id, customer_id, rating, review) VALUES (?,?,?,?)",
                [$id, $auth['user_id'], $rating, $review]);
            success(null, 'Review submitted!', 201);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                // Update existing
                $this->db->execute(
                    "UPDATE Product_Ratings SET rating=?, review=? WHERE product_id=? AND customer_id=?",
                    [$rating, $review, $id, $auth['user_id']]);
                success(null, 'Review updated!');
            }
            throw $e;
        }
    }
}

// ── Router ───────────────────────────────────────────────
$ctrl = new ProductController();
$method = $_SERVER['REQUEST_METHOD'];
$uri   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = array_filter(explode('/', trim($uri, '/')));
$parts = array_values($parts);

// /api/products/{id}/reviews
$id = isset($parts[2]) ? (int)$parts[2] : null;

match(true) {
    $method === 'GET'  && $id && isset($parts[3]) && $parts[3] === 'reviews' => $ctrl->reviews($id),
    $method === 'POST' && $id && isset($parts[3]) && $parts[3] === 'reviews' => $ctrl->addReview($id),
    $method === 'GET'    && !$id => $ctrl->index(),
    $method === 'GET'    && $id  => $ctrl->show($id),
    $method === 'POST'   && !$id => $ctrl->create(),
    $method === 'PUT'    && $id  => $ctrl->update($id),
    $method === 'DELETE' && $id  => $ctrl->delete($id),
    default => error('Endpoint not found.', 404),
};
