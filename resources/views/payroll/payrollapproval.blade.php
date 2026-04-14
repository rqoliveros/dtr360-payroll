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
    <link rel="stylesheet" href="https://cdn.datatables.net/select/1.7.0/css/select.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/select/1.7.0/js/dataTables.select.min.js"></script>
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

            <button id="filterBtn"
                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                Load Approvals
            </button>

        </div>
                <!-- Additional Filters (Hidden Initially) -->
        <div id="extraFilters" class="flex gap-3 items-end mt-4 hidden">

    

            <div>
                <label class="block text-sm font-medium">Document Type</label>
                <select id="docType" class="border rounded px-2 py-1">
                    <option value="">All</option>
                    <option value="Leave">Leave</option>
                    <option value="OB">Correction</option>
                    <option value="WFH">Overtime</option>
                </select>
            </div>

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
            <!-- Right side buttons -->
            <div class="ml-auto flex gap-2">
                <button id="approveSelected" class="btn btn-success btn-sm">
                    Approve
                </button>
                <button id="rejectSelected" class="btn btn-danger btn-sm">
                    Reject
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="container mx-auto mt-6">
            <table id="documentTable" class="table-auto border-collapse border border-gray-300 w-full text-sm">
                <thead class="bg-gray-200">
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th class="border px-2 py-1">Employee Name</th>
                        <th class="border px-2 py-1">Document Type</th>
                        <th class="border px-2 py-1">Document No</th>
                        <th class="border px-2 py-1">Date Filed</th>
                        <th class="border px-2 py-1">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
       <div class="modal fade" id="viewModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Document No.: <span id="modalUniqueId"></span> </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        <p><strong>Employee Name:</strong> <span id="modalEmployee"></span></p>
                        <p><strong>Document Type:</strong> <span id="modalDocType"></span></p>
                        <p><strong>Date:</strong> <span id="modalDate"></span></p>
                        <hr>

                        <!-- Dynamic content -->
                        <div id="modalExtraDetails"></div>

                        </div>
                        <div class="modal-footer flex flex-col items-start gap-2">

                            <div id="rejectReasonContainer" class="w-full hidden">
                                <label class="text-sm font-medium">Reject Reason</label>
                                <textarea id="rejectReason" class="form-control" rows="3"></textarea>
                            </div>

                            <div class="flex gap-2 w-full justify-end">
                                <button id="rejectDoc" class="btn btn-danger">
                                    Reject
                                </button>

                                <button id="approveDoc" class="btn btn-success">
                                    Approve
                                </button>
                            </div>

                        </div>

                </div>
            </div>
        </div>
        <div class="modal fade" id="rejectModal" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                
                <div class="modal-header">
                    <h5 class="modal-title">Reject Documents</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <textarea id="rejectReason" class="form-control" placeholder="Enter reason..." rows="3"></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmReject" class="btn btn-danger">Reject</button>
                </div>

                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script>
        const userDept = "{{ $dept }}";
        const email = "{{ $authUser }}";
        const usertype = "{{ $usertype }}";
    </script>
    <script>
        
        $(document).ready(function () {
            let currentDocId = null;
            let empKey = null;
            const dept = "{{ str_replace('/', ',', $dept) }}";
            const baseUrl = "{{ url('/payroll/approval') }}";
            
            let table = null;

            function loadTable(startDate, endDate){

                let url = `${baseUrl}/${dept}/${startDate}/${endDate}`;

                if(table){
                    table.destroy();
                }

                table = $('#documentTable').DataTable({
                    ajax:{
                        url: url,
                        dataSrc:''
                    },
                    dom: 'Bfrtip',
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
                        {
                            data: null,
                            orderable: false,
                            render: function(){
                                return `<input type="checkbox" class="rowCheckbox">`;
                            }
                        },
                        { data:'employeeName' },
                        { data:'docType' },
                        { data:'uniqueId' },
                        { data:'date' },
                        {
                            data:null,
                            render:function(){
                                return `<button class="viewBtn text-blue-500">Edit</button>`;
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
            $('#selectAll').on('click', function(){

                let rows = table.rows({ search:'applied' }).nodes();

                $('input.rowCheckbox', rows).prop('checked', this.checked);
                getSelectedRows();
            });

            function getSelectedRows(){
                // let selected = [];

                $('#documentTable tbody input.rowCheckbox:checked').each(function(){

                    let row = table.row($(this).closest('tr')).data();
                    selected.push(row);

                });

                console.log(selected);
            }
            $('#approveSelected').click(function(){
                let selected = [];

                $('#documentTable tbody input.rowCheckbox:checked').each(function(){

                    let row = table.row($(this).closest('tr')).data();
                    selected.push(row);

                });
                alert(selected[0]);
                console.log(selected[0]);
                if(selected.length === 0){
                    alert("No documents selected");
                    return;
                }
                else{
                    alert(selected.length);
                }
                const baseUrl = "{{ url('/payroll') }}";
                $.ajax({
                    url: `${baseUrl}/approve-documents`,
                    method: 'POST',
                    data:{
                        approver: email,
                        ids: Array.from(selected),
                        _token: '{{ csrf_token() }}'
                    },
                    success:function(res){

                        alert("Documents Approved");

                        selected.clear();

                        table.ajax.reload(null,false);

                    }
                });

            });
            $('#rejectSelected').click(function(){

                selectedReject = [];

                $('#documentTable tbody input.rowCheckbox:checked').each(function(){
                    let row = table.row($(this).closest('tr')).data();
                    selectedReject.push(row);
                });

                if(selectedReject.length === 0){
                    alert("No documents selected");
                    return;
                }

                // show modal instead of ajax
                $('#rejectModal').modal('show');
            });
            $('#confirmReject').click(function(){

                let reason = $('#rejectModal #rejectReason').val().trim();

                if(reason === ''){
                    alert("Please enter a reason");
                    return;
                }

                const baseUrl = "{{ url('/payroll') }}";

                $.ajax({
                    url: `${baseUrl}/reject-multiple-docs`,
                    method: 'POST',
                    data:{
                        approver: email,
                        ids: selectedReject,
                        reason: reason,
                        _token: '{{ csrf_token() }}'
                    },
                    success:function(res){

                        alert("Documents Rejected");

                        selectedReject = [];
                        $('#rejectReason').val('');
                        $('#rejectModal').modal('hide');

                        table.ajax.reload(null,false);
                    }
                });

            });
            $('#rejectModal').on('hidden.bs.modal', function () {
                $('#rejectReason').val('');
            });
            $('#documentTable').on('click', '.viewBtn', function(){

                let data = table.row($(this).closest('tr')).data();
                currentDocId = data.id;
                empKey = data.empKey;
                $('#modalEmployee').text(data.employeeName);
                $('#modalDocType').text(data.docType);
                $('#modalUniqueId').text(data.uniqueId);
                $('#modalDate').text(data.date);
                let extraHtml = '';

                if(data.docType === 'Leave'){

                    extraHtml = `
                        <p><strong>Date From:</strong> ${data.dateFrom}</p>
                        <p><strong>Date To:</strong> ${data.dateTo}</p>
                        <p><strong>Reason:</strong> ${data.reason}</p>
                        <p><strong>No. of days:</strong> ${data.noOfDay}</p>
                        <p><strong>Is Half Day:</strong> ${data.isHalfday}</p>
                        <p><strong>Deduct to leave credits:</strong> ${data.deductLeave}</p>
                    `;

                }else if(data.docType === 'Overtime'){

                    extraHtml = `
                        <p><strong>OT Date:</strong> ${data.otDate}</p>
                        <p><strong>Start Time:</strong> ${data.otfrom}</p>
                        <p><strong>End Time:</strong> ${data.otTo}</p>
                        <p><strong>Reason:</strong> ${data.reason}</p>
                    `;

                }else if(data.docType === 'Correction'){
                    if(data.correctBothTime != ""){
                        extraHtml = `
                            <p><strong>Corrected Date:</strong> ${data.correctDate}</p>
                            <p><strong>Corrected Time:</strong> ${data.correctTime}</p>
                            <p><strong>Corrected Time Out:</strong> ${data.correctTime}</p>
                            <p><strong>Reason:</strong> ${data.reason}</p>
                        `;
                    }
                    else{
                        extraHtml = `
                            <p><strong>Corrected Date:</strong> ${data.correctDate}</p>
                            <p><strong>Corrected Time:</strong> ${data.correctTime}</p>
                            <p><strong>Is Out:</strong> ${data.isOut}</p>
                            <p><strong>Reason:</strong> ${data.reason}</p>
                        `;
                    }
                    

                }

                $('#modalExtraDetails').html(extraHtml);
                let modal = new bootstrap.Modal(document.getElementById('viewModal'));
                modal.show();

            });
            $('#approveDoc').click(function(){
                if(!currentDocId) return;

                if(!confirm("Are you sure you want to approve this document?")){
                    return;
                }
                const baseUrl = "{{ url('/payroll') }}";
                $.ajax({
                    url: `${baseUrl}/approve-document`,
                    method: 'POST',
                    data:{
                        id: currentDocId,
                        approver: email,
                        empKey: empKey,
                        _token: '{{ csrf_token() }}'
                    },
                    success:function(){

                        alert("Document Approved");

                        table.ajax.reload(null,false);

                        bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();

                    }
                });

            });
            let rejectMode = false;

            $('#rejectDoc').click(function(){

                if(!rejectMode){

                    $('#rejectReasonContainer').removeClass('hidden');
                    rejectMode = true;

                    return;

                }
                let reason = $('#rejectReason').val();

                if(!reason){
                    alert("Please enter a reject reason");
                    return;
                }
                const baseUrl = "{{ url('/payroll') }}";
                $.ajax({
                    url: `${baseUrl}/reject-document`,
                    method:'POST',
                    data:{
                        id: currentDocId,
                        approver: email,
                        reason: reason,
                        _token:'{{ csrf_token() }}'
                    },
                    success:function(){

                        alert("Document Rejected");

                        $('#rejectReason').val('');
                        $('#rejectReasonContainer').addClass('hidden');
                        rejectMode = false;

                        table.ajax.reload(null,false);

                        bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();

                    }
                });

            });
            

        });
        

        
    </script>
</body>
</html>