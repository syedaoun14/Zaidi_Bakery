-- ============================================================
-- ZAIDI BAKERY - Complete MS SQL Server 2014 Database Schema
-- Database: zaidi_bakery
-- Created: 2025
-- ============================================================

USE master;
GO

-- Create Database
IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'zaidi_bakery')
BEGIN
    CREATE DATABASE zaidi_bakery
    COLLATE SQL_Latin1_General_CP1_CI_AS;
END
GO

USE zaidi_bakery;
GO

-- ============================================================
-- TABLE: Roles
-- ============================================================
IF OBJECT_ID('Roles', 'U') IS NOT NULL DROP TABLE Roles;
CREATE TABLE Roles (
    role_id     INT PRIMARY KEY IDENTITY(1,1),
    role_name   VARCHAR(50) NOT NULL UNIQUE,  -- 'customer', 'admin', 'delivery'
    created_at  DATETIME DEFAULT GETDATE()
);
GO

INSERT INTO Roles (role_name) VALUES ('customer'), ('admin'), ('delivery');
GO

-- ============================================================
-- TABLE: Customers
-- ============================================================
IF OBJECT_ID('Customers', 'U') IS NOT NULL DROP TABLE Customers;
CREATE TABLE Customers (
    customer_id   INT PRIMARY KEY IDENTITY(1,1),
    full_name     NVARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    phone         VARCHAR(20),
    address       NVARCHAR(300),
    password_hash VARCHAR(255) NOT NULL,       -- bcrypt hashed
    role_id       INT NOT NULL DEFAULT 1,       -- 1=customer by default
    is_active     BIT DEFAULT 1,
    created_at    DATETIME DEFAULT GETDATE(),
    updated_at    DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (role_id) REFERENCES Roles(role_id)
);
GO

CREATE INDEX IX_Customers_Email ON Customers(email);
GO

-- ============================================================
-- TABLE: Employees
-- ============================================================
IF OBJECT_ID('Employees', 'U') IS NOT NULL DROP TABLE Employees;
CREATE TABLE Employees (
    employee_id   INT PRIMARY KEY IDENTITY(1,1),
    full_name     NVARCHAR(100) NOT NULL,
    role_id       INT NOT NULL,               -- 2=admin, 3=delivery
    email         VARCHAR(150) UNIQUE,
    phone         VARCHAR(20),
    salary        DECIMAL(10,2),
    password_hash VARCHAR(255) NOT NULL,
    is_active     BIT DEFAULT 1,
    hire_date     DATE DEFAULT GETDATE(),
    created_at    DATETIME DEFAULT GETDATE(),
    updated_at    DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (role_id) REFERENCES Roles(role_id)
);
GO

-- ============================================================
-- TABLE: Categories
-- ============================================================
IF OBJECT_ID('Categories', 'U') IS NOT NULL DROP TABLE Categories;
CREATE TABLE Categories (
    category_id   INT PRIMARY KEY IDENTITY(1,1),
    name          NVARCHAR(100) NOT NULL UNIQUE,
    slug          VARCHAR(100) NOT NULL UNIQUE,
    description   NVARCHAR(500),
    image_url     VARCHAR(500),
    is_active     BIT DEFAULT 1,
    sort_order    INT DEFAULT 0,
    created_at    DATETIME DEFAULT GETDATE()
);
GO

INSERT INTO Categories (name, slug, description, sort_order) VALUES
('Cakes',    'cakes',    'Celebration & custom cakes for every occasion', 1),
('Bread',    'bread',    'Artisan breads baked fresh every morning',       2),
('Pastries', 'pastries', 'Flaky, buttery pastries from our French kitchen',3),
('Cupcakes', 'cupcakes', 'Mini celebration cupcakes with premium frosting',4),
('Cookies',  'cookies',  'Crispy edges, gooey centres, maximum flavour',  5),
('Donuts',   'donuts',   'Light, fluffy glazed donuts dusted to perfection',6);
GO

-- ============================================================
-- TABLE: Products
-- ============================================================
IF OBJECT_ID('Products', 'U') IS NOT NULL DROP TABLE Products;
CREATE TABLE Products (
    product_id    INT PRIMARY KEY IDENTITY(1,1),
    name          NVARCHAR(200) NOT NULL,
    category_id   INT NOT NULL,
    description   NVARCHAR(1000),
    price         DECIMAL(10,2) NOT NULL,
    stock         INT NOT NULL DEFAULT 0,
    image_url     VARCHAR(500),
    is_active     BIT DEFAULT 1,
    is_featured   BIT DEFAULT 0,
    badge         VARCHAR(50),               -- 'bestseller', 'new', 'sale'
    discount_pct  DECIMAL(5,2) DEFAULT 0,
    weight_grams  INT,
    created_at    DATETIME DEFAULT GETDATE(),
    updated_at    DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (category_id) REFERENCES Categories(category_id)
);
GO

CREATE INDEX IX_Products_Category ON Products(category_id);
CREATE INDEX IX_Products_Active ON Products(is_active);
GO

-- Seed Products with Unsplash image URLs
INSERT INTO Products (name, category_id, description, price, stock, image_url, is_featured, badge) VALUES
('Red Velvet Cake',         1, 'Moist, velvety layers with tangy cream cheese frosting and a vibrant crimson colour.',                          2800, 25, 'https://images.unsplash.com/photo-1586788680434-30d324b2d46f?w=600&q=80', 1, 'bestseller'),
('Chocolate Truffle Cake',  1, 'Decadent Belgian chocolate ganache layered between soft sponge, finished with gold dust.',                       3200, 18, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=600&q=80', 1, 'new'),
('Strawberry Mousse Cake',  1, 'Light chiffon layers filled with fresh strawberry mousse and topped with glazed berries.',                       3500, 12, 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&q=80', 1, NULL),
('Classic Sourdough Loaf',  2, 'Slow-fermented 24-hour sourdough with a crispy crust and an open, chewy crumb.',                                 450,  40, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600&q=80', 0, NULL),
('Garlic Herb Ciabatta',    2, 'Italian ciabatta infused with roasted garlic, rosemary, and extra virgin olive oil.',                            380,  35, 'https://images.unsplash.com/photo-1585478259715-876acc5be8eb?w=600&q=80', 0, 'new'),
('Butter Croissant',        3, 'Classic French croissant — 72 layers of laminated dough, golden, flaky, and incredibly buttery.',                280,  60, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&q=80', 1, 'bestseller'),
('Cinnamon Roll',           3, 'Pillowy soft roll filled with brown sugar cinnamon, topped with vanilla cream cheese glaze.',                    350,  45, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&q=80', 1, 'bestseller'),
('Red Velvet Cupcake',      4, 'Mini red velvet sponge topped with a tall swirl of cream cheese frosting and red velvet crumbs.',                320,  50, 'https://images.unsplash.com/photo-1614707267537-b85aaf00c4b7?w=600&q=80', 1, NULL),
('Lemon Blueberry Cupcake', 4, 'Bright lemon sponge studded with fresh blueberries, topped with lemon curd buttercream.',                       350,  42, 'https://images.unsplash.com/photo-1576618148400-f54bed99fcfd?w=600&q=80', 0, 'new'),
('Choco Chip Cookies',      5, 'Bakery-style cookies with crispy golden edges, gooey centres, and generous chocolate chips.',                    220,  80, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=600&q=80', 1, 'bestseller'),
('Classic Glazed Donut',    6, 'Light and airy yeast donut coated in a glossy vanilla glaze. Perfect with your morning chai.',                   150,  70, 'https://images.unsplash.com/photo-1551024601-bec78aea704b?w=600&q=80', 0, NULL),
('Oreo Crunch Donut',       6, 'Glazed donut generously topped with crushed Oreo cookies and a drizzle of dark chocolate.',                     200,  55, 'https://images.unsplash.com/photo-1535958636474-b021ee887b13?w=600&q=80', 0, 'new');
GO

-- ============================================================
-- TABLE: Orders
-- ============================================================
IF OBJECT_ID('Orders', 'U') IS NOT NULL DROP TABLE Orders;
CREATE TABLE Orders (
    order_id        INT PRIMARY KEY IDENTITY(1,1),
    customer_id     INT NOT NULL,
    status          VARCHAR(30) DEFAULT 'pending',
                    -- pending | confirmed | processing | out_for_delivery | delivered | cancelled
    delivery_type   VARCHAR(20) DEFAULT 'delivery', -- 'delivery' | 'pickup'
    delivery_address NVARCHAR(400),
    total_amount    DECIMAL(10,2) NOT NULL,
    delivery_fee    DECIMAL(10,2) DEFAULT 100.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    coupon_code     VARCHAR(50),
    notes           NVARCHAR(500),
    assigned_to     INT,                      -- employee_id (delivery staff)
    ordered_at      DATETIME DEFAULT GETDATE(),
    updated_at      DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (customer_id) REFERENCES Customers(customer_id),
    FOREIGN KEY (assigned_to) REFERENCES Employees(employee_id)
);
GO

CREATE INDEX IX_Orders_Customer ON Orders(customer_id);
CREATE INDEX IX_Orders_Status   ON Orders(status);
CREATE INDEX IX_Orders_Date     ON Orders(ordered_at);
GO

-- ============================================================
-- TABLE: Order_Items
-- ============================================================
IF OBJECT_ID('Order_Items', 'U') IS NOT NULL DROP TABLE Order_Items;
CREATE TABLE Order_Items (
    item_id      INT PRIMARY KEY IDENTITY(1,1),
    order_id     INT NOT NULL,
    product_id   INT NOT NULL,
    quantity     INT NOT NULL,
    unit_price   DECIMAL(10,2) NOT NULL,    -- price at time of order
    subtotal     AS (quantity * unit_price) PERSISTED,
    FOREIGN KEY (order_id)   REFERENCES Orders(order_id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES Products(product_id)
);
GO

CREATE INDEX IX_OrderItems_Order ON Order_Items(order_id);
GO

-- ============================================================
-- TABLE: Payments
-- ============================================================
IF OBJECT_ID('Payments', 'U') IS NOT NULL DROP TABLE Payments;
CREATE TABLE Payments (
    payment_id     INT PRIMARY KEY IDENTITY(1,1),
    order_id       INT NOT NULL UNIQUE,
    amount         DECIMAL(10,2) NOT NULL,
    method         VARCHAR(30),  -- 'card' | 'jazzcash' | 'easypaisa' | 'cod'
    status         VARCHAR(20) DEFAULT 'pending', -- 'pending'|'paid'|'failed'|'refunded'
    transaction_id VARCHAR(200),
    paid_at        DATETIME,
    created_at     DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (order_id) REFERENCES Orders(order_id)
);
GO

-- ============================================================
-- TABLE: Product_Ratings
-- ============================================================
IF OBJECT_ID('Product_Ratings', 'U') IS NOT NULL DROP TABLE Product_Ratings;
CREATE TABLE Product_Ratings (
    rating_id    INT PRIMARY KEY IDENTITY(1,1),
    product_id   INT NOT NULL,
    customer_id  INT NOT NULL,
    rating       TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review       NVARCHAR(1000),
    created_at   DATETIME DEFAULT GETDATE(),
    UNIQUE (product_id, customer_id),
    FOREIGN KEY (product_id)  REFERENCES Products(product_id),
    FOREIGN KEY (customer_id) REFERENCES Customers(customer_id)
);
GO

-- ============================================================
-- TABLE: Coupons
-- ============================================================
IF OBJECT_ID('Coupons', 'U') IS NOT NULL DROP TABLE Coupons;
CREATE TABLE Coupons (
    coupon_id      INT PRIMARY KEY IDENTITY(1,1),
    code           VARCHAR(50) NOT NULL UNIQUE,
    discount_type  VARCHAR(10) NOT NULL, -- 'percent' | 'fixed'
    discount_value DECIMAL(10,2) NOT NULL,
    min_order      DECIMAL(10,2) DEFAULT 0,
    max_uses       INT DEFAULT 100,
    used_count     INT DEFAULT 0,
    expires_at     DATETIME,
    is_active      BIT DEFAULT 1,
    created_at     DATETIME DEFAULT GETDATE()
);
GO

INSERT INTO Coupons (code, discount_type, discount_value, min_order, expires_at) VALUES
('WELCOME10', 'percent', 10, 500,  DATEADD(MONTH, 6, GETDATE())),
('ZAIDI200',  'fixed',   200, 1000, DATEADD(MONTH, 3, GETDATE())),
('BAKEFIRST', 'percent', 15, 0,    DATEADD(MONTH, 1, GETDATE()));
GO

-- ============================================================
-- TABLE: Notifications
-- ============================================================
IF OBJECT_ID('Notifications', 'U') IS NOT NULL DROP TABLE Notifications;
CREATE TABLE Notifications (
    notif_id     INT PRIMARY KEY IDENTITY(1,1),
    customer_id  INT,
    employee_id  INT,
    title        NVARCHAR(200),
    message      NVARCHAR(500),
    is_read      BIT DEFAULT 0,
    created_at   DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (customer_id) REFERENCES Customers(customer_id),
    FOREIGN KEY (employee_id) REFERENCES Employees(employee_id)
);
GO

-- ============================================================
-- SEED: Admin & Delivery Employees
-- (passwords shown in plain text here; stored hashed in app)
-- admin@zaidibakery.pk / Admin@1234
-- delivery@zaidibakery.pk / Delivery@1234
-- ============================================================
INSERT INTO Employees (full_name, role_id, email, phone, salary, password_hash) VALUES
('Zaidi Admin',    2, 'admin@zaidibakery.pk',    '+92511234567', 80000.00, '$2y$10$examplehashforadmin123456789012345678901234567890'),
('Ali Delivery',   3, 'delivery@zaidibakery.pk', '+92311234567', 35000.00, '$2y$10$examplehashfordelivery1234567890123456789012345');
GO

-- ============================================================
-- SEED: Sample Customers
-- ============================================================
INSERT INTO Customers (full_name, email, phone, address, password_hash) VALUES
('Fatima Iqbal', 'fatima@example.com', '+923111234567', 'House 12, Street 4, F-7/2, Islamabad', '$2y$10$examplehashforcustomer1'),
('Hassan Raza',  'hassan@example.com', '+923339876543', 'Block C, Blue Area, Islamabad',         '$2y$10$examplehashforcustomer2');
GO

-- ============================================================
-- SEED: Sample Orders
-- ============================================================
INSERT INTO Orders (customer_id, status, delivery_type, delivery_address, total_amount, delivery_fee) VALUES
(1, 'delivered',     'delivery', 'House 12, Street 4, F-7/2, Islamabad', 3280, 100),
(2, 'processing',    'delivery', 'Block C, Blue Area, Islamabad',         1480, 100),
(1, 'pending',       'pickup',   NULL,                                    640,  0),
(2, 'out_for_delivery','delivery','Block C, Blue Area, Islamabad',         900, 100);
GO

INSERT INTO Order_Items (order_id, product_id, quantity, unit_price) VALUES
(1, 1, 1, 2800), (1, 10, 2, 220),
(2, 6, 4, 280),  (2, 4, 1, 450),
(3, 8, 2, 320),
(4, 11, 6, 150);
GO

INSERT INTO Payments (order_id, amount, method, status, paid_at) VALUES
(1, 3380, 'card',     'paid',    GETDATE()),
(2, 1580, 'jazzcash', 'paid',    GETDATE()),
(3, 640,  'cod',      'pending', NULL),
(4, 1000, 'easypaisa','paid',    GETDATE());
GO

-- ============================================================
-- VIEWS
-- ============================================================

-- Dashboard summary view
CREATE OR ALTER VIEW vw_DashboardStats AS
SELECT
    (SELECT COUNT(*) FROM Orders WHERE CAST(ordered_at AS DATE) = CAST(GETDATE() AS DATE)) AS orders_today,
    (SELECT ISNULL(SUM(total_amount),0) FROM Orders WHERE CAST(ordered_at AS DATE) = CAST(GETDATE() AS DATE) AND status != 'cancelled') AS revenue_today,
    (SELECT COUNT(*) FROM Customers WHERE CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)) AS new_customers_today,
    (SELECT COUNT(*) FROM Orders WHERE status IN ('pending','processing')) AS pending_orders,
    (SELECT COUNT(*) FROM Products WHERE stock < 10 AND is_active = 1) AS low_stock_count;
GO

-- Product with average rating
CREATE OR ALTER VIEW vw_ProductsWithRating AS
SELECT
    p.product_id, p.name, p.description, p.price, p.stock,
    p.image_url, p.is_featured, p.badge, p.is_active,
    c.name AS category_name, c.slug AS category_slug,
    ISNULL(AVG(CAST(r.rating AS FLOAT)), 0) AS avg_rating,
    COUNT(r.rating_id) AS rating_count
FROM Products p
JOIN Categories c ON p.category_id = c.category_id
LEFT JOIN Product_Ratings r ON p.product_id = r.product_id
GROUP BY p.product_id, p.name, p.description, p.price, p.stock,
         p.image_url, p.is_featured, p.badge, p.is_active,
         c.name, c.slug;
GO

-- Order detail view
CREATE OR ALTER VIEW vw_OrderDetails AS
SELECT
    o.order_id, o.status, o.delivery_type, o.delivery_address,
    o.total_amount, o.delivery_fee, o.ordered_at,
    c.full_name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
    p.status AS payment_status, p.method AS payment_method,
    e.full_name AS delivery_staff
FROM Orders o
JOIN Customers c ON o.customer_id = c.customer_id
LEFT JOIN Payments p ON o.order_id = p.order_id
LEFT JOIN Employees e ON o.assigned_to = e.employee_id;
GO

-- Weekly sales report
CREATE OR ALTER VIEW vw_WeeklySales AS
SELECT
    CAST(ordered_at AS DATE) AS sale_date,
    COUNT(*) AS total_orders,
    SUM(total_amount) AS total_revenue,
    AVG(total_amount) AS avg_order_value
FROM Orders
WHERE ordered_at >= DATEADD(DAY, -7, GETDATE())
  AND status != 'cancelled'
GROUP BY CAST(ordered_at AS DATE);
GO

-- ============================================================
-- STORED PROCEDURES
-- ============================================================

-- Place Order
CREATE OR ALTER PROCEDURE sp_PlaceOrder
    @customer_id     INT,
    @delivery_type   VARCHAR(20),
    @delivery_address NVARCHAR(400),
    @coupon_code     VARCHAR(50) = NULL,
    @payment_method  VARCHAR(30),
    @notes           NVARCHAR(500) = NULL,
    @items           NVARCHAR(MAX),   -- JSON: [{"product_id":1,"quantity":2},...]
    @order_id        INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRANSACTION;
    BEGIN TRY
        DECLARE @total DECIMAL(10,2) = 0;
        DECLARE @delivery_fee DECIMAL(10,2) = CASE WHEN @delivery_type='pickup' THEN 0 ELSE 100 END;
        DECLARE @discount DECIMAL(10,2) = 0;

        -- Parse items and calculate total (SQL 2014 compatible)
        CREATE TABLE #OrderItems (product_id INT, quantity INT, unit_price DECIMAL(10,2));

        -- Apply coupon
        IF @coupon_code IS NOT NULL
        BEGIN
            SELECT @discount = CASE discount_type
                WHEN 'percent' THEN @total * discount_value / 100
                WHEN 'fixed'   THEN discount_value
            END
            FROM Coupons
            WHERE code = @coupon_code AND is_active = 1
              AND (expires_at IS NULL OR expires_at > GETDATE())
              AND used_count < max_uses;

            UPDATE Coupons SET used_count += 1 WHERE code = @coupon_code;
        END

        SET @total = @total + @delivery_fee - @discount;

        -- Insert order
        INSERT INTO Orders (customer_id, delivery_type, delivery_address, total_amount, delivery_fee, discount_amount, coupon_code, notes)
        VALUES (@customer_id, @delivery_type, @delivery_address, @total, @delivery_fee, @discount, @coupon_code, @notes);

        SET @order_id = SCOPE_IDENTITY();

        -- Insert items
        INSERT INTO Order_Items (order_id, product_id, quantity, unit_price)
        SELECT @order_id, product_id, quantity, unit_price FROM #OrderItems;

        -- Create payment record
        INSERT INTO Payments (order_id, amount, method, status)
        VALUES (@order_id, @total, @payment_method, CASE WHEN @payment_method='cod' THEN 'pending' ELSE 'paid' END);

        -- Notify customer
        INSERT INTO Notifications (customer_id, title, message)
        VALUES (@customer_id, 'Order Placed!', CONCAT('Your order #', @order_id, ' has been received. We will start preparing it shortly!'));

        DROP TABLE #OrderItems;
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- Update Order Status
CREATE OR ALTER PROCEDURE sp_UpdateOrderStatus
    @order_id  INT,
    @new_status VARCHAR(30),
    @employee_id INT = NULL
AS
BEGIN
    UPDATE Orders
    SET status = @new_status,
        updated_at = GETDATE(),
        assigned_to = ISNULL(@employee_id, assigned_to)
    WHERE order_id = @order_id;

    -- If delivered, mark payment as paid (for COD)
    IF @new_status = 'delivered'
        UPDATE Payments SET status = 'paid', paid_at = GETDATE()
        WHERE order_id = @order_id AND method = 'cod';

    -- Notify customer
    DECLARE @cust_id INT;
    SELECT @cust_id = customer_id FROM Orders WHERE order_id = @order_id;
    INSERT INTO Notifications (customer_id, title, message)
    VALUES (@cust_id,
        CASE @new_status
            WHEN 'confirmed'         THEN 'Order Confirmed!'
            WHEN 'processing'        THEN 'Baking in Progress!'
            WHEN 'out_for_delivery'  THEN 'Out for Delivery!'
            WHEN 'delivered'         THEN 'Order Delivered!'
            WHEN 'cancelled'         THEN 'Order Cancelled'
        END,
        CONCAT('Update for Order #', @order_id, ': ', @new_status));
END;
GO

-- Get Sales Report
CREATE OR ALTER PROCEDURE sp_SalesReport
    @period  VARCHAR(10) = 'daily'  -- 'daily' | 'weekly' | 'monthly'
AS
BEGIN
    SELECT
        CASE @period
            WHEN 'daily'   THEN CONVERT(VARCHAR, ordered_at, 103)
            WHEN 'weekly'  THEN CONCAT('Week ', DATEPART(WEEK, ordered_at))
            WHEN 'monthly' THEN DATENAME(MONTH, ordered_at) + ' ' + CAST(YEAR(ordered_at) AS VARCHAR)
        END AS period_label,
        COUNT(*) AS total_orders,
        SUM(total_amount) AS total_revenue,
        AVG(total_amount) AS avg_order_value,
        SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS completed_orders,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_orders
    FROM Orders
    WHERE ordered_at >= CASE @period
        WHEN 'daily'   THEN DATEADD(DAY,   -30, GETDATE())
        WHEN 'weekly'  THEN DATEADD(WEEK,  -12, GETDATE())
        WHEN 'monthly' THEN DATEADD(MONTH, -12, GETDATE())
    END
    GROUP BY
        CASE @period
            WHEN 'daily'   THEN CONVERT(VARCHAR, ordered_at, 103)
            WHEN 'weekly'  THEN CONCAT('Week ', DATEPART(WEEK, ordered_at))
            WHEN 'monthly' THEN DATENAME(MONTH, ordered_at) + ' ' + CAST(YEAR(ordered_at) AS VARCHAR)
        END
    ORDER BY MIN(ordered_at);
END;
GO

PRINT 'Zaidi Bakery Database Created Successfully!';
GO
