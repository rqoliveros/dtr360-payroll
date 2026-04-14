<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | People360</title>
    @vite('resources/css/app.css')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
</head>
<body class="bg-gray-100 flex">

    <!-- Sidebar -->
    @include('layouts.sidebar')

    <!-- Main Content -->
    <main class="flex-1 p-6">
        @include('layouts.header')
        <!-- Date Filters -->
        <div class="flex gap-3 items-end mt-4">
            <div>
                <label class="block text-sm font-medium">Start Date</label>
                <input type="date" id="startDate" class="border rounded px-2 py-1">
            </div>

            <div>
                <label class="block text-sm font-medium">End Date</label>
                <input type="date" id="endDate" class="border rounded px-2 py-1">
            </div>
            @php
                $canSelectDept = in_array($usertype, ['Approver','Admin']);
                $allDepartments = [
                    'Human Resource',
                    'IT',
                    'Marketing',
                    'Sales',
                    'Consulting',
                    'IH',
                    'Testing',
                    'Training',
                    'FAD',
                    'IMS'
                ];
            @endphp

            @if($canSelectDept)
            <div>
                <label class="block text-sm font-medium">Department</label>
                <select id="departmentFilter" class="border rounded px-2 py-1">

                    {{-- HR = Full access --}}
                    @if($dept === 'Human Resource')
                        <option value="all">All Departments</option>
                        @foreach($allDepartments as $d)
                            <option value="{{ $d }}">{{ $d }}</option>
                        @endforeach

                    {{-- Multiple departments --}}
                    @elseif(str_contains($dept, '/'))
                        @php
                            $deptList = array_map('trim', explode('/', $dept));
                        @endphp

                        @foreach($deptList as $d)
                            <option value="{{ $d }}">{{ $d }}</option>
                        @endforeach

                        {{-- Combined option --}}
                        <option value="{{ implode(',', $deptList) }}">
                            {{ implode(' and ', $deptList) }}
                        </option>

                    {{-- Single department --}}
                    @else
                        <option value="{{ $dept }}">{{ $dept }}</option>
                    @endif

                </select>
            </div>
            @endif
            <button id="filterBtn"
                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                Load Attendance
            </button>

        </div>

        <!-- Cutoff shortcuts -->
        <div class="flex gap-2 mt-3">

            <button id="cutoff1"
                class="bg-gray-200 px-3 py-1 rounded hover:bg-gray-300">
                26 prev - 10 current
            </button>

            <button id="cutoff2"
                class="bg-gray-200 px-3 py-1 rounded hover:bg-gray-300">
                11 - 25
            </button>

            <button id="thisMonth"
                class="bg-gray-200 px-3 py-1 rounded hover:bg-gray-300">
                Full Month
            </button>

        </div>
        
        <!-- Table -->
        <div class="container mx-auto mt-6">
            <table id="attendanceTable" class="table-auto border-collapse border border-gray-300 w-full text-sm">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="border px-2 py-1">Employee ID</th>
                        <th class="border px-2 py-1">Employee Name</th>
                        <th class="border px-2 py-1">Department</th>
                        <th class="border px-2 py-1">Date/Time In</th>
                        <th class="border px-2 py-1">Day</th>
                        <th class="border px-2 py-1">Time In</th>
                        <th class="border px-2 py-1">Time Out</th>
                        <th class="border px-2 py-1">Hours Worked</th>
                        <th class="border px-2 py-1">Remarks</th>
                        <th class="border px-2 py-1">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </main>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script>
        const userDept = "{{ $dept }}";
        const usertype = " {{ $usertype }}";
    </script>
    <script>

        $(document).ready(function () {

            const dept = "{{ $dept }}";
            const baseUrl = "{{ url('/payroll/attendance') }}";
            
            let table = null;
            
            function loadTable(startDate, endDate){
                let selectedDept = $('#departmentFilter').length 
                    ? $('#departmentFilter').val() 
                    : "{{ $dept }}";

                if(!selectedDept){
                    selectedDept = 'all';
                }
                let url = `${baseUrl}/${selectedDept}/${startDate}/${endDate}`;

                if(table){
                    table.destroy();
                }

                table = $('#attendanceTable').DataTable({
                    ajax:{
                        url: url,
                        dataSrc:''
                    },
                    dom: 'lBfrtip',
                    pageLength: 50,
                    lengthMenu: [10, 25, 50, 100],
                    scrollY: '60vh',
                    scrollCollapse: true,
                    createdRow: function(row, data) {

                        if(data.remarks && data.remarks.includes('Pending')){
                            $(row).css({
                                'background-color': '#ebcc67',
                                'color': '#374151  b'
                            });
                        }
                        else if (data.holiday !== '' || data.late > 0 || data.undertime > 0) {
                            $(row).css({
                                'background-color': '#F3F4F6',
                                'color': '#374151'
                            });
                        } 
                        else if (data.isAbsent === true) {
                            $(row).css({
                                'background-color': '#FEE2E2',
                                'color': '#991B1B'
                            });

                        }

                    },
                    buttons: [
                                {
                                    extend: 'excelHtml5',
                                    text: 'Export to Excel',
                                    className: 'btn btn-success btn-sm'
                                },
                                {
                                    extend: 'csvHtml5',
                                    text: 'Export to CSV',
                                    className: 'btn btn-primary btn-sm'
                                }
                            ],
                    columns:[
                        { data:'employeeID' },
                        { data:'employeeName' },
                        { data:'department' },
                        { data:'dateTimeIn' },
                        { data:'day' },
                        { 
                            data:'timeIn',
                            render: function(data){
                                if(!data) return '';
                                let date = new Date(data);
                                return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                            }
                        },
                        { 
                            data:'timeOut',
                            render: function(data){
                                if(!data) return '';
                                let date = new Date(data);
                                return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                            }
                        },
                        {
                            data: 'hoursWorked',
                            render: function(data){
                                if(!data) return '';
                                // Split "HH:MM"
                                let parts = data.split(':');
                                let hours = parseInt(parts[0]);
                                let minutes = parseInt(parts[1]);
                                // Convert minutes to fraction of hour
                                let decimal = hours + (minutes / 60);
                                // Round to 2 decimal places
                                return decimal.toFixed(2);
                            }
                        },
                        { data:'remarks' },
                        {
                            data:null,
                            render:function(){
                                return `<button class="text-blue-500">Edit</button>`;
                            }
                        }
                    ]
                });

            }

            // Manual filter
            $('#filterBtn').click(function(){

                let startDate = $('#startDate').val();
                let endDate = $('#endDate').val();

                if(!startDate || !endDate){
                    alert("Please select start and end date");
                    return;
                }

                loadTable(startDate,endDate);

            });
            $('#departmentFilter').change(function(){
                console.log("Changed to:", $(this).val());
            });

            // Cutoff 26 prev month -> 10 current month
            $('#cutoff1').click(function(){
                let today = new Date();
                let start = new Date(today.getFullYear(), today.getMonth()-1, 26);
                let end = new Date(today.getFullYear(), today.getMonth(), 10);

                $('#startDate').val(start.toLocaleDateString('en-CA'));
                $('#endDate').val(end.toLocaleDateString('en-CA'));
            });

            // Cutoff 11 -> 25 current month
            $('#cutoff2').click(function(){
                let today = new Date();
                let start = new Date(today.getFullYear(), today.getMonth(), 11);
                let end = new Date(today.getFullYear(), today.getMonth(), 25);

                $('#startDate').val(start.toLocaleDateString('en-CA'));
                $('#endDate').val(end.toLocaleDateString('en-CA'));
            });

             // Full month
            $('#thisMonth').click(function(){
                let today = new Date();
                let start = new Date(today.getFullYear(), today.getMonth(), 1);
                let end = new Date(today.getFullYear(), today.getMonth()+1, 0);

                $('#startDate').val(start.toLocaleDateString('en-CA'));
                $('#endDate').val(end.toLocaleDateString('en-CA'));
            });

        });

    </script>
</body>
</html>