<?php

declare(strict_types=1);

namespace App\Services\Resume;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one place resume/CV files are read from and written to.
 *
 * Resumes live on the private, env-selected {@see config('filesystems.resume_disk')}
 * disk — S3-compatible object storage (Cloudflare R2 / AWS S3) in production so
 * files survive Railway's ephemeral disk on redeploy, and the private local
 * disk in development. Files are never public: they are served only through
 * {@see self::view()}, which hands out a short-lived signed URL when the disk
 * supports it (S3/R2) and otherwise streams the file through the app after the
 * caller has authorized access.
 */
class ResumeStorage
{
    /** Where resumes are kept within the disk. */
    private const DIRECTORY = 'resumes';

    /** Signed-URL lifetime for S3/R2-backed views. */
    private const URL_TTL_MINUTES = 5;

    /**
     * Server-side upload rules shared by every resume entry point: PDF only
     * (by extension and by sniffed content type) and 5 MB max.
     *
     * @return array<int, string>
     */
    public static function validationRules(): array
    {
        return ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:5120'];
    }

    public function diskName(): string
    {
        return (string) config('filesystems.resume_disk', 'local');
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    /**
     * Store an uploaded resume under a safe, random, always-".pdf" filename
     * (the client-supplied name never touches the stored path) and return the
     * disk-relative path.
     */
    public function store(UploadedFile $file): string
    {
        $name = Str::random(40).'.pdf';

        return $file->storeAs(self::DIRECTORY, $name, $this->diskName());
    }

    /**
     * Copy an already-stored resume to a new, application-owned path and
     * return it — or null when the source is empty/missing. Used when a
     * candidate applies without uploading a fresh file: the application keeps
     * its own immutable snapshot of the profile resume, so later replacing
     * the profile resume never breaks a submitted application's copy.
     */
    public function copy(?string $sourcePath): ?string
    {
        if (! $this->exists($sourcePath)) {
            return null;
        }

        $destination = self::DIRECTORY.'/'.Str::random(40).'.pdf';

        return $this->disk()->copy($sourcePath, $destination) ? $destination : null;
    }

    public function exists(?string $path): bool
    {
        return filled($path) && $this->disk()->exists($path);
    }

    /**
     * Read the raw file contents, or null when the path is empty/missing.
     * Used by the resume-parsing pipeline.
     */
    public function get(?string $path): ?string
    {
        if (! $this->exists($path)) {
            return null;
        }

        return $this->disk()->get($path);
    }

    public function delete(?string $path): void
    {
        if ($this->exists($path)) {
            $this->disk()->delete($path);
        }
    }

    /**
     * A response that lets an already-authorized caller view a resume inline.
     *
     * On S3/R2 this is a redirect to a short-lived signed URL (the file is
     * never public); on the local disk it streams the file through the app.
     * Callers MUST authorize access before calling this — it performs no
     * authorization of its own.
     */
    public function view(string $path, string $downloadName = 'resume.pdf'): Response
    {
        abort_unless($this->exists($path), 404);

        $disk = $this->disk();

        // S3/R2 (and the local disk with serve=true) hand out a short-lived
        // signed URL. If a disk claims support but can't actually mint one,
        // fall back to streaming the file through the app.
        if ($disk->providesTemporaryUrls()) {
            try {
                return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(self::URL_TTL_MINUTES), [
                    'ResponseContentType' => 'application/pdf',
                    'ResponseContentDisposition' => 'inline; filename="'.$downloadName.'"',
                ]));
            } catch (RuntimeException) {
                // Driver does not support temporary URLs after all — stream.
            }
        }

        return $disk->response($path, $downloadName, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
