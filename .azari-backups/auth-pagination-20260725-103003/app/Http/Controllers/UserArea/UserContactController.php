<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\View\View;

class UserContactController extends Controller
{
    public function __invoke(string $page = 'contact'): View
    {
        return view('user.contact', [
            'page' => $page,
            'contactEmail' => SiteSetting::valueFor('customer_dashboard_contact_email', SiteSetting::valueFor('contact_email', config('mail.from.address'))),
            'contactPhone' => SiteSetting::valueFor('contact_phone'),
            'whatsApp' => SiteSetting::valueFor('whatsapp_number'),
            'supportHours' => SiteSetting::valueFor('support_hours', 'Support hours are available from the residence team.'),
        ]);
    }
}
