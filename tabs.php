<?php
///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.org                                            //
//                                                                       //
// Copyright (C) 2005 Martin Dougiamas  http://dougiamas.com             //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 2 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

// This file to be included so we can assume config.php has already been included.
// We also assume that $user, $course, $currenttab have been set


    if (empty($currenttab) or empty($callforpaper) or empty($course)) {
        throw new \moodle_exception('cannotcallscript');
    }

    $context = context_module::instance($cm->id);

    $row = array();

    $row[] = new tabobject('list', new moodle_url('/mod/callforpaper/view.php', array('d' => $callforpaper->id)), get_string('list','callforpaper'));

    if (isset($record)) {
        $row[] = new tabobject('single', new moodle_url('/mod/callforpaper/view.php', array('d' => $callforpaper->id, 'rid' => $record->id)), get_string('single','callforpaper'));
    } else {
        $row[] = new tabobject('single', new moodle_url('/mod/callforpaper/view.php', array('d' => $callforpaper->id, 'mode' => 'single')), get_string('single','callforpaper'));
    }

    // Add an advanced search tab.
    $row[] = new tabobject('asearch', new moodle_url('/mod/callforpaper/view.php', array('d' => $callforpaper->id, 'mode' => 'asearch')), get_string('search', 'callforpaper'));

    if (isloggedin()) { // just a perf shortcut
        if (callforpaper_user_can_add_entry($callforpaper, $currentgroup, $groupmode, $context)) { // took out participation list here!
            $addstring = empty($editentry) ? get_string('add', 'callforpaper') : get_string('editentry', 'callforpaper');
            $row[] = new tabobject('add', new moodle_url('/mod/callforpaper/edit.php', array('d' => $callforpaper->id)), $addstring);
        }
        if (has_capability(CALLFORPAPER_CAP_EXPORT, $context)) {
            // The capability required to Export callforpaper records is centrally defined in 'lib.php'
            // and should be weaker than those required to edit Templates, Fields and Presets.
            $row[] = new tabobject('export', new moodle_url('/mod/callforpaper/export.php', array('d' => $callforpaper->id)),
                         get_string('export', 'callforpaper'));
        }
        if (has_capability('mod/callforpaper:managetemplates', $context)) {
            if ($currenttab == 'list') {
                $defaultemplate = 'listtemplate';
            } else if ($currenttab == 'add') {
                $defaultemplate = 'addtemplate';
            } else if ($currenttab == 'asearch') {
                $defaultemplate = 'asearchtemplate';
            } else {
                $defaultemplate = 'singletemplate';
            }

            $templatestab = new tabobject('templates', new moodle_url('/mod/callforpaper/templates.php', array('d' => $callforpaper->id, 'mode' => $defaultemplate)),
                         get_string('templates','callforpaper'));
            $row[] = $templatestab;
            $row[] = new tabobject('fields', new moodle_url('/mod/callforpaper/field.php', array('d' => $callforpaper->id)),
                         get_string('fields','callforpaper'));
            $row[] = new tabobject('presets', new moodle_url('/mod/callforpaper/preset.php', array('d' => $callforpaper->id)),
                         get_string('presets', 'callforpaper'));
        }
    }

    if ($currenttab == 'templates' and isset($mode) && isset($templatestab)) {
        $templatestab->inactive = true;
        $templatelist = array ('listtemplate', 'singletemplate', 'asearchtemplate', 'addtemplate', 'rsstemplate', 'csstemplate', 'jstemplate', 'reviewerlisttemplate', 'slottemplate');

        $currenttab ='';
        foreach ($templatelist as $template) {
            $templatestab->subtree[] = new tabobject($template, new moodle_url('/mod/callforpaper/templates.php', array('d' => $callforpaper->id, 'mode' => $template)), get_string($template, 'callforpaper'));
            if ($template == $mode) {
                $currenttab = $template;
            }
        }
        if ($currenttab == '') {
            $currenttab = $mode = 'singletemplate';
        }
    }

// Print out the tabs and continue!
    echo $OUTPUT->tabtree($row, $currenttab);
