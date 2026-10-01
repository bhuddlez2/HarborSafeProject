<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/*
An uploaded event image or newsletter PDF, bytes and all. See the
create_content_files_table migration for why these live in the database.

THE ONE RULE: never let `contents` into a query that returns more than one
row. A LONGBLOB in a SELECT * is read fully into PHP memory, so a listing page
would move every stored byte to render a table of filenames.

That is enforced here rather than left to callers, because the trap is
invisible at the call site - `$event->imageFile->name` looks harmless and would
otherwise drag a 4 MB image along with it. A global scope narrows every query
to the metadata columns. The only way to the bytes is contents(), which fetches
that one column for one row and does not hydrate a model at all.

Consequences worth knowing:
  - `ContentFile::first()->contents` is null, not the bytes. That is on
    purpose. Use ->contents().
  - A query that needs its own ->select() will drop this scope's column list,
    which is fine as long as it does not ask for `contents`.
*/
class ContentFile extends BaseModel
{
    use HasUuids;

    protected $connection = 'Content';

    protected $table = 'content_files';

    protected $primaryKey = 'FileID';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    // Everything except `contents`. Listed explicitly so adding a column is a
    // deliberate choice rather than an accidental blob fetch.
    public const METADATA_COLUMNS = [
        'FileID',
        'name',
        'mime_type',
        'size_bytes',
        'checksum',
        'created_at',
    ];

    protected $fillable = [
        'name',
        'mime_type',
        'size_bytes',
        'checksum',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    protected static function booted(): void
    {
        // BaseModel::booted() is what enforces the $connection declaration;
        // overriding without this call would quietly disable that guard.
        parent::booted();

        static::addGlobalScope(
            'withoutContents',
            fn (Builder $query) => $query->select(array_map(
                fn (string $column): string => 'content_files.'.$column,
                self::METADATA_COLUMNS,
            )),
        );
    }

    /*
    The bytes, fetched on demand. Deliberately not an attribute: a raw column
    read keeps the blob out of the model's attribute array, so it is not
    retained, cast, or serialised by accident.
    */
    public function contents(): ?string
    {
        $contents = DB::connection($this->connection)
            ->table($this->table)
            ->where($this->primaryKey, $this->getKey())
            ->value('contents');

        return $contents === null ? null : (string) $contents;
    }

    /*
    Stores an upload and returns the saved record.

    READING THE BYTES IS NOT file_get_contents($file->getRealPath()). Livewire
    hands the form a TemporaryUploadedFile, whose getRealPath() resolves
    against the livewire-tmp *disk* - it only yields a readable local path when
    that disk happens to be local, and returns something unreadable otherwise.
    Its get() reads through the disk, so that is preferred wherever it exists.
    Getting this wrong stores a zero-byte row and fails nowhere until someone
    opens the file.
    */
    public static function storeUpload(UploadedFile $file): self
    {
        $contents = method_exists($file, 'get')
            ? $file->get()
            : file_get_contents($file->getRealPath());

        if ($contents === false || $contents === null) {
            throw new \RuntimeException('The uploaded file could not be read.');
        }

        return self::store(
            $contents,
            $file->getClientOriginalName(),
            // getMimeType() sniffs the file itself rather than taking the
            // browser's word for it. The caller validates the result against
            // an allow-list - see DatabaseFileUpload.
            $file->getMimeType() ?: 'application/octet-stream',
        );
    }

    /*
    The primitive: bytes in, row out.

    Written with the query builder rather than Eloquent::create() so the blob
    is bound as a parameter and never enters the model's attribute array. Size
    comes from the bytes actually stored, never from a client-supplied length.
    */
    public static function store(string $contents, string $name, string $mimeType): self
    {
        $record = new self([
            'name' => $name,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($contents),
            'checksum' => hash('sha256', $contents),
        ]);

        $record->setAttribute($record->getKeyName(), $record->newUniqueId());

        DB::connection($record->connection)
            ->table($record->table)
            ->insert([
                ...$record->getAttributes(),
                'contents' => $contents,
                'created_at' => now(),
            ]);

        $record->exists = true;
        $record->wasRecentlyCreated = true;

        return $record;
    }

    // "2.3 MB" / "812 KB", matching how the public site labels a newsletter.
    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;

        return match (true) {
            $bytes >= 1048576 => round($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => round($bytes / 1024).' KB',
            default => $bytes.' B',
        };
    }
}
