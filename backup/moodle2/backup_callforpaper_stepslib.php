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
 * @package    mod_callforpaper
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_callforpaper_activity_task
 */

/**
 * Define the complete data structure for backup, with file and id annotations
 */
class backup_callforpaper_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $callforpaper = new backup_nested_element('callforpaper', array('id'), array(
            'name', 'intro', 'introformat', 'comments',
            'timeavailablefrom', 'timeavailableto', 'timeviewfrom', 'timeviewto',
            'requiredentries', 'requiredentriestoview', 'maxentries', 'rssarticles',
            'singletemplate', 'listtemplate', 'listtemplateheader', 'listtemplatefooter',
            'addtemplate', 'rsstemplate', 'rsstitletemplate', 'csstemplate',
            'jstemplate', 'asearchtemplate', 'approval', 'manageapproved', 'scale',
            'assessed', 'assesstimestart', 'assesstimefinish', 'defaultsort',
            'defaultsortdir', 'editany', 'notification', 'timemodified', 'config', 'completionentries',
            'reviewerlisttemplate', 'reviewerlisttemplateheader', 'reviewerlisttemplatefooter', 'slottemplate'
        ));

        $tags = new backup_nested_element('recordstags');
        $tag = new backup_nested_element('tag', array('id'), array('itemid', 'rawname'));

        $fields = new backup_nested_element('fields');

        $field = new backup_nested_element('field', array('id'), array(
            'type', 'name', 'description', 'required', 'param1', 'param2',
            'param3', 'param4', 'param5', 'param6',
            'param7', 'param8', 'param9', 'param10', 'hidden'));

        $records = new backup_nested_element('records');

        $record = new backup_nested_element('record', array('id'), array(
            'userid', 'groupid', 'timecreated', 'timemodified',
            'approved'));

        $contents = new backup_nested_element('contents');

        $content = new backup_nested_element('content', array('id'), array(
            'fieldid', 'content', 'content1', 'content2',
            'content3', 'content4'));

        $ratings = new backup_nested_element('ratings');

        $rating = new backup_nested_element('rating', array('id'), array(
            'component', 'ratingarea', 'scaleid', 'value', 'userid', 'timecreated', 'timemodified'));

        // Build the tree
        $callforpaper->add_child($fields);
        $fields->add_child($field);

        $callforpaper->add_child($records);
        $records->add_child($record);

        $record->add_child($contents);
        $contents->add_child($content);

        $record->add_child($ratings);
        $ratings->add_child($rating);

        $callforpaper->add_child($tags);
        $tags->add_child($tag);

        // Define sources
        $callforpaper->set_source_table('callforpaper', array('id' => backup::VAR_ACTIVITYID));

        $field->set_source_sql('
            SELECT *
              FROM {callforpaper_fields}
             WHERE callforpaperid = ?',
            array(backup::VAR_PARENTID));

        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
            $record->set_source_table('callforpaper_records', array('callforpaperid' => backup::VAR_PARENTID));

            $content->set_source_table('callforpaper_content', array('recordid' => backup::VAR_PARENTID));

            $rating->set_source_table('rating', array('contextid'  => backup::VAR_CONTEXTID,
                                                      'itemid'     => backup::VAR_PARENTID,
                                                      'component'  => backup_helper::is_sqlparam('mod_callforpaper'),
                                                      'ratingarea' => backup_helper::is_sqlparam('entry')));
            $rating->set_source_alias('rating', 'value');
            if (core_tag_tag::is_enabled('mod_callforpaper', 'callforpaper_records')) {
                $tag->set_source_sql('SELECT t.id, ti.itemid, t.rawname
                                        FROM {tag} t
                                        JOIN {tag_instance} ti
                                          ON ti.tagid = t.id
                                       WHERE ti.itemtype = ?
                                         AND ti.component = ?
                                         AND ti.contextid = ?', array(
                    backup_helper::is_sqlparam('callforpaper_records'),
                    backup_helper::is_sqlparam('mod_callforpaper'),
                    backup::VAR_CONTEXTID));
            }
        }

        // Define id annotations
        $callforpaper->annotate_ids('scale', 'scale');

        $record->annotate_ids('user', 'userid');
        $record->annotate_ids('group', 'groupid');

        $rating->annotate_ids('scale', 'scaleid');
        $rating->annotate_ids('user', 'userid');

        // Define file annotations
        $callforpaper->annotate_files('mod_callforpaper', 'intro', null); // This file area hasn't itemid
        $content->annotate_files('mod_callforpaper', 'content', 'id'); // By content->id

        // Return the root element (callforpaper), wrapped into standard activity structure
        return $this->prepare_activity_structure($callforpaper);
    }
}
