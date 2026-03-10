<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Kreait\Firebase\Database;
use App\Models\FirebaseUsers;

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
}
