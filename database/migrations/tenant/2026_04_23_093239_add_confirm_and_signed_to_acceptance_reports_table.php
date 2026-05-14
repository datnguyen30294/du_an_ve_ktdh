<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acceptance_reports', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('note');
            $table->string('confirmed_signature_name')->nullable()->after('confirmed_at');
            $table->text('confirmed_note')->nullable()->after('confirmed_signature_name');

            $table->string('signed_file_path')->nullable()->after('confirmed_note');
            $table->string('signed_file_original_name')->nullable()->after('signed_file_path');
            $table->string('signed_file_mime', 100)->nullable()->after('signed_file_original_name');
            $table->unsignedBigInteger('signed_file_size')->nullable()->after('signed_file_mime');
            $table->timestamp('signed_uploaded_at')->nullable()->after('signed_file_size');
            $table->unsignedBigInteger('signed_uploaded_by_account_id')->nullable()->after('signed_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('acceptance_reports', function (Blueprint $table) {
            $table->dropColumn([
                'confirmed_at',
                'confirmed_signature_name',
                'confirmed_note',
                'signed_file_path',
                'signed_file_original_name',
                'signed_file_mime',
                'signed_file_size',
                'signed_uploaded_at',
                'signed_uploaded_by_account_id',
            ]);
        });
    }
};
