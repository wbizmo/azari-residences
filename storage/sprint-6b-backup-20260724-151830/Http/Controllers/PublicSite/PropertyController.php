<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Contracts\View\View;

class PropertyController extends Controller
{
    public function show(Property $property): View
    {
        abort_unless($property->is_published, 404);

        return view('public.properties.show', [
            'property' => $property->load('amenities'),
        ]);
    }
}
