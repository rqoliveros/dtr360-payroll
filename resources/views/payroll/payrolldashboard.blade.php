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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .flatpickr-input.form-control {
            width: 100%;
        }
        .flatpickr-input.form-control:focus {
            box-shadow: none;
        }
        #editRowModal .flatpickr-wrapper {
            width: 100%;
        }
        #editRowModal .form-label {
            margin-bottom: 0.25rem;
        }
    </style>
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
                        <th class="border px-2 py-1">Payroll Computations</th>
                        <th class="border px-2 py-1">Remarks</th>
                        <th class="border px-2 py-1">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <!-- Edit Row Modal -->
        <div class="modal fade" id="editRowModal" tabindex="-1" aria-labelledby="editRowModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editRowModalLabel">Edit Attendance Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editRowForm">
                            <div class="row g-3">
                                
                                <div class="col-md-6">
                                    <label class="form-label">Date/Time In</label>
                                    <input type="text" id="editTimeIn" class="form-control" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Time Out</label>
                                    <input type="text" id="editTimeOut" class="form-control" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Day</label>
                                    <input type="text" id="editDay" class="form-control" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Hours Worked</label>
                                    <input type="text" id="editHoursWorked" class="form-control" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Remarks</label>
                                    <textarea id="editRemarks" class="form-control" rows="4"></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="saveRowBtn">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>

        $(document).ready(function () {

            const dept = "{{ $dept }}";
            const usertype = " {{ $usertype }}";
            const guid = " {{ $guid }}";
            const baseUrl = "{{ url('/payroll/attendance') }}";
            
            let table = null;
            let timeInPicker = null;
            let timeOutPicker = null;
            let currentRowData = null;

            function initEditPickers(){
                timeInPicker = flatpickr('#editTimeIn', {
                    enableTime: true,
                    dateFormat: 'Y-m-d h:i K',
                    altInput: true,
                    altInputClass: 'form-control',
                    altFormat: 'F j, Y h:i K',
                    allowInput: true,
                    minuteIncrement: 15,
                    enableSeconds: false,
                    time_24hr: false,
                    defaultDate: null,
                    clickOpens: true,
                    onChange: function(selectedDates){
                        const date = selectedDates[0];
                        if(date){
                            $('#editDay').val(date.toLocaleDateString('en-US', { weekday: 'long' }));
                        } else {
                            $('#editDay').val('');
                        }
                        computeHoursWorked();
                    },
                    onClose: function(selectedDates, dateStr, instance){
                        if(!selectedDates.length && dateStr){
                            const parsed = new Date(dateStr);
                            if(!isNaN(parsed)){
                                instance.setDate(parsed, true, 'Y-m-d h:i K');
                            }
                        }
                    }
                });

                timeOutPicker = flatpickr('#editTimeOut', {
                    enableTime: true,
                    dateFormat: 'Y-m-d h:i K',
                    altInput: true,
                    altInputClass: 'form-control',
                    altFormat: 'F j, Y h:i K',
                    allowInput: true,
                    minuteIncrement: 15,
                    enableSeconds: false,
                    time_24hr: false,
                    defaultDate: null,
                    clickOpens: true,
                    onChange: computeHoursWorked,
                    onClose: function(selectedDates, dateStr, instance){
                        if(!selectedDates.length && dateStr){
                            const parsed = new Date(dateStr);
                            if(!isNaN(parsed)){
                                instance.setDate(parsed, true, 'Y-m-d h:i K');
                            }
                        }
                    }
                });
            }

            function computeHoursWorked(){
                const inDate = timeInPicker && timeInPicker.selectedDates[0] ? timeInPicker.selectedDates[0] : null;
                const outDate = timeOutPicker && timeOutPicker.selectedDates[0] ? timeOutPicker.selectedDates[0] : null;
                if(!inDate || !outDate){
                    $('#editHoursWorked').val('');
                    return;
                }

                let diffMs = outDate - inDate;
                if(diffMs < 0){
                    diffMs += 24 * 60 * 60 * 1000;
                }

                const hours = diffMs / (1000 * 60 * 60);
                $('#editHoursWorked').val(hours.toFixed(2));
            }

            function loadTable(startDate, endDate, usertype, guid){
                let selectedDept = $('#departmentFilter').length 
                    ? $('#departmentFilter').val() 
                    : "{{ $dept }}";

                if(!selectedDept){
                    selectedDept = 'all';
                }
                let url = `${baseUrl}/${selectedDept}/${startDate}/${endDate}/${usertype}/${guid}`;

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
                        },
                        {
                            data: null,
                            render: function(data){
                                if(!data) return '';
                                const parts = [];
                                function pushIf(label, value){ if(value && value != 0) parts.push(label + ': ' + parseFloat(value).toFixed(2) + ' hrs'); }

                                // Day / night breakdowns
                                pushIf('Regular Day', data.regularDayHours);
                                pushIf('Rest Day', data.restDayHours);
                                pushIf('Regular Holiday', data.regularHolidayHours);
                                pushIf('Regular Holiday (Rest)', data.regularHolidayRestDayHours);
                                pushIf('Special Non-working', data.specialNonWorkingHours);
                                pushIf('Special Non-working (Rest)', data.specialNonWorkingRestDayHours);
                                pushIf('Night Shift', data.nightShiftHours);
                                pushIf('Night Shift (Rest)', data.nightShiftRestDayHours);
                                pushIf('Night Shift (Reg Holiday)', data.nightShiftRegularHolidayHours);
                                pushIf('Night Shift (Special)', data.nightShiftSpecialNonWorkingHours);

                                // OT breakdowns
                                pushIf('Regular OT', data.regularOt);
                                pushIf('Regular Night OT', data.regularNightOt);
                                pushIf('Rest Day OT', data.restOt);
                                pushIf('Rest Day Night OT', data.restNightOt);
                                pushIf('Holiday OT', data.holidayOt);
                                pushIf('Holiday(Rest) OT', data.holidayRestOt);
                                pushIf('Holiday Night OT', data.holidayNightOt);
                                pushIf('Holiday(Rest) Night OT', data.holidayRestNightOt);
                                pushIf('Special OT', data.specialOt);
                                pushIf('Special Night OT', data.specialNightOt);
                                pushIf('Special(Rest) OT', data.specialRestOt);
                                pushIf('Special(Rest) Night OT', data.specialRestNightOt);

                                return parts.join(' | ');
                            }
                        },
                        { data:'remarks' },
                        {
                            data:null,
                            orderable: false,
                            className: 'text-center',
                            render:function(data, type, row){
                                return `<button type="button" class="btn btn-link btn-sm edit-row">Edit</button>`;
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

                loadTable(startDate,endDate, usertype, guid);

            });
            $('#departmentFilter').change(function(){
                console.log("Changed to:", $(this).val());
            });

            function formatPayrollText(data){
                if(!data) return '';
                const parts = [];
                function pushIf(label, value){
                    if(value !== undefined && value !== null && value !== 0){
                        const num = parseFloat(value);
                        if(!isNaN(num) && num !== 0){
                            parts.push(label + ': ' + num.toFixed(2) + ' hrs');
                        }
                    }
                }

                pushIf('Regular Day', data.regularDayHours);
                pushIf('Rest Day', data.restDayHours);
                pushIf('Regular Holiday', data.regularHolidayHours);
                pushIf('Regular Holiday (Rest)', data.regularHolidayRestDayHours);
                pushIf('Special Non-working', data.specialNonWorkingHours);
                pushIf('Special Non-working (Rest)', data.specialNonWorkingRestDayHours);
                pushIf('Night Shift', data.nightShiftHours);
                pushIf('Night Shift (Rest)', data.nightShiftRestDayHours);
                pushIf('Night Shift (Reg Holiday)', data.nightShiftRegularHolidayHours);
                pushIf('Night Shift (Special)', data.nightShiftSpecialNonWorkingHours);
                pushIf('Regular OT', data.regularOt);
                pushIf('Regular Night OT', data.regularNightOt);
                pushIf('Rest Day OT', data.restOt);
                pushIf('Rest Day Night OT', data.restNightOt);
                pushIf('Holiday OT', data.holidayOt);
                pushIf('Holiday(Rest) OT', data.holidayRestOt);
                pushIf('Holiday Night OT', data.holidayNightOt);
                pushIf('Holiday(Rest) Night OT', data.holidayRestNightOt);
                pushIf('Special OT', data.specialOt);
                pushIf('Special Night OT', data.specialNightOt);
                pushIf('Special(Rest) OT', data.specialRestOt);
                pushIf('Special(Rest) Night OT', data.specialRestNightOt);

                return parts.join(' | ');
            }

            function openEditModal(data){
                currentRowData = Object.assign({}, data);

                const timeInSource = data.timeIn || data.dateTimeIn || '';
                if(timeInSource){
                    const parsedIn = new Date(timeInSource);
                    if(!isNaN(parsedIn)){
                        timeInPicker.setDate(parsedIn, true, 'Y-m-d h:i K');
                        $('#editDay').val(parsedIn.toLocaleDateString('en-US', { weekday: 'long' }));
                    } else {
                        timeInPicker.clear();
                        $('#editDay').val('');
                    }
                } else {
                    timeInPicker.clear();
                    $('#editDay').val('');
                }

                if(data.timeOut){
                    const parsedOut = new Date(data.timeOut);
                    if(!isNaN(parsedOut)){
                        timeOutPicker.setDate(parsedOut, true, 'Y-m-d h:i K');
                    } else {
                        timeOutPicker.clear();
                    }
                } else {
                    timeOutPicker.clear();
                }

                computeHoursWorked();
                $('#editPayrollComputations').val(formatPayrollText(data));
                $('#editRemarks').val(data.remarks || '');
                editModal.show();
            }

            $('#attendanceTable tbody').on('click', '.edit-row', function(){
                const rowData = table.row($(this).closest('tr')).data();
                if(rowData){
                    openEditModal(rowData);
                }
            });

            const editModalEl = document.getElementById('editRowModal');
            const editModal = new bootstrap.Modal(editModalEl);

            initEditPickers();

            $('#saveRowBtn').click(function(){
                if(!currentRowData){
                    console.warn('No row selected to save');
                    return;
                }

                const updatedTimeIn = timeInPicker.selectedDates[0] ? timeInPicker.selectedDates[0].toISOString() : null;
                const updatedTimeOut = timeOutPicker.selectedDates[0] ? timeOutPicker.selectedDates[0].toISOString() : null;
                const updatedDay = $('#editDay').val();
                const updatedHoursWorked = $('#editHoursWorked').val();
                const updatedRemarks = $('#editRemarks').val();

                const updatedRow = Object.assign({}, currentRowData, {
                    timeIn: updatedTimeIn,
                    timeOut: updatedTimeOut,
                    day: updatedDay,
                    hoursWorked: updatedHoursWorked,
                    remarks: updatedRemarks,
                });

                console.log('Saved row data:', updatedRow);

                $.ajax({
                    url: '{{ url('/payroll/edit-attendance') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        id: currentRowData.id || currentRowData.key || null,
                        timeIn: updatedTimeIn,
                        timeOut: updatedTimeOut,
                        employeeID: currentRowData.employeeID || '',
                        employeeName: currentRowData.employeeName || '',
                        department: currentRowData.department || '',
                        editedBy: '{{ Session::get('firebase_user.name') ?? 'System' }}'
                    },
                    success: function(response){
                        console.log('Attendance saved:', response);
                        alert(response.message || 'Attendance saved successfully');
                        editModal.hide();
                    },
                    error: function(xhr){
                        console.error('Attendance save failed:', xhr.responseText);
                        alert('Failed to save attendance.');
                    }
                });
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