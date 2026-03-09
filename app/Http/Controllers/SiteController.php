<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class SiteController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSiteRequest $request)
    {
        $site = Site::make($request->safe()->except(['icon']));
        $site->addMedia($request->file('icon'))->toMediaCollection();
        $site->save();

        return to_route('speed-dial');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSiteRequest $request, Site $site): RedirectResponse
    {
        $site->fill($request->safe()->except(['icon']));

        if ($request->hasFile('icon')) {
            $site->deleteAllMedia();
            $site->addMedia($request->file('icon'))->toMediaCollection();
        }

        $site->save();

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
