<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Session; 
use Illuminate\Support\Facades\View;

class FirebaseAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Session::has('firebase_user')) {
            return redirect('/login');
        }

        $email = Session::get('firebase_user.email');

        // get employees (your existing function)
        $employees = app()->make(\App\Http\Controllers\PayrollController::class)->getAllEmployees();

        $user = collect($employees)->firstWhere('email', $email);
        
        if ($user) {
            View::share('authUser', $user->empName);
            View::share('dept', $user->dept);
            View::share('usertype', $user->usertype);
        }

        return $next($request);
    }
}
