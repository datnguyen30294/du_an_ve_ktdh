<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace plain unique indexes with partial unique indexes (WHERE deleted_at IS NULL)
 * so that soft-deleted records don't block re-creation of the same code/email.
 *
 * PostgreSQL supports partial indexes natively.
 */
return new class extends Migration
{
    public function up(): void
    {
        // departments.code
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });
        DB::statement('CREATE UNIQUE INDEX departments_code_unique ON departments (code) WHERE deleted_at IS NULL');

        // projects.code
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });
        DB::statement('CREATE UNIQUE INDEX projects_code_unique ON projects (code) WHERE deleted_at IS NULL');

        // job_titles.code
        Schema::table('job_titles', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });
        DB::statement('CREATE UNIQUE INDEX job_titles_code_unique ON job_titles (code) WHERE deleted_at IS NULL');

        // roles.name
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
        DB::statement('CREATE UNIQUE INDEX roles_name_unique ON roles (name) WHERE deleted_at IS NULL');

        // accounts.email
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
        DB::statement('CREATE UNIQUE INDEX accounts_email_unique ON accounts (email) WHERE deleted_at IS NULL');

        // accounts.employee_code
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['employee_code']);
        });
        DB::statement('CREATE UNIQUE INDEX accounts_employee_code_unique ON accounts (employee_code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        // Revert to plain unique indexes
        DB::statement('DROP INDEX IF EXISTS departments_code_unique');
        Schema::table('departments', function (Blueprint $table) {
            $table->unique('code');
        });

        DB::statement('DROP INDEX IF EXISTS projects_code_unique');
        Schema::table('projects', function (Blueprint $table) {
            $table->unique('code');
        });

        DB::statement('DROP INDEX IF EXISTS job_titles_code_unique');
        Schema::table('job_titles', function (Blueprint $table) {
            $table->unique('code');
        });

        DB::statement('DROP INDEX IF EXISTS roles_name_unique');
        Schema::table('roles', function (Blueprint $table) {
            $table->unique('name');
        });

        DB::statement('DROP INDEX IF EXISTS accounts_email_unique');
        Schema::table('accounts', function (Blueprint $table) {
            $table->unique('email');
        });

        DB::statement('DROP INDEX IF EXISTS accounts_employee_code_unique');
        Schema::table('accounts', function (Blueprint $table) {
            $table->unique('employee_code');
        });
    }
};
