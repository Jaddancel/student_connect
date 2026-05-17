<?php

namespace App\Http\Controllers;

use App\Helpers\DashboardSearchHelper;
use Illuminate\Http\Request;

class DashboardSearchController extends Controller
{
    public function index(Request $request)
    {
        $q = (string) $request->query('q', '');
        $results = DashboardSearchHelper::search(auth()->user(), $q);
        return response()->json($results);
    }
}
