# LaraJob

Global job board platform built on Laravel 13.

## Stack

- **Framework**: Laravel 13
- **Database**: SQLite (`database/database.sqlite`) — no MySQL/Postgres
- **Templating**: Blade
- **Frontend interactivity**: Alpine.js
- **Styling**: Tailwind CSS

## Folder Structure

Standard Laravel 13 layout:

- `app/Models` — Eloquent models (`User`, `Company`, `Job`, `Application`)
- `app/Http/Controllers` — controllers
- `database/migrations` — schema migrations
- `database/factories`, `database/seeders` — test/demo data
- `resources/views` — Blade templates
- `routes/web.php` — application routes

## Data Model Notes

- `users.role` — enum (`admin`, `employer`, `candidate`), default `candidate`.
- `companies` — belongs to a `User` (employer/owner).
- **Job listings table is named `job_listings`, not `jobs`.** Laravel's
  default queue system (`QUEUE_CONNECTION=database`) already owns the
  `jobs`, `job_batches`, and `failed_jobs` tables created by the
  `0001_01_01_000002_create_jobs_table.php` migration. To avoid colliding
  with the queue, job postings live in `job_listings` but are modeled by
  `App\Models\Job` (`protected $table = 'job_listings'`), so application
  code still reads naturally (`Job::create(...)`, `$company->jobs`, etc.).
- `applications` — links a `User` (candidate) to a `Job` listing.

## Email Notifications

Two queued notifications are in `app/Notifications/`:

- `NewApplicationReceived` — sent to the employer when a candidate submits an application.
- `ApplicationStatusChanged` — sent to the candidate when an employer updates their application status.

Both implement `ShouldQueue` and use the `mail` channel.

### Local development setup

Set `MAIL_MAILER=log` in `.env`. Emails are written to `storage/logs/laravel.log` — no SMTP credentials required.

Start the queue worker to process queued notifications:

```bash
php artisan queue:work
```

To run the worker once (consume available jobs and exit):

```bash
php artisan queue:work --stop-when-empty
```

## Coding Conventions

- PSR-12 coding style (enforced via Laravel Pint).
- English only for code, comments, and identifiers.
- Use Eloquent relationships (`hasMany`/`belongsTo`) instead of manual joins.
- Define `fillable` and `casts()` on every model.
- Migrations should be reversible (`down()` drops what `up()` creates).
