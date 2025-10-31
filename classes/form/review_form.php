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
use mod_callforpaper\manager;

require_once($CFG->libdir . '/formslib.php');
class review_form extends persistent {

    /** @var string $persistentclass */
    protected static $persistentclass = 'mod_callforpaper\\local\\persistent\\record_review';

    protected function definition() {
        global $USER;

        $mform = $this->_form;

        $this->get_persistent();
        $persistent = $this->get_persistent();
        $userid = $persistent->get('revieweruserid');
        $canedit = $userid == $USER->id;

        $params = [];
        if (!$canedit) {
            $params['disabled'] = true;
        }

        $mform->addElement(
            'select',
            'approval',
            get_string('rating', 'mod_callforpaper'),
            manager::get_status_options(),
            $params,
        );

        $mform->addElement(
            'textarea',
            'reviewtext',
            get_string('review_comment', 'mod_callforpaper'),
            $params + ['rows' => 6]
        );
        $mform->setType('reviewtext', PARAM_RAW);

        if ($canedit) {
            $this->add_action_buttons();
        }
    }
}
