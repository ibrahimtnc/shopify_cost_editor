# .htaccess Files Setup Guide

This guide explains where to place 2 different .htaccess files.

---

## 📁 File Structure

Your project folder structure should be:

```
public_html/shopify-cost/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/              ← Laravel's public folder
│   ├── index.php
│   ├── .htaccess       ← This file ALREADY EXISTS (public_html_htaccess)
│   └── ...
├── resources/
├── routes/
├── storage/
├── vendor/
├── artisan
├── composer.json
└── .htaccess           ← To be added to main folder (root_htaccess)
```

---

## 🎯 Two Scenarios

### Scenario 1: Can Redirect Document Root to Public Folder (RECOMMENDED)

**If you can change Document Root from CPanel Subdomain Settings:**

1. **CPanel** → **Subdomains**
2. Find your subdomain
3. Change **Document Root** to: `/home/yourusername/public_html/shopify-cost/public`
4. Click **Save** button

**In this case:**
- ✅ Only `public/.htaccess` file is used
- ✅ You DON'T need to add `.htaccess` to main folder
- ✅ `public/.htaccess` file ALREADY EXISTS (comes with Laravel)
- ✅ You DON'T need to use `public_html_htaccess` file (it already exists)

**To do:**
- Nothing! Laravel's own `.htaccess` file is already in `public/` folder.

---

### Scenario 2: Cannot Change Document Root (Alternative)

**If you can't change Document Root in CPanel or subdomain automatically shows main folder:**

**In this case:**
1. You need to add `.htaccess` to main folder (public_html/shopify-cost)
2. This file will redirect all requests to `public/` folder

**Steps:**

#### 1. Copy `root_htaccess` File

1. **File Manager** → Navigate to `public_html/shopify-cost` folder
2. Create `.htaccess` file via **Upload** or **New File**
3. Copy content from `root_htaccess` file and paste into `.htaccess` file:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

#### 2. Check `public/.htaccess` File

There should be a `.htaccess` file in `public/` folder. If not:

1. **File Manager** → Navigate to `public_html/shopify-cost/public` folder
2. Copy content from `public_html_htaccess` file
3. Create `.htaccess` file in `public/` folder and paste content:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

---

## 📋 Summary Table

| Status | Document Root | Main Folder .htaccess | Public Folder .htaccess |
|-------|--------------|---------------------|------------------------|
| **Scenario 1** (Recommended) | `public/` folder | ❌ Not needed | ✅ Already exists |
| **Scenario 2** (Alternative) | Main folder | ✅ Use `root_htaccess` | ✅ Use `public_html_htaccess` |

---

## 🔍 How to Know Which Scenario You're In?

### Test 1: Check Subdomain Settings

1. **CPanel** → **Subdomains**
2. Find your subdomain
3. Look at **Document Root** column:
   - If you see `/home/username/public_html/shopify-cost/public` → **Scenario 1** ✅
   - If you see `/home/username/public_html/shopify-cost` → **Scenario 2** (root_htaccess needed)

### Test 2: URL Test

Open this address in browser:
```
https://shopify-cost.yourdomain.com
```

- ✅ **Works**: Configured correctly!
- ❌ **404 or error**: `.htaccess` file is missing or in wrong place

---

## ⚠️ Important Notes

1. **Laravel's own .htaccess**: `public/.htaccess` file comes with Laravel and usually already exists. Use `public_html_htaccess` if it doesn't exist.

2. **Main folder .htaccess**: Only needed when you can't change Document Root. This file redirects all requests to `public/` folder.

3. **File names**: 
   - We named them `root_htaccess` and `public_html_htaccess` in the project
   - You need to name them `.htaccess` in CPanel (starts with dot)

4. **Hidden files**: Enable "Show Hidden Files" option in File Manager (`.htaccess` files may be hidden)

---

## 🛠️ Step-by-Step Installation (For Scenario 2)

### Step 1: Add .htaccess to Main Folder

1. **File Manager** → Navigate to `public_html/shopify-cost` folder
2. Top right **Settings** → Check **Show Hidden Files**
3. **New File** → File name: `.htaccess`
4. Paste content from `root_htaccess` file:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

5. Click **Save** button

### Step 2: Check .htaccess in Public Folder

1. **File Manager** → Navigate to `public_html/shopify-cost/public` folder
2. Check if `.htaccess` file exists
3. **If not**: Copy content from `public_html_htaccess` file and paste here
4. **If exists**: No action needed

### Step 3: Test

Open in browser:
```
https://shopify-cost.yourdomain.com
```

If it works, success! 🎉

---

## 🐛 Troubleshooting

### Problem: "404 Not Found"
**Solution**: 
- Check if `.htaccess` file exists in main folder
- Check if `public/.htaccess` file exists
- Check if "Show Hidden Files" is active in File Manager

### Problem: "500 Internal Server Error"
**Solution**:
- There might be a syntax error in `.htaccess` file
- Check if mod_rewrite is active in CPanel
- Make sure PHP version is 8.1+

### Problem: "Laravel not working"
**Solution**:
- Make sure Document Root is redirected correctly
- Check if `public/index.php` file exists
- Check storage permissions (775)

---

## ✅ Conclusion

**Summary:**
- **Easiest way**: Redirect Document Root to `public/` folder (Scenario 1)
- **Alternative**: Add `.htaccess` to main folder (Scenario 2)
- In both cases, `public/.htaccess` file should exist (comes with Laravel)

Let me know if you have questions! 🚀
