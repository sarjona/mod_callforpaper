<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file is part of the Call for paper module for Moodle
 *
 * @copyright 2025 Sara Arjona <sara@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package mod_callforpaper
 */

use mod_callforpaper\manager;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/callforpaper/locallib.php');
require_once($CFG->libdir . '/rsslib.php');

$id = required_param('id', PARAM_INT);
$activedayid = optional_param('activeday', -1, PARAM_INT);
$headers = optional_param('headers', true, PARAM_BOOL);
$returnurl = optional_param('returnurl', null, PARAM_URL);

list($course, $cm) = get_course_and_cm_from_cmid($id, manager::MODULE);
$manager = manager::create_from_coursemodule($cm);

$recordsbyrooms = $manager->get_records_per_rooms();
$entriesbydate = $manager->get_entries_per_programdate();

$data = $manager->get_instance();
$context = $manager->get_context();

require_login($course, true, $cm);

$pageurl = new \core\url('/mod/callforpaper/program.php', ['id' => $cm->id]);
$titleparts = [
    format_string($data->name),
    format_string($course->fullname),
];

$PAGE->set_url($pageurl);
$PAGE->set_title(implode(moodle_page::TITLE_SEPARATOR, $titleparts));
$PAGE->set_heading($course->fullname);
$PAGE->force_settings_menu(true);

if (!$headers) {
    $PAGE->set_pagelayout('popup');
    if (!$returnurl) {
        $returnurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'javascript:history.go(-1)';
    }
    echo '<div class="callforpaper-program-header m-4">';
    echo '<a class="btn btn-outline-dark" href="' . $returnurl . '">' . get_string('back') . '</a>';
    echo '<h1 class="mt-4">' . format_string($data->name) . '</h1>';
    echo '</div>';
}

echo $OUTPUT->header();

// Rearrange entries by date and time, to display a navigation for days and slots.
$days = [];
$timeslots = [];
$allentries = [];
foreach ($entriesbydate as $starttime => $startentries) {
    $day = userdate($starttime, get_string('strftimedayshort', 'core_langconfig'), 0);
    $timeslotsbyday[$day][(int)$starttime] = true;
    foreach ($startentries as $endtime => $entries) {
        $timeslotsbyday[$day][(int)$endtime] = true;
        foreach($entries as $recordid => $entryfields) {
            $allentries[$recordid] = $entryfields;
            $days[$day][] = $recordid;
        }
    }
}

// Sort all time points chronologically (e.g. [9:00, 10:00, 10:30]).
$daykeys = array_keys($days);
if ($activedayid < 0 || $activedayid > count($daykeys)) {
    $currenttime = userdate(time(), get_string('strftimedayshort', 'core_langconfig'), 0);
    foreach ($daykeys as $key => $day) {
        if ($day === $currenttime) {
            $activedayid = $key;
            break;
        }
    }
    if ($activedayid < 0) {
        $activedayid = 0;
    }
}
if (!array_key_exists($activedayid, $daykeys)) {
    // Display a message and exit if there are no rooms (i.e. no fields created yet).
    $renderer = $manager->get_renderer();
    // SARATODO: Create a specific zero state for program when there are no entries scheduled.
    echo 'No program available yet.';
    echo $OUTPUT->footer();
    // Don't check the rest of the options. There is no field, there is nothing else to work with.
    exit;
}

$activeday = $daykeys[$activedayid];
$timeslots = array_keys($timeslotsbyday[$activeday]);
sort($timeslots);

echo $OUTPUT->box_start('', 'callforpaper-program');

$parser = $manager->get_template('slottemplate');
$canapprove = $manager->can_approve_entries();

// Days tab navigation.
echo '<div class="btn-group mb-3" role="group">';
$i = 0;
foreach($days as $day => $notused) {
    $isactive = ($i === $activedayid) ? ' active' : '';
    $programurl = new \core\url(
        '/mod/callforpaper/program.php',[
            'id' => $cm->id,
            'activeday' => $i++,
            'headers' => $headers,
            'returnurl' => $returnurl,
        ],
    );
    echo '<a class="btn btn-outline-primary' . $isactive . '" aria-current="page" href="' . $programurl->out(false). '">';
    echo $day . '</a>';
}
echo '</div>';

// Program schedule table by day.
echo '<table class="table text-center table-hover program-schedule">';
echo '<thead class="table-dark">';
// Table header row with room names.
echo '<tr>';
    echo '<th>&nbsp;</th>';
$sessionscurrentday = $days[$activeday];
$rooms = $manager->get_rooms();
foreach ($rooms as $room) {
    if (!array_key_exists($room, $recordsbyrooms)) {
        continue;
    }

    // Check that, at least, one entry is scheduled in this room for the active day.
    $roomhasentries = false;
    $recordsbyroom = $recordsbyrooms[$room];
    foreach ($recordsbyroom as $recordid => $record) {
        if (
            ($record->approved || $canapprove)
            && in_array($recordid, $sessionscurrentday)
        ) {
            $roomhasentries = true;
            break;
        }
    }
    if ($roomhasentries) {
        echo '<th>' . htmlspecialchars($room) . '</th>';
    } else {
        // Remove room from recordsbyrooms to avoid processing it in the body.
        unset($recordsbyrooms[$room]);
    }
}
echo '</tr>';
echo '</thead>';
// Table body with time slots and entries.
echo '<tbody>';
for ($i = 0; $i < count($timeslots) - 1; $i++) {
    $starttime = $timeslots[$i];
    $endtime = $timeslots[$i+1];
    // Safety check: skip any zero-duration slots.
    if ($starttime == $endtime) {
        continue;
    }

    // Get display-friendly times for the row header.
    $starttimedisplay = userdate($starttime, get_string('strftimetime24', 'core_langconfig'), 0);
    $endtimedisplay = userdate($endtime, get_string('strftimetime24', 'core_langconfig'), 0);
    // Create the row for this specific time slot.
    echo '<tr>';
    echo '<th>' . $starttimedisplay .  ' - '. $endtimedisplay . '</th>';
    foreach ($recordsbyrooms as $room => $recordsbyroom) {
        $entry = null;
        foreach ($recordsbyroom as $recordid => $record) {
            if (
                ($record->approved || $canapprove)
                && $manager->is_entry_scheduled($allentries[$recordid], $room, $starttime, $endtime)
            ) {
                $entry = $record;
                break;
            }
        }
        if (empty($entry)) {
            echo '<td>&nbsp;</td>';
        } else {
            $record = $recordsbyroom[$recordid];
            $notapproved = !$record->approved ? 'entry-not-approved' : '';
            echo '<td class="' . $notapproved . '">' . $parser->parse_entries([$recordid => $record]) . '</td>';
        }
    }
    echo '</tr>';
}
echo '</tbody>';
echo '</table>';

echo $OUTPUT->box_end();

echo $OUTPUT->footer();
