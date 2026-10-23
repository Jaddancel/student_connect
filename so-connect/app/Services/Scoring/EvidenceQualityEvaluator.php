<?php

namespace App\Services\Scoring;

use Illuminate\Support\Facades\Storage;

class EvidenceQualityEvaluator
{
    /**
     * A deterministic, explainable quality check—not an authenticity claim.
     * It evaluates availability, file size, image resolution, and report-field
     * completeness so weak evidence is flagged for human review.
     *
     * @param array<string,mixed> $payload
     * @param string[] $paths
     * @return array{score:?int,status:string,notes:string[]}
     */
    public function evaluate(array $payload, array $paths): array
    {
        if ($paths === []) {
            return ['score' => null, 'status' => 'not_applicable', 'notes' => ['No uploaded attachment is linked to this evidence.']];
        }

        $disk = Storage::disk((string) config('documents.disk', 'public'));
        $fileScores = [];
        $notes = [];

        foreach (array_values(array_unique($paths)) as $path) {
            if (! $disk->exists($path)) {
                $fileScores[] = 0;
                $notes[] = basename($path).': file is missing.';
                continue;
            }

            $score = 30;
            $size = (int) ($disk->size($path) ?: 0);
            if ($size >= 50 * 1024) {
                $score += 20;
            } else {
                $notes[] = basename($path).': file is smaller than 50 KB.';
            }

            $mime = (string) ($disk->mimeType($path) ?: '');
            if (str_starts_with($mime, 'image/')) {
                $absolute = method_exists($disk, 'path') ? $disk->path($path) : null;
                $dimensions = $absolute ? @getimagesize($absolute) : false;
                if ($dimensions && $dimensions[0] >= 800 && $dimensions[1] >= 600) {
                    $score += 40;
                } else {
                    $notes[] = basename($path).': image resolution is below 800×600 or unreadable.';
                }
            } else {
                $score += 30;
            }

            $fileScores[] = min(90, $score);
        }

        $meaningful = collect($payload)->except(['_waiver_validation', '_signature_verification', '_manual_source'])
            ->filter(fn ($value) => ! ($value === null || $value === '' || $value === []));
        $completionBonus = $meaningful->count() >= 4 ? 10 : 0;
        if ($completionBonus === 0) {
            $notes[] = 'The linked report has fewer than four completed data fields.';
        }

        $score = min(100, (int) round(array_sum($fileScores) / max(1, count($fileScores))) + $completionBonus);
        $status = $score >= 80 ? 'good' : ($score >= 50 ? 'needs_review' : 'poor');

        return ['score' => $score, 'status' => $status, 'notes' => $notes ?: ['File and report completeness checks passed.']];
    }
}
