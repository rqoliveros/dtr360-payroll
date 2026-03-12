<?php

namespace App\Http\Controllers;

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

    public function approveDocuments(Request $request){
        // dd($request);

        foreach($request as $req){
            dd($req);
        }
        return response()->json($request);
    }

    public function rejectDocument(Request $request){
        $approver = Session::get('firebase_user.name');
        $timestamp = now()->format('Y-m-d H:i:s.u');
        $docs = $this->database->getReference('FilingDocuments/'. $request->id);
        if($docs) {
            $docs->update([
                'isApproved'=> false,
                'approveRejectBy'=> $approver,
                'approveRejectDate'=> $timestamp,
                'approveRejectReason' => $request->reason
            ]);
            return response()->json([
                'success' => true
            ]);
        }
        
    }

    public function approveDocument(Request $request){
        $approver = Session::get('firebase_user.name');
        $timestamp = now()->format('Y-m-d H:i:s.u');
        $docs = $this->database->getReference('FilingDocuments/'. $request->id);
        $documentData = $docs->getValue();
        if($documentData) {
            if($request->docType == 'Overtime') {
                $this->approveOvertime($request, $approver, $timestamp);
            }
            else if( $request->docType == 'Correction') {

            }
            else if( $request->docType == 'Leave') {
                $this->approveLeave($approver, $timestamp, $docs, $request->empKey, $documentData);
            }
        }
    }

    public function approveLeave($approver, $timestamp, $docs, $empKey, $documentData){
        //Deduct the leave if deductLeave is true
        if($documentData['deductLeave' == true]){
            $employee = $this->database->getReference('Employee/'. $empKey);
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
        $docs->update([
                'isApproved'=> true,
                'approveRejectBy'=> $approver,
                'approveRejectDate'=> $timestamp
            ]);
        return response()->json([
            'success' => true
        ]);

        
    }

    public function approveCorrection(Request $request){

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
}
