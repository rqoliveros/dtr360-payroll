<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FirebaseUsers;
use App\Models\FirebaseAttendance;
use App\Models\FirebaseFilingDocuments;
use App\Models\FirebaseHolidays;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DateTime;
use DatePeriod;
use DateInterval;
use Kreait\Firebase\Auth;
use Mockery\Undefined;

class FirebaseController extends Controller
{
    //
    protected $database;

    public function __construct()
    {
        $this->database = app('firebase.database');
    }

    // Get all users
    public function getEmployees($dept)
    {
        $users = $this->database
            ->getReference('Employee')
            ->getValue();

        $firebaseUsers = [];

        if ($users) {
            foreach ($users as $id => $data) {
                if ($dept === 'all' || $dept === '') {

                    $firebaseUsers[$data['employeeID']] = new FirebaseUsers($id, $data);

                } else {

                    // Check if multiple departments (e.g. "IT,FAD")
                    $deptList = str_contains($dept, ',') ? explode(',', $dept) : [$dept];

                    foreach ($deptList as $d) {
                        if (str_contains($data['department'], trim($d))) {
                            $firebaseUsers[$data['employeeID']] = new FirebaseUsers($id, $data);
                            break; // stop once matched
                        }
                    }

                }
                
            }
        }
        return $firebaseUsers;
    }

    public function getAttendanceByDateRange($dept,$startDate, $endDate, $usertype, $guid)
    {
        $service = new \App\Services\FirebaseAttendanceService($this->database);
        $formatted = $service->getAttendanceByDateRange($dept, $startDate, $endDate, $usertype, $guid);
        return response()->json($formatted);
    }

    public function getDocumentsByDepartment($dept,$startDate, $endDate){
        $reference = $this->database->getReference('FilingDocuments');
        $query = $reference->orderByChild('date')    // 'date' is the key in your data
                        ->startAt($startDate)    // start of range
                        ->endAt($endDate);       // end of range

        $docs = $query->getValue();

        $firebaseDocs = [];

        if ($docs) {

            // Convert incoming dept to array
            $deptList = str_contains($dept, ',') ? explode(',', $dept) : [$dept];

            foreach ($docs as $id => $data) {

                // Check if dept matches ANY in the list
                $match = false;

                foreach ($deptList as $d) {
                    if (trim($data['dept']) === trim($d)) {
                        $match = true;
                        break;
                    }
                }

                if (
                    $match &&
                    $data['isApproved'] == false &&
                    $data['docType'] != '' &&
                    !isset($data['approveRejectBy'])
                ) {
                    $firebaseDocs[] = new FirebaseFilingDocuments($id, $data);
                }
            }
        }

        foreach ($firebaseDocs as $emp) {
            $formatted[] = [
                'id' => $emp->id,
                'employeeName' => $emp->employeeName,
                'guid' => $emp->guid ?? null,
                'dept' => $emp->dept ?? null,
                'date' => $emp->date ?? null,
                'dateFrom' => $emp->dateFrom ?? null,
                'dateTo' => $emp->dateTo ?? null,
                'docType' => $emp->docType ?? null,
                'correctDate' => $emp->correctDate ?? null,
                'correctTime' => $emp->correctTime ?? null,
                'correctBothTime' => $emp->correctBothTime ?? null,
                'deductLeave' => $emp->remarks ?? null,
                'empKey' => $emp->empKey ?? null,
                'hoursNo' => $emp->hoursNo ?? 0.00,
                'isAm' => $emp->isAm ?? null,
                'isApproved' => $emp->isApproved ?? null,
                'isHalfday' => $emp->isHalfday ?? null,
                'isNextdayTimeOut' => $emp->isNextdayTimeOut ?? null,
                'isOut' => $emp->isOut ?? null,
                'isOvernightOt' => $emp->isOvernightOt ?? null,
                'leaveType' => $emp->leaveType ?? null,
                'noOfDay' => $emp->noOfDay ?? null,
                'otDate' => $emp->otDate ?? null,
                'otTo' => $emp->otTo ? Carbon::parse($emp->otTo)->setTimezone('Asia/Singapore')->format('h:i A') : null,
                'otType' => $emp->otType ?? null,
                'otfrom' => $emp->otfrom ? Carbon::parse($emp->otfrom)->setTimezone('Asia/Singapore')->format('h:i A') : null,
                'reason' => $emp->reason ?? null,
                'uniqueId' => $emp->uniqueId ?? null
            ];
        }
        
        return response()->json($formatted ?? []);
        // return $firebaseDocs;
    }

    public function getHolidays($startDate, $endDate)
    {
        $startTimestamp = strtotime($startDate);
        $endTimestamp   = strtotime($endDate);

        $reference = $this->database->getReference('Holidays');
        $holidays = $reference->getValue(); // fetch all, then filter in PHP

        $firebaseHolidays = [];

        if ($holidays) {
            foreach ($holidays as $id => $data) {
                // Convert holidayDate to timestamp
                if (isset($data['holidayDate'])) {
                    $holidayTimestamp = strtotime($data['holidayDate']);

                    if ($holidayTimestamp >= $startTimestamp && $holidayTimestamp <= $endTimestamp) {
                        $firebaseHolidays[] = new FirebaseHolidays($id, $data);
                    }
                }
            }
        }
        return $firebaseHolidays;
    }

    public function getFiledDocumentsByDateRange($dept, $startDate, $endDate, $filter)
    {

        $reference = $this->database->getReference('FilingDocuments');
        $startAt = $startDate . ' 00:00:00';
        $endAt = $endDate . ' 23:59:59';
        $query = $reference->orderByChild($filter)    // 'date' is the key in your data
                        ->startAt($startAt)    // start of range
                        ->endAt($endAt);       // end of range

        $docs = $query->getValue();

        $firebaseDocs = [];

        if ($docs) {
            foreach ($docs as $id => $data) {
                if($dept == 'all'){
                    $firebaseDocs[$data['guid']] = new FirebaseFilingDocuments($id, $data);
                }
                else{
                    $deptList = str_contains($dept, ',') ? explode(',', $dept) : [$dept];
                    foreach ($deptList as $d) {
                        if (str_contains($data['dept'], trim($d))) {
                            $firebaseDocs[] = new FirebaseFilingDocuments($id, $data);
                            break; // stop once matched
                        }
                    }
                }   
                
            }
        }
        return $firebaseDocs;
        
    }

    // Add a new user
    public function store(Request $request)
    {
        $data = [
            'name' => $request->name,
            'age' => $request->age,
        ];

        $ref = $this->database->getReference('users')->push($data);

        return response()->json([
            'message' => 'User added',
            'id' => $ref->getKey(),
            'data' => $data
        ]);
    }

    // Update a user
    public function update(Request $request, $id)
    {
        $data = [
            'name' => $request->name,
            'age' => $request->age,
        ];

        $this->database->getReference("users/{$id}")->update($data);

        return response()->json([
            'message' => 'User updated',
            'id' => $id,
            'data' => $data
        ]);
    }

    // Delete a user
    public function destroy($id)
    {
        $this->database->getReference("users/{$id}")->remove();

        return response()->json([
            'message' => 'User deleted',
            'id' => $id
        ]);
    }
}
