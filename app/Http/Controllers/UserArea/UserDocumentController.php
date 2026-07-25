<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $bookings = $request->user()->bookings()->with(['property', 'payments' => fn ($q) => $q->where('status', 'successful')])->latest()->paginate(10);
        return view('user.documents.index', compact('bookings'));
    }
}
