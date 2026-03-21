<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()?->isSuperuser()) {
            return Inertia::render('Superuser/Profile');
        }

        return Inertia::render('Profile');
    }

    public function superuser(Request $request)
    {
        abort_unless($request->user()?->isSuperuser(), 403);

        return Inertia::render('Superuser/Profile');
    }
}
