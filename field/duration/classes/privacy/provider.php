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

namespace callforpaperfield_datetime\privacy;

use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use mod_callforpaper\privacy\callforpaperfield_provider;

/**
 * Privacy Subsystem for callforpaperfield_duration implementing null_provider.
 *
 * @package    callforpaperfield_duration
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider,
        callforpaperfield_provider {
    /**
     * Get the language string identifier with the component's language
     * file to explain why this plugin stores no callforpaper.
     *
     * @return  string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }

    /**
     * Exports callforpaper about one record in {callforpaper_content} table.
     *
     * @param \context_module $context
     * @param \stdClass $recordobj record from DB table {callforpaper_records}
     * @param \stdClass $fieldobj record from DB table {callforpaper_fields}
     * @param \stdClass $contentobj record from DB table {callforpaper_content}
     * @param \stdClass $defaultvalue pre-populated default value that most of plugins will use
     */
    public static function export_callforpaper_content($context, $recordobj, $fieldobj, $contentobj, $defaultvalue) {
        $defaultvalue->content = transform::date($defaultvalue->content);
        writer::with_context($context)->export_data([$recordobj->id, $contentobj->id], $defaultvalue);
    }

    /**
     * Allows plugins to delete locally stored data.
     *
     * @param \context_module $context
     * @param \stdClass $recordobj record from DB table {callforpaper_records}
     * @param \stdClass $fieldobj record from DB table {callforpaper_fields}
     * @param \stdClass $contentobj record from DB table {callforpaper_content}
     */
    public static function delete_callforpaper_content($context, $recordobj, $fieldobj, $contentobj) {

    }
}
