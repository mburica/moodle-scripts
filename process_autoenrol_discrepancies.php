<?php

define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');

/**
* EDIT HERE
*/
$category = 54;
/**
* STOP EDITING
*/

// Get list of active course shortnames for category = 53
// Active course is defined as any course that has not yet passed its enddate
$active_courses = $DB->get_records_sql("SELECT shortname, id FROM {course} WHERE enddate <= now() and category = $category");

// Get old and new file from file system
$fs = get_file_storage();
$files = $fs->get_area_files(\context_system::instance()->id, 'local_autoenrol', 'files', 0, 'timecreated desc');
$recentfiles = array_slice($files, 0, 2);

$newfile = array_shift($recentfiles);
$file = new \SplFileObject($newfile->copy_content_to_temp());
$file->setFlags(\SplFileObject::READ_CSV);

// Iterate over newfile and build an array of all users indexed by course
$users_by_course = [];
foreach($file as $row) {
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
    WHERE c.enddate <= NOW() AND ra.roleid IN (?,?)";

$teacherroleid = $DB->get_record('role', ['shortname' => 'teacher'])->id;
$studentroleid = $DB->get_record('role', ['shortname' => 'student'])->id;

$enroled_users = $DB->get_recordset_sql($enroled_user_sql, [$teacherroleid, $studentroleid]);

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
    //$to_be_enroled_users [$course->shortname] [] = $this->diff_arrays($users_by_course[$course->shortname], $enroled_users_by_course[$course->shortname]);
    $to_be_enroled_users [$course->shortname] [] = array_map('unserialize', array_diff(array_map('serialize', $users_by_course[$course->shortname]), array_map('serialize', $enroled_users_by_course[$course->shortname])));

    // Get users that need to be unenrolled from the course
    //$to_be_unenroled_users [$course->shortname] [] = $this->diff_arrays($enroled_users_by_course[$course->shortname], $users_by_course[$course->shortname]);
    $to_be_unenroled_users [$course->shortname] [] = array_map('unserialize', array_diff(array_map('serialize', $enroled_users_by_course[$course->shortname]), array_map('serialize', $users_by_course[$course->shortname])));
}

echo '----ENROLL----' . PHP_EOL;

// Loop through and enrol the necessary users
foreach($to_be_enroled_users as $shortname => $users) {
    $course = $DB->get_record('course', ['shortname' => $shortname]);

    foreach($users [0] as $userinfo) {
        $user = $DB->get_record('user', ['username' => $userinfo['username']]);

        // Get info needed to proceed with unenrolment
        $context = \context_course::instance($course->id);

        // Check that user is not already enroled
        if(!is_enrolled($context, $user) && !empty($user->id)) {
            if($userinfo['role'] == 'teacher') {
                $roleid = $teacherroleid;
            }

            if($userinfo['role'] == 'student') {
                $roleid = $studentroleid;
            }

            // Enrol user in the course
            /**
            * EDIT HERE
            */
            //enrol_try_internal_enrol($course->id, $user->id, $roleid);    // RITO UNCOMMENT THIS LINE TO PERFORM ENROLS
            /**
            * STOP EDITING
            */
            echo $course->shortname . ' - ' . $user->username . ' - ' . $roleid . PHP_EOL;
        }
    }
}

echo '----UNENROLL----' . PHP_EOL;

// Loop through and unenrol the necessary users
foreach($to_be_unenroled_users as $shortname => $users) {
    $course = $DB->get_record('course', ['shortname' => $shortname]);

    foreach($users [0] as $userinfo) {
        $user = $DB->get_record('user', ['username' => $userinfo['username']]);

        // Get info needed to proceed with unenrolment
        $context = \context_course::instance($course->id);
        $enroled = is_enrolled($context, $user);

        if(!$enroled) {
            return;
        }

        // Get the enrol instances
        $enrolinstances = enrol_get_instances($course->id, 1);

        foreach($enrolinstances as $instance) {
            // Get the enrol plugin
            $enrolplugin = enrol_get_plugin($instance->enrol);

            // Unenrol the user
            /**
            * EDIT HERE
            */
            //$enrolplugin->unenrol_user($instance, $user->id);  // RITO UNCOMMENT THIS LINE TO PERFORM UNENROLLS
            /**
            * STOP EDITING
            */
            echo $course->shortname . ' - ' . $user->username . PHP_EOL;
        }
    }
}
