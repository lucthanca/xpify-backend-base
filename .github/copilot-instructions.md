# Copilot Instructions for Xpify Backend (Magento 2.4.5-p5)

## Project Overview
This is a **Shopify App backend** built on Magento 2.4.5-p5, primarily handling Shopify app integrations, webhooks, GraphQL APIs, and section builder functionality. The project serves as a multi-tenant platform for Shopify merchants using various custom modules.

## Core Architecture

### Module Structure
- **Xpify_*** modules: Core platform functionality (Auth, App, Webhook, Merchant, PricingPlan)
- **SectionBuilder_*** modules: Page builder components with GraphQL APIs
- **Eurus_Merchant** & **Mageplaza_*** modules: Third-party integrations
- Dependencies flow: `SectionBuilder_Core` → `Xpify_App/Auth/PricingPlan` → `Xpify_Core`

### Key Integration Points
- **Shopify API**: Uses `shopify/shopify-api` ^5.3 for app authentication and data sync
- **WebSocket Server**: Real-time communication via `app/code/SectionBuilder/wss/` (Node.js + uWebSockets)
- **Redis Pub/Sub**: Message queue between Magento and WebSocket server
- **GraphQL**: Primary API interface for frontend apps (`*GraphQl` modules)
- **Custom GraphQL Controller**: `Xpify\Core\GraphQl\Controller\GraphQl` overrides Magento's default with IP whitelisting and custom error handling

## Development Patterns

### Authentication & Security
```php
// Webhook endpoints disable CSRF (see Xpify\Webhook\Controller\Webhook\Index)
class Index implements IHttpAction, CsrfAwareActionInterface {
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException {
        return null; // Shopify webhooks require CSRF bypass
    }
}
```

### Module Registration
All custom modules use standard Magento registration:
```php
ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Xpify_Core', __DIR__);
```

### GraphQL Schema Convention
- Query resolvers: `*Query` classes (e.g., `AppQuery`)
- Mutation resolvers: `*Mutation` classes (e.g., `AppSaveMutation`)
- Schema files: `etc/schema.graphqls` in each `*GraphQl` module
- **App-scoped GraphQL**: Use `@app(id: "SectionBuilder")` directive to scope queries/types to specific apps
- **Authentication pattern**: Extend `AuthSessionAbstractResolver` for merchant session validation
- **Shopify ID handling**: Auto-convert `gid://shopify/App/{id}` to numeric ID in resolvers

### Webhook Registration Pattern
Webhooks are registered via virtual types in `di.xml`:
```xml
<virtualType name="Shopify_Webhooks_Topics_AppUninstalled" type="Xpify\Webhook\Model\WebhookTopic">
    <arguments>
        <argument name="topic" xsi:type="const">Shopify\Webhooks\Topics::APP_UNINSTALLED</argument>
        <argument name="handler" xsi:type="object">Xpify\Auth\Service\Webhooks\AppUninstalled</argument>
    </arguments>
</virtualType>
<type name="Xpify\Webhook\Service\WebhookHandlerRegister">
    <arguments>
        <argument name="webhookTopics" xsi:type="array">
            <item name="app_uninstalled" xsi:type="object">Shopify_Webhooks_Topics_AppUninstalled</item>
        </argument>
    </arguments>
</type>
```

## Critical Commands

### Installation & Setup
```bash
# Initial setup (requires auth.json from marketplace.magento.com)
composer install
find var generated vendor pub/static pub/media app/etc -type f -exec chmod g+w {} +
find var generated vendor pub/static pub/media app/etc -type d -exec chmod g+ws {} +

# Database installation
php8.1 bin/magento setup:install \
    --base-url="https://backend.com/" \
    --db-host="localhost" \
    --db-name="db_name" \
    --admin-user="admin" \
    --use-rewrites="1" \
    --backend-frontname="admin"

# Disable unnecessary modules (search engines disabled for performance)
php8.1 bin/magento mod:dis Magento_AdvancedSearch Magento_Search Magento_CatalogSearch
```

### Development Workflow
```bash
# Standard Magento commands
bin/magento cache:clean
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy

# Custom logging available via app/Helper/Helper.php
vadu_log($data); // Logs to var/log/vadu.log

# WebSocket server (requires Node.js)
cd app/code/SectionBuilder/wss
npm install
npm run dev  # Development with hot reload
npm run build && npm start  # Production

# Register webhooks for all merchants
bin/magento webhook:install
```

### WebSocket Integration
- **Server location**: `app/code/SectionBuilder/wss/` (TypeScript/Node.js)
- **Dependencies**: uWebSockets.js, Prisma, Redis client
- **Architecture**: Magento publishes to Redis → WSS subscribes → Forwards to authenticated WebSocket clients
- **Authentication**: Uses MySQL to verify merchant sessions before allowing WebSocket connections

## Environment Configuration

### Required PHP Extensions
- PHP 8.1 with: bcmath, ctype, curl, dom, gd, iconv, intl, mbstring, simplexml, soap, xsl, zip, sockets, mysql, bz2
- Memory limit: 768M, Max execution time: 18000s

### Infrastructure
- **Web server**: nginx with php8.1-fpm
- **Database**: MariaDB 10.4
- **Cache**: Varnish 6
- **Tunnel**: Cloudflare tunnel for local development (see `tunnel.yml`)

## Important Files
- `app/etc/config.php`: Module enable/disable status
- `app/Helper/Helper.php`: Custom logging utilities with Symfony VarDumper
- `nginx.conf.sample`: Production-ready nginx configuration
- `composer.json`: Magento 2.4.5-p5 with Shopify SDK dependencies
- `tunnel.yml`: Cloudflare tunnel configuration for local development
- `app/code/SectionBuilder/wss/`: WebSocket server with TypeScript/Node.js stack

## Testing & Quality
- PHPUnit config: `app/code/phpunit.xml.dist`
- Code standards: Uses Magento coding standards
- Real-time features: WebSocket server in `app/code/SectionBuilder/wss/` (Node.js)

## Project-Specific Patterns

### Error Handling & Logging
```php
// Use custom logger with Symfony VarDumper
vadu_log($data, $moreData); // Logs to var/log/vadu.log

// GraphQL error logging pattern
Logger::getLogger('graphql.log')->debug(__("Error message: %1", $exception->getMessage())->render());
```

### Shopify ID Conversion Pattern
```php
// Auto-convert Shopify GraphQL IDs to numeric IDs
if (str_starts_with($value, 'gid://shopify/App/')) {
    $value = str_replace('gid://shopify/App/', '', $value);
}
```

### Multi-tenant Context Initialization
```php
// Required before Shopify API calls
$app = $this->appOrException();
$this->contextInitializer->initialize($app);
```
