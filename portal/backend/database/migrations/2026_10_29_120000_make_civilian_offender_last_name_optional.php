<?php

use App\Support\SensitiveData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
The offender's last name is optional on the civilian assessment (Burchfield
B29, 2026-10-29): someone reporting for another person may not know it. The
officer assessment still requires it, so law_enforcement_assessment is left
alone. The column stays encrypted TEXT.

down() stores an encrypted empty string for any missing name before making
the column required again.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    public function up(): void
    {
        Schema::connection('Portal')->table('_private_assessment', function (Blueprint $table): void {
            $table->text('OffenderLastName')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::connection('Portal')->table('_private_assessment')->whereNull('OffenderLastName')
            ->update(['OffenderLastName' => SensitiveData::encrypt('')]);

        Schema::connection('Portal')->table('_private_assessment', function (Blueprint $table): void {
            $table->text('OffenderLastName')->nullable(false)->change();
        });
    }
};
