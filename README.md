## About the Project

This is a Laravel application that uses Shopify's GraphQL Admin API to update product variant costs, stock information, and pricing. It works as an embedded app, meaning it opens inside the Shopify Admin panel as an iframe.

### Application Demo
Visit https://shopify.saboproje.com and enter your Shopify store domain without https (e.g., product-editor-2.myshopify.com). Click "Connect Store" and you'll be redirected to the Shopify admin page where you can edit products. If you're not logged into Shopify, you'll need to do that first.

### Technical Approach

- **Laravel 12** - Backend framework
- **Shopify GraphQL Admin API 2024-10** - API version
- **Embedded App** - Runs inside Shopify Admin panel
- **Service Layer Pattern** - Business logic in services
- **HMAC Verification** - OAuth security
- **Encrypted Tokens** - Access tokens stored encrypted

## Requirements

- PHP 8.2+
- Composer
- MySQL
- Shopify Partner account (free)

## Installation

### 1. Clone the Project and Install Dependencies

```bash
git clone <repo-url>
cd shopify_cost_editor
composer install
npm install
```

### 2. Configure Environment File

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

Configure Shopify information in `.env`:

```env
SHOPIFY_API_KEY=your_api_key
SHOPIFY_API_SECRET=your_api_secret
SHOPIFY_API_VERSION=2024-10
SHOPIFY_SCOPES=read_products,write_products,read_inventory,write_inventory
SHOPIFY_REDIRECT_URI=http://localhost:8000/shopify/callback
```

### 3. Generate Application Key

```bash
php artisan key:generate
```

### 4. Run Database Migrations

For SQLite (development):

```bash
touch database/database.sqlite
php artisan migrate
```

For MySQL, configure database settings in `.env` and run:

```bash
php artisan migrate
```

If you encounter migration errors, you can load the `shopify_cost_editor.sql` file included in the project.

### 5. Start Development Server

```bash
php artisan serve
```

## Shopify Partner Dashboard Setup

### 1. Create App

1. Go to https://partners.shopify.com
2. "Apps" → "Create app" → "Custom app"
3. Enter app name (e.g., "Cost Editor")

### 2. OAuth Settings

**App URL**: `http://localhost:8000` (for development)

**Allowed redirection URL(s)**: `http://localhost:8000/shopify/callback`

### 3. API Credentials

From "Configuration" tab:
- **Client ID** (API Key) → Add to `.env` as `SHOPIFY_API_KEY`
- **Client secret** → Add to `.env` as `SHOPIFY_API_SECRET`

### 4. Scopes

Select the following from "Scopes" section:
- `read_products`
- `write_products`
- `read_inventory`
- `write_inventory`
- `read_locations`
- `write_locations`

### 5. Create Development Store

1. Partner Dashboard → "Development stores" → "Add store"
2. Create store and add test products
3. Then authorize the app we created.

Demo account: shopify.saboproje.com - you can connect your own Shopify store from here.

## Project Structure and Architecture

### Service Layer

The project uses service layer pattern. All Shopify API calls are in service classes:

```
app/Services/Shopify/
├── ShopifyService.php      # Base service - GraphQL calls
├── AuthService.php          # OAuth operations
├── ProductService.php       # Product listing and details
└── InventoryService.php     # Cost updates
```

**ShopifyService** acts as the base class managing all GraphQL calls. HTTP errors, GraphQL errors, and rate limit checking are done here.

### OAuth Flow

The OAuth flow works as follows:

1. **Installation** (`/shopify/install`)
   - Shop domain is retrieved
   - Random state is generated (40 characters)
   - State is saved to both DB and session
   - Shopify OAuth URL is created
   - If inside embedded app, popup opens; otherwise normal redirect

2. **Callback** (`/shopify/callback`)
   - HMAC verification is performed (`VerifyShopifyRequest` middleware)
   - State verification (DB + session)
   - Access token exchange
   - Shop record is created/updated
   - Access token is stored encrypted

3. **Embedded App URL**
   - After callback, redirects to Shopify embedded app URL
   - If inside popup, sends message to parent frame via `postMessage`

### Embedded App Structure

There's a special middleware for working as an embedded app: `ShopifyEmbeddedHeaders`

This middleware:
- Removes X-Frame-Options header
- Sets CSP (Content-Security-Policy) header
- Sets cookies as `SameSite=None; Secure`
- Adds Shopify domains as frame-ancestors

### Cost Update

Cost updates are done at Inventory Item level. Shopify's `inventoryItemUpdate` mutation is used:

```graphql
mutation UpdateInventoryItemCost($id: ID!, $input: InventoryItemInput!) {
  inventoryItemUpdate(id: $id, input: $input) {
    inventoryItem {
      id
      unitCost {
        amount
        currencyCode
      }
    }
    userErrors {
      field
      message
    }
  }
}
```

Cost value is sent directly as a decimal string (format expected by Shopify).

### Audit Log

All cost, price, and stock changes are logged to the `audit_logs` table. Model is `CostAuditLog`, table name is `audit_logs`.

Log structure:
- `field_type`: cost, price, stock
- `field_name`: cost, price, stock_on_hand, stock_available
- `old_value` / `new_value`: Generic values
- `old_cost` / `new_cost`: Specific cost fields (backward compatibility)
- `old_price` / `new_price`: Price fields
- `old_stock` / `new_stock`: Stock fields

### Rate Limit Handling

There's rate limit checking in ShopifyService. `X-Shopify-Shop-Api-Call-Limit` header is checked and if it reaches 80%, it waits 2 seconds.

### Error Handling

Custom exception: `ShopifyApiException`

Special messages for HTTP errors:
- 401: Unauthorized
- 403: Forbidden
- 429: Rate limit

GraphQL errors are parsed and logged.

## Database Structure

### shops
- `shop_domain`: Store domain (unique)
- `access_token`: Encrypted access token
- `scope`: OAuth scopes
- `installed_at` / `uninstalled_at`: Timestamps

### audit_logs
- `shop_id`: Foreign key
- `product_id` / `variant_id` / `inventory_item_id`: Shopify IDs
- `field_type` / `field_name`: Log type
- `old_value` / `new_value`: Generic values
- `old_cost` / `new_cost` / `old_price` / `new_price` / `old_stock` / `new_stock`: Specific fields
- `currency_code`: Currency

### oauth_states
- `state`: Random state string (40 characters)
- `shop_domain`: Associated shop
- `expires_at`: Expires after 10 minutes

## Middleware

### ShopifyAuth
Checks session. If shop domain is not in session, redirects to `/shopify/install`.

### VerifyShopifyRequest
Performs HMAC verification on OAuth callbacks. Uses `AuthService::verifyHmac()` method.

### ShopifyEmbeddedHeaders
Sets required headers for embedded app. CSP, X-Frame-Options, Cookie settings.

## Frontend

- **Blade Templates**: Laravel's view engine
- **Alpine.js**: Client-side interactivity (form validation, notifications)
- **Tailwind CSS**: Styling (via CDN)

### Product List
- Cursor-based pagination (Shopify GraphQL standard)
- Cost range is calculated for each product (from variants)

### Cost Edit
- Cost, price, and stock can be edited per variant
- Location-based stock viewing
- Inline validation (Alpine.js)
- Toast notifications (success/error)

## API Usage

### Product List

```php
$productService = new ProductService();
$result = $productService->getProducts($shopDomain, 20, $after);
```

In GraphQL query:
- `products` query
- Pagination with `pageInfo`
- Cost information is retrieved from variants and range is calculated

### Cost Update

```php
$inventoryService = new InventoryService();
$inventoryService->updateInventoryItemCost(
    $shopDomain,
    $inventoryItemId, // In GID format
    $costAmount,
    $currencyCode,
    $oldCost, // For audit log
    $productId,
    $variantId
);
```

## Deployment

### Development (Ngrok)

Use ngrok for quick testing:

```bash
# Terminal 1
php artisan serve

# Terminal 2
ngrok http 8000
```

Set ngrok URL in Shopify Partner Dashboard.

### Production

For CPanel deployment:
- `DEPLOYMENT_GUIDE.md` - With terminal
- `CPANEL_INSTALLATION.md` - Without terminal
- `HTACCESS_SETUP.md` - .htaccess settings

Important points:
- HTTPS is required (Shopify requirement)
- Session driver must be database
- Cookies must be SameSite=None and Secure

## Troubleshooting

### OAuth Callback Error

If HMAC verification fails:
- Check `SHOPIFY_API_SECRET` in `.env` file
- Make sure redirect URL is correct in Shopify

### Embedded App Not Showing

- Check if X-Frame-Options header is removed
- Check if CSP headers are correct
- Check if you're using HTTPS (Shopify requirement)

### Cost Update Not Working

- Make sure Inventory Item ID is in GID format (`gid://shopify/InventoryItem/123456789`)
- Make sure `write_inventory` scope is present
- Check Laravel logs: `storage/logs/laravel.log`

## Code Examples

### Running GraphQL Query

```php
$query = <<<'GRAPHQL'
    query GetProducts($first: Int!) {
      products(first: $first) {
        edges {
          node {
            id
            title
          }
        }
      }
    }
GRAPHQL;

$data = $this->executeGraphQL($shopDomain, $query, ['first' => 20]);
```

### Access Token Usage

Access tokens are stored encrypted in `Shop` model:

```php
$shop = Shop::where('shop_domain', $shopDomain)->first();
$accessToken = $shop->access_token; // Automatically decrypted
```

### HMAC Verification

```php
$params = $request->all();
$hmac = $request->get('hmac');

if (!$authService->verifyHmac($params, $hmac)) {
    abort(403, 'Invalid HMAC');
}
```

## Notes

- Using Laravel 12 (not Laravel 11)
- PHP 8.2+ required
- SQLite is sufficient for development, MySQL recommended for production
- Since it's an embedded app, it runs inside an iframe
- Session database driver must be used (cookie driver causes issues in iframe)

## License

This project was prepared for a case study.
