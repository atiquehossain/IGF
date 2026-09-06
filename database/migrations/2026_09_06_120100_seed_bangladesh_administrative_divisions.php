<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EXPECTED_DIVISIONS = 8;
    private const EXPECTED_DISTRICTS = 64;
    private const EXPECTED_UPAZILAS = 495;

    public function up(): void
    {
        if (!Schema::hasTable('divisions') || !Schema::hasTable('districts') || !Schema::hasTable('upazilas')) {
            throw new RuntimeException('The Bangladesh geography tables must exist before importing their canonical data.');
        }

        $path = database_path('data/bangladesh-administrative-divisions.json');
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("The canonical Bangladesh geography dataset is unavailable at {$path}.");
        }

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $divisions = $this->validatedDivisions($decoded);

        DB::transaction(function () use ($divisions): void {
            $now = now();

            foreach ($divisions as $division) {
                $divisionId = DB::table('divisions')
                    ->whereRaw('LOWER(name) = ?', [strtolower($division['name'])])
                    ->value('id');

                if ($divisionId === null) {
                    $divisionId = DB::table('divisions')->insertGetId([
                        'name' => $division['name'],
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                foreach ($division['districts'] as $district) {
                    $districtId = DB::table('districts')
                        ->where('division_id', $divisionId)
                        ->whereRaw('LOWER(name) = ?', [strtolower($district['name'])])
                        ->value('id');

                    if ($districtId === null) {
                        $districtId = DB::table('districts')->insertGetId([
                            'name' => $district['name'],
                            'division_id' => $divisionId,
                            'status' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    foreach ($district['upazilas'] as $upazila) {
                        $exists = DB::table('upazilas')
                            ->where('district_id', $districtId)
                            ->whereRaw('LOWER(name) = ?', [strtolower($upazila)])
                            ->exists();

                        if (!$exists) {
                            DB::table('upazilas')->insert([
                                'name' => $upazila,
                                'district_id' => $districtId,
                                'status' => 1,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Geography is shared reference data. A rollback must not remove rows that
        // may already be in use or that pre-dated this idempotent import.
    }

    /**
     * @return list<array{name: string, districts: list<array{name: string, upazilas: list<string>}>>>
     */
    private function validatedDivisions(mixed $decoded): array
    {
        if (!is_array($decoded) || !isset($decoded['divisions']) || !is_array($decoded['divisions'])) {
            throw new RuntimeException('The Bangladesh geography dataset must contain a divisions array.');
        }

        $districtCount = 0;
        $upazilaCount = 0;
        $seenDivisions = [];

        foreach ($decoded['divisions'] as $divisionIndex => $division) {
            if (!is_array($division) || !$this->validName($division['name'] ?? null) || !isset($division['districts']) || !is_array($division['districts'])) {
                throw new RuntimeException("Invalid division at dataset index {$divisionIndex}.");
            }

            $divisionKey = strtolower(trim($division['name']));
            if (isset($seenDivisions[$divisionKey])) {
                throw new RuntimeException("Duplicate division in geography dataset: {$division['name']}.");
            }
            $seenDivisions[$divisionKey] = true;
            $seenDistricts = [];

            foreach ($division['districts'] as $districtIndex => $district) {
                if (!is_array($district) || !$this->validName($district['name'] ?? null) || !isset($district['upazilas']) || !is_array($district['upazilas'])) {
                    throw new RuntimeException("Invalid district at {$division['name']} index {$districtIndex}.");
                }

                $districtKey = strtolower(trim($district['name']));
                if (isset($seenDistricts[$districtKey])) {
                    throw new RuntimeException("Duplicate district in geography dataset: {$division['name']} / {$district['name']}.");
                }
                $seenDistricts[$districtKey] = true;
                $seenUpazilas = [];
                $districtCount++;

                foreach ($district['upazilas'] as $upazilaIndex => $upazila) {
                    if (!$this->validName($upazila)) {
                        throw new RuntimeException("Invalid upazila at {$division['name']} / {$district['name']} index {$upazilaIndex}.");
                    }

                    $upazilaKey = strtolower(trim($upazila));
                    if (isset($seenUpazilas[$upazilaKey])) {
                        throw new RuntimeException("Duplicate upazila in geography dataset: {$division['name']} / {$district['name']} / {$upazila}.");
                    }
                    $seenUpazilas[$upazilaKey] = true;
                    $upazilaCount++;
                }
            }
        }

        if (count($decoded['divisions']) !== self::EXPECTED_DIVISIONS
            || $districtCount !== self::EXPECTED_DISTRICTS
            || $upazilaCount !== self::EXPECTED_UPAZILAS) {
            throw new RuntimeException(sprintf(
                'Unexpected Bangladesh geography counts: expected %d/%d/%d divisions/districts/upazilas, received %d/%d/%d.',
                self::EXPECTED_DIVISIONS,
                self::EXPECTED_DISTRICTS,
                self::EXPECTED_UPAZILAS,
                count($decoded['divisions']),
                $districtCount,
                $upazilaCount
            ));
        }

        return $decoded['divisions'];
    }

    private function validName(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= 255;
    }
};
