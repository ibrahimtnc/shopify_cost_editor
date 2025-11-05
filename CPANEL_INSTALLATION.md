# CPanel Installation Guide (Without Terminal)

This guide helps you install the project on CPanel without terminal access. We'll only use File Manager and PHPMyAdmin.

---

## 📋 Requirements

- ✅ CPanel access
- ✅ File Manager
- ✅ PHPMyAdmin
- ✅ Subdomain (or domain)
- ✅ SSL certificate (Let's Encrypt - free)

---

## 🚀 Step 1: Subdomain and SSL Setup

### 1.1 Create Subdomain

1. **CPanel** → **Subdomains**
2. **Subdomain**: `shopify-cost` (or your preferred name)
3. **Domain**: Your main domain
4. **Document Root**: `/public_html/shopify-cost` (auto-created)
5. Click **Create** button

Example: `shopify-cost.yourdomain.com`

### 1.2 Install SSL Certificate

1. **CPanel** → **SSL/TLS Status**
2. Select your subdomain
3. Click **Run AutoSSL** button
4. Or manually install with **Let's Encrypt**

✅ **Important**: Shopify won't work without HTTPS!

---

## 📁 Step 2: Upload Project Files

### 2.1 Upload Files via File Manager

1. **CPanel** → **File Manager**
2. Navigate to `public_html/shopify-cost` folder
3. Upload all project files:
   - `app/` folder
   - `bootstrap/` folder
   - `config/` folder
   - `database/` folder
   - `public/` folder
   - `resources/` folder
   - `routes/` folder
   - `storage/` folder
   - `vendor/` folder (composer packages - included in project)
   - `artisan` file
   - `composer.json` file
   - `.htaccess` file (copy from public folder one level up)

### 2.2 Set Public Folder as Document Root

**Option A: From Subdomain Settings (Recommended)**

1. **CPanel** → **Subdomains**
2. Find your subdomain
3. Change **Document Root** to: `/home/yourusername/public_html/shopify-cost/public`

**Option B: Using .htaccess (If you can't change root)**

Create `.htaccess` file in main folder (public_html/shopify-cost):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

---

## 🗄️ Step 3: Create Database (PHPMyAdmin)

### 3.1 Create Database

1. **CPanel** → **MySQL Databases**
2. **Create New Database**: `shopify_cost` (or your preferred name)
3. Click **Create Database** button
4. Note the database name: `yourusername_shopify_cost`

### 3.2 Create Database User

1. Go to **MySQL Users** section on the same page
2. **Username**: `shopify_user` (or your preferred name)
3. **Password**: Create a strong password (note it down!)
4. Click **Create User** button
5. Note the user name: `yourusername_shopify_user`

### 3.3 Add User to Database

1. In **Add User To Database** section:
   - User: `yourusername_shopify_user`
   - Database: `yourusername_shopify_cost`
2. Click **Add** button
3. Select **ALL PRIVILEGES**
4. Click **Make Changes** button

---

## 📊 Step 4: Create Database Tables (SQL)

### 4.1 Access PHPMyAdmin

1. **CPanel** → **PHPMyAdmin**
2. Select the database you created (from left menu)

### 4.2 Go to SQL Tab

1. Click **SQL** tab from top menu
2. Execute the following SQL codes one by one:

#### Table 1: shops
```sql
CREATE TABLE `shops` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_domain` varchar(255) NOT NULL,
  `access_token` text NOT NULL,
  `scope` text DEFAULT NULL,
  `installed_at` timestamp NULL DEFAULT NULL,
  `uninstalled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shops_shop_domain_unique` (`shop_domain`),
  KEY `shops_shop_domain_index` (`shop_domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table 2: oauth_states
```sql
CREATE TABLE `oauth_states` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `state` varchar(255) NOT NULL,
  `shop_domain` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `oauth_states_state_unique` (`state`),
  KEY `oauth_states_state_index` (`state`),
  KEY `oauth_states_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table 3: audit_logs
```sql
CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` varchar(255) DEFAULT NULL,
  `variant_id` varchar(255) DEFAULT NULL,
  `inventory_item_id` varchar(255) NOT NULL,
  `field_type` varchar(20) DEFAULT NULL,
  `field_name` varchar(50) DEFAULT NULL,
  `old_value` decimal(10,2) DEFAULT NULL,
  `new_value` decimal(10,2) DEFAULT NULL,
  `old_cost` decimal(10,2) DEFAULT NULL,
  `new_cost` decimal(10,2) DEFAULT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `new_price` decimal(10,2) DEFAULT NULL,
  `old_stock` decimal(10,2) DEFAULT NULL,
  `new_stock` decimal(10,2) DEFAULT NULL,
  `currency_code` varchar(3) NOT NULL DEFAULT 'USD',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_shop_id_index` (`shop_id`),
  KEY `audit_logs_inventory_item_id_index` (`inventory_item_id`),
  KEY `audit_logs_field_type_index` (`field_type`),
  KEY `audit_logs_field_name_index` (`field_name`),
  KEY `audit_logs_created_at_index` (`created_at`),
  CONSTRAINT `audit_logs_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table 4: migrations (for Laravel)
```sql
CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Add migration records:
```sql
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('0001_01_01_000000_create_users_table', 1),
('0001_01_01_000001_create_cache_table', 1),
('0001_01_01_000002_create_jobs_table', 1),
('2025_11_04_122440_create_shops_table', 2),
('2025_11_04_122708_create_oauth_states_table', 2),
('2025_11_04_122708_create_audit_logs_table', 2);
```

---

## ⚙️ Step 5: Configure .env File

### 5.1 Create .env File

1. **File Manager** → Navigate to `public_html/shopify-cost` folder
2. Copy `.env.example` file
3. Rename the copy to `.env`

### 5.2 Edit .env File

Open `.env` file and fill in the following information:

```env
APP_NAME="Shopify Cost Editor"
APP_ENV=production
APP_KEY=base64:muEAod3oo7WcAThW0FWdKFehujIF1gODwcRtJRYCZcY=
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database Settings (from CPanel)
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=yourusername_shopify_cost
DB_USERNAME=yourusername_shopify_user
DB_PASSWORD=your_database_password

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Cache
CACHE_STORE=database

# Queue
QUEUE_CONNECTION=database

# Shopify Settings
SHOPIFY_API_KEY=your_api_key
SHOPIFY_API_SECRET=your_api_screet
SHOPIFY_API_VERSION=2024-10
SHOPIFY_SCOPES=read_products,write_products,read_inventory,write_inventory
SHOPIFY_REDIRECT_URI=https://yourdomain.com/shopify/callback
```

**Important Changes:**
- `APP_URL`: Your subdomain's full URL
- `DB_DATABASE`: Database name from CPanel
- `DB_USERNAME`: User name from CPanel
- `DB_PASSWORD`: Password you created
- `SHOPIFY_REDIRECT_URI`: Your subdomain's callback URL

### 5.3 Generate APP_KEY

If APP_KEY doesn't exist, edit `.env` file in File Manager:

**Note**: If there's no terminal in CPanel, you can create APP_KEY this way:

1. Make sure `artisan` file exists
2. Or use an online PHP key generator: https://www.random.org/strings/
3. Create a 32-character random string
4. Base64 encode it: https://www.base64encode.org/
5. Add to `.env` file: `APP_KEY=base64:your_encoded_key_here`

**Alternative**: Temporarily run `php artisan key:generate` locally and copy the key from `.env` file.

---

## 📂 Step 6: Storage and Cache Permissions

### 6.1 Set Permissions via File Manager

1. **File Manager** → Navigate to `public_html/shopify-cost/storage` folder
2. Right-click folder → **Change Permissions**
3. **Numeric Value**: Enter `775`
4. Check **Recurse into subdirectories**
5. Click **Change Permissions** button

Do the same for these folders:
- `storage/framework`
- `storage/logs`
- `bootstrap/cache`

### 6.2 public/storage Symlink (Optional)

1. Create a symlink named `storage` in `public` folder
2. This symlink should point to `../storage/app/public` folder
3. If you can't do this in CPanel File Manager, you can skip it for now

---

## 🔧 Step 7: Shopify Partner Dashboard Settings

### 7.1 Set Production URLs

1. **Shopify Partner Dashboard** → **Apps** → **Your App**
2. Go to **App setup** tab
3. **App URL**: 
   ```
   https://shopify-cost.yourdomain.com
   ```
4. **Allowed redirection URL(s)**:
   ```
   https://shopify-cost.yourdomain.com/shopify/callback
   ```
5. Click **Save** button

---

## ✅ Step 8: Testing

### 8.1 Open Application

Open this address in your browser:
```
https://shopify-cost.yourdomain.com
```

### 8.2 OAuth Test

1. Go to your development store's admin panel
2. **Apps** → **Develop apps** → Select your app
3. Click **Install** button
4. OAuth flow should start and app should be installed

### 8.3 Error Check

If you encounter errors:
1. **File Manager** → Check `storage/logs/laravel.log` file
2. Verify tables are created in PHPMyAdmin
3. Verify information in `.env` file

---

## 🐛 Troubleshooting

### Problem: "500 Internal Server Error"
**Solution**:
- Make sure `.env` file is configured correctly
- Check storage permissions (775)
- Check `storage/logs/laravel.log` file

### Problem: "Database connection failed"
**Solution**:
- Verify database and user are created in PHPMyAdmin
- Verify database information in `.env` file
- Make sure `DB_HOST=localhost`

### Problem: "Redirect URI mismatch"
**Solution**:
- Make sure Redirect URL in Shopify Partner Dashboard exactly matches the one in `.env` file
- Make sure you're using HTTPS

### Problem: "Class not found" or "Vendor autoload"
**Solution**:
- Make sure `vendor/` folder is uploaded
- If vendor folder doesn't exist, Composer might be installed in CPanel (Software section)

### Problem: "Storage directory not writable"
**Solution**:
- Set storage folder permissions to 775 in File Manager
- Also check permissions for `storage/framework` and `storage/logs` folders

---

## 📝 Checklist

- [ ] Subdomain created
- [ ] SSL certificate installed
- [ ] Project files uploaded
- [ ] Database created
- [ ] Database user created and connected
- [ ] SQL tables created (PHPMyAdmin)
- [ ] `.env` file created and configured
- [ ] Storage permissions set (775)
- [ ] Public folder configured
- [ ] URLs set in Shopify Partner Dashboard
- [ ] Application tested

---

## 💡 Additional Notes

- **Vendor Folder**: If `vendor/` folder doesn't exist and Composer is installed in CPanel, you might see a terminal icon in File Manager. You can run `composer install` from there.
- **PHP Version**: Make sure PHP 8.1+ is selected in CPanel (Select PHP Version)
- **Memory Limit**: Make sure PHP memory limit is sufficient (128M recommended)

---

You can run your project on CPanel without terminal by following this guide! 🎉
