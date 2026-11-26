# Pixelforge AI Image Studio

A full-stack asynchronous image-generation platform built with **Laravel 12**, **Vue 3 (TypeScript)**, **Inertia.js**, and **Tailwind CSS**, integrating with the **Pixelforge API**.

---

## Project Overview

Pixelforge is an asynchronous AI image provider simulation where generations are queued, processed in the background, and reported via webhooks or polling. Token counts (text variance, reasoning) are only finalized once generation completes, requiring dynamic upfront estimation and exact post-generation cost recording.

This application fulfills all required assessment criteria and bonus stretch goals:
1. **Generating with Upfront Estimation**: Calculates token counts and shows price estimates (+25% platform markup) *before* submission.
2. **Exact Money & Token Pricing**: Arbitrary-precision math (`bcmath` scale 9) with zero floating-point drift. Failed generations cost \$0.00.
3. **Live Generations Monitor**: Real-time status tracking (`queued`, `processing`, `succeeded`, `failed`) with auto-polling, image preview, 24h expiration countdown, and live cost audit.
4. **Zero-Reload Reactive Balance**: Header credit balance automatically updates in real time upon generation completion without refreshing the page.
5. **Robust Webhook Handling**: HMAC-SHA256 signature verification, replay protection (300s window), event deduplication by `event_id`, and immunity to out-of-order events.
6. **Admin Usage & Reconciliation Report** *(Stretch Goal #1)*: Operator dashboard reconciling daily user spend, provider costs, and net profit against Pixelforge's official `GET /v1/usage` source of truth.
7. **Credit Wallet & Balance Gating** *(Stretch Goal #2)*: User balance tracking, simulated top-up modal, and instant warnings/locking when balance hits `$0.00`.
8. **Server Protection**: Blocks direct `/index.php` access with middleware and Apache rewrite rules, displaying a custom 403 Forbidden page.

---

## How It Works

### 1. Upfront Pricing & Exact Decimal Engine
- **Token Pricing Rates** (per 1M tokens):
  - Input text: \$5.00
  - Input image: \$10.00 (765 tokens per reference image)
  - Output: \$40.00 (determined by quality & size matrix)
  - Reasoning: \$12.50 (low: 0–500, med: 200–2,000, high: 1,000–8,000 tokens)
- **Customer Pricing**: Users pay Pixelforge's cost **plus a 25% markup** (`provider_cost * 1.25`).
- **Precision**: Calculated using PHP `bcmath` (`bcmul`, `bcdiv`, `bcadd`, `bcsub`, scale 9) and stored as `decimal(16, 8)` to prevent floating-point drift.
- **Failures**: Failed generations (`generation.failed` or errors) are unbilled (`$0.000000`).

### 2. Image Generation Studio (`/generations/create`)
- Users specify prompt (up to 4,000 chars), dimensions (`1024x1024` Square, `1536x1024` Landscape, `1024x1536` Portrait), quality (`low`, `medium`, `high`), and up to 4 style reference image URLs.
- **Live Estimator Component**: Debounced live API calls calculate upfront text variance (±15%), output tokens, and reasoning bounds, displaying min, expected, and max price ranges before submission.
- **Test Keyword Shortcuts**: One-click pills to inject test triggers (`[succeed]`, `[fail]`, `[blocked]`, `[overloaded]`, `[slow]`, `[duplicate]`).

### 3. Generations Monitor & History (`/generations`)
- Displays generations ordered newest first with status badges:
  - `queued` (amber spinner)
  - `processing` (blue spinner)
  - `succeeded` (emerald badge + image preview)
  - `failed` (rose badge + error tooltip)
- **Background Polling Loop**: Active generations poll `/generations/{id}/poll` every 3 seconds, updating the card in place when completed.
- **Reactive Balance Refresh**: Polling responses deliver the fresh `user_balance`, broadcasting a `balance-updated` event that animates and updates the header balance without page reloads.

### 4. Resilient Webhooks (`POST /webhooks/pixelforge`)
- **Signature Verification**: Verifies `Pixelforge-Signature: t=<timestamp>,v1=<signature>` using `hash_equals(hash_hmac('sha256', "{$t}.{$body}", $secret))` and rejects timestamps older or newer than 300 seconds.
- **Deduplication**: Webhooks arrive at least once. The `webhook_events` table enforces unique `event_id` (`evt_...`); duplicate events return `204 No Content` immediately.
- **Out-of-Order Safety**: Terminal states (`succeeded` and `failed`) cannot be reverted by delayed or retried `generation.processing` events.
- **Early Arrival Resilience**: Reconciles by both `pixelforge_id` and metadata `idempotency_key` (UUID) in case the webhook arrives before the `POST` request resolves.

### 5. Operator Audit & Reconciliation (`/admin/usage`)
- Date range filter (default 14 days, UTC).
- Displays total generations, user spend (gross revenue), provider cost (COGS), and net profit (+25% margin).
- Automatically compares local daily provider costs against Pixelforge's `GET /v1/usage` API to ensure 100% reconciliation match.

### 6. User Balance & Zero-Balance Warning
- Every registered user starts with **\$10.00 in credits**.
- When balance reaches **\$0.00**:
  - A persistent zero-balance banner appears across the site.
  - The navigation credit pill turns red with an animated pulse.
  - The Image Studio form locks and displays an alert.
- Quick top-up modal allows adding \$5, \$10, \$25, or \$50 simulated credits instantly.

---

## Project Structure

```
├── app/
│   ├── Enums/                     # Strict Enums (Size, Quality, Status, TestingKeyword)
│   ├── Exceptions/                # PixelforgeApiException with error code classification
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AdminUsageController.php       # Operator reporting & reconciliation
│   │   │   ├── GenerationController.php       # Studio, history, estimation & polling
│   │   │   ├── PixelforgeWebhookController.php# Signature verification & deduplication
│   │   │   └── WalletController.php           # Simulated credit top-ups
│   │   └── Middleware/
│   │       └── PreventDirectIndexAccess.php   # Direct /index.php protection
│   ├── Models/                    # Generation, User, WebhookEvent
│   └── Services/
│       ├── GenerationService.php  # State machine, balance deduction & terminal guards
│       ├── PixelforgeClient.php   # Authenticated API client with retry & rate-limits
│       ├── PricingService.php     # Exact bcmath token costing & upfront estimates
│       └── UsageReportService.php # Aggregates & reconciles with GET /v1/usage
├── database/migrations/           # SQLite migrations for users, generations, webhook_events
├── resources/js/
│   ├── Components/Generation/     # CostEstimator.vue
│   ├── Layouts/                   # AuthenticatedLayout.vue with reactive balance stream
│   ├── Pages/
│   │   ├── Admin/Usage.vue        # Operator reconciliation dashboard
│   │   ├── Generations/Create.vue # Image Studio
│   │   ├── Generations/Index.vue  # History & Live Monitor
│   │   └── Welcome.vue            # Public landing & entry point
│   └── types/pixelforge.d.ts      # TypeScript interfaces
└── tests/                         # Feature and unit test suites (43 passing tests)
```

---

## Local Setup

### Prerequisites
- PHP 8.5+ with `bcmath` and `pdo_sqlite` extensions
- Composer 2.x
- Node.js 20+ & npm

### Installation Steps

1. **Clone the repository**:
   ```bash
   git clone https://github.com/dkusumah/pixelforge-assesment.git
   cd pixelforge-assesment
   ```

2. **Install PHP and Node dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Configure environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Add your Pixelforge credentials to `.env`:
   ```dotenv
   PIXELFORGE_BASE_URL=https://pixelforge.promptedit.dev/v1
   PIXELFORGE_API_KEY=your_pixelforge_api_key
   PIXELFORGE_WEBHOOK_SECRET=your_webhook_signing_secret
   ```

4. **Run database migrations**:
   ```bash
   php artisan migrate
   ```

5. **Build frontend assets**:
   ```bash
   npm run build
   # Or for active development:
   npm run dev
   ```

6. **Start local server**:
   ```bash
   php artisan serve
   ```
   Open `http://127.0.0.1:8000` in your browser.

7. **Webhook Local Tunnel (Optional)**:
   To receive live webhooks from Pixelforge during local development, expose your local port with a tunnel:
   ```bash
   # Using Cloudflare Tunnel, ngrok, or Expose:
   ngrok http 8000
   ```
   Configure the resulting public URL in your Pixelforge Webhooks dashboard:
   `https://<your-tunnel-subdomain>/webhooks/pixelforge`

---

## Production Server Deployment (Self-Hosted)

For deploying to your own Linux server (Ubuntu/Debian, VPS, or dedicated server):

### 1. Initial Server Setup
1. Clone the repository into `/var/www/pixelforge`:
   ```bash
   git clone https://github.com/dkusumah/pixelforge-assesment.git /var/www/pixelforge
   cd /var/www/pixelforge
   ```
2. Copy and configure production `.env`:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain.com`, and your **production Pixelforge API Key & Webhook Secret**.

3. Set up permissions:
   ```bash
   sudo chown -R www-data:www-data /var/www/pixelforge
   sudo chmod -R 775 /var/www/pixelforge/storage /var/www/pixelforge/bootstrap/cache
   ```

### 2. Nginx & SSL Configuration
Copy `nginx.conf.example` to your Nginx sites directory:
```bash
sudo cp nginx.conf.example /etc/nginx/sites-available/pixelforge
sudo ln -s /etc/nginx/sites-available/pixelforge /etc/nginx/sites-enabled/
# Issue SSL with Let's Encrypt Certbot:
sudo certbot --nginx -d your-domain.com
sudo systemctl reload nginx
```

### 3. Background Queue Worker (Supervisor)
Copy `supervisor.conf.example` to ensure background jobs and retries run reliably:
```bash
sudo cp supervisor.conf.example /etc/supervisor/conf.d/pixelforge-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### 4. Zero-Downtime Updates with `deploy.sh`
To deploy code updates in the future, simply run:
```bash
./deploy.sh
```

---

## Running Automated Tests

Run the complete test suite (43 passing tests covering pricing, worked examples, webhooks, deduplication, balance deductions, and access protection):

```bash
php artisan test
```
