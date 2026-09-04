# Atlas Wallet

Atlas Wallet is a Laravel 12 and Vue 3 custodial wallet starter for fiat balances, crypto trading, KYC review, and multi-user administration.

The staged product plan is documented in [ROADMAP.md](ROADMAP.md). Versions 1–6 are included in this source package.

## Included security and accounting controls

- MySQL transactions and ordered row locks for balance mutations
- 18-decimal wallet, transaction, rate, and trade storage
- Required idempotency keys for trades, transfers, and administrator adjustments
- Per-currency balanced ledger journals with customer, treasury, fee-revenue, and external-clearing accounts
- KYC-gated crypto trading with encrypted document numbers and private file storage
- Locked KYC approval/rejection workflow
- Login, trading, transfer, KYC, and administrator endpoint throttling
- Expiry checks for fiat exchange rates and crypto reference prices
- Administrator activity and financial-operation audit logs
- Protected last-administrator and self-demotion rules
- Production-safe administrator provisioning with no built-in production password
- Signed email verification and TOTP two-factor authentication with one-time recovery codes
- Queued email/database notifications for transfers, trades, KYC decisions, and exports
- Signed, retried HTTPS webhooks with private-network/SSRF protection
- Restricted, expiring user data exports and private KYC storage
- Safe reversal workflow for standalone adjustments, deposits, and withdrawals
- Cached read models, health checks, operational commands, and scheduled cleanup
- Pending deposits and KYC/2FA-protected withdrawals with reserved balances
- Encrypted beneficiary allowlists and daily withdrawal limits
- Two-person movement approval and controlled settlement
- Single-use expiring trade quotes, compliance cases, and wallet-to-ledger reconciliation
- Provider-ready money-rail contract with a safe manual settlement driver

## Requirements

- PHP 8.2 or newer
- PHP extensions: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PDO MySQL, Tokenizer, and XML
- Composer 2
- Node.js 20.19+ or Node.js 22+
- npm 10+
- MySQL 8.0+
- A web server whose document root points to `public/`

## Fresh installation

### 1. Install dependencies

```bash
composer install --no-interaction
npm ci
```

### 2. Create the MySQL database

```sql
CREATE DATABASE atlas_wallet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'atlas_wallet'@'localhost' IDENTIFIED BY 'replace-with-a-long-password';
GRANT ALL PRIVILEGES ON atlas_wallet.* TO 'atlas_wallet'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Configure the application

Linux/macOS:

```bash
cp .env.example .env
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Update these values in `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wallet.example.com
APP_TIMEZONE=UTC

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=atlas_wallet
DB_USERNAME=atlas_wallet
DB_PASSWORD=replace-with-the-database-password

SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
WALLET_RATE_MAX_AGE_MINUTES=15
WALLET_MARKET_PRICE_MAX_AGE_MINUTES=5
WALLET_DEFAULT_PROFIT_PERCENTAGE=1.5000
WALLET_QUOTE_TTL_SECONDS=30
WALLET_ENHANCED_REVIEW_THRESHOLD=5000
WALLET_MONEY_RAIL=manual

ADMIN_NAME="Primary Administrator"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=replace-with-a-unique-12-character-or-longer-password

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=replace-me
MAIL_PASSWORD=replace-me
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=wallet@example.com
```

Never commit `.env` or reuse the administrator password elsewhere.

### 4. Generate the application key

```bash
php artisan key:generate
php artisan config:clear
```

The application key encrypts sessions and KYC document numbers. Back it up securely; losing it makes encrypted data unreadable.

### 5. Create and seed the database

```bash
php artisan migrate --seed --force
```

The normal seeder installs currencies, reference rates, the trading setting, and the administrator configured in `.env`. It does not install a predictable production account.

### 6. Build the Vue frontend

```bash
npm run build
```

### 7. Prepare Laravel directories

Linux:

```bash
chmod -R ug+rw storage bootstrap/cache
php artisan optimize
```

On Windows, give the web-server account modify permission for `storage` and `bootstrap/cache`.

### 8. Validate and run locally

```bash
php artisan wallet:check
php artisan serve
```

Open `http://127.0.0.1:8000` and sign in using the administrator credentials configured in `.env`.

For frontend development, run `npm run dev` in a second terminal.

### 9. Start background processing

Notifications, data exports, and webhook deliveries use Laravel's queue. Run a durable worker under Supervisor, systemd, or your hosting platform:

```bash
php artisan queue:work --tries=5 --backoff=30 --timeout=120
```

Run Laravel's scheduler every minute (replace the path with the deployed project path):

```cron
* * * * * cd /var/www/atlas-wallet && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler monitors health hourly, removes expired idempotency records daily, and warms wallet caches every 15 minutes.

## Optional local demo data

Demo credentials are available only through the explicit non-production seeder:

```bash
php artisan db:seed --class=DemoSeeder
```

- Administrator: `admin@atlas.test` / `password`
- Member: `member@atlas.test` / `password`

`DemoSeeder` refuses to run when `APP_ENV=production`.

## Updating an existing installation

Back up MySQL and `storage/app/private/kyc` first, enable maintenance mode, and run:

If an earlier demo seed was used, delete or rotate the passwords for `admin@atlas.test` and `member@atlas.test` before reopening the application.

```bash
php artisan down
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize
php artisan up
```

The hardening migration increases monetary precision, encrypts existing KYC document numbers, adds idempotency records, and creates ledger and audit tables. Keep the current `APP_KEY` during an upgrade.

## API requirements for money operations

Clients must send a unique header for every new transfer, trade, deposit, withdrawal, or adjustment:

```http
Idempotency-Key: 8ee76262-b1bd-4de4-85b4-bfc3498c56b1
```

Retry the same operation with the same key and identical request body. Never reuse a key for different request data.

## Account security and KYC

- New users must verify their email before wallet, KYC, trading, export, or administrator actions.
- Users can enable TOTP in **Security**. The setup secret can be entered into Google Authenticator, Microsoft Authenticator, 1Password, or another standard TOTP app.
- Crypto buy, sell, and crypto-to-crypto exchange remain unavailable until an administrator approves KYC.
- KYC files and exports live on the private local disk and are downloaded only through authorized controller actions.

## Trading and administrator controls

- Fiat-to-crypto is a buy, crypto-to-fiat is a sell, and crypto-to-crypto is an exchange.
- The administrator controls the percentage spread applied to the output amount and publishes fiat rates/reference crypto prices.
- Peer-to-peer transfers intentionally permit another user's wallet as the destination. Only the source wallet must belong to the sender; changing this to “own wallets only” would remove the requested multi-user transfer feature.
- Reversal is intentionally limited to standalone deposits, withdrawals, and adjustments. Linked transfer/trade legs must be reversed as a complete journal and are rejected by the endpoint.

## API and operations

- OpenAPI document: `/openapi.yaml`
- Health probe: `GET /api/health` (returns only status, time, and version)
- Environment validation: `php artisan wallet:check`
- Operational monitoring: `php artisan wallet:monitor`
- Wallet/ledger reconciliation: `php artisan wallet:reconcile`
- Queue status/failures: `php artisan queue:monitor database:default --max=100` and `php artisan queue:failed`
- User exports: `POST /api/exports`, then poll `GET /api/exports` and download the ready result within 24 hours.
- Administrator webhooks: `POST /api/admin/webhooks` with an HTTPS URL and one or more supported events. The signing secret is shown once; deliveries use `X-Atlas-Signature: sha256=<hmac>`.

Example webhook request:

```json
{
  "url": "https://events.example.com/atlas",
  "events": ["transfer.completed", "trade.completed", "kyc.approved"]
}
```

## Production deployment checklist

```bash
php artisan down
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
npm ci && npm run build
php artisan wallet:check
php artisan test
php artisan optimize
php artisan wallet:monitor
php artisan up
```

Configure TLS, a process manager for queue workers, the scheduler, automated encrypted database/file backups, centralized logs, uptime/error monitoring, dependency scanning, and tested restore procedures. Keep `APP_KEY` in a secrets manager and never rotate it without a migration plan for encrypted fields.

Financial users, wallets, KYC reviews, and ledger records use explicit status and immutable audit history instead of soft deletes. Soft deletion is not legal erasure and can break accounting uniqueness or hide records needed for reconciliation. Implement jurisdiction-specific retention/anonymization as a separately reviewed policy.

## Production boundary

This repository supplies the application ledger and workflow, not licensed custody or banking infrastructure. Before holding real customer funds, connect licensed custody/blockchain, payment, market-data, sanctions-screening, transaction-monitoring, and identity-verification providers. Add withdrawal address controls, maker/checker approvals, jurisdiction rules, reconciliation jobs, penetration testing, privacy/retention policies, and independent legal/compliance review.
