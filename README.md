# LaraJob

A full-featured global job board built with Laravel 13 — portfolio-quality demonstration of professional PHP / Laravel development practices.

## Features

- **Three-role system** — Admin, Employer, and Candidate, each with a dedicated dashboard and enforced access control
- **Employer tools** — Company profile management; full job listing CRUD with status control (active / draft / closed)
- **Candidate tools** — Browse and search open positions; one-click apply with cover letter; application tracking dashboard; withdraw pending applications
- **Public job board** — Live search by keyword, employment type, and remote flag; company directory; individual job detail pages
- **AI features (optional, config-gated)** — Semantic job search, "similar jobs", candidate↔job match scores with narrative, and AI-assisted cover-letter / job-description drafting. The entire layer is **off by default** (`AI_PROVIDER=none`) and the app is fully functional without it; enable it in minutes with free local **Ollama** or with **OpenAI**. See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- **Email notifications** — Queued notifications when a candidate applies (employer notified) and when an employer updates a status (candidate notified)
- **Admin panel** — View all users, companies, and applications; manage role assignments
- **144-test suite** — PHPUnit tests covering auth flows, authorization boundaries, employer CRUD, candidate pipeline, public browsing rules, and the AI layer in **both** its enabled (Prism faked — zero real API calls) and disabled/degraded states

## Screenshots

> Add screenshots here once deployed. Suggested views: homepage hero, job index with filters, employer dashboard, candidate applications list.

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3 |
| Database | SQLite (zero-config development) |
| Templating | Blade |
| Frontend | Alpine.js 3, Tailwind CSS 3 |
| Build tool | Vite 8 |
| AI (optional) | Prism (`prism-php/prism`) → OpenAI or Ollama; disabled by default |
| Tests | PHPUnit 12 |
| Code style | Laravel Pint (PSR-12) |

## Local Setup

### Prerequisites

- PHP 8.3+
- Composer
- Node.js 18+

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/your-username/larajob.git
cd larajob

# 2. Install PHP dependencies
composer install

# 3. Set up the environment file
cp .env.example .env
php artisan key:generate

# 4. Create and seed the database
touch database/database.sqlite
php artisan migrate:fresh --seed

# 5. Build frontend assets
npm install
npm run build

# 6. Start the development server
php artisan serve
```

Open [http://localhost:8000](http://localhost:8000).

Email is set to the `log` driver by default — all outgoing mail is written to `storage/logs/laravel.log`, so no SMTP credentials are needed.

To process queued notifications (application alerts):

```bash
php artisan queue:work --stop-when-empty
```

To run the full dev environment (server + queue + Vite + log tail) in one command:

```bash
composer dev
```

### Enabling AI (optional)

The AI features are **off by default** — the app runs fully without any AI
configuration. To switch them on in a few minutes with free local **Ollama**
or with **OpenAI**, follow [docs/ARCHITECTURE.md → Enabling the AI layer](docs/ARCHITECTURE.md#enabling-the-ai-layer).
In short:

```dotenv
# .env — pick ONE
AI_PROVIDER=ollama      # free, local; also set OLLAMA_URL=http://localhost:11434
# AI_PROVIDER=openai    # set OPENAI_API_KEY=sk-...
```

```bash
php artisan config:clear
php artisan jobs:embed                      # backfill embeddings for existing jobs
php artisan queue:work --stop-when-empty    # process the embedding queue
```

## Running Tests

```bash
php artisan test
```

Tests use an in-memory SQLite database configured in `phpunit.xml` and do not touch your development database.

## Demo Accounts

All accounts use the password `password`.

### Admin

| Email | Password |
|---|---|
| admin@larajob.test | `password` |

### Employers

Each employer owns one company and 3–6 active job listings.

| Email | Company | Location |
|---|---|---|
| employer1@larajob.test | Apex Digital Solutions | San Francisco, CA, USA |
| employer2@larajob.test | Meridian Cloud Technologies | New York, NY, USA |
| employer3@larajob.test | Cobalt Software Group | London, UK |
| employer4@larajob.test | Stratos Labs | Berlin, Germany |
| employer5@larajob.test | Helix Data Systems | Toronto, Canada |
| employer6@larajob.test | Luminary Studio | Amsterdam, Netherlands |
| employer7@larajob.test | Pinnacle Tech | Sydney, Australia |
| employer8@larajob.test | Orion Analytics | Singapore |

### Candidates

Each candidate has a complete profile and 2–5 submitted applications.

| Email | Password |
|---|---|
| candidate1@larajob.test | `password` |
| candidate2@larajob.test | `password` |
| candidate3@larajob.test | `password` |
| candidate4@larajob.test | `password` |
| candidate5@larajob.test | `password` |
| candidate6@larajob.test | `password` |
| candidate7@larajob.test | `password` |
| candidate8@larajob.test | `password` |
| candidate9@larajob.test | `password` |
| candidate10@larajob.test | `password` |
| candidate11@larajob.test | `password` |
| candidate12@larajob.test | `password` |
| candidate13@larajob.test | `password` |
| candidate14@larajob.test | `password` |
| candidate15@larajob.test | `password` |

## Architecture Overview

### Data Model

```
User (role: admin | employer | candidate)
 ├── Company          (employer only; one per employer)
 │    └── Job         (table: job_listings; many per company)
 │         └── Application  (many per job; unique per candidate)
 └── CandidateProfile (candidate only; one per candidate)
```

> The job postings table is named `job_listings` instead of `jobs` to avoid colliding with Laravel's built-in queue tables. The model is still `App\Models\Job` with `protected $table = 'job_listings'`, so application code reads naturally.

### Key Laravel Patterns Used

| Pattern | Implementation |
|---|---|
| **Policies** | `JobPolicy` and `ApplicationPolicy` guard every mutating route — employers can only modify their own listings; candidates can only view their own applications |
| **Form Requests** | `StoreJobRequest`, `UpdateJobRequest`, `StoreApplicationRequest`, `UpdateStatusRequest` keep validation out of controllers |
| **Query Scopes** | `Job::active()` scope encapsulates `status = 'active' AND (expires_at IS NULL OR expires_at > now())`, reused across public listing, detail, and apply routes |
| **Notifications** | `NewApplicationReceived` (to employer) and `ApplicationStatusChanged` (to candidate) — both implement `ShouldQueue` and use the `mail` channel |
| **Middleware** | `CheckRole` (alias: `role`) guards role-specific route groups; returns 403 on a role mismatch |
| **Factories / Seeders** | Full factory coverage (`CompanyFactory`, `JobFactory`, `ApplicationFactory`, `CandidateProfileFactory`) with states (`active()`, `remote()`, `pending()`, `accepted()`, etc.); one `migrate:fresh --seed` populates the entire platform |
| **Tests** | 144 PHPUnit tests, 385 assertions; `RefreshDatabase` + `Notification::fake()` for isolation; authorization boundary tests deliberately send valid payloads to distinguish a 403 (policy) from a 422 (validation); the AI layer is tested both enabled (with Prism's fake — no real API calls) and disabled |
| **AI layer (optional)** | Provider-agnostic `AIService` behind an `AIProvider` contract (Prism → OpenAI/Ollama), queue-driven embeddings, cosine-similarity `VectorSearch`, and cached hybrid match scoring — all gated by `config('ai.enabled')` and off by default. Full write-up in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) |

## License

MIT — see [LICENSE](LICENSE).
