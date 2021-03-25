<?php

define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');

global $DB;

$programs = [
        'ABA',
        'NU',
        'PBH',
        'SW',
        'HP'
];

echo 'Mid Course Evaluations </br> <hr>';

foreach($programs as $program) {
        // Get all mid course evaluation modules for the programs courses
        $sql = "SELECT DISTINCT cm.id
                FROM mdl_course_modules cm
                JOIN mdl_questionnaire q ON q.id = cm.instance
                JOIN mdl_course c ON c.id = q.course
                WHERE cm.module = 25 AND c.shortname LIKE '$program-%' AND q.name LIKE '%Mid Course%'";
        $cmids = implode(',', array_keys($DB->get_records_sql($sql)));

        // Get total number of enrolled students in all ABA courses that have a mid course evaluation
        $sql = "SELECT COUNT(*) total
                FROM mdl_role_assignments ra
                JOIN mdl_context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                JOIN mdl_user u ON u.id = ra.userid AND u.deleted = 0
                WHERE ra.roleid = 5 AND ctx.instanceid IN (
                        SELECT DISTINCT c.id
                        FROM mdl_course_modules cm
                        JOIN mdl_questionnaire q ON q.id = cm.instance
                        JOIN mdl_course c ON c.id = q.course
                        WHERE cm.module = 25 AND c.shortname LIKE '$program-%' AND q.name LIKE '%Mid Course%'
                )";
        $enrolledStudentCount = $DB->get_record_sql($sql)->total;

        // Get number of completed questionnaire modules for all program courses that match provided cmids
        $sql = "SELECT COUNT(*) total
                FROM mdl_course_modules_completion cmc
                WHERE coursemoduleid IN ($cmids) AND completionstate = 1";
        $completedQuestionnaireCount = $DB->get_record_sql($sql)->total;

        // Program Courses with a mid course evaluation
        $sql = "SELECT DISTINCT c.id
                FROM mdl_course_modules cm
                JOIN mdl_questionnaire q ON q.id = cm.instance
                JOIN mdl_course c ON c.id = q.course
                WHERE cm.module = 25 AND c.shortname LIKE '$program-%' AND q.name LIKE '%Mid Course%'";
        $programCourses = $DB->get_records_sql($sql);

        echo 'Total # of Students: ' . $enrolledStudentCount . '</br>';
        echo 'Total # of Completed Questionnaires: ' . $completedQuestionnaireCount . '</br>';
        echo 'Response Rate for ' . $program . ' Courses: ' . $completedQuestionnaireCount / $enrolledStudentCount . '</br>';
        echo 'List of Courses Checked: (' . count($programCourses) . ') </br>' . implode(',', array_keys($programCourses)) . '</br></br></br>';
}

echo 'End of Course Evaluations </br> <hr>';

foreach($programs as $program) {
        // Get all end of course evaluation modules for the programs courses
        $sql = "SELECT DISTINCT cm.id
                FROM mdl_course_modules cm
                JOIN mdl_questionnaire q ON q.id = cm.instance
                JOIN mdl_course c ON c.id = q.course
                WHERE cm.module = 25 AND c.shortname LIKE '$program-%' AND q.name LIKE '%End of Course%'";
        $cmids = implode(',', array_keys($DB->get_records_sql($sql)));

        // Get total number of enrolled students in all ABA courses that have a mid course evaluation
        $sql = "SELECT COUNT(*) total
                FROM mdl_role_assignments ra
                JOIN mdl_context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                JOIN mdl_user u ON u.id = ra.userid AND u.deleted = 0
                WHERE ra.roleid = 5 AND ctx.instanceid IN (
                        SELECT DISTINCT c.id
                        FROM mdl_course_modules cm
                        JOIN mdl_questionnaire q ON q.id = cm.instance
                        JOIN mdl_course c ON c.id = q.course
                        WHERE cm.module = 25 AND c.shortname LIKE '$program-%' AND q.name LIKE '%End of Course%'
                )";
        $enrolledStudentCount = $DB->get_record_sql($sql)->total;

        // Get number of completed questionnaire modules for all program courses that match provided cmids
        $sql = "SELECT COUNT(*) total
                FROM mdl_course_modules_completion cmc
                WHERE coursemoduleid IN ($cmids) AND completionstate = 1";
        $completedQuestionnaireCount = $DB->get_record_sql($sql)->total;

        // Program Courses with a mid course evaluation
        $sql = "SELECT DISTINCT c.id
                FROM mdl_course_modules cm
                JOIN mdl_questionnaire q ON q.id = cm.instance
                JOIN mdl_course c ON c.id = q.course
                WHERE cm.module = 25 AND c.shortname LIKE '$program-%' AND q.name LIKE '%End of Course%'";
        $programCourses = $DB->get_records_sql($sql);

        echo 'Total # of Students: ' . $enrolledStudentCount . '</br>';
        echo 'Total # of Completed Questionnaires: ' . $completedQuestionnaireCount . '</br>';
        echo 'Response Rate for ' . $program . ' Courses: ' . $completedQuestionnaireCount / $enrolledStudentCount . '</br>';
        echo 'List of Courses Checked: (' . count($programCourses) . ') </br>' . implode(',', array_keys($programCourses)) . '</br></br></br>';
}
