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

MAX SIZE IS NOT COSMETIC. `max_allowed_packet` is 16 MB on the dev box and on
a default MariaDB install, and it caps a single row in both directions - an
oversized blob fails on INSERT and, worse, on every later SELECT. The ceilings
here leave room for query overhead on top of the bytes.
*/
class DatabaseFileUpload extends FileUpload
{
    // Kilobytes, as Filament's maxSize() expects. 4 MB for an image, which is
    // generous for a web banner; 8 MB for a PDF, which fits a photo-heavy
    // quarterly newsletter. Both sit well inside the 16 MB packet limit.
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
        return static::make($name)
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(self::MAX_IMAGE_KB)
            ->imagePreviewHeight('150')
            ->helperText('JPEG, PNG or WebP, up to 4 MB. Stored in the database.');
    }

    // A newsletter issue. See forImage() on the naming.
    public static function forDocument(string $name): static
    {
        return static::make($name)
            ->acceptedFileTypes(['application/pdf'])
            ->maxSize(self::MAX_DOCUMENT_KB)
            ->helperText('PDF, up to 8 MB. Stored in the database.');
    }
}
