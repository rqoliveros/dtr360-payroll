<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirebaseFilingDocuments extends Model
{
    use HasFactory;
    public $id;
    public $approveRejectBy;
    public $approveRejectDate;
    public $approveRejectReason;
    public $attachmentName;
    public $correctDate;
    public $correctTime;
    public $date;
    public $dateFrom;
    public $dateTo;
    public $deductLeave;
    public $dept;
    public $docType;
    public $empKey;
    public $employeeName;
    public $fileId;
    public $finalDate;
    public $guid;
    public $hoursNo;
    public $isAm;
    public $isApproved;
    public $isHalfday;
    public $isNextdayTimeOut;
    public $isOut;
    public $isOvernightOt;
    public $leaveType;
    public $location;
    public $noOfDay;
    public $notifyStatus;
    public $otDate;
    public $otTo;
    public $otType;
    public $otfrom;
    public $reason;
    public $uniqueId;
    public $correctBothTime;

    public function __construct($id, $data)
    {
        $this->id = $id;
        $this->approveRejectBy       = $data['approveRejectBy'] ?? null;
        $this->approveRejectReason   = $data['approveRejectReason'] ?? null;
        $this->approveRejectDate     = $data['approveRejectDate'] ?? null;
        $this->attachmentName        = $data['attachmentName'] ?? null;
        $this->correctDate           = $data['correctDate'] ?? null;
        $this->correctBothTime       = $data['correctBothTime'] ?? null;
        $this->correctTime           = $data['correctTime'] ?? null;
        $this->date                  = $data['date'] ?? null;
        $this->dateFrom              = $data['dateFrom'] ?? null;
        $this->dateTo                = $data['dateTo'] ?? null;
        $this->deductLeave           = $data['deductLeave'] ?? null;
        $this->dept                  = $data['dept'] ?? null;
        $this->docType              = $data['docType'] ?? null;
        $this->empKey                = $data['empKey'] ?? null;
        $this->employeeName          = $data['employeeName'] ?? null;
        $this->fileId                = $data['fileId'] ?? null;
        $this->finalDate             = $data['finalDate'] ?? null;
        $this->guid                  = $data['guid'] ?? null;
        $this->hoursNo               = $data['hoursNo'] ?? null;
        $this->isAm                  = $data['isAm'] ?? null;
        $this->isApproved            = $data['isApproved'] ?? null;
        $this->isHalfday             = $data['isHalfday'] ?? null;
        $this->isNextdayTimeOut      = $data['isNextdayTimeOut'] ?? null;
        $this->isOut                 = $data['isOut'] ?? null;
        $this->isOvernightOt         = $data['isOvernightOt'] ?? null;
        $this->leaveType             = $data['leaveType'] ?? null;
        $this->location              = $data['location'] ?? null;
        $this->noOfDay               = $data['noOfDay'] ?? null;
        $this->notifyStatus          = $data['notifyStatus'] ?? null;
        $this->otDate                = $data['otDate'] ?? null;
        $this->otTo                  = $data['otTo'] ?? null;
        $this->otType                = $data['otType'] ?? null;
        $this->otfrom                = $data['otfrom'] ?? null;
        $this->reason                = $data['reason'] ?? null;
        $this->uniqueId              = $data['uniqueId'] ?? null;
    }
}
