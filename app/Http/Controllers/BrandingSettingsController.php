<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBrandingSettingsRequest;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BrandingSettingsController extends Controller
{
    public function edit(): View
    {
        return view('branding.edit');
    }

    public function update(UpdateBrandingSettingsRequest $request): RedirectResponse
    {
        $settings = AppSetting::safeCurrent();

        $data = $request->safe()->only([
            'issuer_name',
            'issuer_rfc',
            'issuer_business_name',
            'quote_brand_name',
        ]);

        if ($request->boolean('remove_logo') && $settings->brand_logo_path) {
            Storage::disk('public')->delete($settings->brand_logo_path);
            $data['brand_logo_path'] = null;
        }

        if ($request->hasFile('brand_logo')) {
            if ($settings->brand_logo_path) {
                Storage::disk('public')->delete($settings->brand_logo_path);
            }

            $data['brand_logo_path'] = $request->file('brand_logo')->store('branding', 'public');
        }

        $settings->fill($data);
        $settings->save();

        AppSetting::refreshCache();

        return Redirect::route('branding.edit')->with('status', 'branding-updated');
    }
}
