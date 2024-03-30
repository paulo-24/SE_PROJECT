<?php
include("php/database.php");

if (
    isset($_POST['action']) &&
    isset($_POST['employeeName']) &&
    !empty($_POST['action']) &&
    !empty($_POST['employeeName'])
) {
    $action = $_POST['action'];
    $employeeName = $_POST['employeeName'];

    date_default_timezone_set('Asia/Manila');
    $currentDateTime = date('Y-m-d H:i:s');
    $response = ""; // Initialize response variable

    $sqlEmployee = "SELECT id FROM employees WHERE name = '$employeeName'";
    $resultEmployee = mysqli_query($connection, $sqlEmployee);

    if (!$resultEmployee) {
        $response = "Error: " . mysqli_error($connection);
    } else {
        if (mysqli_num_rows($resultEmployee) > 0) {
            $rowEmployee = mysqli_fetch_assoc($resultEmployee);
            $employeeId = $rowEmployee['id'];

            // Check if it's within the normal working hours
            $morningStart = date('Y-m-d') . " 01:00:00";
            $morningEnd = date('Y-m-d') . " 12:00:00";
            $afternoonStart = date('Y-m-d') . " 13:00:00";
            $afternoonEnd = date('Y-m-d') . " 17:00:00";
            $overtimeStart = date('Y-m-d') . " 17:00:00";
            $overtimeEnd = date('Y-m-d') . " 24:00:00";

            $currentTime = strtotime($currentDateTime);

            // Check if employee has a morning entry
            $sqlCheckMorning = "SELECT * FROM attendance WHERE name = '$employeeName' AND DATE(morning_time_in) = CURDATE()";
            $resultCheckMorning = mysqli_query($connection, $sqlCheckMorning);

            if (!$resultCheckMorning) {
                $response = "Error: " . mysqli_error($connection);
            } else {
                if (mysqli_num_rows($resultCheckMorning) > 0) {
                    $rowMorning = mysqli_fetch_assoc($resultCheckMorning);
                    $morningId = $rowMorning['id'];

                    // Update existing morning entry
                    if ($currentTime >= strtotime($afternoonStart) && $currentTime < strtotime($overtimeStart)) {
                        // Employee is within afternoon shift
                        if ($action === 'Time In') {
                            $status = 'Time In';
                            $sqlUpdateAfternoon = "UPDATE attendance SET afternoon_time_in = '$currentDateTime', status = '$status' WHERE id = $morningId";

                            if (mysqli_query($connection, $sqlUpdateAfternoon)) {
                                $response = "TIME - IN (Afternoon) " . date('g:i A', strtotime($currentDateTime)) . " ✔️ ";
                            } else {
                                $response = "Error updating afternoon entry: " . mysqli_error($connection);
                            }
                        } else if ($action === 'Time Out') {
                            $status = 'Time Out';
                            $sqlUpdateAfternoon = "UPDATE attendance SET afternoon_time_out = '$currentDateTime', afternoon_total_hours = TIMESTAMPDIFF(SECOND, afternoon_time_in, '$currentDateTime') / 3600, status = '$status' WHERE id = $morningId";

                            if (mysqli_query($connection, $sqlUpdateAfternoon)) {
                                $response = "TIME - OUT (Afternoon) " . date('g:i A', strtotime($currentDateTime)) . " ✔️ ";
                            } else {
                                $response = "Error updating afternoon entry: " . mysqli_error($connection);
                            }
                        }
                    } else if ($currentTime >= strtotime($overtimeStart) && $currentTime <= strtotime($overtimeEnd)) {
                        // Employee is within overtime shift
                        if ($action === 'Time In') {
                            $status = 'Overtime In';
                            $sqlUpdateOvertime = "UPDATE attendance SET overtime_time_in = '$currentDateTime', status = '$status' WHERE id = $morningId";

                            if (mysqli_query($connection, $sqlUpdateOvertime)) {
                                $response = "OVERTIME - IN " . date('g:i A', strtotime($currentDateTime)) . " ✔️ ";
                            } else {
                                $response = "Error updating overtime entry: " . mysqli_error($connection);
                            }
                        } else if ($action === 'Time Out') {
                            $status = 'Overtime Out';
                            $sqlUpdateOvertime = "UPDATE attendance SET overtime_time_out = '$currentDateTime', overtime_total_hours = TIMESTAMPDIFF(SECOND, overtime_time_in, '$currentDateTime') / 3600, status = '$status' WHERE id = $morningId";

                            if (mysqli_query($connection, $sqlUpdateOvertime)) {
                                $response = "OVERTIME - OUT " . date('g:i A', strtotime($currentDateTime)) . " ✔️ ";
                            } else {
                                $response = "Error updating overtime entry: " . mysqli_error($connection);
                            }
                        }
                    }
                } else {
                    // No morning entry, create new morning entry
                    if ($currentTime >= strtotime($morningStart) && $currentTime <= strtotime($morningEnd)) {
                        // Employee is within morning shift
                        if ($action === 'Time In') {
                            $status = 'Time In';
                            $sqlInsertMorning = "INSERT INTO attendance (name, morning_time_in, status) VALUES ('$employeeName', '$currentDateTime', '$status')";

                            if (mysqli_query($connection, $sqlInsertMorning)) {
                                $response = "TIME - IN (Morning) " . date('g:i A', strtotime($currentDateTime)) . " ✔️ ";
                            } else {
                                $response = "Error inserting morning entry: " . mysqli_error($connection);
                            }
                        } else {
                            $response = "Cannot Time Out. No corresponding Time In.";
                        }
                    } else {
                        $response = "Can't Overtime without Morning and Afternoon working Hours.";
                    }
                }
            }
        } else {
            $response = "Employee not found.";
        }
    }

    echo $response;
} else if (isset($_POST['action']) && $_POST['action'] === 'getAttendance') {
    $sql = "SELECT *, 
            DATE_FORMAT(morning_time_in, '%h:%i %p') AS formatted_morning_time_in,
            DATE_FORMAT(morning_time_out, '%h:%i %p') AS formatted_morning_time_out,
            DATE_FORMAT(afternoon_time_in, '%h:%i %p') AS formatted_afternoon_time_in,
            DATE_FORMAT(afternoon_time_out, '%h:%i %p') AS formatted_afternoon_time_out,
            DATE_FORMAT(overtime_time_in, '%h:%i %p') AS formatted_overtime_time_in,
            DATE_FORMAT(overtime_time_out, '%h:%i %p') AS formatted_overtime_time_out,
            TIMESTAMPDIFF(SECOND, morning_time_in, morning_time_out) AS morning_total_seconds,
            TIMESTAMPDIFF(SECOND, afternoon_time_in, afternoon_time_out) AS afternoon_total_seconds,
            (TIMESTAMPDIFF(SECOND, morning_time_in, morning_time_out) + TIMESTAMPDIFF(SECOND, afternoon_time_in, afternoon_time_out)) AS overall_total_seconds,
            TIMESTAMPDIFF(SECOND, overtime_time_in, overtime_time_out) AS overtime_total_seconds
            FROM attendance";

$result = mysqli_query($connection, $sql);

if (!$result) {
    die("Query failed: " . mysqli_error($connection));
}

$attendanceData = array(); // Initialize an empty array

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Convert the 24-hour format times to 12-hour format
        $formattedMorningIn = date('h:i A', strtotime($row['morning_time_in']));
        $formattedMorningOut = date('h:i A', strtotime($row['morning_time_out']));
        $formattedAfternoonIn = date('h:i A', strtotime($row['afternoon_time_in']));
        $formattedAfternoonOut = date('h:i A', strtotime($row['afternoon_time_out']));
        $formattedOTIn = date('h:i A', strtotime($row['overtime_time_in']));
        $formattedOTOut = date('h:i A', strtotime($row['overtime_time_out']));
        
        // Calculate morning total hours
        $morningTotalSeconds = $row['morning_total_seconds'];
        $morningTotalHours = floor($morningTotalSeconds / 3600); // 3600 seconds in an hour
        $morningTotalMinutes = floor(($morningTotalSeconds % 3600) / 60); // Remaining seconds converted to minutes

        // Correct the total hours if minutes are 60 or more
        if ($morningTotalMinutes >= 60) {
            $morningTotalHours += 1;
            $morningTotalMinutes -= 60;
        }

        // Format the total hours and minutes
        $formattedMorningTotalHours = sprintf('%02d', $morningTotalHours);
        $formattedMorningTotalMinutes = sprintf('%02d', $morningTotalMinutes);
        $formattedMorningTotal = $formattedMorningTotalHours . ':' . $formattedMorningTotalMinutes;

        // Add the formatted morning total to the row
        $row['formatted_morning_total'] = $formattedMorningTotal;

        // Calculate AFTERNOON total hours
        $afternoonTotalSeconds = $row['afternoon_total_seconds'];
        $afternoonTotalHours = floor($afternoonTotalSeconds / 3600); // 3600 seconds in an hour
        $afternoonTotalMinutes = floor(($afternoonTotalSeconds % 3600) / 60); // Remaining seconds converted to minutes

        // Correct the total hours if minutes are 60 or more
        if ($afternoonTotalMinutes >= 60) {
            $afternoonTotalHours += 1;
            $afternoonTotalMinutes -= 60;
        }

        // Format the total hours and minutes
        $formattedAfternoonTotalHours = sprintf('%02d', $afternoonTotalHours);
        $formattedAfternoonTotalMinutes = sprintf('%02d', $afternoonTotalMinutes);
        $formattedAfternoonTotal = $formattedAfternoonTotalHours . ':' . $formattedAfternoonTotalMinutes;

        // Add the formatted afternoon total to the row
        $row['formatted_afternoon_total'] = $formattedAfternoonTotal;

        // Calculate overtime total hours
        $overtimeTotalSeconds = $row['overtime_total_seconds'];
        $overtimeTotalHours = floor($overtimeTotalSeconds / 3600); // 3600 seconds in an hour
        $overtimeTotalMinutes = floor(($overtimeTotalSeconds % 3600) / 60); // Remaining seconds converted to minutes

        // Correct the total hours if minutes are 60 or more
        if ($overtimeTotalMinutes >= 60) {
            $overtimeTotalHours += 1;
            $overtimeTotalMinutes -= 60;
        }

        // Format the total hours and minutes
        $formattedOvertimeTotalHours = sprintf('%02d', $overtimeTotalHours);
        $formattedOvertimeTotalMinutes = sprintf('%02d', $overtimeTotalMinutes);
        $formattedOvertimeTotal = $formattedOvertimeTotalHours . ':' . $formattedOvertimeTotalMinutes;

        // Add the formatted overtime total to the row
        $row['formatted_overtime_total'] = $formattedOvertimeTotal;

        // Calculate overall total hours
        $overallTotalSeconds = $row['morning_total_seconds'] + $row['afternoon_total_seconds'];
        $overallTotalHours = floor($overallTotalSeconds / 3600); // Calculate overall total hours
        $overallTotalMinutes = floor(($overallTotalSeconds % 3600) / 60); // Remaining seconds converted to minutes

        // Correct the total hours if minutes are 60 or more
        if ($overallTotalMinutes >= 60) {
            $overallTotalHours += 1;
            $overallTotalMinutes -= 60;
        }

        // Format the total hours and minutes
        $formattedOverallTotalHours = sprintf('%02d', $overallTotalHours);
        $formattedOverallTotalMinutes = sprintf('%02d', $overallTotalMinutes);
        $formattedOverallTotal = $formattedOverallTotalHours . ':' . $formattedOverallTotalMinutes;

        // Add the formatted overall total to the row
        $row['formatted_overall_total'] = $formattedOverallTotal;

        $attendanceData[] = $row;
    }
}

echo json_encode($attendanceData); // Return the JSON encoded attendance data
} else {
$response = "Invalid request. Please provide action and employee name.";
echo $response;
}
?>
