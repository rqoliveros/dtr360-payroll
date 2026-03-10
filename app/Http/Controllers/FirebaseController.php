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
                if(str_contains($data['department'], $dept)){
                    $firebaseUsers[$data['employeeID']] = new FirebaseUsers($id, $data);
                }
            }
        }
        return $firebaseUsers;
    }

    public function getAttendanceByDateRange($dept,$startDate, $endDate)
    {   date_default_timezone_set('Asia/Manila');

        $periods = CarbonPeriod::create(
            Carbon::createFromFormat('Y-m-d', $startDate),
            Carbon::createFromFormat('Y-m-d', $endDate)
        );

        $dates = [];
        $holidays = $this->getHolidays($startDate, $endDate);
        $employees = $this->getEmployees($dept);
        $docs = $this->getFiledDocumentsByDateRange($dept, $startDate, $endDate, 'dateFrom');
        $ots = $this->getFiledDocumentsByDateRange($dept, $startDate, $endDate, 'otDate');
        $departments = config('departments'); //fetch departments
        $startDate = strtotime($startDate) * 1000;
        $endDate   = strtotime($endDate  . ' +1 day') * 1000;
        $reference = $this->database->getReference('Logs');
        $query = $reference->orderByChild('dateTimeIn')    // 'date' is the key in your data
                        ->startAt($startDate)    // start of range
                        ->endAt($endDate);       // end of range

        $logs = $query->getValue();
        $firebaseAttendance = [];

        if ($logs) {
            foreach ($logs as $id => $data) {
                if(str_contains($data['department'], $dept)){
                    $firebaseAttendance[] = new FirebaseAttendance($id, $data);
                }
                
            }
           
        }
        $uniqueEmployees = collect($firebaseAttendance) //get unique employees
        ->unique('employeeName')
        ->values();

        $uniqueEmployeeIds = $uniqueEmployees->pluck('employeeID');

        $missingEmployees = collect($employees)
            ->filter(function ($emp) use ($uniqueEmployeeIds) {
                return !$uniqueEmployeeIds->contains($emp->empId) && $emp->usertype != 'Former Employee';
            })
            ->values();
        foreach($missingEmployees as $missings){
            
            $missingRow = new \stdClass();
            $missingRow->id = null;
            $missingRow->employeeID = $missings->empId;
            $missingRow->employeeName = $missings->empName;
            $missingRow->department = $missings->dept;
            $missingRow->dateTimeIn = date('m/d/Y', $startDate / 1000);
            $missingRow->day = date('l', $startDate / 1000);
            $missingRow->timeIn = null;
            $missingRow->hoursWorked = 0.00;
            $missingRow->timeOut = null;
            $missingRow->remarks = 'Absent';

            $firebaseAttendance[] = $missingRow;
        }


        //SHIFT IDENTIFICATION
        $shiftMap = [];
        foreach ($uniqueEmployees as $employeeName) {
            //Leave
            
            if (!empty($employeeName->guid) && isset($docs[$employeeName->guid]) && $docs[$employeeName->guid]->docType == 'Leave') {
                $leave = $docs[$employeeName->guid];
                $from = $leave->dateFrom;
                $to = $leave->dateTo;
                $start = new DateTime(substr($from, 0, 10)); // "2026-02-17"
                $end   = new DateTime(substr($to, 0, 10));
                $end->modify('+1 day');
                $period = new DatePeriod($start, new DateInterval('P1D'), $end);
                foreach ($period as $date) {
                    $leaveRow = new \stdClass();
                    $leaveRow->id = null;
                    $leaveRow->employeeID = $employeeName->employeeID;
                    $leaveRow->employeeName = $employeeName->employeeName;
                    $leaveRow->department = $employeeName->department;
                    $leaveRow->dateTimeIn = $date->format('m/d/Y');
                    $leaveRow->day = $date->format('l');
                    $leaveRow->timeIn = null;
                    $leaveRow->timeOut = null;
                    $leaveRow->remarks = $docs[$employeeName->guid]->leaveType;
                    $leaveRow->leave = $docs[$employeeName->guid]->leaveType;

                    $firebaseAttendance[] = $leaveRow;
                    
                }
                
            }
            if (!empty($employeeName->guid) && isset($ots[$employeeName->guid]) && $ots[$employeeName->guid]->docType == 'Overtime') {

                $ot = $ots[$employeeName->guid];
                $timestamp = strtotime($ot->otDate);
                $date = date('m/d/Y', $timestamp);
                $existingRow = collect($firebaseAttendance)->first(function ($row) use ($employeeName, $date) {
                    return $row->employeeID == $employeeName->employeeID
                        && $row->dateTimeIn == $date;
                });
                
                if ($existingRow) {
                    // merge remarks
                    if (!empty($existingRow->remarks)) {
                        if (!str_contains($existingRow->remarks, $holiday->holidayType)) {
                            $existingRow->remarks .= ' | ' . $ot->otType . ': ' . $ot->hoursNo;
                            $existingRow->otType = $ot->otType;
                            $existingRow->otHours = $ot->hoursNo;
                            
                        }
                    } else {
                        $existingRow->remarks = $ot->otType . ': ' . $ot->hoursNo;
                        $existingRow->otType = $ot->otType;
                        $existingRow->otHours = $ot->hoursNo;
                    }

                }
                
            }
            foreach ($holidays as $holiday) {

                $timestamp = strtotime($holiday->holidayDate . ' 00:00:00');
                $date = date('m/d/Y', $timestamp);

                // find existing attendance row
                $existingRow = collect($firebaseAttendance)->first(function ($row) use ($employeeName, $date) {
                    return $row->employeeID == $employeeName->employeeID
                        && $row->dateTimeIn == $date;
                });

                if ($existingRow) {
                    // merge remarks
                    if (!empty($existingRow->remarks)) {
                        if (!str_contains($existingRow->remarks, $holiday->holidayType)) {
                            $existingRow->remarks .= ' | ' . $holiday->holidayType;
                            $existingRow->holiday = $holiday->holidayType;
                            
                        }
                    } else {
                        $existingRow->remarks = $holiday->holidayType;
                        $existingRow->holiday = $holiday->holidayType;
                    }

                } else {
                    // create new holiday row if none exists
                    $holidayRow = new \stdClass();
                    $holidayRow->id = null;
                    $holidayRow->employeeID = $employeeName->employeeID;
                    $holidayRow->employeeName = $employeeName->employeeName;
                    $holidayRow->department = $employeeName->department;
                    $holidayRow->dateTimeIn = $date;
                    $holidayRow->day = date('l', $timestamp);
                    $holidayRow->timeIn = null;
                    $holidayRow->timeOut = null;
                    $holidayRow->remarks = $holiday->holidayType;
                    $holidayRow->holiday = $holiday->holidayType;

                    $firebaseAttendance[] = $holidayRow;
                }
            }
        }

        


        // Compute Hours Worked and append date and compute lates
        foreach ($firebaseAttendance as $row) {

            if (!empty($row->timeIn) && !empty($row->timeOut) && $row->timeIn != '-' && $row->timeOut != '-') {

                $remarks = [];
                $dayOfWeek = strtolower(date('l', strtotime($row->dateTimeIn)));
                $currentShift = $employees[$row->employeeID]->$dayOfWeek;
                if(isset($employees[$row->employeeID]) && $currentShift == 1){

                    $shiftIn  = $employees[$row->employeeID]->shiftTimeIn;
                    $shiftOut = $employees[$row->employeeID]->shiftTimeOut;
                    $shiftInTime  = strtotime($row->dateTimeIn . ' ' . $shiftIn);
                    $shiftOutTime = strtotime($row->dateTimeIn . ' ' . $shiftOut);
                    
                    $timeIn  = strtotime($row->timeIn);
                    $timeOut = strtotime($row->timeOut);

                    if($timeIn > $shiftInTime){
                        $lateSeconds = $timeIn - $shiftInTime;
                        $row->remarks .= " Late " . gmdate("i", $lateSeconds) . " mins";
                        $row->late = $lateSeconds;
                    }

                    if($timeOut < $shiftOutTime){
                        $utSeconds = $shiftOutTime - $timeOut;
                        $row->remarks .= " | Undertime " . gmdate("i", $utSeconds) . " mins";
                        $row->undertime = $utSeconds;
                    }
                }

                // Merge with existing remarks
                if (!empty($remarks)) {
                    if (!empty($row->remarks) && $row->remarks != '-') {
                        $row->remarks .= ' | ' . implode(' | ', $remarks);
                    } else {
                        $row->remarks = implode(' | ', $remarks);
                    }
                }

            } 
        }

        foreach ($uniqueEmployees as $employeeName) {
            foreach ($periods as $period) {

                $timestamp = $period->timestamp;
                $date = $period->format('m/d/Y');
                $dayofweek = strtolower($period->format('l'));
                // find existing attendance row
                $existingRow = collect($firebaseAttendance)->first(function ($row) use ($employeeName, $date) {
                    return $row->employeeID == $employeeName->employeeID
                        && $row->dateTimeIn == $date;
                });
                $currentShift = $employees[$row->employeeID]->$dayofweek;
                if (!$existingRow && $currentShift == 1) {
                    // merge remarks
                    $missingRow = new \stdClass();
                    $missingRow->id = null;
                    $missingRow->employeeID = $employeeName->employeeID;
                    $missingRow->employeeName = $employeeName->employeeName;
                    $missingRow->department = $employeeName->department;
                    $missingRow->dateTimeIn = $date;
                    $missingRow->day = date('l', $timestamp);
                    $missingRow->timeIn = null;
                    $missingRow->timeOut = null;
                    $missingRow->hoursWorked = 0.00;
                    $missingRow->remarks = 'Absent';

                    $firebaseAttendance[] = $missingRow;

                } 
            }
        }

        foreach ($missingEmployees as $missings) {
            foreach ($periods as $period) {

                $timestamp = $period->timestamp;
                $date = $period->format('m/d/Y');
                $dayofweek = strtolower($period->format('l'));
                // find existing attendance row
                $existingRow = collect($firebaseAttendance)->first(function ($row) use ($missings, $date) {
                    return $row->employeeID == $missings->empId
                        && $row->dateTimeIn == $date;
                });
                $currentShift = $employees[$row->employeeID]->$dayofweek;
                if (!$existingRow && $currentShift == 1) {
                    // merge remarks
                    $missingRow = new \stdClass();
                    $missingRow->id = null;
                    $missingRow->employeeID = $missings->empId;
                    $missingRow->employeeName = $missings->empName;
                    $missingRow->department = $missings->dept;
                    $missingRow->dateTimeIn = $date;
                    $missingRow->day = date('l', $timestamp);
                    $missingRow->timeIn = null;
                    $missingRow->timeOut = null;
                    $missingRow->hoursWorked = 0.00;
                    $missingRow->remarks = 'Absent';

                    $firebaseAttendance[] = $missingRow;

                } 
            }
        }

        usort($firebaseAttendance, function($a, $b) {
            $nameComparison = strcmp($a->employeeName, $b->employeeName);
            if ($nameComparison === 0) {
                // Convert to DateTime
                $dateA = new DateTime($a->dateTimeIn);
                $dateB = new DateTime($b->dateTimeIn);
                return $dateA <=> $dateB; // ascending by actual date
            }
            return $nameComparison;
        });
        $formatted = [];

        foreach ($firebaseAttendance as $emp) {
            $formatted[] = [
                'employeeID' => $emp->employeeID,
                'employeeName' => $emp->employeeName,
                'department' => $emp->department,
                'dateTimeIn' => $emp->dateTimeIn,
                'day' => $emp->day,
                'hoursWorked' => $emp->hoursWorked ?? 0.00,
                'timeIn' => $emp->timeIn,
                'timeOut' => $emp->timeOut,
                'remarks' => $emp->remarks
            ];
        }
        
        return response()->json($formatted);
        // return view('payroll.payrolldashboard', compact('formatted'));
        // return view('attendance.attendancetable', compact('firebaseAttendance'));
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
        $query = $reference->orderByChild($filter)    // 'date' is the key in your data
                        ->startAt($startDate)    // start of range
                        ->endAt($endDate);       // end of range

        $docs = $query->getValue();

        $firebaseDocs = [];

        if ($docs) {
            foreach ($docs as $id => $data) {
                if(str_contains($data['dept'], $dept)){
                    $firebaseDocs[$data['guid']] = new FirebaseFilingDocuments($id, $data);
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
