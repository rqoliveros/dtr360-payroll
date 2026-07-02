<header class="mb-6 flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white">
            P
        </div>
        <div>
            <h1 class="text-lg font-semibold text-slate-900">@yield('page-title', 'Payroll')</h1>
            <p class="text-sm text-slate-500">Welcome back</p>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <div class="relative">
            <details class="group relative">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span>Notifications</span>
                    <span class="rounded-full bg-rose-500 px-2 py-0.5 text-[11px] font-semibold text-white">3</span>
                </summary>

                <div class="absolute right-0 z-20 mt-2 w-80 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                    <div class="flex items-center justify-between border-b border-slate-100 px-2 pb-2">
                        <p class="text-sm font-semibold text-slate-800">Notifications</p>
                        <a href="#" class="text-xs font-medium text-sky-600 hover:underline">View all</a>
                    </div>

                    <div class="mt-2 space-y-2">
                        <a href="#" class="flex items-start gap-2 rounded-lg p-2 hover:bg-slate-50">
                            <div class="mt-0.5 h-2.5 w-2.5 rounded-full bg-sky-500"></div>
                            <div>
                                <p class="text-sm text-slate-700">New attendance report is ready.</p>
                                <p class="text-xs text-slate-400">10 mins ago</p>
                            </div>
                        </a>
                        <a href="#" class="flex items-start gap-2 rounded-lg p-2 hover:bg-slate-50">
                            <div class="mt-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500"></div>
                            <div>
                                <p class="text-sm text-slate-700">Payroll update completed successfully.</p>
                                <p class="text-xs text-slate-400">1 hour ago</p>
                            </div>
                        </a>
                        <a href="#" class="flex items-start gap-2 rounded-lg p-2 hover:bg-slate-50">
                            <div class="mt-0.5 h-2.5 w-2.5 rounded-full bg-amber-500"></div>
                            <div>
                                <p class="text-sm text-slate-700">Reminder: submit timesheet before 5 PM.</p>
                                <p class="text-xs text-slate-400">Today</p>
                            </div>
                        </a>
                    </div>
                </div>
            </details>
        </div>

        <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-2">
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white">
                {{ strtoupper(substr(Session::get('firebase_user.name') ?? 'User', 0, 1)) }}
            </div>
            <div class="flex flex-col">
                <span class="text-sm font-semibold text-slate-800">{{ Session::get('firebase_user.name') ?? 'User' }}</span>
                <a href="{{ url('/logout') }}" class="text-xs text-rose-500 hover:underline">Logout</a>
            </div>
        </div>
    </div>
</header>