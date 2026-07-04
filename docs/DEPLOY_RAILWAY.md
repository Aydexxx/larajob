# Deploying LaraJob to Railway (PostgreSQL + pgvector)

LaraJob runs on SQLite locally and PostgreSQL in production. All driver
differences are handled in code and migrations — the same codebase runs on
both with no branching in feature code. Semantic search uses pgvector on
PostgreSQL and falls back to in-memory cosine ranking on SQLite.

## 1. Provision PostgreSQL with pgvector

Railway's current **PostgreSQL** template ships with pgvector available —
the extension only has to be *created*, which our migrations do
automatically (`CREATE EXTENSION IF NOT EXISTS vector`, PostgreSQL-only,
no-op elsewhere). No manual SQL is needed.

If you provisioned an older Railway Postgres whose image lacks pgvector
(`could not open extension control file .../vector.control` during
`migrate`), redeploy the database from the **pgvector** template in
Railway's template gallery, or set the database service's image to
`pgvector/pgvector:pg17`, then re-run migrations.

You can confirm the extension from Railway's database "Data" tab:

```sql
SELECT extversion FROM pg_extension WHERE extname = 'vector';
```

## 2. Environment variables (app service)

Reference the database service's variables instead of hardcoding
credentials — Railway keeps them in sync:

```bash
APP_NAME=LaraJob
APP_ENV=production
APP_DEBUG=false
APP_KEY=            # php artisan key:generate --show, paste the value
APP_URL=https://<your-app>.up.railway.app

DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.PGDATABASE}}
DB_USERNAME=${{Postgres.PGUSER}}
DB_PASSWORD=${{Postgres.PGPASSWORD}}
DB_SSLMODE=require

# Sessions, cache and queue all use the database (no Redis needed)
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

LOG_CHANNEL=stderr   # Railway captures stdout/stderr

# AI layer. NOTE: the switch is AI_PROVIDER (not LLM_PROVIDER):
# none | openai | ollama. "none" disables all AI features cleanly.
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
# Optional model overrides (defaults shown):
# AI_OPENAI_CHAT_MODEL=gpt-4o-mini
# AI_OPENAI_EMBEDDING_MODEL=text-embedding-3-small
# AI_EMBEDDING_DIMENSIONS=1536   # must match the vector(1536) columns

# Object storage for resumes/CVs (see "File uploads" below). REQUIRED in
# production — Railway's local disk is wiped on every redeploy.
RESUME_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_BUCKET=larajob-resumes
AWS_DEFAULT_REGION=auto            # "auto" for Cloudflare R2
AWS_ENDPOINT=https://<accountid>.r2.cloudflarestorage.com   # R2 only; omit for AWS S3
AWS_USE_PATH_STYLE_ENDPOINT=false
```

> `AI_EMBEDDING_DIMENSIONS` must stay 1536 unless you also migrate the
> `vector(1536)` columns and re-embed everything.

### File uploads (resumes/CVs) — why object storage is required

Railway's container filesystem is **ephemeral**: anything written to local
disk (including `storage/app`) is lost on every redeploy and every restart.
Resumes must therefore live on external object storage.

Resumes are stored on the private, env-selected `resume_disk`
(`config/filesystems.php`):

- **`RESUME_DISK`** — `s3` or `local`. Defaults to `s3` whenever `AWS_BUCKET`
  is set, otherwise `local`. Set it to `s3` in production.
- The `s3` disk is S3-compatible and works with both **AWS S3** and
  **Cloudflare R2**. For R2, set `AWS_ENDPOINT` to your account's S3 API
  endpoint and `AWS_DEFAULT_REGION=auto`. For AWS S3, omit `AWS_ENDPOINT`
  and set the real region.
- Files are **private** (no public ACL). "View resume" links resolve to a
  short-lived (5-minute) **signed URL** on S3/R2, or an app-streamed
  response on the local disk — resumes are never publicly reachable and
  no `storage:link` symlink is used.
- Uploads are validated server-side: **PDF only** (extension + sniffed
  content type), **5 MB max**, stored under a random `.pdf` filename so the
  client-supplied name never touches the path.

Create a private bucket (R2 or S3), generate an access key limited to it,
and fill in the variables above. No `php artisan storage:link` step is
needed. (`FILESYSTEM_DISK` can stay `local`; it is unrelated — resumes use
`RESUME_DISK`.)

**Migrating existing files:** resumes previously lived on the public disk
(`storage/app/public/resumes`). Fresh production deploys have no such data.
For an existing local dev environment, move any old files to the private
disk so "View resume" keeps working:

```bash
mkdir -p storage/app/private/resumes
mv storage/app/public/resumes/* storage/app/private/resumes/ 2>/dev/null || true
```

Company logos are intentionally left on the public disk (they render on
public job listings), so `storage:link` is still used for those.

## 3. Deploy / migrate command order

Configure on the app service:

- **Pre-deploy command:** `php artisan migrate --force`
- **Start command:** your HTTP server (e.g. what Railpack/Nixpacks
  generates, or `php artisan serve --host 0.0.0.0 --port $PORT` for a
  minimal setup)
- **Worker service** (second service, same repo/image):
  `php artisan queue:work --tries=3 --max-time=3600`
  — embeddings are generated on the queue; without a worker nothing
  gets embedded.

First-deploy order:

1. `php artisan migrate --force` (pre-deploy) — creates the schema,
   enables pgvector, creates the `vector(1536)` columns and the ivfflat
   cosine indexes.
2. App + worker come up.
3. Seed / import data (optional): `php artisan db:seed --force`.
4. Backfill embeddings: `php artisan larajob:backfill-embeddings`
   (queues one job per listing/profile; the worker fills them in).
5. Optional but recommended once real data is embedded: the ivfflat
   indexes were built while the tables were empty, so their cluster
   centres are untrained. Rebuild them for good recall:

   ```sql
   REINDEX INDEX job_listings_embedding_cosine_index;
   REINDEX INDEX candidate_profiles_embedding_cosine_index;
   ```

One-off commands can be run with `railway run php artisan ...` or from a
shell on the service.

## 4. Health check

Point Railway's service health check at **`/health`**. It returns:

- `200 {"status":"ok", ...}` — DB reachable, and (on PostgreSQL) the
  pgvector extension is installed and both `embedding` columns are real
  `vector` columns;
- `503 {"status":"fail", ...}` — with per-check details when any of that
  is missing.

`/up` also exists (framework liveness only, no DB check). `/health` is
registered outside the web middleware group, so polling it does not write
session rows.

## 5. SQLite vs PostgreSQL — behavioural notes

- **Vector search:** on PostgreSQL, match ranking runs in the database
  (`embedding <=> query` with ivfflat indexes, only top-N rows hydrated).
  On SQLite it ranks in memory over the filtered candidate set. Results
  are identical for the same data; ivfflat is approximate, so on very
  large tables the top-N *can* differ slightly from exact ranking —
  that's the intended ANN trade-off.
- **Keyword search:** all user-facing searches use `whereLike()`, which
  compiles to `ILIKE` on PostgreSQL and `LIKE` on SQLite — consistently
  case-insensitive on both.
- **Embeddings storage:** `vector(1536)` on PostgreSQL, JSON on SQLite;
  both round-trip through the same `array` model cast. One precision note:
  pgvector stores components as 4-byte floats, so an embedding read back
  from PostgreSQL differs from the written array in the ~7th decimal.
  Similarity scores are unaffected in any meaningful way.
- **Test suite:** phpunit.xml pins the suite to sqlite by design — many
  tests use short, readable fixture vectors (e.g. `[1.0, 0.0]`) that the
  JSON column accepts but `vector(1536)` rejects. Don't point the suite at
  a pgvector database and expect green; production parity is verified by
  running migrations, the embedding backfill, and the `/health` check
  against a real PostgreSQL instance (see sections above).
