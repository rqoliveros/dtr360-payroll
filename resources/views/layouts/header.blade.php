<header class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-semibold">@yield('page-title', 'Payroll')</h1>
    <div>
        <span class="text-gray-700 mr-4">Hello, {{ Session::get('firebase_user.name') ?? 'User' }}</span>
        <a href="{{ url('/logout') }}" class="text-red-500 hover:underline">Logout</a>
    </div>
</header>