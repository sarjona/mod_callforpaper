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
 * @copyright 2005 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package mod_callforpaper
 */

use mod_callforpaper\manager;

require_once('../../config.php');
require_once('lib.php');

$id    = optional_param('id', 0, PARAM_INT);  // course module id
$d     = optional_param('d', 0, PARAM_INT);   // callforpaper id
$mode  = optional_param('mode', 'addtemplate', PARAM_ALPHA);
$action  = optional_param('action', '', PARAM_ALPHA);
$useeditor = optional_param('useeditor', null, PARAM_BOOL);

$url = new moodle_url('/mod/callforpaper/templates.php');

if ($id) {
    list($course, $cm) = get_course_and_cm_from_cmid($id, manager::MODULE);
    $manager = manager::create_from_coursemodule($cm);
    $url->param('d', $cm->instance);
} else {   // We must have $d.
    $instance = $DB->get_record('callforpaper', ['id' => $d], '*', MUST_EXIST);
    $manager = manager::create_from_instance($instance);
    $cm = $manager->get_coursemodule();
    $course = get_course($cm->course);
    $url->param('d', $d);
}

$instance = $manager->get_instance();
$context = $manager->get_context();

$url->param('mode', $mode);
$PAGE->set_url($url);

require_login($course, false, $cm);
require_capability('mod/callforpaper:managetemplates', $context);

if ($action == 'resetalltemplates') {
    require_sesskey();
    $manager->reset_all_templates();
    redirect($PAGE->url, get_string('templateresetall', 'mod_callforpaper'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$manager->set_template_viewed();

if ($useeditor !== null) {
    // The useeditor param was set. Update the value for this template.
    callforpaper_set_config($instance, "editor_{$mode}", !!$useeditor);
}

$PAGE->requires->js('/mod/callforpaper/callforpaper.js');
switch ($mode) {
    case 'asearchtemplate':
        $title = get_string('asearchtemplate', 'callforpaper');
        break;
    case 'csstemplate':
        $title = get_string('csstemplate', 'callforpaper');
        break;
    case 'jstemplate':
        $title = get_string('jstemplate', 'callforpaper');
        break;
    case 'listtemplate':
        $title = get_string('listtemplate', 'callforpaper');
        break;
    case 'rsstemplate':
        $title = get_string('rsstemplate', 'callforpaper');
        break;
    case 'singletemplate':
        $title = get_string('singletemplate', 'callforpaper');
        break;
    case 'reviewerlisttemplate':
        $title = get_string('reviewerlisttemplate', 'callforpaper');
        break;
    case 'slottemplate':
        $title = get_string('slottemplate', 'callforpaper');
        break;
    default:
        if ($manager->has_fields()) {
            $title = get_string('addtemplate', 'callforpaper');
        } else {
            $title = get_string('callforpaper:managetemplates', 'callforpaper');
        }
        break;
}
$titleparts = [
    $title,
    format_string($instance->name),
    format_string($course->fullname),
];
$PAGE->set_title(implode(moodle_page::TITLE_SEPARATOR, $titleparts));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('admin');
$PAGE->force_settings_menu(true);
$PAGE->activityheader->disable();
$PAGE->add_body_class('limitedwidth');

echo $OUTPUT->header();

$renderer = $manager->get_renderer();
// Check if it is an empty callforpaper with no fields.
if (!$manager->has_fields()) {
    echo $renderer->render_templates_zero_state($manager);
    echo $OUTPUT->footer();
    // Don't check the rest of the options. There is no field, there is nothing else to work with.
    exit;
}

$actionbar = new \mod_callforpaper\output\action_bar($instance->id, $url);
echo $actionbar->get_templates_action_bar();

if (($formdata = data_submitted()) && confirm_sesskey()) {
    if (!empty($formdata->defaultform)) {
        // Reset the template to default.
        if (!empty($formdata->resetall)) {
            $manager->reset_all_templates();
            $notificationstr = get_string('templateresetall', 'mod_callforpaper');
        } else {
            $manager->reset_template($mode);
            $notificationstr = get_string('templatereset', 'callforpaper');
        }
    } else {
        $manager->update_templates($formdata);
        $notificationstr = get_string('templatesaved', 'callforpaper');
    }
}

if (!empty($notificationstr)) {
    echo $OUTPUT->notification($notificationstr, 'notifysuccess');
}

$templateeditor = new \mod_callforpaper\output\template_editor($manager, $mode);
echo $renderer->render($templateeditor);

/// Finish the page
echo $OUTPUT->footer();
