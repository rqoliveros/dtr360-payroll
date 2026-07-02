<?php

namespace App\Http\Controllers;

use App\Models\FirebaseAttendance;
use App\Models\FirebaseFilingDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Kreait\Firebase\Database;
use App\Models\FirebaseUsers;
use Carbon\Carbon;

class PayrollController extends Controller
{
    protected $database;

    public function __construct()
    {
        $this->database = app('firebase.database');
    }
    //
    public function dashboard(){
        return view('payroll.payrolldashboard');
    }

    public function approval(){
        return view('payroll.payrollapproval');
    }

    public function getAllEmployees()
    {
        $users = $this->database
            ->getReference('Employee')
            ->getValue();

        $firebaseUsers = [];

        if ($users) {
            foreach ($users as $id => $data) {
                if($data['usertype'] != 'Former Employee'){
                    $firebaseUsers[$data['employeeID']] = new FirebaseUsers($id, $data);
                }
            }
        }
        return $firebaseUsers;
    }
    
    //Bulk approval
    public function approveDocuments(Request $request){
        // dd($request);
        $ids = $request->ids;
        $approver = $request->approver;
        $timestamp = now()->format('Y-m-d H:i:s.u');
        $employees = $this->database->getReference('Employee')->getValue();
        foreach($ids as $id){
            $docuId = $id['id'];
            $docType = $id['docType'];
            $docs = $this->database->getReference('FilingDocuments/'. $docuId);
            $documentData = $docs->getValue();
            if($docType == 'Overtime') {
                $this->approveOvertime($approver, $timestamp, $docs);
            }
            else if($docType == 'Correction') {
                $this->approveCorrection($approver, $timestamp, $documentData, $docs, $employees);
            }
            else if($docType == 'Leave') {
                $this->approveLeave($approver, $timestamp, $docs, $id['empKey'], $documentData);
            }
        }
        return response()->json($request);
    }

    //Reject docoument
    public function rejectDocument(Request $request){
        $approver = $request->approver;
        $timestamp = now()->format('Y-m-d H:i:s.u');
        $docs = $this->database->getReference('FilingDocuments/'. $request->id);
        if($docs) {
            $docs->update([
                'isRejected'=> true,
                'approveRejectBy'=> $approver,
                'approveRejectDate'=> $timestamp,
                'approveRejectReason' => $request->reason
            ]);
            return response()->json([
                'success' => true
            ]);
        }
        
    }

    public function rejectMultipleDocuments(Request $request){
        $ids = $request->ids;
        $approver = $request->approver;
        $timestamp = now()->format('Y-m-d H:i:s.u');
        $updates = [];
        foreach($ids as $id){
            $docId = $id['id'];
            $updates["FilingDocuments/$docId"] = [
                'isRejected'=> true,
                'approveRejectBy'=> $approver,
                'approveRejectDate'=> $timestamp,
                'approveRejectReason' => $request->reason
            ];
        }
        $this->database->getReference()->update($updates);

        return response()->json([
            'success' => true
        ]);
    }

    //Single document approval
    public function approveDocument(Request $request){
        $approver = Session::get('firebase_user.name');
        $timestamp = now()->format('Y-m-d H:i:s.u');
        $docs = $this->database->getReference('FilingDocuments/'. $request->id);
        $employees = $this->database->getReference('Employee')->getValue();
        $documentData = $docs->getValue();
        if($documentData) {
            if($documentData['docType'] == 'Overtime') {
                $this->approveOvertime($request->approver,  $timestamp, $docs);
            }
            else if( $documentData['docType'] == 'Correction') {
                $this->approveCorrection($request->approver, $timestamp, $documentData, $docs, $employees);
            }
            else if( $documentData['docType'] == 'Leave') {
                $this->approveLeave($request->approver, $timestamp, $docs, $request->empKey, $documentData);
            }
        }
    }

    public function approveLeave($approver, $timestamp, $docs, $empKey, $documentData){
        //Deduct the leave if deductLeave is true
        if($documentData['deductLeave'] == true){
            $employee = $this->database->getReference('Employee/'. $empKey);
            $empData =  $employee->getValue();
            if($employee){
                
                $leaveCredit = (float) ($empData['remainingLeaves'] ?? 0);
                $deduction = (float) ($documentData['noOfDay'] ?? 0);
                $remainingLeave = max(0, $leaveCredit - $deduction);
                $employee->update([
                    'remainingLeaves' => $remainingLeave
                ]);
            }
        }

        //Then update FilingDocuments document status
        $this->approveRejectDocuments($docs, true, $approver, $timestamp);
        
    }

    public function approveRejectDocuments($docs, $isApproved, $approver, $timestamp){
        $docs->update([
                'isApproved'=> true,
                'approveRejectBy'=> $approver,
                'approveRejectDate'=> $timestamp
            ]);
        return response()->json([
            'success' => true
        ]);
    }

    public function approveCorrection($approver, $timestamp, $documentData, $docs, $employees){
        date_default_timezone_set('Asia/Manila');
        $guid = $documentData['guid'];
        $reference = $this->database->getReference('Logs');
        $query = $reference
                    ->orderByChild('guid')
                    ->equalTo($guid)
                    ->getValue();  // end of range

        $correctDate = \Carbon\Carbon::parse($documentData['correctDate'])
                ->format('Y-m-d');     
        $isTrue = false;
        if ($query) {
            foreach ($query as $id => $data) {
                $attendanceDate = date('Y-m-d', $data['dateTimeIn'] / 1000);
                if ($attendanceDate === $correctDate) {
                    $isTrue = true;
                    $combined = $correctDate . ' ' . $documentData['correctTime'];
                    
                    $newTimestamp = \Carbon\Carbon::parse($combined)->valueOf();
                    $date = $this->database->getReference('Logs/'.$id);
                    if($documentData['isOut'] && $documentData['correctBothTime'] == null){
                        $date->update([
                            'timeOut' => $newTimestamp
                        ]);
                    }
                    else if(!$documentData['isOut']){
                        $date->update([
                            'timeIn' => $newTimestamp
                        ]);
                    }
                    else if($documentData['isOut'] && $documentData['correctBothTime'] != null){
                        if($documentData['isNextdayTimeOut']){
                            $tempDate = \Carbon\Carbon::parse($documentData['correctDate']);
                            $tempDate->addDay();
                            $correctDate = $tempDate->format('Y-m-d');
                            
                            $combinedBoth = $correctDate . ' ' . $documentData['correctBothTime'];
                        }
                        else{
                            $combinedBoth = $correctDate . ' ' . $documentData['correctBothTime'];
                        }
                        
                        $newTimestampOut = \Carbon\Carbon::parse($combinedBoth)->valueOf();
                        $date->update([
                            'timeIn' => $newTimestamp,
                            'timeOut' => $newTimestampOut
                        ]);
                    }
                    
                    $this->approveRejectDocuments($docs, true, $approver, $timestamp);
                    break;
                }
            }
            
        }

        if(!$isTrue){
            $empKey = $documentData['empKey'];
            $combined = $correctDate . ' ' . $documentData['correctTime'];
            $empData = $employees[$empKey];
            $timeInTimestamp = \Carbon\Carbon::parse($combined)->valueOf();

            $timeOutTimestamp = null;

            if (!empty($documentData['correctBothTime'])) {

                $date = \Carbon\Carbon::parse($documentData['correctDate']);

                if (!empty($documentData['isNextdayTimeOut'])) {
                    $date->addDay();
                }

                $combinedOut = $date->format('Y-m-d') . ' ' . $documentData['correctBothTime'];

                $timeOutTimestamp = \Carbon\Carbon::parse($combinedOut)->valueOf();

            }
            else if ($documentData['isOut']) {

                $timeOutTimestamp = $timeInTimestamp;

            }

            $logData = [
                'guid' => $documentData['guid'],
                'dateTimeIn' => $timeInTimestamp,
                'employeeID'=> $empData['employeeID'],
                'employeeName' => $empData['employeeName'],
                'department' => $empData['department'],
                'isWfh' => false
            ];

            if (!$documentData['isOut'] || !empty($documentData['correctBothTime'])) {
                $logData['timeIn'] = $timeInTimestamp;
            }

            if ($documentData['isOut'] || !empty($documentData['correctBothTime'])) {
                $logData['timeOut'] = $timeOutTimestamp;
            }

            $this->database->getReference('Logs')->push($logData);
            $this->approveRejectDocuments($docs, true, $approver, $timestamp);
        }

    }
    public function approveOvertime($approver, $timestamp, $docs){
        $docs->update([
                'isApproved'=> true,
                'approveRejectBy'=> $approver,
                'approveRejectDate'=> $timestamp
            ]);
        return response()->json([
            'success' => true
        ]);
    }

    public function editAttendance(Request $request)
    {
        $attendanceId = $request->input('id');
        $newTimeIn = $request->input('timeIn');
        $newTimeOut = $request->input('timeOut');
        $employeeId = $request->input('employeeID');
        $employeeName = $request->input('employeeName');
        $userType = $request->input('userType');
        $guid = $request->input('guid');
        $department = $request->input('department');
        $editedBy = $request->input('editedBy') ?? Session::get('firebase_user.name') ?? 'System';

        $timeInTimestamp = !empty($newTimeIn) ? Carbon::parse($newTimeIn)->valueOf() : null;
        $timeOutTimestamp = !empty($newTimeOut) ? Carbon::parse($newTimeOut)->valueOf() : null;

        $attendanceData = [
            'timeIn' => $timeInTimestamp,
            'timeOut' => $timeOutTimestamp,
            'dateTimeIn' => $timeInTimestamp,
        ];

        if (!empty($employeeId)) {
            $attendanceData['employeeID'] = $employeeId;
        }

        if (!empty($guid)) {
            $attendanceData['guid'] = $guid;
        }

        if (!empty($employeeName)) {
            $attendanceData['employeeName'] = $employeeName;
        }

        if (!empty($userType)) {
            $attendanceData['userType'] = $userType;
        }

        if (!empty($department)) {
            $attendanceData['department'] = $department;
        }

        $attendanceData['isEdited'] = true;

        $action = 'updated';
        $savedAttendanceId = $attendanceId;

        if (!empty($attendanceId)) {
            $attendanceRef = $this->database->getReference('Logs/' . $attendanceId);
            $existingAttendance = $attendanceRef->getValue();

            if ($existingAttendance) {
                $attendanceRef->update($attendanceData);
            } else {
                $newAttendanceRef = $this->database->getReference('Logs')->push($attendanceData);
                $savedAttendanceId = $newAttendanceRef->getKey();
                $action = 'created';
            }
        } else {
            $newAttendanceRef = $this->database->getReference('Logs')->push($attendanceData);
            $savedAttendanceId = $newAttendanceRef->getKey();
            $action = 'created';
        }

        $this->database->getReference('NotificationLogs')->push([
            'type' => 'attendance_edit',
            'action' => $action,
            'attendanceId' => $savedAttendanceId,
            'editedBy' => $editedBy,
            'recipientDepartment' => 'Human Resource',
            'message' => 'Attendance record was ' . $action . ' by ' . $editedBy,
            'createdAt' => Carbon::now('Asia/Manila')->toDateTimeString(),
            'status' => 'unread',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance updated successfully.',
            'action' => $action,
            'attendanceId' => $savedAttendanceId,
        ]);
    }
}
