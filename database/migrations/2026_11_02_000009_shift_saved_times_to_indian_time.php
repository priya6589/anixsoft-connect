<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The app used to run on UTC and now runs on Indian time (Asia/Kolkata, UTC+5:30).
 * Moves every date-time saved so far 5 h 30 min forward, so old records show the right
 * time too. Date-only columns (such as connected_on) are left alone.
 */
return new class extends Migration
{
    private const TABLES = ['users', 'contacts', 'contact_imports', 'email_drafts', 'email_replies', 'skills', 'failed_jobs', 'password_reset_tokens'];

    public function up(): void
    {
        $this->shift(330);
    }

    public function down(): void
    {
        $this->shift(-330);
    }

    private function shift(int $minutes): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (Schema::getColumns($table) as $column) {
                if (! in_array($column['type_name'], ['datetime', 'timestamp'], true)) {
                    continue;
                }

                $name = $column['name'];
                $shifted = $sqlite
                    ? "datetime(\"{$name}\", '{$minutes} minutes')"
                    : "DATE_ADD(`{$name}`, INTERVAL {$minutes} MINUTE)";

                DB::table($table)->whereNotNull($name)->update([$name => DB::raw($shifted)]);
            }
        }
    }
};
