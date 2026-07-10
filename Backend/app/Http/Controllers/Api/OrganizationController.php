<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function show()
    {
        return response()->json($this->singleton());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'organized_by' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'twitter' => ['nullable', 'string', 'max:255'],
            'privacy_policy_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $org = $this->singleton();
        $org->update($data);

        return response()->json($org);
    }

    /**
     * There is only ever one organizations row (id=1), matching the mock's
     * flat getOrg()/updateOrg() (plan §7 - no multi-tenancy).
     */
    private function singleton(): Organization
    {
        return Organization::firstOrCreate(['id' => 1], ['name' => 'My Organization']);
    }
}
