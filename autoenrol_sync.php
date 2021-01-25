<?php

public function get_outofsync_records() {
        global $DB;

        // Get list of active course shortnames
        // Active course is defined as any course that has not yet passed its enddate
        $active_courses = $DB->get_records_select('course', 'enddate <= now()', null, '', 'shortname, id');

        // Iterate over newfile and build an array of all users indexed by course
        $users_by_course = [];
        foreach($this->newfile as $row) {
            // Skip if record not for active course
            if(!in_array($row[5], array_keys($active_courses))) {
                continue;
            }

            // Add user to course index with role
            $users_by_course [$row[5]] [] = [
                'username' => $row[0],
                'role' => $row[6]
            ];
        }

        // Get list of all user enrolments (teachers and students) for active courses
        $enroled_user_sql =
           "SELECT DISTINCT u.id, u.username, c.shortname, ra.roleid, r.shortname role
            FROM {user_enrolments} ue
            JOIN {user} u ON u.id = ue.userid
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {course} c ON c.id = e.courseid
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            WHERE c.enddate <= NOW() AND ra.roleid IN (4,5)";

        $enroled_users = $DB->get_recordset_sql($enroled_user_sql);

        // Create array of enroled users indexed by course to match array from file
        $enroled_users_by_course = [];
        foreach($enroled_users as $record) {
            $enroled_users_by_course [$record->shortname] [] = [
                'username' => $record->username,
                'role' => $record->role
            ];
        }

        // Iterate over all active courses and compare enrolments to file records
        $to_be_unenroled_users = [];
        $to_be_enroled_users = [];
        foreach($active_courses as $course) {
            // Dont process courses with no records in the file
            if(!isset($users_by_course[$course->shortname]) || empty($users_by_course[$course->shortname])) {
                continue;
            }

            // Get users that need to be enrolled in the course
            $to_be_enroled_users [$course->shortname] [] = $this->diff_arrays($users_by_course[$course->shortname], $enroled_users_by_course[$course->shortname]);

            // Get users that need to be unenrolled from the course
            $to_be_unenroled_users [$course->shortname] [] = $this->diff_arrays($enroled_users_by_course[$course->shortname], $users_by_course[$course->shortname]);
        }

        return [
            'enrol' => $to_be_enroled_users,
            'unenrol' => $to_be_unenroled_users
        ];
    }

/// test.php output CLI

<?php

//define('CLI_SCRIPT', true);

require_once '../../config.php';
require_once 'locallib.php';

$processor = \local_autoenrol\processor::load();
$data = $processor->get_outofsync_records();

$csvdata = [];

foreach($data['enrol'] as $course => $record) {
    if(!empty($record[0])) {
        foreach($record[0] as $line) {
            $csvdata [] = [$line['username'], $course, $line['role'], 'enrol'];
        }
    }
}

foreach($data['unenrol'] as $course => $record) {
    if(!empty($record[0])) {
        foreach($record[0] as $line) {
            $csvdata [] = [$line['username'], $course, $line['role'], 'unenrol'];
        }
    }
}

// output headers so that the file is downloaded rather than displayed
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=data.csv');

// create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// output the column headings
fputcsv($output, array('username', 'course', 'role', 'action'));

foreach($csvdata as $row) {
    fputcsv($output, $row);
}
