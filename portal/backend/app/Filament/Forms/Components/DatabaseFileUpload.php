<?php

namespace App\Filament\Forms\Components;

use App\Models\ContentFile;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/*
A FileUpload that stores bytes in `content_files` instead of on a filesystem
disk. The form column ends up holding a ContentFile uuid.

Filament's FileUpload assumes a disk throughout, so three of its hooks are
redirected:

  saveUploadedFileUsing - writes the row, returns the uuid for the column.
  getUploadedFileUsing  - supplies name/size/type/url for the preview tile,
                          which would otherwise be read off the disk.
  fetchFileInformation  - turned OFF. Filament calls
                          $this->getDisk()->exists($value) when hydrating an
                          existing record, and a uuid is not a path, so every
                          saved upload would be silently dropped from the form
                          on edit.

Deletion is intentionally NOT wired. Removing the tile clears the foreign key
and leaves the row in place, because an unpublished-then-republished event
would otherwise lose its image irrecoverably, and because a stray row costs a
few kilobytes. If orphan cleanup is ever wanted it belongs in a scheduled
command that checks both referencing columns, not in a form hook.

MAX SIZE IS NOT COSMETIC, AND IT IS NOT ONE NUMBER. An upload has to clear
four separate ceilings, and the lowest one wins:

  1. `upload_max_filesize` (PHP)      - 2M on a stock php.ini
  2. `post_max_size` (PHP)            - 8M on a stock php.ini, bounds the
                                        whole request, so it must exceed (1)
  3. Livewire's temporary upload rule - 12M by default
  4. `max_allowed_packet` (MariaDB)   - 16M by default, and it caps a single
                                        row in BOTH directions, so an
                                        oversized blob fails on INSERT and on
                                        every later SELECT

Numbers 1 and 2 are the ones that bite, because PHP rejects the file before
Laravel or Filament ever runs - so none of Filament's own validation messages
appear, and the failure looks like the panel being broken rather than the file
being too big. A stock `upload_max_filesize` of 2M means a 3.5 MB photo fails
no matter what this class says.

Which is why the limits below are CLAMPED TO WHAT PHP WILL ACTUALLY ACCEPT
rather than merely declared. A hardcoded cap above PHP's is not a limit, it is
a false promise printed under the upload box. See phpUploadCeilingKb().
*/
class DatabaseFileUpload extends FileUpload
{
    /*
    What we would like to allow, in kilobytes, as Filament's maxSize() expects.
    4 MB for an image is generous for a web banner; 8 MB for a PDF fits a
    photo-heavy quarterly newsletter. Both sit inside the 16 MB packet limit
    with room for query overhead.

    These are intentions, not guarantees - effectiveLimitKb() lowers them to
    PHP's ceiling when the environment cannot honour them.
    */
    public const MAX_IMAGE_KB = 4096;

    public const MAX_DOCUMENT_KB = 8192;

    protected function setUp(): void
    {
        parent::setUp();

        // See the class comment - without this, editing a record silently
        // discards the existing upload.
        $this->fetchFileInformation(false);

        $this->saveUploadedFileUsing(
            fn (TemporaryUploadedFile $file): ?string => ContentFile::storeUpload($file)->getKey(),
        );

        $this->getUploadedFileUsing(function (string $file): ?array {
            $record = ContentFile::find($file);

            if (! $record) {
                return null;
            }

            return [
                'name' => $record->name,
                'size' => $record->size_bytes,
                'type' => $record->mime_type,
                // The staff route, not the public one: the public endpoint
                // serves published content only, and most previews are drafts.
                'url' => route('staff.content-files.show', ['file' => $record->getKey()]),
            ];
        });
    }

    /*
    An event image.

    Named forImage(), not image(): FileUpload::image() already exists as an
    INSTANCE method (it restricts the accepted types), and PHP refuses to
    redeclare it static. Same for forDocument() below, for symmetry.

    The accepted types are the allow-list the streaming controller later hands
    straight back as Content-Type, so keep them narrow - svg is excluded on
    purpose, since it can carry script.
    */
    public static function forImage(string $name): static
    {
        $limit = self::effectiveLimitKb(self::MAX_IMAGE_KB);

        return static::make($name)
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize($limit)
            ->imagePreviewHeight('150')
            ->helperText(sprintf(
                'JPEG, PNG or WebP, up to %s. Stored in the database.',
                self::describeKb($limit),
            ));
    }

    // A newsletter issue. See forImage() on the naming.
    public static function forDocument(string $name): static
    {
        $limit = self::effectiveLimitKb(self::MAX_DOCUMENT_KB);

        return static::make($name)
            ->acceptedFileTypes(['application/pdf'])
            ->maxSize($limit)
            ->helperText(sprintf(
                'PDF, up to %s. Stored in the database.',
                self::describeKb($limit),
            ));
    }

    /*
    The intended limit, lowered to what PHP will actually accept.

    Clamping rather than throwing is deliberate: a misconfigured server should
    make the panel say "up to 2 MB" and enforce it cleanly, not refuse to
    render the form. The number in the helper text is then always the truth,
    which is what makes an over-sized file a clear validation message instead
    of a mystery.
    */
    public static function effectiveLimitKb(int $intendedKb): int
    {
        $ceiling = self::phpUploadCeilingKb();

        return $ceiling === null ? $intendedKb : min($intendedKb, $ceiling);
    }

    /*
    The largest single upload PHP will accept, in kilobytes, or null when
    neither limit is set.

    post_max_size bounds the entire request body, upload_max_filesize bounds
    one file within it, so the real ceiling is the lower of the two. A value of
    0 or -1 means unlimited and is ignored.
    */
    public static function phpUploadCeilingKb(): ?int
    {
        $limits = array_filter(array_map(
            fn (string $directive): ?int => self::iniBytes($directive),
            ['upload_max_filesize', 'post_max_size'],
        ), fn (?int $bytes): bool => $bytes !== null && $bytes > 0);

        return $limits === [] ? null : (int) floor(min($limits) / 1024);
    }

    // Parses PHP's shorthand byte notation ("8M", "512K", "1G"). Returns null
    // when unset, and -1 unchanged so callers can treat it as unlimited.
    private static function iniBytes(string $directive): ?int
    {
        $raw = trim((string) ini_get($directive));

        if ($raw === '') {
            return null;
        }

        $value = (int) $raw;

        return match (strtoupper(substr($raw, -1))) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }

    private static function describeKb(int $kilobytes): string
    {
        return $kilobytes >= 1024
            ? round($kilobytes / 1024, 1).' MB'
            : $kilobytes.' KB';
    }
}
