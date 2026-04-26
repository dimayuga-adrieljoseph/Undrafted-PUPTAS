<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Generates unique student numbers atomically using a dedicated sequences table.
 *
 * The `student_number_sequences` table holds one row per (year, prefix) pair,
 * storing the last-issued sequence number. We lock that row with SELECT FOR UPDATE
 * inside a transaction so that concurrent callers are serialised at the DB level,
 * making duplicate generation impossible.
 *
 * Format produced: YYYY-<PREFIX>-NNNN   (e.g. 2026-MED-0001)
 */
class StudentNumberService
{
    /**
     * Generate and reserve the next student number for the given prefix.
     *
     * This method MUST be called inside an open DB::transaction() to guarantee
     * that the SELECT FOR UPDATE lock is held for the full unit of work.
     *
     * @param  string  $prefix  Upper-case prefix (e.g. 'MED', 'STU')
     * @param  int|null  $year  4-digit year; defaults to the current year
     * @return string           e.g. "2026-MED-0001"
     */
    public function generate(string $prefix = 'MED', ?int $year = null): string
    {
        $year = $year ?? (int) date('Y');

        // Lock the row for this (year, prefix) pair for the duration of the transaction.
        // lockForUpdate() issues SELECT … FOR UPDATE, blocking other transactions
        // that try to lock the same row until this transaction commits or rolls back.
        $sequence = DB::table('student_number_sequences')
            ->where('year', $year)
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->first();

        if ($sequence) {
            $nextNum = $sequence->last_number + 1;
            DB::table('student_number_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'last_number' => $nextNum,
                    'updated_at'  => now(),
                ]);
        } else {
            // First student number for this year/prefix — seed the row.
            $nextNum = 1;
            DB::table('student_number_sequences')->insert([
                'year'        => $year,
                'prefix'      => $prefix,
                'last_number' => $nextNum,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return sprintf('%d-%s-%04d', $year, $prefix, $nextNum);
    }
}
