<?php

define('CLI_SCRIPT', true);

require_once '../../config.php';

global $DB;

$usage = 'Attempts to fix missing tags for quiz questions in quiz_slot_tags and question.name";

mtrace('Quiz tag fix starting...');

// Get all random question quiz slots and their corresponding question info
$quiz_slots = $DB->get_records_sql("
    SELECT 
        slot.id, question.id questionid, question.name questionname, qc.name questioncategory
    FROM {quiz_slots} slot
    JOIN {question} question ON question.id = slot.questionid
    JOIN {question_categories} qc ON qc.id = question.category
    WHERE question.qtype = 'random'
");

foreach($quiz_slots as $slot) {
    // Get tags from question name and slot_tags
    $slot_tags = $DB->get_records('quiz_slot_tags', ['slotid' => $slot->id]);

    // Check if question name has tags
    $start = strpos($slot->questionname, 'tags');

    // Has tags in question name
    if($start != 0) {
        // Has no slot tags, try to fix
        if(empty($slot_tags)) {
            $name_tags = explode(',', substr($slot->questionname, $start + 6, -1));
            
            // Iterate name tags
            foreach($name_tags as $name_tag) {
                // Find tag instance
                if($tag = $DB->get_record('tag', ['name' => $name_tag])) {
                    // Build new slot tag
                    $new_slot_tag = new stdClass();
                    $new_slot_tag->slotid = $slot->id;
                    $new_slot_tag->tagid = $tag->id;
                    $new_slot_tag->tagname = $tag->name;

                    // Add new slot tag
                    $new_slot_tag_id = $DB->insert_record('quiz_slot_tags', $new_slot_tag);
                }
            }
        }
    }  
    // Has no tags in question name
    else {
        // Has slot tags
        if(!empty($slot_tags)) {
            // Get full question record
            $question = $DB->get_record('question', ['id' => $slot->questionid]);

            // Turn slot tags to string for concatenation
            $slot_tags_str = implode(',', array_map(function($slot_tag) {
                return $slot_tag->tagname;
            }, $slot_tags));

            // Build updated name
            $updated_name = 'Random (' . trim($slot->questioncategory) . ', tags: ' . $slot_tags_str . ')';

            // Update question name
            $question->name = $updated_name;
            $DB->update_record('question', $question);
        }
    }
}

mtrace('Quiz tag fix finished.');

