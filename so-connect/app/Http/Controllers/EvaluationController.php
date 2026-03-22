<?php

namespace App\Http\Controllers;

use App\Services\ActionService;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    public function createEvaluationRequest(Request $request)
    {

        $validatedData = $request->validate([
            'evaluation_description_text' => 'required|string',
            'evaluation_author' => auth()->user()->member()->member_id ?? null,
            'evaluation_score' => 'required|integer|min:1|max:5',
        ]);

        // Here you would typically save the evaluation to the database
        // For example:
        // Evaluation::create($validatedData);
        ((new ActionService)->passAction($validatedData, 2));
    }
}
