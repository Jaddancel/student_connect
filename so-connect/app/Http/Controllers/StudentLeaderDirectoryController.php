<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentLeaderDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $organizations = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->select(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name")])
            ->orderBy('od.name')
            ->get();

        $currentSemester = Semester::current();

        $ocrForm = Form::query()
            ->where('directory_assignment_key', 'student-leader-directory')
            ->where('is_active', true)
            ->first();

        return view('pages.auth.signup', [
            'title'             => 'Directory of Student Leader',
            'organizations'     => $organizations,
            'currentSchoolYear' => Semester::currentSchoolYear(),
            'currentSemester'   => $currentSemester?->semesterLabel() ?? '',
            'ocrForm'           => $ocrForm,
        ]);
    }

    public function store(Request $request)
    {
        $user   = $request->user();
        $userId = $user ? (int) $user->getKey() : 0;

        $validated = $request->validate([
            'first_name'           => ['required', 'string', 'max:100'],
            'middle_name'          => ['nullable', 'string', 'max:100'],
            'last_name'            => ['required', 'string', 'max:100'],
            'email'                => ['required', 'email', 'max:255', 'unique:users,user_email'],
            'organization_id'      => ['required', 'integer', 'min:1'],
            'semester'             => ['required', 'string', 'in:1st,2nd,summer'],
            'season'               => ['required', 'string', 'in:summer,fall'],
            'school_year'          => ['required', 'string', 'max:20'],
            'position'             => ['required', 'string', 'in:President,Treasurer,Auditor,Secretary,Others'],
            'contact_number'       => ['required', 'string', 'max:50'],
            'photo'                => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'organization_name'    => ['nullable', 'string', 'max:255'],
            'faculty_advisers'     => ['nullable', 'array'],
            'faculty_advisers.*'   => ['nullable', 'string', 'max:255'],
            'age'                  => ['required', 'integer', 'min:1', 'max:99'],
            'sex'                  => ['required', 'string', 'in:Male,Female'],
            'religious_affiliation'=> ['nullable', 'string', 'max:255'],
            'nationality'          => ['required', 'string', 'max:255'],
            'birthplace'           => ['nullable', 'string', 'max:255'],
            'birthday'             => ['required', 'date'],
            'present_address'      => ['required', 'string', 'max:500'],
            'home_address'         => ['nullable', 'string', 'max:500'],
            'parents_guardian'     => ['required', 'string', 'max:255'],
            'course'               => ['required', 'string', 'max:255'],
            'year_level'           => ['required', 'string', 'max:50'],
            'talents_hobbies'      => ['nullable', 'string', 'max:1000'],
            'financial_support'    => ['nullable', 'array'],
            'financial_support.*'  => ['string', 'max:50'],
            'scholar_provider'     => ['nullable', 'string', 'max:255'],
            'others_specify'       => ['nullable', 'string', 'max:255'],
            'date_filed'           => ['required', 'date'],
            'signature'            => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'password'             => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'student_id'           => ['required', 'digits_between:1,50'],
            'id_photo_front'       => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'id_photo_back'        => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
        ]);

        $organizationId = (int) $validated['organization_id'];

        $orgName = $validated['organization_name'] ?? DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_id', $organizationId)
            ->value('od.name') ?? '';

        $fileDir = 'form-submissions/officer-photos/'.now()->format('Y/m');

        $photoPath = '';
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            $file      = $request->file('photo');
            $photoPath = $file->storeAs(
                $fileDir,
                Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        $signaturePath = '';
        if ($request->hasFile('signature') && $request->file('signature')->isValid()) {
            $file          = $request->file('signature');
            $signaturePath = $file->storeAs(
                $fileDir,
                Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        $idPhotoFrontPath = '';
        if ($request->hasFile('id_photo_front') && $request->file('id_photo_front')->isValid()) {
            $file             = $request->file('id_photo_front');
            $idPhotoFrontPath = $file->storeAs(
                $fileDir,
                Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        $idPhotoBackPath = '';
        if ($request->hasFile('id_photo_back') && $request->file('id_photo_back')->isValid()) {
            $file            = $request->file('id_photo_back');
            $idPhotoBackPath = $file->storeAs(
                $fileDir,
                Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        $payload = [
            'first_name'            => $validated['first_name'],
            'middle_name'           => $validated['middle_name'] ?? '',
            'last_name'             => $validated['last_name'],
            'email'                 => $validated['email'],
            'organization_id'       => $organizationId,
            'organization_name'     => $orgName,
            'semester'              => $validated['semester'],
            'season'                => $validated['season'],
            'school_year'           => $validated['school_year'],
            'position'              => $validated['position'],
            'contact_number'        => $validated['contact_number'],
            'photo'                 => $photoPath,
            'faculty_advisers'      => (function () use ($validated) {
                $rows = array_filter($validated['faculty_advisers'] ?? [], fn ($v) => trim($v) !== '');
                if (empty($rows)) return '';
                return '(' . implode(', ', array_map(fn ($v) => '"' . $v . '"', array_values($rows))) . ')';
            })(),
            'age'                   => $validated['age'],
            'sex'                   => $validated['sex'],
            'religious_affiliation' => $validated['religious_affiliation'] ?? '',
            'nationality'           => $validated['nationality'],
            'birthplace'            => $validated['birthplace'] ?? '',
            'birthday'              => $validated['birthday'],
            'present_address'       => $validated['present_address'],
            'home_address'          => $validated['home_address'] ?? '',
            'parents_guardian'      => $validated['parents_guardian'],
            'course'                => $validated['course'],
            'year_level'            => $validated['year_level'],
            'talents_hobbies'       => $validated['talents_hobbies'] ?? '',
            'financial_support'     => $validated['financial_support'] ?? [],
            'scholar_provider'      => $validated['scholar_provider'] ?? '',
            'others_specify'        => $validated['others_specify'] ?? '',
            'date_filed'            => $validated['date_filed'],
            'signature'             => $signaturePath,
            'password'              => Hash::make($validated['password']),
            'student_id'            => $validated['student_id'] ?? '',
            'id_photo_front'        => $idPhotoFrontPath,
            'id_photo_back'         => $idPhotoBackPath,
        ];

        // Create the FormSubmission first so its ID can be stored in the request payload
        $submissionId = null;
        $form = Form::query()->where('route_name', 'student-leader-directory')->first();

        if ($form) {
            $submission = FormSubmission::query()->create([
                'form_id'         => (int) $form->getKey(),
                'organization_id' => $organizationId,
                'submitted_by'    => $userId > 0 ? $userId : null,
                'submitted_at'    => now(),
                'payload'         => array_merge($payload, [
                    'name' => trim(implode(' ', array_filter([
                        $validated['first_name'],
                        $validated['middle_name'] ?? '',
                        $validated['last_name'],
                    ]))),
                ]),
            ]);
            $submissionId = (int) $submission->getKey();
        }

        // Create the promotion request (action_type=11) for admin approval
        ActionRequest::query()->create([
            'action'       => "0|{$organizationId}|new_officer",
            'action_type'  => 11,
            'payload'      => array_merge($payload, ['form_submission_id' => $submissionId]),
            'user'         => $userId > 0 ? $userId : null,
            'requested_at' => now(),
        ]);

        return redirect()->route('signup')
            ->with('success', 'Your directory submission has been received. Awaiting admin approval.');
    }
}
