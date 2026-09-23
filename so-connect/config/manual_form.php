<?php

use App\Forms\FieldType;

return [

    /*
    |--------------------------------------------------------------------------
    | Writable-area minimum sizes
    |--------------------------------------------------------------------------
    |
    | When a paper field is located inside a printed table cell, its writable
    | area is derived deterministically from the OOXML cell geometry (position
    | + size). These thresholds decide whether the blank space that remains in
    | that cell is large enough for a handwritten entry: a required field whose
    | remaining space is below the minimum is reported `missing`, forcing the
    | value onto the digital form instead.
    |
    | Sizes are in twips (1/20 pt, Word's native unit). `min_height` is the
    | blank vertical room; `min_width` is the horizontal room. `per_type`
    | overrides the default for specific Form Builder field types.
    |
    */

    'writable_area' => [
        'default' => [
            'min_height' => 240,  // ~one 12pt line
            'min_width' => 720,   // ~0.5 inch
        ],

        'per_type' => [
            FieldType::TEXTAREA => ['min_height' => 720, 'min_width' => 720],
            FieldType::TABLE_INPUT => ['min_height' => 720, 'min_width' => 720],
            FieldType::TEXT_LIST => ['min_height' => 480, 'min_width' => 720],
            FieldType::SIGNATURE => ['min_height' => 480, 'min_width' => 1080],
        ],

        // Below this fraction of the required minimum the cell is `missing`;
        // between this and 1.0 it is `uncertain`; at or above it is `present`.
        'uncertain_ratio' => 0.75,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-height row estimation
    |--------------------------------------------------------------------------
    |
    | Rows without an exact height grow with their content. To capture the
    | "distortion" a long digital value introduces, the height of an auto row
    | is estimated from the characters it holds. These knobs tune that model.
    |
    */

    'row_estimation' => [
        'default_font_twips' => 240,  // 12pt
        'line_height_factor' => 1.15, // rendered line height vs. font size
        // Average glyph advance as a fraction of the font size; used to turn a
        // cell's usable width into an approximate characters-per-line budget.
        'avg_char_width_factor' => 0.5,
        'cell_margin_twips' => 216,   // Word's default 0.15" left+right inset
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature capture
    |--------------------------------------------------------------------------
    |
    | A hand-signed signature is cropped from the aligned scan and stored so the
    | review can pre-fill it. Cropping the wrong region extracts printed text as
    | "ink", so it only runs when a field's bounds came from the accurate
    | rendered-position locator (bounds_source = "render"), never the coarse
    | OOXML position estimate. When no accurate bounds exist the field stays
    | blank for the user to supply.
    |
    */

    'signature_capture' => [
        'enabled' => env('MANUAL_FORM_SIGNATURE_CAPTURE', true),
        // Fraction of page height to crop above a signature anchor when no text
        // below it is found to bound the region.
        'fallback_band' => 0.06,
    ],

];
