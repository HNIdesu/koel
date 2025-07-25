<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('songs', static function (Blueprint $table): void {
            // This migration is actually to fix a mistake that the original one was deleted.
            // Therefore, we just "try" it and ignore on error.
            rescue_if(Schema::hasColumn('songs', 'contributing_artist_id'), static function () use ($table): void {
                Schema::withoutForeignKeyConstraints(static function () use ($table): void {
                    $table->dropForeign(['contributing_artist_id']);
                    $table->dropColumn('contributing_artist_id');
                });
            });
        });
    }
};
