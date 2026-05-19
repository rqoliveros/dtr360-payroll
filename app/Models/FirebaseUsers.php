<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirebaseUsers extends Model
{
    use HasFactory;
    public $id;
    public $empName;
    public $empId;
    public $dept;
    public $email;
    public $empStatus;
    public $guid;
    public $remainingLeaves;
    public $shiftTimeIn;
    public $shiftTimeOut;
    public $usertype;
    public $monday;
    public $tuesday;
    public $wednesday;
    public $thursday;
    public $friday;
    public $saturday;
    public $sunday;

    public function __construct($id, $data)
    {
        $this->id = $id;
        $this->empName = $data['employeeName'] ?? null;
        $this->empId = $data['employeeID'] ?? null;
        $this->dept = $data['department'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->empStatus = $data['employeeStatus'] ?? null;
        $this->guid = $data['guid'] ?? null;
        $this->remainingLeaves = $data['remainingLeaves'] ?? null;
        $this->shiftTimeIn = $data['shiftTimeIn'] ?? null;
        $this->shiftTimeOut = $data['shiftTimeOut'] ?? null;
        $this->usertype = $data['usertype'] ?? null;
        $this->monday = $data['monday'] ?? null;
        $this->tuesday = $data['tuesday'] ?? null;
        $this->wednesday = $data['wednesday'] ?? null;
        $this->thursday = $data['thursday'] ?? null;
        $this->friday = $data['friday'] ?? null;
        $this->saturday = $data['saturday'] ?? null;
        $this->sunday = $data['sunday'] ?? null;
        $this->userRole = $data['userRole'] ?? null;
    }
}
