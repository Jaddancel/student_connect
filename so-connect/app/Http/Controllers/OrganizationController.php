<?php

namespace App\Http\Controllers;

<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
use App\Models\Organization;
use App\Services\UserOrganizationService;
>>>>>>> main
>>>>>>> 38779d9f7f289501ec430fe173a943e7552a93e4
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
<<<<<<< HEAD
    //
=======
<<<<<<< HEAD
    //
=======
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function getUserOrganizations(UserOrganizationService $userOrganizationService)
    {
        $organizations = $userOrganizationService->getUserOrganizations(auth()->user());

        return view('my_organization', ['organizations' => $organizations]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Organization $organization)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Organization $organization)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization)
    {
        //
    }
>>>>>>> main
>>>>>>> 38779d9f7f289501ec430fe173a943e7552a93e4
}
