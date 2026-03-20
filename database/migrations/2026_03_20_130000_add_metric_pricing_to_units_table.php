<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SQM_PER_SQFT = 0.09290304;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('size_sqm', 10, 2)->nullable()->after('size_sqft');
            $table->decimal('rate_per_sqm', 12, 2)->nullable()->after('size_sqm');
            $table->enum('currency', ['TZS', 'USD'])->default('USD')->after('rate_per_sqm');
        });

        DB::table('units')
            ->select(['id', 'size_sqft', 'rent'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $sizeSqm = round(((float) $row->size_sqft) * self::SQM_PER_SQFT, 2);
                    $ratePerSqm = $sizeSqm > 0 ? round(((float) $row->rent) / $sizeSqm, 2) : 0;

                    DB::table('units')
                        ->where('id', $row->id)
                        ->update([
                            'size_sqm' => $sizeSqm,
                            'rate_per_sqm' => $ratePerSqm,
                            'currency' => 'USD',
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['size_sqm', 'rate_per_sqm', 'currency']);
        });
    }
};
