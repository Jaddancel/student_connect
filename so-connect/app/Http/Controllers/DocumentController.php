<?php

namespace App\Http\Controllers;

use App\Models\Organization;

class DocumentController extends Controller
{
    public function uploadForm()
    {
        $organizations = Organization::orderBy('organization_name')->get();

        return view('forms.upload_document', compact('organizations'));
    }
}
