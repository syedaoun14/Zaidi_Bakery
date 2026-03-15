# 🎂 Zaidi Bakery — Full Stack Web Application

## Tech Stack
- **Frontend**: HTML5, CSS3, Vanilla JavaScript (Single Page Application)
- **Backend**: PHP 8.0+ (REST API)
- **Database**: Microsoft SQL Server 2014
- **Auth**: JWT (JSON Web Tokens) + bcrypt password hashing
- **Images**: Unsplash CDN (real product photography)

---

## 📁 Project Structure

```
zaidi_bakery/
├── index.html                   ← Main frontend (SPA)
├── .htaccess                    ← Apache URL rewriting
├── php/
│   ├── index.php                ← API Front Controller / Router
│   ├── config/
│   │   ├── database.php         ← SQL Server PDO connection
│   │   └── config.php           ← App config, helpers, JWT utils
│   └── controllers/
│       ├── AuthController.php   ← Register, Login, Me
│       ├── ProductController.php← CRUD products + reviews
│       ├── OrderController.php  ← Place order, track, update
│       └── AdminController.php  ← Dashboard, reports, employees
└── sql/
    └── zaidi_bakery_database.sql← Complete MS SQL Server 2014 schema
```

---

## ⚙️ Setup Instructions

### 1. Database Setup (MS SQL Server 2014)

Open **SQL Server Management Studio (SSMS)** and run:
```sql
-- Run the full schema file
zaidi_bakery/sql/zaidi_bakery_database.sql
```

This creates:
- All 9 tables (Customers, Employees, Products, Categories, Orders, Order_Items, Payments, Coupons, Notifications)
- All views (vw_DashboardStats, vw_ProductsWithRating, vw_OrderDetails)
- Stored procedures (sp_PlaceOrder, sp_UpdateOrderStatus, sp_SalesReport)
- Sample data (12 products, 6 categories, 2 employees, 2 customers)

### 2. Configure Database Connection

Edit `php/config/database.php`:
```php
define('DB_SERVER',   'localhost');     // Your SQL Server host/IP
define('DB_NAME',     'zaidi_bakery');
define('DB_USER',     'sa');            // Your SQL login
define('DB_PASSWORD', 'YourPassword!');
```

