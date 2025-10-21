<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>;.

/**
 * Form for creating and editing an event.
 *
 * @package     mod_callforpaper
 * @copyright   2025 Justus Dieckmann, Ruhr-Universität Bochum <justus.dieckmann@ruhr-uni-bochum.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_callforpaper\form;

global $CFG;

use core\form\persistent;

require_once($CFG->libdir . '/formslib.php');
class review_form extends persistent {

    /** @var string $persistentclass */
    protected static $persistentclass = 'mod_callforpaper\\local\\persistent\\record_review';

    protected function definition() {
        $mform = $this->_form;

        $persistent =  $this->get_persistent();


        $mform->addElement('textarea', 'reviewtext', get_string('review_comment', 'mod_callforpaper'));
        $mform->setType('reviewtext', PARAM_RAW);

        $dislike_options = [
            get_string('dislike', 'mod_callforpaper'),
            'no like',
            'i hate',
            'igitt',
            'don\'t like'
        ];
        // SARATODO: Should this be removed?
        $dislike_string = $dislike_options[rand(0, count($dislike_options) - 1)];
        $options = [
            0 => '--',
            1 => get_string('ilike', 'mod_callforpaper'),
            2 => get_string('meh', 'mod_callforpaper'),
            3 => $dislike_string,
        ];
        $mform->addElement('select', 'approval', get_string('rating', 'mod_callforpaper'), $options);
        $this->add_action_buttons();
    }
}
