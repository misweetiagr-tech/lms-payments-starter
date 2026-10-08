<?php

namespace App\Services\Results;

use App\Models\Enrollment;
use App\Models\Result;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * Imports marks from sheet rows.
 *
 * Trust rule: a sheet supplies ONLY the enrollment number, the subject code and
 * the marks. Which student, which course and which subjects are valid is always
 * re-derived on the server. Any extra columns (a typed student name, a course)
 * are ignored, so a wrong or tampered sheet cannot change who gets a result.
 *
 * Valid rows are saved, invalid rows are reported back with their row number.
 * Re-importing the same sheet updates rows instead of duplicating them.
 */
class ResultImporter
{
    /**
     * @param  list<array<string, mixed>>  $rows  keys: enrollment_number, subject_code, marks (number or "absent")
     * @return array{imported: int, errors: list<array{row: int, error: string}>}
     */
    public function import(array $rows): array
    {
        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;

            try {
                DB::transaction(fn () => $this->importRow($row));
                $imported++;
            } catch (InvalidResultRow $e) {
                $errors[] = ['row' => $line, 'error' => $e->getMessage()];
            }
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    private function importRow(array $row): void
    {
        $number = trim((string) ($row['enrollment_number'] ?? ''));
        $code = trim((string) ($row['subject_code'] ?? ''));
        $marks = strtolower(trim((string) ($row['marks'] ?? '')));

        $enrollment = Enrollment::where('enrollment_number', $number)->first();
        if (! $enrollment) {
            throw new InvalidResultRow("Unknown enrollment number '{$number}'.");
        }

        $subject = Subject::where('code', $code)
            ->whereIn('id', DB::table('course_subjects')->where('course_id', $enrollment->course_id)->select('subject_id'))
            ->first();
        if (! $subject) {
            throw new InvalidResultRow("Subject '{$code}' is not part of this student's course.");
        }

        $absent = $marks === 'absent';

        if (! $absent) {
            if (! ctype_digit($marks)) {
                throw new InvalidResultRow("Marks '{$marks}' is not a whole number or 'absent'.");
            }
            if ((int) $marks > $subject->max_marks) {
                throw new InvalidResultRow("Marks {$marks} exceed the maximum of {$subject->max_marks}.");
            }
        }

        Result::updateOrCreate(
            ['enrollment_id' => $enrollment->id, 'subject_id' => $subject->id],
            ['marks' => $absent ? null : (int) $marks, 'absent' => $absent],
        );
    }
}
