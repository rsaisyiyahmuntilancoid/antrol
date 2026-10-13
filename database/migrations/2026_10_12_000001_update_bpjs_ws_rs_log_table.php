<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = Schema::connection('log');

        if (!$schema->hasTable('bpjs_ws_rs_log')) {
            return;
        }

        $schema->table('bpjs_ws_rs_log', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'operation')) {
                $table->string('operation', 120)->nullable()->after('request_id');
            }
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'status_code')) {
                $table->integer('status_code')->nullable()->after('code');
            }
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'http_status')) {
                $table->integer('http_status')->nullable()->after('status_code');
            }
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'error_type')) {
                $table->string('error_type', 50)->nullable()->after('http_status');
            }
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'bpjs_meta_code')) {
                $table->string('bpjs_meta_code', 50)->nullable()->after('error_type');
            }
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'bpjs_meta_message')) {
                $table->text('bpjs_meta_message')->nullable()->after('bpjs_meta_code');
            }
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'response')) {
                $table->longText('response')->nullable()->after('request');
            }
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'endpoint')) {
                $table->string('endpoint', 255)->nullable()->after('message');
            }
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'norm')) {
                $table->string('norm', 30)->nullable()->after('method');
            }
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'nokartu')) {
                $table->string('nokartu', 30)->nullable()->after('norm');
            }
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'no_sep')) {
                $table->string('no_sep', 50)->nullable()->after('nokartu');
            }
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'no_rujukan')) {
                $table->string('no_rujukan', 50)->nullable()->after('no_sep');
            }
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'exception')) {
                $table->longText('exception')->nullable()->after('no_rujukan');
            }
            if (!$schema->hasColumn('bpjs_ws_rs_log', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        // Add indexes if not present
        try {
            $schema->table('bpjs_ws_rs_log', function (Blueprint $table) {
                // Ignore if index already exists
                $table->index('operation', 'bpjs_ws_rs_log_operation_index');
                $table->index(['operation', 'created_at'], 'bpjs_ws_rs_log_operation_created_at_index');
                $table->index('endpoint', 'bpjs_ws_rs_log_endpoint_index');
                $table->index('norm', 'bpjs_ws_rs_log_norm_index');
                $table->index('nokartu', 'bpjs_ws_rs_log_nokartu_index');
                $table->index('no_sep', 'bpjs_ws_rs_log_no_sep_index');
            });
        } catch (\Throwable $e) {
            // Indexes might already exist if table was updated manually
        }

        // Backfill operation for existing legacy rows if null
        try {
            DB::connection('log')->table('bpjs_ws_rs_log')
                ->whereNull('operation')
                ->where('url', 'like', '%/antrean/updatewaktu%')
                ->update(['operation' => 'updatewaktu', 'endpoint' => '/antrean/updatewaktu']);

            DB::connection('log')->table('bpjs_ws_rs_log')
                ->whereNull('operation')
                ->where('url', 'like', '%/antrean/add%')
                ->update(['operation' => 'add_antrean', 'endpoint' => '/antrean/add']);

            DB::connection('log')->table('bpjs_ws_rs_log')
                ->whereNull('operation')
                ->where('url', 'like', '%/antrean/batal%')
                ->update(['operation' => 'batal_antrean', 'endpoint' => '/antrean/batal']);

            DB::connection('log')->table('bpjs_ws_rs_log')
                ->whereNull('operation')
                ->where('url', 'like', '%/antrean/getlisttask%')
                ->update(['operation' => 'listtask', 'endpoint' => '/antrean/getlisttask']);

            DB::connection('log')->table('bpjs_ws_rs_log')
                ->whereNull('operation')
                ->where('url', 'like', '%/antrean/pendaftaran%')
                ->update(['operation' => 'get_booking', 'endpoint' => '/antrean/pendaftaran/kodebooking']);

            DB::connection('log')->table('bpjs_ws_rs_log')
                ->whereNull('operation')
                ->where('url', 'like', '%/antrean/dashboard%')
                ->update(['operation' => 'dashboard', 'endpoint' => '/antrean/dashboard/pertanggal']);
        } catch (\Throwable $e) {
            // Log and continue
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep columns in down() to avoid accidental data loss
    }
};
