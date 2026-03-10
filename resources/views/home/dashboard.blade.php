<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | People360</title>
    @vite('resources/css/app.css')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 flex">

    <!-- Sidebar -->
    @include('layouts.sidebar')

    <!-- Main Content -->
    <main class="flex-1 p-6">
        @include('layouts.header')

        <!-- Cards -->
        <div class="grid grid-cols-3 gap-6 mb-6">
            <div class="bg-white shadow rounded p-4">
                <h2 class="text-gray-500">Employees</h2>
                <p class="text-2xl font-bold mt-2">120</p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <h2 class="text-gray-500">Departments</h2>
                <p class="text-2xl font-bold mt-2">8</p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <h2 class="text-gray-500">Pending Leaves</h2>
                <p class="text-2xl font-bold mt-2">5</p>
            </div>
        </div>

        <!-- Chart Example -->
        <div class="bg-white shadow rounded p-6">
            <h2 class="text-xl font-semibold mb-4">Attendance Summary</h2>
            <canvas id="attendanceChart" height="100"></canvas>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                datasets: [{
                    label: 'Present',
                    data: [20, 22, 18, 25, 21],
                    backgroundColor: 'rgba(34,197,94,0.7)'
                },{
                    label: 'Absent',
                    data: [2, 0, 4, 1, 3],
                    backgroundColor: 'rgba(239,68,68,0.7)'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } }
            }
        });
    </script>

</body>
</html>