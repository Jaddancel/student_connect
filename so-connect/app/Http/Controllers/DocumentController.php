<?php

namespace App\Http\Controllers;

use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2 || (int) $user->user_type === 1;
        $filterOrgId = (int) $request->query('organization_id', 0);

        $base = DB::table('generated_documents as gd')
            ->leftJoin('form_submissions as fs', 'fs.form_submission_id', '=', 'gd.form_submission_id')
            ->leftJoin('forms as f', 'f.id', '=', 'fs.form_id')
            ->leftJoin('organizations as o', 'o.organization_id', '=', 'fs.organization_id')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('gd.status', 'generated')
            ->select([
                'gd.generated_document_id',
                'gd.document_id',
                'gd.pdf_path',
                'gd.generated_at',
                'f.name as form_name',
                'f.route_name as form_route',
                'fs.organization_id',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as org_name"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fs.payload, '$.schoolyear')) as semester_name"),
            ])
            ->orderByDesc('gd.generated_at');

        if ($isAdmin) {
            if ($filterOrgId > 0) {
                $base->where('fs.organization_id', $filterOrgId);
            }

            $orgNames = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', function ($sub) {
                    $sub->select('fs2.organization_id')
                        ->from('generated_documents as gd2')
                        ->leftJoin('form_submissions as fs2', 'fs2.form_submission_id', '=', 'gd2.form_submission_id')
                        ->where('gd2.status', 'generated')
                        ->whereNotNull('fs2.organization_id');
                })
                ->orderBy('od.name')
                ->get(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name")]);
        } else {
            $orgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);

            if (empty($orgIds)) {
                $documents = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
                return view('pages.documents.index', [
                    'title' => 'Documents',
                    'documents' => $documents,
                    'orgNames' => collect(),
                    'filterOrgId' => 0,
                    'isAdmin' => false,
                ]);
            }

            $base->whereIn('fs.organization_id', $orgIds);
            $orgNames = collect();
        }

        $documents = $base->paginate(20);

        return view('pages.documents.index', [
            'title' => 'Documents',
            'documents' => $documents,
            'orgNames' => $orgNames ?? collect(),
            'filterOrgId' => $filterOrgId,
            'isAdmin' => $isAdmin,
        ]);
    }
}
