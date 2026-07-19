<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Waiver form recognition
    |--------------------------------------------------------------------------
    |
    | Tunable thresholds for matching a scanned waiver's extracted details
    | against the event's expected details (WaiverValidationService), and the
    | zone types a waiver template may declare.
    */

    // Minimum normalized name similarity (0..1) for a name field to count as a match.
    'name_similarity_threshold' => (float) env('WAIVER_NAME_SIMILARITY', 0.80),

    // Whether an embossed dry-seal / stamp must be detected for the waiver to pass.
    'require_stamp' => (bool) env('WAIVER_REQUIRE_STAMP', true),

    // Whether a handwritten signature must be present.
    'require_signature' => (bool) env('WAIVER_REQUIRE_SIGNATURE', true),

    // Allowed waiver-template zone types.
    'zone_types' => ['text', 'signature', 'stamp'],

    // Where scanned waiver images are stored (on the documents disk).
    'storage_dir' => 'waivers',
];
