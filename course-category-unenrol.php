<?php

/**
 * Loop through all courses in a category and unenroll every student
 */

require_once 'lib/enrollib.php';
require_once 'enrol/locallib.php';

global $DB;

$category = -1;

require_capability('moodle:site/config', context_system::instance());

// Unenrol students in a course category
$courses = $DB->get_records('course', ['category' => $category]);

foreach($courses as $course) {
    $context = context_course::instance($course->id);
    $enrolinstances = enrol_get_instances($course->id, 1);
    
    foreach($enrolinstances as $instance) {
        $enrolplugin = enrol_get_plugin($instance->enrol);
        
        foreach(get_enrolled_users($context, '', 0, 'u.id') as $user) {
            foreach(get_user_roles($context, $user->id) as $role) {
                if($role->roleid == 5) {
                    $enrolplugin->unenrol_user($instance, $user->id);
                    echo 'unenrol ' . $user->id . ' from ' . $course->id . '<br/>';
                }
            }
        }
    }
}
