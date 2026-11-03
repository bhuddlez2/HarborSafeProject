<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
Laravel's standard notifications table, for the staff panel's bell (team plan
task 17): one row per alert per recipient, read_at set once they've seen it.
On Portal beside users, since the recipients live there; read and written
through App\Models\StaffNotification, which pins that connection.

Alerts are short-lived by design: a read alert drops out of the bell after 12
hours, and StaffNotification is Prunable so `php artisan model:prune` (daily,
routes/console.php) removes it from here too.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        Schema::connection('Portal')->create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('Portal')->dropIfExists('notifications');
    }
};
