<?php

namespace App\Services\DocumentVision;

/**
 * Provider-independent boundary for the document-vision model that reads
 * handwritten scans of printed request forms.
 *
 * Implementations MUST never throw into the request/job flow: every method
 * returns a structured envelope (see docs/manual-form-parsing-contract.md), and
 * failures are reported as `ok: false` with a closed-set `status`. Raw model
 * output is never returned to callers without strict validation first.
 */
interface DocumentVisionClient
{
    /**
     * Extract written/typed values for the declared fields from the aligned
     * scan pages. Fields with an entry in `crops` are read from that cropped
     * answer area on its own; the rest (and any whose crop read failed) are
     * read from the whole pages.
     *
     * @param  array{
     *     known_values?: array<string,mixed>,
     *     fields: array<int,array{key:string,label?:string,type:string,options?:?array<int,array{value:string,label:string}>,page:int,bounds?:array<int,float>}>,
     *     pages: array<int,array{index:int,reference_image:string,scan_image:string}>,
     *     crops?: array<int,array{key:string,label:string,type:string,options:mixed,image:string}>,
     *     progress?: callable(int $done, int $total, string $label): void
     * }  $request
     * @return array{
     *     ok: bool,
     *     status?: string,
     *     model?: string,
     *     values?: array<string,mixed>,
     *     signatures?: array<string,array{present:bool,page:int,bounds:?array<int,float>}>,
     *     confidence?: array<string,float>,
     *     unresolved?: array<int,string>,
     *     warnings?: array<int,string>,
     *     sources?: array<string,string>,
     *     error?: string
     * }
     */
    public function extract(array $request): array;

    /**
     * Locate the writable region of each declared field on a blank render of a
     * template page. Used to build the template baseline schema.
     *
     * @param  array{
     *     fields: array<int,array{key:string,label:string,type:string}>,
     *     pages: array<int,array{index:int,image:string}>
     * }  $request
     * @return array{
     *     ok: bool,
     *     status?: string,
     *     model?: string,
     *     areas?: array<string,array{page:int,bounds:?array<int,float>,writable_area:string}>,
     *     warnings?: array<int,string>,
     *     error?: string
     * }
     */
    public function locateWritableAreas(array $request): array;

    /**
     * Is the provider configured and reachable? Diagnostics only — never gates
     * draft creation, since drafts stay resumable while the model is down.
     */
    public function healthy(): bool;
}
