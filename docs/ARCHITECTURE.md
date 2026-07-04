# LaraJob Architecture

LaraJob is a global job board platform built on Laravel 13. This document
describes the stack, folder structure, data model, and coding conventions,
followed by a deep dive into the AI service layer (semantic search, job
embeddings, and candidate/job match scoring).

## Stack

- **Framework**: Laravel 13
- **Database**: SQLite (`database/database.sqlite`) — no MySQL/Postgres
- **Templating**: Blade
- **Frontend interactivity**: Alpine.js
- **Styling**: Tailwind CSS
- **AI integration** (optional, disabled by default): [Prism](https://prismphp.com) (`prism-php/prism`), talking to OpenAI or Ollama

## Folder Structure

Standard Laravel 13 layout, plus a dedicated AI service layer:

- `app/Models` — Eloquent models (`User`, `Company`, `Job`, `Application`, `CandidateProfile`)
- `app/Http/Controllers` — controllers
- `app/Services/AI` — the AI service layer: `AIService`, `MatchService`, `JobSearchService`, `VectorSearch`, draft generators, and their contracts (`Contracts/AIProvider`, `Contracts/VectorSearch`)
- `app/Jobs` — queued jobs (`GenerateJobEmbedding`, `ComputeApplicationMatch`)
- `app/Observers` — model observers (`JobObserver`, which queues embedding generation)
- `app/Console/Commands` — Artisan commands (`EmbedJobs` for backfilling embeddings)
- `app/Notifications` — queued email notifications
- `database/migrations` — schema migrations
- `database/factories`, `database/seeders` — test/demo data
- `resources/views` — Blade templates
- `routes/web.php` — application routes
- `config/ai.php`, `config/prism.php` — AI provider configuration

## Data Model

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
- `candidate_profiles` — one per candidate `User`; holds headline, bio,
  skills, and the other fields used to score a candidate against a job (see
  [Match scoring](#match-scoring-candidate--job) below).

## Coding Conventions

- PSR-12 coding style (enforced via Laravel Pint).
- English only for code, comments, and identifiers.
- Use Eloquent relationships (`hasMany`/`belongsTo`) instead of manual joins.
- Define `fillable` and `casts()` on every model.
- Migrations should be reversible (`down()` drops what `up()` creates).
- Frontend follows the design tokens in [`docs/DESIGN.md`](DESIGN.md) — use
  `brand-*` (never `indigo-*`), the `<x-ui.*>` component library, and the shadow
  scale rather than one-off values.

## Email Notifications

Two queued notifications live in `app/Notifications/`:

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

---

# AI Service Layer

LaraJob's AI integration powers semantic job search, "similar jobs",
candidate↔job match scoring, and AI-assisted drafting (cover letters, job
descriptions). The whole layer is **optional and disabled by default** — the
app is fully functional with zero AI configuration.

## Layers

```
Feature code (search, matching, etc.)
        |
        v
App\Services\AI\Contracts\AIProvider   <-- depend on this, never on Prism
        |
        v
App\Services\AI\AIService              <-- the only class that talks to Prism
        |
        v
Prism (prism-php/prism)  --->  OpenAI / Ollama
```

`config/ai.php` is the single switch. `AI_PROVIDER` in `.env` is one of
`none` (default), `openai`, or `ollama`. `config('ai.enabled')` is computed
once, in that file, and is `true` only when a real provider is selected
**and** that provider's credentials are present (`OPENAI_API_KEY` for
OpenAI, `OLLAMA_URL` for Ollama). With `AI_PROVIDER=none`, the app boots
and runs fully — no API key required.

Nothing in feature code branches on the provider name. Swapping providers
is a `.env` change only.

### Component map

How the pieces fit together — entry points on the left, the single provider
boundary (`AIService`) in the middle, external models on the right:

```
 ENTRY POINTS                 SERVICES (depend on contracts)          BOUNDARY            EXTERNAL
 ───────────                  ──────────────────────────────         ─────────           ────────

 JobObserver ─┐
 EmbedJobs cmd ┼─dispatch──▶ [queue] GenerateJobEmbedding ──┐
 (job created/ │                                            │
  edited)      │             [queue] ComputeApplicationMatch┼──┐
              │                       (on apply, warms cache)  │
 PublicJobController ──────▶ JobSearchService ──┐              │
   (search / similar)                           ├─▶ VectorSearch (cosine, in-PHP)
 Candidate/Employer ───────▶ MatchService ──────┘              │
   match endpoints                │                            │
                                  ├──────────────────embed()/chat()─────▶ AIProvider
 Candidate apply form ─────▶ CoverLetterDraftService ──┐       │          (interface)
 Employer job form ───────▶ JobDescriptionDraftService ┴───────┘              │
                                                                              ▼
                                                                          AIService ──▶ Prism ──▶ OpenAI
                                                                          (only Prism                 │
                                                                           caller)                    └─▶ Ollama

 Cache (Laravel cache store): query embeddings (5 min), profile/job
 embeddings (24 h), and (profile,job) MatchResults (24 h) — keyed by model
 + record updated_at, so a warm pair is never rescored. See "Caching" below.
```

Two contracts define the seams: **`AIProvider`** (embed/chat, the only thing
that knows about Prism) and **`VectorSearch`** (ranking, the only thing that
knows how similarity is computed). Every service depends on those interfaces,
never on `AIService`, Prism, or Eloquent vector queries directly — which is
what makes both the provider switch and the future vector-DB swap one-line
container rebinds (see [Scaling](#scaling-to-a-dedicated-vector-database)).

## Enabling the AI layer

The app ships with `AI_PROVIDER=none` and runs fully without it. To turn the
AI features on, pick **one** provider below. Both are config-only — no code
changes — and after enabling, embeddings must be backfilled once.

### Option A — Ollama (free, local, recommended for trying it out)

[Ollama](https://ollama.com) runs open models on your own machine: no API
key, no cost, nothing leaves your laptop. Ideal for trying the AI features
out in a few minutes.

1. **Install Ollama** — download from <https://ollama.com/download> (macOS,
   Windows, Linux), or on macOS `brew install ollama`. Start it; it listens
   on `http://localhost:11434` by default.

2. **Pull the two models** the app uses (chat + embeddings):

   ```bash
   ollama pull llama3.2          # chat: match narratives, cover letters, JDs
   ollama pull nomic-embed-text  # embeddings: semantic search & match scores
   ```

3. **Point `.env` at Ollama** and clear config cache:

   ```dotenv
   AI_PROVIDER=ollama
   OLLAMA_URL=http://localhost:11434
   # Optional overrides (these are the defaults):
   # AI_OLLAMA_CHAT_MODEL=llama3.2
   # AI_OLLAMA_EMBEDDING_MODEL=nomic-embed-text
   ```

   ```bash
   php artisan config:clear
   ```

4. **Backfill embeddings and run the queue** (see below). Visit the job board
   and search — the "Smart search" badge and match cards now appear.

`config('ai.enabled')` becomes `true` as soon as `AI_PROVIDER=ollama` and
`OLLAMA_URL` is set, so no key is required.

### Option B — OpenAI (managed, recommended for production)

Best quality with zero local setup; needs an API key and incurs (small)
usage cost.

1. **Get an API key** at <https://platform.openai.com/api-keys>.

2. **Configure `.env`** and clear config cache:

   ```dotenv
   AI_PROVIDER=openai
   OPENAI_API_KEY=sk-...
   # Optional overrides (these are the defaults):
   # AI_OPENAI_CHAT_MODEL=gpt-4o-mini
   # AI_OPENAI_EMBEDDING_MODEL=text-embedding-3-small
   ```

   ```bash
   php artisan config:clear
   ```

3. **Backfill embeddings and run the queue** (see below).

**Recommended models & cost notes.** The defaults are chosen to be cheap and
more than good enough for this workload:

| Use | Model | Why |
|---|---|---|
| Embeddings | `text-embedding-3-small` (1536-dim) | A few cents per *million* tokens; one short vector per job/profile. |
| Chat/narrative | `gpt-4o-mini` | Lowest-cost chat model; output is short, strict JSON or a single draft. |

Cost stays tiny because the design minimizes calls: job embeddings are
generated **once** per create/edit (not per view), match narratives and query
embeddings are **cached** (24 h / 5 min), and every model call passes through
the cost controls below. A board with a few thousand jobs embeds for well
under a dollar; day-to-day search/match traffic is mostly cache hits. You can
swap in larger models (`gpt-4o`, `text-embedding-3-large`) purely via the env
overrides above if you want higher quality.

### Cost & abuse controls

Now that real users trigger embeddings, CV parsing, match explanations and job
chat, four layers keep spend bounded — all in `config/ai.php` and
`App\Services\AI\AICostGuard`. Every layer degrades to the existing
none-provider fallback; nothing errors the user out of a page.

1. **Caching (first line of defense).** Match explanations and per-candidate
   summaries are cached by a `(profile embedding version, job version, models)`
   key, so repeat views never re-hit the API — only the first computation for a
   given pair spends. Editing unrelated profile fields (phone, LinkedIn) does
   not invalidate. Verified by tests (`AICostControlTest`, `MatchExplainTest`).

2. **Per-user daily caps** (`ai.limits.*`, enforced by `AICostGuard::allow()`).
   Once an actor (authenticated user, or IP for the public ask-about-job chat)
   spends their daily allowance for a feature, that feature quietly drops to its
   rule-based fallback — rule-based match explanation, template job description,
   keyword bias scan, a short "limit reached" chat reply — for the rest of the
   day. Per-minute **burst** limiters (`throttle:ai-ask|ai-draft|ai-explain` in
   `AppServiceProvider`) sit in front as a second guard.

3. **CV re-parse debounce** (`ai.limits.cv-parse.debounce_minutes`). Re-uploading
   the exact same resume file (matched by content hash) within the window stores
   the file but skips a redundant parse; a per-user daily cap
   (`cv-parse.per_day`) bounds it further. The profile save always succeeds.

4. **Soft global budget** (`AI_DAILY_CALL_BUDGET`). A day-wide ceiling on real
   model calls across all users. When reached, `AIService::isEnabled()` reports
   false and **every** feature degrades to its fallback until the counter resets
   — fail safe, not fail expensive. `0` (default) disables the guard.

**Observability.** Every real call is tagged with a `feature` label at the
single `AIService` choke point and recorded to per-feature + global daily
counters (`AICostGuard::record()`), logged to the `ai` channel
(`storage/logs/ai.log`) as `AI call recorded` with the running `feature_calls_today`
/ `total_calls_today` tallies — so spend is attributable per feature.

### After enabling either provider

Generate embeddings for existing jobs, then process the queue:

```bash
php artisan jobs:embed          # queues a GenerateJobEmbedding per job
php artisan queue:work --stop-when-empty   # actually generates them
```

New/edited jobs embed automatically via `JobObserver` (still on the queue).
With `AI_PROVIDER=none`, every one of these steps is a safe no-op — see
[the degradation behavior](#what-happens-with-ai-disabled) and the
`Tests\Feature\AI\AiDisabledDegradationTest` suite, which proves every page
renders and no AI affordance leaks when AI is off.

## Job embeddings

Every job listing can have a semantic embedding stored alongside it,
used to power similarity-based search/matching later.

### Schema

`job_listings` has two extra columns (migration
`2026_06_17_084458_add_embedding_to_job_listings_table`):

- `embedding` (`json`, nullable) — the vector, cast to `array` on the
  `Job` model.
- `embedded_at` (`timestamp`, nullable) — when it was last generated.

Neither column is in `Job::$fillable` — they are only ever written by
`GenerateJobEmbedding`, never from a controller/form.

### How a job gets embedded

1. `App\Models\Job` has `#[ObservedBy(JobObserver::class)]`.
2. `App\Observers\JobObserver`:
   - `created()` — always queues `App\Jobs\GenerateJobEmbedding`.
   - `updated()` — queues it only if one of `Job::EMBEDDABLE_FIELDS`
     (`title`, `description`, `requirements`, `location`, `type`) changed.
     Editing salary, status, etc. does not re-embed.
3. `App\Jobs\GenerateJobEmbedding` (a queued job, `ShouldQueue`):
   - If `AIService::isEnabled()` is false, it returns immediately — no
     error, no retry, no log noise. This is intentional: with
     `AI_PROVIDER=none` the queue can churn through these jobs forever
     and nothing happens.
   - Otherwise it builds an input string from title + description +
     requirements + location + type, calls `AIService::embed()`, and
     writes the vector + timestamp back with `saveQuietly()`.

`saveQuietly()` matters: a normal `save()` would fire the `updated` model
event again, which `JobObserver` would see and use to queue *another*
embedding job — an infinite loop. `saveQuietly()` skips model events for
that write, so storing the result doesn't re-trigger generation.

Embedding generation **never happens inline during a web request** — it
is always dispatched to the queue, even when nothing has changed about
the request/response cycle's timing requirements. This keeps job
create/update requests fast regardless of how slow (or down) the
configured AI provider is.

### Running the queue

Embeddings only get generated once a worker is processing the `database`
queue connection:

```bash
php artisan queue:work
```

or, for local dev, the existing `composer dev` script already starts a
queue listener alongside the app server.

### Backfilling existing jobs

```bash
# Embed every job that doesn't have one yet
php artisan jobs:embed

# Re-embed everything, including jobs that already have an embedding
php artisan jobs:embed --force
```

The command:

- Exits immediately with a clear warning if AI is disabled
  (`AI_PROVIDER=none` or missing credentials) — it does not queue
  anything in that case.
- Otherwise counts the target jobs, shows a progress bar, and dispatches
  one `GenerateJobEmbedding` per job in chunks of 100 (`chunkById`, so it
  scales past however many rows are in the table without loading them
  all into memory at once).
- Only queues jobs; actual embedding happens once `queue:work` (or
  `queue:work --stop-when-empty`) processes them.

## Ranking by similarity: `VectorSearch`

`App\Services\AI\Contracts\VectorSearch` is the contract for ranking
records by embedding similarity:

```php
cosineSimilarity(array $a, array $b): float   // in [-1, 1], 0.0 for empty/zero vectors
search(array $queryVector, Collection $candidates, int $limit): Collection
```

`App\Services\AI\VectorSearch` is the current implementation: it computes
cosine similarity in PHP over an in-memory `Collection`. Each matching
candidate gets a `similarity` property attached and the collection comes
back sorted, most similar first. Candidates without a usable `embedding`
are skipped rather than erroring.

This is intentionally simple — fine for the dozens-to-low-thousands of
rows this app deals with. When it stops being fast enough, the fix is a
different implementation behind the same interface, rebound in
`AppServiceProvider` with no caller changes — because callers depend on
`VectorSearch` the interface, never on this class or on Eloquent directly
(the same pattern `AIProvider`/`AIService` use for the provider switch).
See [Scaling to a dedicated vector database](#scaling-to-a-dedicated-vector-database)
for the concrete pgvector / managed-store upgrade path.

## Semantic search on the public job board

`App\Services\AI\JobSearchService` is the only place that decides between
semantic and keyword search, and the only caller of `VectorSearch` outside
the embedding pipeline itself. `PublicJobController` never branches on the
provider or checks `isEnabled()` directly — it just asks the service and
reacts to the result.

### Index search (`PublicJobController::index`)

1. Structured filters (`location`, `company`, `types`, `remote`,
   `salary_min`) are applied to a `Job::active()` query first, exactly as
   before — this is `applyStructuredFilters()`, shared by both paths.
2. If a `search` term is present, `JobSearchService::rankByQuery()` is
   tried:
   - Returns `null` immediately if AI is disabled — the controller then
     falls through to the existing `Job::search()` keyword scope,
     unchanged. This is the **only** branch point; there is no
     semantic/keyword duplication elsewhere.
   - Otherwise it embeds the query (`AIService::embed()`, cached 5 minutes
     in the default cache store, keyed by model + query hash, so repeated
     searches/page loads don't re-embed), loads only the candidates that
     already match the structured filters **and** have an embedding
     (`scopeHasEmbedding`), and ranks them with `VectorSearch::search()`.
3. Semantic ranking happens in memory, so pagination can't be `paginate()`
   at the SQL level. The controller instead slices the ranked collection
   itself and wraps it in a `LengthAwarePaginator` built the same way
   `paginate()` would (`resolveCurrentPage()`/`resolveCurrentPath()`, plus
   `'query' => $request->query()` standing in for `withQueryString()`), so
   the view's `$jobs->links()`/`->total()` work identically either way.
4. The view gets an `$isSemanticSearch` flag and shows a small "Smart
   search" badge next to the search bar only when this specific response
   was ranked semantically — not just "AI is enabled" globally, and never
   when the keyword fallback served the request.

Jobs that haven't been embedded yet (queue hasn't caught up) simply don't
appear in semantic results until `GenerateJobEmbedding` runs — there is no
special-casing for that; `scopeHasEmbedding` excludes them like any other
non-candidate row.

### "Similar jobs" (`PublicJobController::show`)

`JobSearchService::similarTo($job)` returns the top 4 active jobs (or
fewer) ranked by similarity to `$job->embedding`, excluding `$job` itself.
It returns an empty collection — never throws, never queries — when AI is
disabled or the job has no embedding yet, so `jobs/show.blade.php` hides
the whole "Similar jobs" card with a single `@if ($similarJobs->isNotEmpty())`
and needs no separate AI-enabled check.

## Match scoring (candidate ⇄ job)

`App\Services\AI\MatchService` scores how well a `CandidateProfile` fits a
`Job` and returns a `MatchResult` DTO (`percentage`, `summary`,
`strengths[]`, `gaps[]`). It powers two surfaces: the match card a candidate
sees on a job page, and the per-applicant score (plus full breakdown) an
employer sees when reviewing applications.

### Hybrid scoring

- **Percentage** is deterministic: cosine similarity between the profile
  embedding and the job embedding, clamped to `[0, 1]` and scaled to
  0-100. The job's stored embedding is reused when present; only the
  profile is embedded on the fly. No LLM involved, so the number is
  stable and cheap.
- **Narrative** (`summary`/`strengths`/`gaps`) comes from a *single* LLM
  call constrained to strict JSON. Output is never trusted: `parseJson()`
  slices to the outermost braces (tolerating prose/markdown fences),
  `json_decode`s, and validates each field. **Any** failure — exception,
  unparseable text, missing `summary` — degrades to an embedding-only
  result with a generic summary and empty strengths/gaps. The percentage is
  always present regardless.

### Caching & cost guard

Every `(profile, job)` result is cached for 24h under a key that embeds
both records' `updated_at` timestamps and the active models, so edits or a
provider switch invalidate naturally and a warm pair is **never** rescored.
Two entry points enforce the cost rules:

- `score()` — `Cache::remember`; computes on a miss, returns cached
  otherwise.
- `cached()` — read-only; returns `null` if not yet computed and **never**
  triggers an embedding or LLM call. List views use this exclusively.

Profile and (fallback) job embeddings are cached separately under their own
`updated_at`-keyed entries.

### UX choice: precompute-to-warm + on-demand fetch

The requirement is "never block the page on a slow AI call." Both
techniques are used, each where it's the cleaner fit, sharing one cache:

- **Employer applications list** must be *sortable* by score, which means
  the scores have to exist server-side at render time. So scoring is
  **precomputed via queue**: applying dispatches
  `App\Jobs\ComputeApplicationMatch`, which warms the cache off the request
  cycle. The list then reads scores with `cached()` only — no inline LLM
  call, no blocking — showing a `—` placeholder for any pair not warm yet.
  `?sort=match` loads the filtered set, attaches cached scores, sorts
  (uncomputed last), and hand-paginates via `LengthAwarePaginator` (same
  pattern as semantic search), so `links()`/`total()` keep working.
- **Candidate job page** and **employer application detail** show one score
  for one pair, with no natural pre-trigger, so they fetch **on demand**:
  the page renders a card immediately (seeded with the cached result if
  warm) and otherwise an Alpine component fetches it from a JSON endpoint
  (`candidate.jobs.match` / `employer.applications.match`) showing a spinner
  meanwhile. A slow or failing call degrades to a retryable inline message,
  never a broken page.

Because both paths go through the same `MatchService` cache, a score
computed by either (queue warm-up, candidate view, employer view) is reused
everywhere.

### Hiding when AI is disabled

`MatchService::isAvailable()` gates everything. Controllers compute
`$matchEnabled` server-side and the Blade simply doesn't render the
`<x-match-card>` when it's false, so **no** match UI, endpoint result, or
queued work happens with `AI_PROVIDER=none`. The JSON endpoints also
`abort(404)` when AI is off, and `ComputeApplicationMatch` no-ops. A
candidate with an incomplete profile (no skills, or no headline/bio) is
shown a "complete your profile" prompt instead of a score —
`profileIsScorable()` is the single definition of "complete enough."

## What happens with AI disabled

With `AI_PROVIDER=none` (the default), the app is fully functional and shows
no sign that an AI layer exists. This is a first-class supported mode, not a
broken state — it's what runs in CI and what most environments see out of
the box. Concretely:

- **Public search** falls back to the `Job::search()` keyword scope and
  returns correct results; no "Smart search" badge appears.
- **No AI affordances render anywhere** — no match cards ("AI match"), no
  "Similar jobs" card, no "Draft with AI" / "Generate description with AI"
  buttons, no employer "Best match" sort.
- **The JSON endpoints** (match, cover-letter draft, JD draft) `abort(404)`
  rather than erroring.
- **The queue pipeline is a clean no-op**: `GenerateJobEmbedding` and
  `ComputeApplicationMatch` check `isEnabled()` and return immediately —
  dispatching them is always safe, so nothing else in the app has to know
  whether AI is on.
- **`php artisan jobs:embed`** prints a clear "AI layer is disabled" warning
  and exits `0` without queuing anything.

This whole contract is locked down by `Tests\Feature\AI\AiDisabledDegradationTest`,
which walks every key page as each role and asserts a clean render with no AI
markers, plus the queue/command no-ops. The enabled path is covered with two
styles of fake: most tests swap the entire `AIProvider` for
`Tests\Support\Doubles\FakeAIProvider`, while
`Tests\Feature\AI\PrismFakeIntegrationTest` fakes **Prism itself**
(`Prism::fake()`) to exercise the real `AIService → Prism` wiring. **No test
makes a real API call.**

## Scaling to a dedicated vector database

The current `VectorSearch` ranks an in-memory `Collection` by computing
cosine similarity in PHP. That is deliberately the simplest thing that works
and is fine for the dozens-to-low-thousands of embedded rows this app deals
with — every active, embedded candidate row is loaded and scored per query.

It stops being ideal once the embedded set grows large enough that loading
and scoring all candidates per request is wasteful (think tens of thousands+
of jobs, or high query volume). The upgrade path is intentionally a
**one-line container rebind**, because every caller depends on the
`VectorSearch` *interface*, never on this implementation or on Eloquent:

```php
// app/Providers/AppServiceProvider.php
$this->app->bind(VectorSearchContract::class, PgVectorSearch::class);
```

Two realistic targets, in order of effort:

- **pgvector (Postgres extension)** — the natural next step. Move
  `job_listings.embedding` into a `vector` column, add an IVFFlat/HNSW index,
  and implement `VectorSearch::search()` as an `ORDER BY embedding <=> ?`
  (cosine distance) query with a `LIMIT`, so ranking and top-K happen in the
  database instead of PHP. Keeps everything in one datastore; no new service
  to operate. (Note: this app uses SQLite by default — pgvector implies
  moving the DB to Postgres, which Laravel supports via config alone.)
- **A managed vector store (Pinecone, Weaviate, Qdrant)** — for very large
  corpora or when similarity search needs to be decoupled from the primary
  DB. `GenerateJobEmbedding` would additionally upsert each vector to the
  store (keyed by job id), and the new `VectorSearch` implementation would
  query it for nearest neighbors, then hydrate the returned ids back into
  `Job` models. More moving parts, but effectively unbounded scale.

In every case the swap is isolated to one binding plus the new implementation
class. `JobSearchService`, `MatchService`, the controllers, and the views are
untouched — the same property that lets the provider switch be `.env`-only.

## Logging

Every `AIService` call (`embed`/`chat`) and every embedding stored by
`GenerateJobEmbedding` is logged to the dedicated `ai` channel
(`storage/logs/ai-*.log`, daily rotation, 14 days by default — see
`config/logging.php`), with provider, model, latency, and token usage
where available. This is kept separate from `storage/logs/laravel.log` so
AI activity doesn't get lost in general app noise.

## What's still out of scope

Embeddings are only ever produced by the observer/backfill pipeline and the
on-demand profile embedding in `MatchService` — nothing recomputes job
embeddings on a schedule. Match scores aren't persisted to a column (they
live in the cache, keyed by `updated_at`), so cross-page global sorting
beyond what `cached()` can see isn't attempted; the employer list sorts the
filtered set using whatever scores are warm. There's no batch "score every
applicant now" admin action — scores accrue as applications arrive (queue
warm-up) and as the detail/candidate pages are viewed.
