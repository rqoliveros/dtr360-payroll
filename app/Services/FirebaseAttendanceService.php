<?php

namespace App\Services;

use App\Models\FirebaseAttendance;
use App\Models\FirebaseFilingDocuments;
use App\Models\FirebaseHolidays;
use App\Models\FirebaseUsers;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DateTime;
use DatePeriod;
use DateInterval;

class FirebaseAttendanceService
{
    protected $database;

    public function __construct($database = null)
    {
        $this->database = $database ?? app('firebase.database');
    }

    public function getAttendanceByDateRange($dept, $startDate, $endDate)
    {
        date_default_timezone_set('Asia/Manila');

        $periods = CarbonPeriod::create(
            Carbon::createFromFormat('Y-m-d', $startDate),
            Carbon::createFromFormat('Y-m-d', $endDate)
        );

        $holidays = $this->getHolidays($startDate, $endDate);
        $employees = $this->getEmployees($dept);
        $docs = $this->getFiledDocumentsByDateRange($dept, $startDate, $endDate, 'dateFrom');
        $ots = $this->getFiledDocumentsByDateRange($dept, $startDate, $endDate, 'otDate');

        $logs = $this->fetchLogs($startDate, $endDate);

        $firebaseAttendance = $this->buildAttendanceFromLogs($logs, $dept);

        $uniqueEmployees = collect($firebaseAttendance)->unique('employeeName')->values();
        $uniqueEmployeeIds = $uniqueEmployees->pluck('employeeID');

        $missingEmployees = collect($employees)
            ->filter(function ($emp) use ($uniqueEmployeeIds) {
                return !$uniqueEmployeeIds->contains($emp->empId) && $emp->usertype != 'Former Employee';
            })
            ->values();

        $this->addMissingEmployees($firebaseAttendance, $missingEmployees, $startDate);

        $uniqueEmployees = collect($firebaseAttendance)->unique('employeeName')->values();

        $this->applyDocuments($firebaseAttendance, $uniqueEmployees, $docs, $ots, $holidays, $employees);

        $this->computeHoursAndRemarks($firebaseAttendance, $employees, $holidays);

        $this->fillMissingShiftDays($firebaseAttendance, $uniqueEmployees, $periods, $employees);

        $this->fillMissingForEmployeesNotInUnique($firebaseAttendance, $missingEmployees, $periods, $employees);

        usort($firebaseAttendance, function($a, $b) {
            $nameComparison = strcmp($a->employeeName, $b->employeeName);
            if ($nameComparison === 0) {
                $dateA = new DateTime($a->dateTimeIn);
                $dateB = new DateTime($b->dateTimeIn);
                return $dateA <=> $dateB;
            }
            return $nameComparison;
        });

        return $this->formatAttendance($firebaseAttendance);
    }

    protected function fetchLogs($startDate, $endDate)
    {
        $startMs = strtotime($startDate) * 1000;
        $endMs = strtotime($endDate . ' +1 day') * 1000;

        $reference = $this->database->getReference('Logs');
        $query = $reference->orderByChild('dateTimeIn')->startAt($startMs)->endAt($endMs);
        return $query->getValue() ?? [];
    }

    protected function buildAttendanceFromLogs($logs, $dept)
    {
        $out = [];
        if (!$logs) return $out;

        foreach ($logs as $id => $data) {
            if ($dept === 'all' || $dept === '') {
                $out[] = new FirebaseAttendance($id, $data);
                continue;
            }

            $deptList = str_contains($dept, ',') ? explode(',', $dept) : [$dept];
            foreach ($deptList as $d) {
                if (str_contains($data['department'], trim($d))) {
                    $out[] = new FirebaseAttendance($id, $data);
                    break;
                }
            }
        }
        return $out;
    }

    protected function addMissingEmployees(&$firebaseAttendance, $missingEmployees, $startDate)
    {
        $startMs = strtotime($startDate) * 1000;
        foreach ($missingEmployees as $missings) {
            $missingRow = new \stdClass();
            $missingRow->id = null;
            $missingRow->employeeID = $missings->empId;
            $missingRow->employeeName = $missings->empName;
            $missingRow->department = $missings->dept;
            $missingRow->dateTimeIn = date('m/d/Y', $startMs / 1000);
            $missingRow->day = date('l', $startMs / 1000);
            $missingRow->timeIn = null;
            $missingRow->hoursWorked = 0.00;
            $missingRow->timeOut = null;
            $missingRow->remarks = 'Absent';
            $missingRow->guid = $missings->guid;
            $missingRow->isAbsent = true;
            $firebaseAttendance[$missings->guid] = $missingRow;
        }
    }

    protected function applyDocuments(&$firebaseAttendance, $uniqueEmployees, $docs, $ots, $holidays, $employees)
    {
        foreach ($uniqueEmployees as $employeeName) {
            foreach ($docs as $doc) {
                if ($doc->guid == $employeeName->guid && $doc->docType == 'Leave') {
                    $this->applyLeave($firebaseAttendance, $employeeName, $doc);
                }
            }

            foreach ($ots as $ot) {
                if ($ot->guid == $employeeName->guid && $ot->docType == 'Overtime') {
                    $this->applyOvertime($firebaseAttendance, $employeeName, $ot);
                }
            }

            foreach ($holidays as $holiday) {
                $timestamp = strtotime($holiday->holidayDate . ' 00:00:00');
                $date = date('m/d/Y', $timestamp);
                $existingRow = collect($firebaseAttendance)->first(function ($row) use ($employeeName, $date) {
                    return $row->employeeID == $employeeName->employeeID && $row->dateTimeIn == $date;
                });

                if ($existingRow) {
                    if (!empty($existingRow->remarks) && !str_contains($existingRow->remarks, $holiday->holidayType)) {
                        $existingRow->remarks .= ' | ' . $holiday->holidayType;
                        $existingRow->holiday = $holiday->holidayType;
                    } else {
                        if (empty($existingRow->remarks)) {
                            $existingRow->remarks = $holiday->holidayType;
                            $existingRow->holiday = $holiday->holidayType;
                        }
                    }
                } else {
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
    }

    protected function applyLeave(&$firebaseAttendance, $employeeName, $leave)
    {
        $from = $leave->dateFrom;
        $to = $leave->dateTo;
        $start = new DateTime(substr($from, 0, 10));
        $end   = new DateTime(substr($to, 0, 10));
        $end->modify('+1 day');
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        $leaveTypeText = $leave->isHalfday ? 'Half day' : $leave->leaveType;

        foreach ($period as $date) {
            $formattedDate = $date->format('m/d/Y');
            $existingRow = null;
            foreach ($firebaseAttendance as $attendance) {
                if ($attendance->employeeID == $employeeName->employeeID && $attendance->dateTimeIn == $formattedDate) {
                    $existingRow = $attendance;
                    break;
                }
            }

            if ($existingRow) {
                $leaveText = $leave->isApproved ? $leaveTypeText : 'Pending: ' . $leaveTypeText;
                $existingRow->remarks = $existingRow->remarks == 'Absent' ? $leaveText : trim(($existingRow->remarks ?? '') . ' | ' . $leaveText);
                $existingRow->leave = $leave->leaveType;
                $existingRow->isAbsent = false;
                $existingRow->isHalfday = $leave->isHalfday;
            } else {
                $leaveRow = new \stdClass();
                $leaveRow->id = null;
                $leaveRow->employeeID = $employeeName->employeeID;
                $leaveRow->employeeName = $employeeName->employeeName;
                $leaveRow->department = $employeeName->department;
                $leaveRow->dateTimeIn = $date->format('m/d/Y');
                $leaveRow->day = $date->format('l');
                $leaveRow->timeIn = null;
                $leaveRow->timeOut = null;
                $leaveRow->remarks = $leave->isApproved ? $leaveTypeText : 'Pending: ' . $leaveTypeText;
                $leaveRow->leave = $leave->leaveType;
                $leaveRow->isApproved = $leave->isApproved;
                $leaveRow->isCancelled = $leave->isCancelled;
                $leaveRow->isHalfday = $leave->isHalfday;
                $firebaseAttendance[] = $leaveRow;
            }
        }
    }

    protected function applyOvertime(&$firebaseAttendance, $employeeName, $overtime)
    {
        $timestamp = strtotime($overtime->otDate);
        $date = date('m/d/Y', $timestamp);
        $existingRow = collect($firebaseAttendance)->first(function ($row) use ($employeeName, $date) {
            return $row->employeeID == $employeeName->employeeID && $row->dateTimeIn == $date;
        });

        if ($existingRow) {
            $text = $overtime->isApproved ? $overtime->otType . ': ' . $overtime->hoursNo : 'Pending: ' . $overtime->otType . ': ' . $overtime->hoursNo;
            $existingRow->remarks = !empty($existingRow->remarks) ? ($existingRow->remarks . ' | ' . $text) : $text;
            $existingRow->otType = $overtime->otType;
            $existingRow->otHours = $overtime->hoursNo;
        }
    }

    protected function computeHoursAndRemarks(&$firebaseAttendance, $employees, $holidays)
    {
        foreach ($firebaseAttendance as $row) {
            if (empty($row->timeIn) || empty($row->timeOut) || $row->timeIn == '-' || $row->timeOut == '-') continue;

            $remarks = [];
            $dayOfWeek = strtolower(date('l', strtotime($row->dateTimeIn)));
            if (!isset($employees[$row->employeeID])) continue;

            $employee = $employees[$row->employeeID];
            $currentShift = $employee->$dayOfWeek;
            if ($currentShift != 1) continue;

            $date = date('Y-m-d', strtotime($row->dateTimeIn));
            $shiftIn  = $employee->shiftTimeIn;
            $shiftOut = $employee->shiftTimeOut;

            $shiftInTime  = strtotime($row->dateTimeIn . ' ' . $shiftIn);
            $shiftOutTime = strtotime($date . ' ' . $shiftOut);

            $timeIn  = strtotime($row->timeIn);
            $timeOut = strtotime($row->timeOut);

            // Role-based computation: drivers ('D') use different rules
            if (isset($employee->userRole) && $employee->userRole === 'D') {
                $this->computeForRoleD($row, $shiftInTime, $shiftOutTime, $timeIn, $timeOut, $currentShift, $employee, $holidays);
            } else {
                if ($timeIn > $shiftInTime) {
                    $lateMinutes = floor(($timeIn - $shiftInTime) / 60);
                    $row->remarks .= " Late {$lateMinutes} mins";
                    $row->late = $lateMinutes;
                }

                if ($timeOut < $shiftOutTime) {
                    $utMinutes = floor(($shiftOutTime - $timeOut) / 60);
                    $row->remarks .= " | Undertime {$utMinutes} mins";
                    $row->undertime = $utMinutes;
                }
            }

            if (!empty($remarks)) {
                if (!empty($row->remarks) && $row->remarks != '-') {
                    $row->remarks .= ' | ' . implode(' | ', $remarks);
                } else {
                    $row->remarks = implode(' | ', $remarks);
                }
            }
        }
    }

    /**
     * Compute hours/remarks for employees with userRole 'D'.
     * Current rule: 15-minute grace period for late arrival; undertime same as default.
     */
    protected function computeForRoleD(&$row, $shiftInTime, $shiftOutTime, $timeIn, $timeOut, $currentShift, $employee, $holidays)
    {
        $graceSeconds = 15 * 60; // 15 minutes

        // Normalize for overnight shifts / overnight logs
        if ($shiftOutTime <= $shiftInTime) {
            $shiftOutTime += 86400; // add 24h
        }
        if ($timeOut <= $timeIn) {
            $timeOut += 86400; // add 24h
        }

        // Total worked seconds
        $workedSeconds = max(0, $timeOut - $timeIn);

        // Regular seconds = overlap between worked interval and shift interval
        $regularStart = max($timeIn, $shiftInTime);
        $regularEnd = min($timeOut, $shiftOutTime);
        $regularSeconds = max(0, $regularEnd - $regularStart);

        // Build overtime segments: before shift and after shift
        $otSegments = [];
        if ($timeIn < $shiftInTime) {
            $otSegments[] = [ $timeIn, min($timeOut, $shiftInTime) ];
        }
        if ($timeOut > $shiftOutTime) {
            $otSegments[] = [ max($timeIn, $shiftOutTime), $timeOut ];
        }

        // Initialize counters and detailed buckets
        $regularHours = round($regularSeconds / 3600, 2);
        $totalOtSeconds = 0;
        // OT buckets (non-night / night)
        $regularOtSeconds = 0;
        $regularNightOtSeconds = 0;
        $restOtSeconds = 0;
        $restNightOtSeconds = 0;
        $holidayOtSeconds = 0;
        $holidayNightOtSeconds = 0;
        $holidayRestOtSeconds = 0;
        $holidayRestNightOtSeconds = 0;
        $specialOtSeconds = 0;
        $specialNightOtSeconds = 0;
        $specialRestOtSeconds = 0;
        $specialRestNightOtSeconds = 0;

        // Regular/shift buckets (day parts inside shift interval) and night-shift breakdowns
        $regularDaySeconds = 0;
        $restDaySeconds = 0;
        $regularHolidaySeconds = 0;
        $regularHolidayRestDaySeconds = 0;
        $specialNonWorkingSeconds = 0;
        $specialNonWorkingRestDaySeconds = 0;

        $nightShiftSeconds = 0;
        $nightShiftRestDaySeconds = 0;
        $nightShiftRegularHolidaySeconds = 0;
        $nightShiftSpecialNonWorkingSeconds = 0;
        $nightShiftRegularHolidayRestDaySeconds = 0;
        $nightShiftSpecialNonWorkingRestDaySeconds = 0;

        $nightSecondsTotal = 0; // total night hours across whole worked interval

        // Helper: get holiday type (string) or null for YYYY-MM-DD
        $holidayTypeForDate = function($dateYmd) use ($holidays) {
            foreach ($holidays as $h) {
                if (isset($h->holidayDate) && date('Y-m-d', strtotime($h->holidayDate)) === $dateYmd) return $h->holidayType;
            }
            return null;
        };

        // Process overtime segments and classify by calendar day, holiday type, rest-day and night window
        foreach ($otSegments as $seg) {
            $segStart = $seg[0];
            $segEnd = $seg[1];
            if ($segEnd <= $segStart) continue;
            $totalOtSeconds += max(0, $segEnd - $segStart);

            $dayStart = (int)floor($segStart / 86400);
            $dayEnd = (int)floor(($segEnd - 1) / 86400);
            for ($d = $dayStart; $d <= $dayEnd; $d++) {
                $daySecStart = $d * 86400;
                $daySecEnd = ($d + 1) * 86400;
                $partStart = max($segStart, $daySecStart);
                $partEnd = min($segEnd, $daySecEnd);
                if ($partEnd <= $partStart) continue;
                $partLen = $partEnd - $partStart;

                // Compute night window and classification based on actual segment start timestamp
                $partDate = date('Y-m-d', $partStart);
                $nightStart = strtotime($partDate . ' 22:00');
                $nightEnd = strtotime($partDate . ' 06:00') + 86400; // 06:00 next day
                $nightOverlap = max(0, min($partEnd, $nightEnd) - max($partStart, $nightStart));
                $nonNight = $partLen - $nightOverlap;
                $nightSecondsTotal += $nightOverlap;

                // Determine the calendar date for this part (use Y-m-d) and holiday type
                $partDateYmd = date('Y-m-d', $partStart);
                $holidayType = $holidayTypeForDate($partDateYmd);
                $isHoliday = !empty($holidayType);
                $isSpecial = $isHoliday && (stripos($holidayType, 'special') !== false || stripos($holidayType, 'non-working') !== false || stripos($holidayType, 'non working') !== false);
                // Determine if this date is a rest day for the employee
                $dayNameLower = strtolower(date('l', $partStart));
                $isShiftOnThisDay = isset($employee->$dayNameLower) ? $employee->$dayNameLower : 0;
                $isRestDay = !$isShiftOnThisDay;

                // Classify into finer-grained OT buckets
                if ($isHoliday) {
                    if ($isSpecial) {
                        // Special non-working holiday
                        if ($nonNight > 0) {
                            if ($isRestDay) $specialRestOtSeconds += $nonNight; else $specialOtSeconds += $nonNight;
                        }
                        if ($nightOverlap > 0) {
                            if ($isRestDay) $specialRestNightOtSeconds += $nightOverlap; else $specialNightOtSeconds += $nightOverlap;
                        }
                    } else {
                        // Regular holiday
                        if ($nonNight > 0) {
                            if ($isRestDay) $holidayRestOtSeconds += $nonNight; else $holidayOtSeconds += $nonNight;
                        }
                        if ($nightOverlap > 0) {
                            if ($isRestDay) $holidayRestNightOtSeconds += $nightOverlap; else $holidayNightOtSeconds += $nightOverlap;
                        }
                    }
                } elseif ($isRestDay) {
                    // Rest day OT
                    $restOtSeconds += $nonNight;
                    $restNightOtSeconds += $nightOverlap;
                } else {
                    // Regular OT
                    $regularOtSeconds += $nonNight;
                    $regularNightOtSeconds += $nightOverlap;
                }
            }
        }

        // Compute total night seconds across whole worked interval (including regular shift)
        $dayStartAll = (int)floor($timeIn / 86400);
        $dayEndAll = (int)floor(($timeOut - 1) / 86400);
        $nightSeconds = 0;
        for ($d = $dayStartAll; $d <= $dayEndAll; $d++) {
            $nightStart = $d * 86400 + strtotime('22:00', 0);
            $nightEnd = ($d + 1) * 86400 + strtotime('06:00', 0);
            $ns = max($timeIn, $nightStart);
            $ne = min($timeOut, $nightEnd);
            if ($ne > $ns) {
                $nightSeconds += $ne - $ns;
            }
        }

        // Split regular shift seconds into fine-grained categories (may span multiple days)
        if ($regularSeconds > 0) {
            $rStart = $regularStart;
            $rEnd = $regularEnd;
            $dayStart = (int)floor($rStart / 86400);
            $dayEnd = (int)floor(($rEnd - 1) / 86400);
            for ($d = $dayStart; $d <= $dayEnd; $d++) {
                $daySecStart = $d * 86400;
                $daySecEnd = ($d + 1) * 86400;
                $partStart = max($rStart, $daySecStart);
                $partEnd = min($rEnd, $daySecEnd);
                if ($partEnd <= $partStart) continue;
                $partLen = $partEnd - $partStart;

                $partDate = date('Y-m-d', $partStart);
                $nightStart = strtotime($partDate . ' 22:00');
                $nightEnd = strtotime($partDate . ' 06:00') + 86400; // 06:00 next day
                $nightOverlap = max(0, min($partEnd, $nightEnd) - max($partStart, $nightStart));
                $nonNight = $partLen - $nightOverlap;

                $partDateYmd = date('Y-m-d', $partStart);
                $holidayType = $holidayTypeForDate($partDateYmd);
                $isHoliday = !empty($holidayType);
                $isSpecial = $isHoliday && (stripos($holidayType, 'special') !== false || stripos($holidayType, 'non-working') !== false || stripos($holidayType, 'non working') !== false);
                $dayNameLower = strtolower(date('l', $partStart));
                $isShiftOnThisDay = isset($employee->$dayNameLower) ? $employee->$dayNameLower : 0;
                $isRestDay = !$isShiftOnThisDay;

                if ($isHoliday) {
                    if ($isSpecial) {
                        // special non-working
                        
                        $specialNonWorkingSeconds += $nonNight;
                        $specialNonWorkingRestDaySeconds += ($isRestDay ? $nonNight : 0);
                        $nightShiftSpecialNonWorkingSeconds += $nightOverlap;
                        $nightShiftSpecialNonWorkingRestDaySeconds += ($isRestDay ? $nightOverlap : 0);
                    } else {
                        // regular holiday
                        $regularHolidaySeconds += $nonNight;
                        $regularHolidayRestDaySeconds += ($isRestDay ? $nonNight : 0);
                        $nightShiftRegularHolidaySeconds += $nightOverlap;
                        $nightShiftRegularHolidayRestDaySeconds += ($isRestDay ? $nightOverlap : 0);
                    }
                } elseif ($isRestDay) {
                    $restDaySeconds += $nonNight;
                    $nightShiftRestDaySeconds += $nightOverlap;
                } else {
                    $regularDaySeconds += $nonNight;
                    $nightShiftSeconds += $nightOverlap;
                }
            }
        }

        // Round values to hours
        $hoursWorked = round($workedSeconds / 3600, 2);
        $computedOtHours = round($totalOtSeconds / 3600, 2);
        $regularOtHours = round($regularOtSeconds / 3600, 2);
        $regularNightOtHours = round($regularNightOtSeconds / 3600, 2);
        $restOtHours = round($restOtSeconds / 3600, 2);
        $restNightOtHours = round($restNightOtSeconds / 3600, 2);
        $holidayOtHours = round($holidayOtSeconds / 3600, 2);
        $holidayNightOtHours = round($holidayNightOtSeconds / 3600, 2);
        $holidayRestOtHours = round($holidayRestOtSeconds / 3600, 2);
        $holidayRestNightOtHours = round($holidayRestNightOtSeconds / 3600, 2);
        $specialOtHours = round($specialOtSeconds / 3600, 2);
        $specialNightOtHours = round($specialNightOtSeconds / 3600, 2);
        $specialRestOtHours = round($specialRestOtSeconds / 3600, 2);
        $specialRestNightOtHours = round($specialRestNightOtSeconds / 3600, 2);

        $regularDayHours = round($regularDaySeconds / 3600, 2);
        $restDayHours = round($restDaySeconds / 3600, 2);
        $regularHolidayHours = round($regularHolidaySeconds / 3600, 2);
        $regularHolidayRestDayHours = round($regularHolidayRestDaySeconds / 3600, 2);
        $specialNonWorkingHours = round($specialNonWorkingSeconds / 3600, 2);
        $specialNonWorkingRestDayHours = round($specialNonWorkingRestDaySeconds / 3600, 2);

        $nightHours = round($nightSeconds / 3600, 2);
        $nightShiftHours = round($nightShiftSeconds / 3600, 2);
        $nightShiftRestDayHours = round($nightShiftRestDaySeconds / 3600, 2);
        $nightShiftRegularHolidayHours = round($nightShiftRegularHolidaySeconds / 3600, 2);
        $nightShiftSpecialNonWorkingHours = round($nightShiftSpecialNonWorkingSeconds / 3600, 2);
        $nightShiftRegularHolidayRestDayHours = round($nightShiftRegularHolidayRestDaySeconds / 3600, 2);
        $nightShiftSpecialNonWorkingRestDayHours = round($nightShiftSpecialNonWorkingRestDaySeconds / 3600, 2);

        // Apply break deduction: 1 hour unpaid if worked more than 8 hours
        $breakSeconds = ($workedSeconds > 8 * 3600) ? 3600 : 0;
        $paidSeconds = max(0, $workedSeconds - $breakSeconds);

        // Allocate up to 8 hours (regular) from shift-part buckets in priority order
        $regularCapSec = min(8 * 3600, $paidSeconds);
        $allocatedRegularSec = 0;
        $alloc_regularHolidaySec = 0;
        $alloc_specialNonWorkingSec = 0;
        $alloc_restDaySec = 0;
        $alloc_regularDaySec = 0;

        $shiftBuckets = [
            'regularHoliday' => $regularHolidaySeconds,
            'specialNonWorking' => $specialNonWorkingSeconds,
            'restDay' => $restDaySeconds,
            'regularDay' => $regularDaySeconds,
        ];

        foreach ($shiftBuckets as $key => $secs) {
            if ($allocatedRegularSec >= $regularCapSec) break;
            $need = $regularCapSec - $allocatedRegularSec;
            $take = min($secs, $need);
            if ($take <= 0) continue;
            switch ($key) {
                case 'regularHoliday': $alloc_regularHolidaySec += $take; break;
                case 'specialNonWorking': $alloc_specialNonWorkingSec += $take; break;
                case 'restDay': $alloc_restDaySec += $take; break;
                case 'regularDay': $alloc_regularDaySec += $take; break;
            }
            $allocatedRegularSec += $take;
        }

        // Remaining paid seconds after allocating regular hours
        $remainingPaid = max(0, $paidSeconds - $allocatedRegularSec);

        // Allocate remaining paid seconds to OT buckets in a deterministic order
        $otBuckets = [
            'regularOt' => $regularOtSeconds,
            'regularNightOt' => $regularNightOtSeconds,
            'restOt' => $restOtSeconds,
            'restNightOt' => $restNightOtSeconds,
            'holidayOt' => $holidayOtSeconds,
            'holidayNightOt' => $holidayNightOtSeconds,
            'holidayRestOt' => $holidayRestOtSeconds,
            'holidayRestNightOt' => $holidayRestNightOtSeconds,
            'specialOt' => $specialOtSeconds,
            'specialNightOt' => $specialNightOtSeconds,
            'specialRestOt' => $specialRestOtSeconds,
            'specialRestNightOt' => $specialRestNightOtSeconds,
        ];

        $allocatedOtSec = array_fill_keys(array_keys($otBuckets), 0);
        foreach ($otBuckets as $k => $secs) {
            if ($remainingPaid <= 0) break;
            $take = min($secs, $remainingPaid);
            $allocatedOtSec[$k] += $take;
            $remainingPaid -= $take;
        }

        // Final computed hours
        $finalRegularHours = round($allocatedRegularSec / 3600, 2);
        $finalRegularHolidayHours = round($alloc_regularHolidaySec / 3600, 2);
        $finalSpecialNonWorkingHours = round($alloc_specialNonWorkingSec / 3600, 2);
        $finalRestDayHours = round($alloc_restDaySec / 3600, 2);
        $finalRegularDayHours = round($alloc_regularDaySec / 3600, 2);

        // OT final hours
        $finalRegularOtHours = round($allocatedOtSec['regularOt'] / 3600, 2);
        $finalRegularNightOtHours = round($allocatedOtSec['regularNightOt'] / 3600, 2);
        $finalRestOtHours = round($allocatedOtSec['restOt'] / 3600, 2);
        $finalRestNightOtHours = round($allocatedOtSec['restNightOt'] / 3600, 2);
        $finalHolidayOtHours = round($allocatedOtSec['holidayOt'] / 3600, 2);
        $finalHolidayNightOtHours = round($allocatedOtSec['holidayNightOt'] / 3600, 2);
        $finalHolidayRestOtHours = round($allocatedOtSec['holidayRestOt'] / 3600, 2);
        $finalHolidayRestNightOtHours = round($allocatedOtSec['holidayRestNightOt'] / 3600, 2);
        $finalSpecialOtHours = round($allocatedOtSec['specialOt'] / 3600, 2);
        $finalSpecialNightOtHours = round($allocatedOtSec['specialNightOt'] / 3600, 2);
        $finalSpecialRestOtHours = round($allocatedOtSec['specialRestOt'] / 3600, 2);
        $finalSpecialRestNightOtHours = round($allocatedOtSec['specialRestNightOt'] / 3600, 2);

        // Assign to row
        $row->hoursWorked = $hoursWorked;
        $row->regularHours = $finalRegularHours;
        $row->computedOt = round(array_sum($allocatedOtSec) / 3600, 2);
        if (!isset($row->otHours)) {
            $row->otHours = $row->computedOt;
        }
        $row->nightHours = $nightHours;

        // New OT breakdown fields (final allocated values)
        $row->regularOt = $finalRegularOtHours;
        $row->regularNightOt = $finalRegularNightOtHours;
        $row->restOt = $finalRestOtHours;
        $row->restNightOt = $finalRestNightOtHours;
        $row->holidayOt = $finalHolidayOtHours;
        $row->holidayNightOt = $finalHolidayNightOtHours;
        // Additional OT breakdowns
        $row->holidayRestOt = $finalHolidayRestOtHours;
        $row->holidayRestNightOt = $finalHolidayRestNightOtHours;
        $row->specialOt = $finalSpecialOtHours;
        $row->specialNightOt = $finalSpecialNightOtHours;
        $row->specialRestOt = $finalSpecialRestOtHours;
        $row->specialRestNightOt = $finalSpecialRestNightOtHours;

        // Regular / day / night breakdowns (final allocated values where applicable)
        $row->regularDayHours = $finalRegularDayHours;
        $row->restDayHours = $finalRestDayHours;
        $row->regularHolidayHours = $finalRegularHolidayHours;
        $row->regularHolidayRestDayHours = $regularHolidayRestDayHours;
        $row->specialNonWorkingHours = $finalSpecialNonWorkingHours;
        $row->specialNonWorkingRestDayHours = $specialNonWorkingRestDayHours;

        $row->nightShiftHours = $nightShiftHours;
        $row->nightShiftRestDayHours = $nightShiftRestDayHours;
        $row->nightShiftRegularHolidayHours = $nightShiftRegularHolidayHours;
        $row->nightShiftSpecialNonWorkingHours = $nightShiftSpecialNonWorkingHours;
        $row->nightShiftRegularHolidayRestDayHours = $nightShiftRegularHolidayRestDayHours;
        $row->nightShiftSpecialNonWorkingRestDayHours = $nightShiftSpecialNonWorkingRestDayHours;

        // Late
        if ($timeIn > ($shiftInTime + $graceSeconds)) {
            $lateMinutes = floor(($timeIn - $shiftInTime) / 60);
            $row->remarks .= " Late {$lateMinutes} mins";
            $row->late = $lateMinutes;
        }

        // Undertime (compared to normalized shiftOutTime)
        if ($timeOut < $shiftOutTime) {
            $utMinutes = floor(($shiftOutTime - $timeOut) / 60);
            $row->remarks .= " | Undertime {$utMinutes} mins";
            $row->undertime = $utMinutes;
        }

        // Add computed pay notes to remarks
        if ($nightHours > 0) {
            $row->remarks .= " | Night {$nightHours} hrs";
        }
        if ($row->computedOt > 0) {
            $row->remarks .= " | OT {$row->computedOt} hrs";
        }
        if ($finalRegularOtHours > 0) {
            $row->remarks .= " | Regular OT: {$finalRegularOtHours} hrs";
        }
        if ($finalRegularNightOtHours > 0) {
            $row->remarks .= " | Regular Night OT: {$finalRegularNightOtHours} hrs";
        }
        if ($finalRestOtHours > 0) {
            $row->remarks .= " | Rest Day OT: {$finalRestOtHours} hrs";
        }
        if ($finalRestNightOtHours > 0) {
            $row->remarks .= " | Rest Day Night OT: {$finalRestNightOtHours} hrs";
        }
        if ($finalHolidayOtHours > 0) {
            $row->remarks .= " | Holiday OT: {$finalHolidayOtHours} hrs";
        }
        if ($finalHolidayNightOtHours > 0) {
            $row->remarks .= " | Holiday Night OT: {$finalHolidayNightOtHours} hrs";
        }
        if ($finalHolidayRestOtHours > 0) {
            $row->remarks .= " | Holiday(Rest Day) OT: {$finalHolidayRestOtHours} hrs";
        }
        if ($finalHolidayRestNightOtHours > 0) {
            $row->remarks .= " | Holiday(Rest Day) Night OT: {$finalHolidayRestNightOtHours} hrs";
        }
        if ($finalSpecialOtHours > 0) {
            $row->remarks .= " | Special Non-working OT: {$finalSpecialOtHours} hrs";
        }
        if ($finalSpecialNightOtHours > 0) {
            $row->remarks .= " | Special Non-working Night OT: {$finalSpecialNightOtHours} hrs";
        }
        if ($finalSpecialRestOtHours > 0) {
            $row->remarks .= " | Special Non-working(Rest Day) OT: {$finalSpecialRestOtHours} hrs";
        }
        if ($finalSpecialRestNightOtHours > 0) {
            $row->remarks .= " | Special Non-working(Rest Day) Night OT: {$finalSpecialRestNightOtHours} hrs";
        }

        // Regular / day / night breakdowns
        if ($finalRegularDayHours > 0) {
            $row->remarks .= " | Regular Day: {$finalRegularDayHours} hrs";
        }
        if ($finalRestDayHours > 0) {
            $row->remarks .= " | Rest Day: {$finalRestDayHours} hrs";
        }
        if ($finalRegularHolidayHours > 0) {
            $row->remarks .= " | Regular Holiday: {$finalRegularHolidayHours} hrs";
        }
        if ($regularHolidayRestDayHours > 0) {
            $row->remarks .= " | Regular Holiday(Rest Day): {$regularHolidayRestDayHours} hrs";
        }
        if ($finalSpecialNonWorkingHours > 0) {
            $row->remarks .= " | Special Non-working: {$finalSpecialNonWorkingHours} hrs";
        }
        if ($specialNonWorkingRestDayHours > 0) {
            $row->remarks .= " | Special Non-working(Rest Day): {$specialNonWorkingRestDayHours} hrs";
        }

        if ($nightShiftHours > 0) {
            $row->remarks .= " | Night Shift: {$nightShiftHours} hrs";
        }
        if ($nightShiftRestDayHours > 0) {
            $row->remarks .= " | Night Shift(Rest Day): {$nightShiftRestDayHours} hrs";
        }
        if ($nightShiftRegularHolidayHours > 0) {
            $row->remarks .= " | Night Shift(Regular Holiday): {$nightShiftRegularHolidayHours} hrs";
        }
        if ($nightShiftSpecialNonWorkingHours > 0) {
            $row->remarks .= " | Night Shift(Special Non-working): {$nightShiftSpecialNonWorkingHours} hrs";
        }
        if ($nightShiftRegularHolidayRestDayHours > 0) {
            $row->remarks .= " | Night Shift(Regular Holiday Rest Day): {$nightShiftRegularHolidayRestDayHours} hrs";
        }
        if ($nightShiftSpecialNonWorkingRestDayHours > 0) {
            $row->remarks .= " | Night Shift(Special Non-working Rest Day): {$nightShiftSpecialNonWorkingRestDayHours} hrs";
        }

        // Holiday / Rest day flags (if already marked earlier)
        $row->isHoliday = isset($row->holiday) && !empty($row->holiday);
        $row->isRestDay = isset($row->remarks) && str_contains($row->remarks, 'Rest Day');
    }

    protected function fillMissingShiftDays(&$firebaseAttendance, $uniqueEmployees, $periods, $employees)
    {
        foreach ($uniqueEmployees as $employeeName) {
            foreach ($periods as $period) {
                $timestamp = $period->timestamp;
                $date = $period->format('m/d/Y');
                $dayofweek = strtolower($period->format('l'));

                $existingRow = collect($firebaseAttendance)->first(function ($row) use ($employeeName, $date) {
                    return $row->employeeID == $employeeName->employeeID && $row->dateTimeIn == $date;
                });

                if (!isset($employees[$employeeName->employeeID])) continue;

                $currentShift = $employees[$employeeName->employeeID]->$dayofweek;
                if (!$existingRow && $currentShift == 1) {
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
                    $missingRow->isAbsent = true;
                    $firebaseAttendance[] = $missingRow;
                }
            }
        }
    }

    protected function fillMissingForEmployeesNotInUnique(&$firebaseAttendance, $missingEmployees, $periods, $employees)
    {
        foreach ($missingEmployees as $missings) {
            foreach ($periods as $period) {
                $timestamp = $period->timestamp;
                $date = $period->format('m/d/Y');
                $dayofweek = strtolower($period->format('l'));

                $existingRow = collect($firebaseAttendance)->first(function ($row) use ($missings, $date) {
                    return $row->employeeID == $missings->empId && $row->dateTimeIn == $date;
                });

                if (!isset($employees[$missings->empId])) continue;

                $currentShift = $employees[$missings->empId]->$dayofweek;
                if (!$existingRow && $currentShift == 1) {
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
                    $missingRow->isAbsent = true;
                    $firebaseAttendance[] = $missingRow;
                }
            }
        }
    }

    protected function formatAttendance($firebaseAttendance)
    {
        $formatted = [];
        foreach ($firebaseAttendance as $emp) {
            $formatted[] = [
                'id' => $emp->id,
                'employeeID' => $emp->employeeID,
                'employeeName' => $emp->employeeName,
                'department' => $emp->department,
                'dateTimeIn' => $emp->dateTimeIn,
                'day' => $emp->day,
                'hoursWorked' => $emp->hoursWorked ?? 0.00,
                'timeIn' => $emp->timeIn,
                'timeOut' => $emp->timeOut,
                'remarks' => $emp->remarks,
                'undertime' => $emp->undertime ?? 0.00,
                'late' => $emp->late ?? 0.00,
                'isAbsent' => $emp->isAbsent ?? false,
                'isCancelled' => $emp->isCancelled ?? false,
                'holiday' => $emp->holiday ?? '',
                'isHalfday' => $emp->isHalfday ?? false
                , 'regularHours' => $emp->regularHours ?? 0.00
                , 'nightHours' => $emp->nightHours ?? 0.00
                , 'computedOt' => $emp->computedOt ?? 0.00
                , 'regularOt' => $emp->regularOt ?? 0.00
                , 'regularNightOt' => $emp->regularNightOt ?? 0.00
                , 'restNightOt' => $emp->restNightOt ?? 0.00
                , 'restOt' => $emp->restOt ?? 0.00
                , 'holidayNightOt' => $emp->holidayNightOt ?? 0.00
                , 'holidayOt' => $emp->holidayOt ?? 0.00
                , 'holidayRestOt' => $emp->holidayRestOt ?? 0.00
                , 'holidayRestNightOt' => $emp->holidayRestNightOt ?? 0.00
                , 'specialOt' => $emp->specialOt ?? 0.00
                , 'specialNightOt' => $emp->specialNightOt ?? 0.00
                , 'specialRestOt' => $emp->specialRestOt ?? 0.00
                , 'specialRestNightOt' => $emp->specialRestNightOt ?? 0.00
                , 'regularDayHours' => $emp->regularDayHours ?? 0.00
                , 'restDayHours' => $emp->restDayHours ?? 0.00
                , 'regularHolidayHours' => $emp->regularHolidayHours ?? 0.00
                , 'regularHolidayRestDayHours' => $emp->regularHolidayRestDayHours ?? 0.00
                , 'specialNonWorkingHours' => $emp->specialNonWorkingHours ?? 0.00
                , 'specialNonWorkingRestDayHours' => $emp->specialNonWorkingRestDayHours ?? 0.00
                , 'nightShiftHours' => $emp->nightShiftHours ?? 0.00
                , 'nightShiftRestDayHours' => $emp->nightShiftRestDayHours ?? 0.00
                , 'nightShiftRegularHolidayHours' => $emp->nightShiftRegularHolidayHours ?? 0.00
                , 'nightShiftSpecialNonWorkingHours' => $emp->nightShiftSpecialNonWorkingHours ?? 0.00
                , 'nightShiftRegularHolidayRestDayHours' => $emp->nightShiftRegularHolidayRestDayHours ?? 0.00
                , 'nightShiftSpecialNonWorkingRestDayHours' => $emp->nightShiftSpecialNonWorkingRestDayHours ?? 0.00
                , 'isHoliday' => $emp->isHoliday ?? false
                , 'isRestDay' => $emp->isRestDay ?? false
            ];
        }
        return $formatted;
    }

    public function getEmployees($dept)
    {
        $users = $this->database->getReference('Employee')->getValue();
        $firebaseUsers = [];
        if ($users) {
            foreach ($users as $id => $data) {
                if ($dept === 'all' || $dept === '') {
                    $firebaseUsers[$data['employeeID']] = new FirebaseUsers($id, $data);
                } else {
                    $deptList = str_contains($dept, ',') ? explode(',', $dept) : [$dept];
                    foreach ($deptList as $d) {
                        if (str_contains($data['department'], trim($d))) {
                            $firebaseUsers[$data['employeeID']] = new FirebaseUsers($id, $data);
                            break;
                        }
                    }
                }
            }
        }
        return $firebaseUsers;
    }

    public function getHolidays($startDate, $endDate)
    {
        $startTimestamp = strtotime($startDate);
        $endTimestamp   = strtotime($endDate);
        $reference = $this->database->getReference('Holidays');
        $holidays = $reference->getValue() ?? [];
        $firebaseHolidays = [];
        foreach ($holidays as $id => $data) {
            if (isset($data['holidayDate'])) {
                $holidayTimestamp = strtotime($data['holidayDate']);
                if ($holidayTimestamp >= $startTimestamp && $holidayTimestamp <= $endTimestamp) {
                    $firebaseHolidays[] = new FirebaseHolidays($id, $data);
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
        $query = $reference->orderByChild($filter)->startAt($startAt)->endAt($endAt);
        $docs = $query->getValue() ?? [];
        $firebaseDocs = [];
        if ($docs) {
            foreach ($docs as $id => $data) {
                if ($dept == 'all') {
                    $firebaseDocs[$data['guid']] = new FirebaseFilingDocuments($id, $data);
                } else {
                    $deptList = str_contains($dept, ',') ? explode(',', $dept) : [$dept];
                    foreach ($deptList as $d) {
                        if (str_contains($data['dept'], trim($d))) {
                            $firebaseDocs[] = new FirebaseFilingDocuments($id, $data);
                            break;
                        }
                    }
                }
            }
        }
        return $firebaseDocs;
    }
}
