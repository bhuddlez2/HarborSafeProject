<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
One-time repair that goes with pinning every database connection to UTC
(config/database.php, see App\Support\Timezones). Run it straight after
pulling that change, before using the app.

WHY. TIMESTAMP columns are stored internally in UTC, and MariaDB converts on
the way in and out using the session's time zone. Until now the session used
each developer's machine time zone. Two kinds of value were affected:

  - Written by the DATABASE (useCurrent(): assessment DateCreated, feedback
    and resource-request SubmissionDate, the change log TimeStamp): stored
    correctly. Under a UTC session they now read back as true UTC. Untouched.
  - Written by LARAVEL, which always sends UTC text (users.created_at,
    ChangeDate, content_files.created_at, EditedAt, ...): MariaDB took that
    text as local time and stored it shifted by the machine's UTC offset.
    Those are the columns listed below.

HOW. For each listed column: read it under the old session time zone
('SYSTEM'), which returns exactly the text Laravel wrote, then write that same
text back under UTC. Row by row, so it is right on either side of a daylight
saving change, and a no-op on any machine whose clock is already UTC.

Not listed, and left alone: event starts_at/ends_at (DATETIME, never
converted), and service_feedback rows entered by staff (stored as local
wall-clock text, which the old session converted correctly).
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    // connection => table => [primary key, [columns written by Laravel]]
    private const COLUMNS = [
        'Portal' => [
            'users' => ['id', ['created_at', 'updated_at', 'email_verified_at', 'two_factor_confirmed_at']],
            'password_reset_tokens' => ['email', ['created_at']],
            'personal_access_tokens' => ['id', ['last_used_at', 'expires_at', 'created_at', 'updated_at']],
            'roles' => ['id', ['created_at', 'updated_at']],
            'permissions' => ['id', ['created_at', 'updated_at']],
            'assessment_edits' => ['EditID', ['EditedAt']],
        ],
        'Feedback' => [
            'services' => ['id', ['ChangeDate']],
            'resources' => ['id', ['ChangeDate']],
            'counties' => ['id', ['ChangeDate']],
            'feedback_scans' => ['FileID', ['created_at']],
        ],
        'Content' => [
            'event_categories' => ['id', ['ChangeDate']],
            'content_files' => ['FileID', ['created_at']],
        ],
    ];

    public function up(): void
    {
        $this->rewrite(readUnder: 'SYSTEM', writeUnder: '+00:00');
    }

    // Puts the values back as the old local-time session would have read them.
    public function down(): void
    {
        $this->rewrite(readUnder: '+00:00', writeUnder: 'SYSTEM');
    }

    private function rewrite(string $readUnder, string $writeUnder): void
    {
        foreach (self::COLUMNS as $connection => $tables) {
            $db = DB::connection($connection);

            foreach ($tables as $table => [$key, $columns]) {
                if (! Schema::connection($connection)->hasTable($table)) {
                    continue;
                }

                $columns = array_values(array_filter(
                    $columns,
                    fn (string $column): bool => Schema::connection($connection)->hasColumn($table, $column),
                ));

                if ($columns === []) {
                    continue;
                }

                // Only the key and the time columns - never a blob.
                $db->statement("SET time_zone = '{$readUnder}'");
                $rows = $db->table($table)->select([$key, ...$columns])->get();

                $db->statement("SET time_zone = '{$writeUnder}'");

                foreach ($rows as $row) {
                    $values = array_filter(
                        array_map(fn (string $column) => $row->{$column}, array_combine($columns, $columns)),
                        fn ($value): bool => $value !== null,
                    );

                    if ($values !== []) {
                        $db->table($table)->where($key, $row->{$key})->update($values);
                    }
                }
            }

            // Leave the connection as config/database.php sets it.
            $db->statement("SET time_zone = '+00:00'");
        }
    }
};
