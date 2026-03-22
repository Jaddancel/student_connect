<?php

namespace App\Http\Controllers;

use App\Services\ActionService;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function createDocumentGenerationRequest(Request $request)
    {
        $validatedData = $request->validate([
            'document_title' => 'required|string',
            'document_desc_text' => 'required|string',
            'document_link' => 'required|string',
            'document_author' => auth()->user()->member()->member_id ?? null,
        ]);

        (new ActionService)->passAction($validatedData, 3);
    }

    public function createDocumentAccessRequest(Request $request)
    {
        $validatedData = $request->validate([
            'document_id' => 'required|integer',
            'member_id' => auth()->user()->member()->member_id ?? null,
        ]);

        (new ActionService)->passAction($validatedData, 4);

    }
}
