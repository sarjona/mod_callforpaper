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

use core\notification;
use mod_callforpaper\local\importer\preset_existing_importer;
use mod_callforpaper\local\importer\preset_importer;
use mod_callforpaper\local\importer\preset_upload_importer;
use mod_callforpaper\manager;

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->dirroot.'/mod/callforpaper/preset_form.php');

$id             = optional_param('id', 0, PARAM_INT);            // course module id
$d              = optional_param('d', 0, PARAM_INT);             // callforpaper id
$fid            = optional_param('fid', 0 , PARAM_INT);          // update field id
$newtype        = optional_param('newtype','',PARAM_ALPHA);      // type of the new field
$mode           = optional_param('mode','',PARAM_ALPHA);
$action         = optional_param('action', '', PARAM_ALPHA);
$fullname       = optional_param('fullname', '', PARAM_PATH);    // Directory the preset is in.
$defaultsort    = optional_param('defaultsort', 0, PARAM_INT);
$defaultsortdir = optional_param('defaultsortdir', 0, PARAM_INT);
$cancel         = optional_param('cancel', 0, PARAM_BOOL);

if ($cancel) {
    $mode = 'list';
}

$url = new moodle_url('/mod/callforpaper/field.php');
if ($fid !== 0) {
    $url->param('fid', $fid);
}
if ($newtype !== '') {
    $url->param('newtype', $newtype);
}
if ($mode !== '') {
    $url->param('mode', $mode);
}
if ($defaultsort !== 0) {
    $url->param('defaultsort', $defaultsort);
}
if ($defaultsortdir !== 0) {
    $url->param('defaultsortdir', $defaultsortdir);
}
if ($cancel !== 0) {
    $url->param('cancel', $cancel);
}
if ($action !== '') {
    $url->param('action', $action);
}

if ($id) {
    list($course, $cm) = get_course_and_cm_from_cmid($id, manager::MODULE);
    $manager = manager::create_from_coursemodule($cm);
    $url->param('id', $cm->id);
} else {   // We must have $d.
    $instance = $DB->get_record('callforpaper', ['id' => $d], '*', MUST_EXIST);
    $manager = manager::create_from_instance($instance);
    $cm = $manager->get_coursemodule();
    $course = get_course($cm->course);
    $url->param('d', $d);
}

$PAGE->set_url($url);
$callforpaper = $manager->get_instance();
$context = $manager->get_context();

require_login($course, true, $cm);
require_capability('mod/callforpaper:managetemplates', $context);

$actionbar = new \mod_callforpaper\output\action_bar($callforpaper->id, $PAGE->url);

$PAGE->add_body_class('limitedwidth');
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();

// Fill in missing properties needed for updating of instance.
$callforpaper->course     = $cm->course;
$callforpaper->cmidnumber = $cm->idnumber;
$callforpaper->instance   = $cm->instance;

/************************************
 *        Call for paper Processing           *
 ***********************************/
$renderer = $manager->get_renderer();

if ($action == 'finishimport' && confirm_sesskey()) {
    $overwritesettings = optional_param('overwritesettings', false, PARAM_BOOL);
    $importer = preset_importer::create_from_parameters($manager);
    $importer->finish_import_process($overwritesettings, $callforpaper);
}

switch ($mode) {

    case 'add':    ///add a new field
        if (confirm_sesskey() and $fieldinput = data_submitted()){

            //$fieldinput->name = callforpaper_clean_field_name($fieldinput->name);

        /// Only store this new field if it doesn't already exist.
            if (($fieldinput->name == '') or callforpaper_fieldname_exists($fieldinput->name, $callforpaper->id)) {

                $displaynoticebad = get_string('invalidfieldname','callforpaper');

            } else {

            /// Check for arrays and convert to a comma-delimited string
                callforpaper_convert_arrays_to_strings($fieldinput);

            /// Create a field object to collect and store the callforpaper safely
                $type = required_param('type', PARAM_FILE);
                $field = callforpaper_get_field_new($type, $callforpaper);

                if (!empty($validationerrors = $field->validate($fieldinput))) {
                    $displaynoticebad = html_writer::alist($validationerrors);
                    $mode = 'new';
                    $newtype = $type;
                    break;
                }

                $field->define_field($fieldinput);
                $field->insert_field();

            /// Update some templates
                callforpaper_append_new_field_to_templates($callforpaper, $fieldinput->name);

                $displaynoticegood = get_string('fieldadded','callforpaper');
            }
        }
        break;


    case 'update':    ///update a field
        if (confirm_sesskey() and $fieldinput = data_submitted()){

            //$fieldinput->name = callforpaper_clean_field_name($fieldinput->name);

            if (($fieldinput->name == '') or callforpaper_fieldname_exists($fieldinput->name, $callforpaper->id, $fieldinput->fid)) {

                $displaynoticebad = get_string('invalidfieldname','callforpaper');

            } else {
            /// Check for arrays and convert to a comma-delimited string
                callforpaper_convert_arrays_to_strings($fieldinput);

            /// Create a field object to collect and store the callforpaper safely
                $field = callforpaper_get_field_from_id($fid, $callforpaper);
                if (!empty($validationerrors = $field->validate($fieldinput))) {
                    $displaynoticebad = html_writer::alist($validationerrors);
                    $mode = 'display';
                    break;
                }
                $oldfieldname = $field->field->name;

                $field->field->name = trim($fieldinput->name);
                $field->field->description = trim($fieldinput->description);
                $field->field->required = !empty($fieldinput->required) ? 1 : 0;
                $field->field->hidden = !empty($fieldinput->hidden) ? 1 : 0;

                for ($i=1; $i<=10; $i++) {
                    if (isset($fieldinput->{'param'.$i})) {
                        $field->field->{'param'.$i} = trim($fieldinput->{'param'.$i});
                    } else {
                        $field->field->{'param'.$i} = '';
                    }
                }

                $field->update_field();

            /// Update the templates.
                callforpaper_replace_field_in_templates($callforpaper, $oldfieldname, $field->field->name);

                $displaynoticegood = get_string('fieldupdated','callforpaper');
            }
        }
        break;


    case 'delete':    // Delete a field
        if (confirm_sesskey()){

            if ($confirm = optional_param('confirm', 0, PARAM_INT)) {


                // Delete the field completely
                if ($field = callforpaper_get_field_from_id($fid, $callforpaper)) {
                    $field->delete_field();

                    // Update the templates.
                    callforpaper_replace_field_in_templates($callforpaper, $field->field->name, '');

                    // Update the default sort field
                    if ($fid == $callforpaper->defaultsort) {
                        $rec = new stdClass();
                        $rec->id = $callforpaper->id;
                        $rec->defaultsort = 0;
                        $rec->defaultsortdir = 0;
                        $DB->update_record('callforpaper', $rec);
                    }

                    $displaynoticegood = get_string('fielddeleted', 'callforpaper');
                }

            } else {
                $titleparts = [
                    get_string('deletefield', 'callforpaper'),
                    format_string($callforpaper->name),
                    format_string($course->fullname),
                ];
                $PAGE->set_title(implode(moodle_page::TITLE_SEPARATOR, $titleparts));
                callforpaper_print_header($course,$cm,$callforpaper, false);
                echo $OUTPUT->heading(get_string('deletefield', 'callforpaper'), 2, 'mb-4');

                // Print confirmation message.
                $field = callforpaper_get_field_from_id($fid, $callforpaper);

                if ($field->type === 'unknown') {
                    $fieldtypename = get_string('unknown', 'callforpaper');
                } else {
                    $fieldtypename = $field->name();
                }
                echo $OUTPUT->confirm('<strong>' . $fieldtypename . ': ' . s($field->field->name) . '</strong><br /><br />' .
                        get_string('confirmdeletefield', 'callforpaper'),
                        'field.php?d=' . $callforpaper->id . '&mode=delete&fid=' . $fid . '&confirm=1',
                        'field.php?d=' . $callforpaper->id,
                        ['type' => single_button::BUTTON_DANGER]);

                echo $OUTPUT->footer();
                exit;
            }
        }
        break;


    case 'sort':    // Set the default sort parameters
        if (confirm_sesskey()) {
            $rec = new stdClass();
            $rec->id = $callforpaper->id;
            $rec->defaultsort = $defaultsort;
            $rec->defaultsortdir = $defaultsortdir;

            $DB->update_record('callforpaper', $rec);
            redirect($CFG->wwwroot.'/mod/callforpaper/field.php?d='.$callforpaper->id, get_string('changessaved'), 2);
            exit;
        }
        break;

    case 'usepreset':
        $importer = preset_importer::create_from_parameters($manager);
        if (!$importer->needs_mapping() || $action == 'notmapping') {
            $backurl = new moodle_url('/mod/callforpaper/field.php', ['id' => $cm->id]);
            if ($importer->import(false)) {
                notification::success(get_string('importsuccess', 'mod_callforpaper'));
            } else {
                notification::error(get_string('cannotapplypreset', 'mod_callforpaper'));
            }
            redirect($backurl);
        }
        $PAGE->navbar->add(get_string('usestandard', 'callforpaper'));
        $fieldactionbar = $actionbar->get_fields_mapping_action_bar();
        callforpaper_print_header($course, $cm, $callforpaper, false, $fieldactionbar);
        $importer = new preset_existing_importer($manager, $fullname);
        echo $renderer->importing_preset($callforpaper, $importer);
        echo $OUTPUT->footer();
        exit;

    default:
        break;
}



/// Print the browsing interface

///get the list of possible fields (plugins)
$plugins = core_component::get_plugin_list('callforpaperfield');
$menufield = array();

foreach ($plugins as $plugin=>$fulldir){
    if (!is_dir($fulldir)) {
        continue;
    }
    $menufield[$plugin] = get_string('pluginname', 'callforpaperfield_'.$plugin);    //get from language files
}
asort($menufield);    //sort in alphabetical order
$PAGE->force_settings_menu(true);

$PAGE->set_pagetype('mod-callforpaper-field-' . $newtype);
$titleparts = [
    format_string($callforpaper->name),
    format_string($course->fullname),
];
if (($mode == 'new') && (!empty($newtype))) { // Adding a new field.
    array_unshift($titleparts, get_string('newfield', 'callforpaper'));
    $PAGE->set_title(implode(moodle_page::TITLE_SEPARATOR, $titleparts));
    callforpaper_print_header($course, $cm, $callforpaper, 'fields');
    echo $OUTPUT->heading(get_string('newfield', 'callforpaper'));

    $field = callforpaper_get_field_new($newtype, $callforpaper);
    $field->display_edit_field();

} else if ($mode == 'display' && confirm_sesskey()) { /// Display/edit existing field
    array_unshift($titleparts, get_string('editfield', 'callforpaper'));
    $PAGE->set_title(implode(moodle_page::TITLE_SEPARATOR, $titleparts));
    callforpaper_print_header($course, $cm, $callforpaper, 'fields');
    echo $OUTPUT->heading(get_string('editfield', 'callforpaper'));

    $field = callforpaper_get_field_from_id($fid, $callforpaper);
    $field->display_edit_field();

} else {                                              /// Display the main listing of all fields
    array_unshift($titleparts, get_string('managefields', 'callforpaper'));
    $PAGE->set_title(implode(moodle_page::TITLE_SEPARATOR, $titleparts));
    $hasfields = $manager->has_fields();
    // Check if it is an empty callforpaper with no fields.
    if (!$hasfields) {
        echo $OUTPUT->header();
        echo $renderer->render_fields_zero_state($manager);
        echo $OUTPUT->footer();
        // Don't check the rest of the options. There is no field, there is nothing else to work with.
        exit;
    }
    $fieldactionbar = $actionbar->get_fields_action_bar(true);
    callforpaper_print_header($course, $cm, $callforpaper, 'fields', $fieldactionbar);

    echo $OUTPUT->box_start();
    echo get_string('fieldshelp', 'callforpaper');
    echo $OUTPUT->box_end();
    echo $OUTPUT->box_start('d-flex flex-row-reverse');
    echo $OUTPUT->render($actionbar->get_create_fields(true));
    echo $OUTPUT->box_end();
    $table = new html_table();
    $table->head = [
        get_string('fieldname', 'callforpaper'),
        get_string('type', 'callforpaper'),
        get_string('required', 'callforpaper'),
        get_string('fielddescription', 'callforpaper'),
        '&nbsp;',
    ];
    $table->align = ['left', 'left', 'left', 'left'];
    $table->wrap = [false,false,false,false];
    $table->responsive = false;

    $fieldrecords = $manager->get_field_records();
    $missingfieldtypes = [];
    foreach ($fieldrecords as $fieldrecord) {

        $field = callforpaper_get_field($fieldrecord, $callforpaper);

        $baseurl = new moodle_url('/mod/callforpaper/field.php', array(
            'd'         => $callforpaper->id,
            'fid'       => $field->field->id,
            'sesskey'   => sesskey(),
        ));

        $displayurl = new moodle_url($baseurl, array(
            'mode'      => 'display',
        ));

        $deleteurl = new moodle_url($baseurl, array(
            'mode'      => 'delete',
        ));

        $actionmenu = new action_menu();
        $actionmenu->set_kebab_trigger();
        $actionmenu->set_action_label(get_string('actions'));
        $actionmenu->set_additional_classes('fields-actions');

        // It display a notification when the field type does not exist.
        if ($field->type === 'unknown') {
            $missingfieldtypes[] = $field->field->name;
            $fieltypecallforpaper = $field->field->type;
        } else {
            $fieltypecallforpaper = $field->image() . '&nbsp;' . $field->name();
            // Edit icon, only displayed when the field type is known.
            $actionmenu->add(new action_menu_link_secondary(
                $displayurl,
                null,
                get_string('edit'),
            ));
        }

        // Delete.
        $actionmenu->add(new action_menu_link_secondary(
            $deleteurl,
            null,
            get_string('delete'),
        ));
        $actionmenutemplate = $actionmenu->export_for_template($OUTPUT);

        $table->data[] = [
            s($field->field->name),
            $fieltypecallforpaper,
            $field->field->required ? get_string('yes') : get_string('no'),
            shorten_text($field->field->description, 30),
            $OUTPUT->render_from_template('core/action_menu', $actionmenutemplate)
        ];

    }
    if (!empty($missingfieldtypes)) {
        echo $OUTPUT->notification(get_string('missingfieldtypes', 'callforpaper') . html_writer::alist($missingfieldtypes));
    }
    echo html_writer::table($table);

    echo '<div class="sortdefault">';
    echo '<form id="sortdefault" action="'.$CFG->wwwroot.'/mod/callforpaper/field.php" method="get">';
    echo '<div class="d-flex flex-column flex-md-row flex-wrap align-items-md-center gap-2">';
    echo '<input type="hidden" name="d" value="'.$callforpaper->id.'" />';
    echo '<input type="hidden" name="mode" value="sort" />';
    echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
    echo '<label for="defaultsort">'.get_string('defaultsortfield','callforpaper').'</label>';
    echo '<select id="defaultsort" name="defaultsort" class="form-select">';
    if ($fields = $DB->get_records('callforpaper_fields', array('callforpaperid'=>$callforpaper->id))) {
        echo '<optgroup label="'.get_string('fields', 'callforpaper').'">';
        foreach ($fields as $field) {
            if ($callforpaper->defaultsort == $field->id) {
                echo '<option value="'.$field->id.'" selected="selected">'.s($field->name).'</option>';
            } else {
                echo '<option value="'.$field->id.'">'.s($field->name).'</option>';
            }
        }
        echo '</optgroup>';
    }
    $options = array();
    $options[CALLFORPAPER_TIMEADDED]    = get_string('timeadded', 'callforpaper');
// TODO: we will need to change defaultsort db to unsinged to make these work in 2.0
/*        $options[CALLFORPAPER_TIMEMODIFIED] = get_string('timemodified', 'callforpaper');
    $options[CALLFORPAPER_FIRSTNAME]    = get_string('authorfirstname', 'callforpaper');
    $options[CALLFORPAPER_LASTNAME]     = get_string('authorlastname', 'callforpaper');
    if ($callforpaper->approval and has_capability('mod/callforpaper:approve', $context)) {
        $options[CALLFORPAPER_APPROVED] = get_string('approved', 'callforpaper');
    }*/
    echo '<optgroup label="'.get_string('other', 'callforpaper').'">';
    foreach ($options as $key => $name) {
        if ($callforpaper->defaultsort == $key) {
            echo '<option value="'.$key.'" selected="selected">'.$name.'</option>';
        } else {
            echo '<option value="'.$key.'">'.$name.'</option>';
        }
    }
    echo '</optgroup>';
    echo '</select>';

    $options = array(0 => get_string('ascending', 'callforpaper'),
                     1 => get_string('descending', 'callforpaper'));
    echo html_writer::label(get_string('sortby'), 'menudefaultsortdir', false, array('class' => 'accesshide'));
    echo html_writer::select($options, 'defaultsortdir', $callforpaper->defaultsortdir, false, ['class' => 'form-select']);
    echo '<input type="submit" class="btn btn-secondary" value="'.get_string('save', 'callforpaper').'" />';
    echo '</div>';
    echo '</form>';

    echo '</div>';
}

/// Finish the page
echo $OUTPUT->footer();
