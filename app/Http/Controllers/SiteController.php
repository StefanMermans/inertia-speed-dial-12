<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SiteController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSiteRequest $request)
    {
        $icon = $request->file('icon');
        $iconPath = $icon->store('images', [
            'disk' => 'public'
        ]);
        $site = Site::make($request->all());
        $site->icon_path = $iconPath;
        $site->save();

        return to_route('speed-dial');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSiteRequest $request, Site $site): RedirectResponse
    {
        $site->update($request->all());

        return to_route('speed-dial');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Site $site)
    {
        $site->delete();
    }
}
