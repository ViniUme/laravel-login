<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SignUpAuthController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Auth/SignUpAuth');
    }
}
