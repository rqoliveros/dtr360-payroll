<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirebaseAttendance extends Model
{
    
    use HasFactory;
    public $id;
    public $dateTimeIn;
    public $department;
    public $employeeID;
    public $employeeName;
    public $guid;
    public $isWfh;
    public $timeIn;
    public $timeOut;
    public $userType;
    public $hoursWorked;

    public function __construct($id, $data)
    {
        date_default_timezone_set('Asia/Manila');

        $this->id = $id;
        $this->department = $data['department'] ?? null;
        $this->employeeID = $data['employeeID'] ?? null;
        $this->employeeName = $data['employeeName'] ?? null;
        $this->guid = $data['guid'] ?? null;
        $this->isWfh = $data['isWfh'] ?? null;
        $this->userType = $data['usertype'] ?? null;

        $this->dateTimeIn = isset($data['dateTimeIn'])
            ? date('m/d/Y', $data['dateTimeIn'] / 1000)
            : null;

        $this->timeIn = isset($data['timeIn'])
            ? date('m/d/Y h:i A', $data['timeIn'] / 1000)
            : null;

        $this->timeOut = isset($data['timeOut'])
            ? date('m/d/Y h:i A', $data['timeOut'] / 1000)
            : null;

        // Compute Day
        $this->day = $this->dateTimeIn
            ? date('l', strtotime($this->dateTimeIn))
            : null;

        // Compute Hours Worked
        if ($this->timeIn && $this->timeOut) {

            $timeIn = strtotime($this->timeIn);
            $timeOut = strtotime($this->timeOut);

            $seconds = $timeOut - $timeIn;

            $this->hoursWorked = $seconds > 0
                ? gmdate("H:i", $seconds)
                : "00:00";

        } else {
            $this->hoursWorked = "00:00";
        }
    }

}
