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
 * @package   mod_callforpaper
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_callforpaper\manager;

defined('MOODLE_INTERNAL') || die();

// Some constants
define ('CALLFORPAPER_MAX_ENTRIES', 50);
define ('CALLFORPAPER_PERPAGE_SINGLE', 1);

define ('CALLFORPAPER_FIRSTNAME', -1);
define ('CALLFORPAPER_LASTNAME', -2);
define ('CALLFORPAPER_APPROVED', -3);
define ('CALLFORPAPER_TIMEADDED', 0);
define ('CALLFORPAPER_TIMEMODIFIED', -4);
define ('CALLFORPAPER_TAGS', -5);
define ('CALLFORPAPER_REVIEWCOUNT', -6);

define ('CALLFORPAPER_CAP_EXPORT', 'mod/callforpaper:viewalluserpresets');
// Users having assigned the default role "Non-editing teacher" can export callforpaper records
// Using the mod/callforpaper capability "viewalluserpresets" existing in Moodle 1.9.x.
// In Moodle >= 2, new roles may be introduced and used instead.

define('CALLFORPAPER_PRESET_COMPONENT', 'mod_callforpaper');
define('CALLFORPAPER_PRESET_FILEAREA', 'site_presets');
define('CALLFORPAPER_PRESET_CONTEXT', SYSCONTEXTID);

define('CALLFORPAPER_EVENT_TYPE_OPEN', 'open');
define('CALLFORPAPER_EVENT_TYPE_CLOSE', 'close');

/**
 * @package   mod_callforpaper
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class callforpaper_field_base {     // Base class for Call for paper Field Types (see field/*/field.class.php)

    /** @var string Subclasses must override the type with their name */
    var $type = 'unknown';
    /** @var object The callforpaper object that this field belongs to */
    var $callforpaper = NULL;
    /** @var object The field object itself, if we know it */
    var $field = NULL;
    /** @var int Width of the icon for this fieldtype */
    var $iconwidth = 16;
    /** @var int Width of the icon for this fieldtype */
    var $iconheight = 16;
    /** @var object course module or cmifno */
    var $cm;
    /** @var object activity context */
    var $context;
    /** @var priority for globalsearch indexing */
    protected static $priority = self::NO_PRIORITY;
    /** priority value for invalid fields regarding indexing */
    const NO_PRIORITY = 0;
    /** priority value for minimum priority */
    const MIN_PRIORITY = 1;
    /** priority value for low priority */
    const LOW_PRIORITY = 2;
    /** priority value for high priority */
    const HIGH_PRIORITY = 3;
    /** priority value for maximum priority */
    const MAX_PRIORITY = 4;

    /** @var bool whether the field is used in preview mode. */
    protected $preview = false;

    /**
     * Constructor function
     *
     * @global object
     * @uses CONTEXT_MODULE
     * @param int $field
     * @param int $callforpaper
     * @param int $cm
     */
    function __construct($field=0, $callforpaper =0, $cm=0) {   // Field or callforpaper or both, each can be id or object
        global $DB;

        if (empty($field) && empty($callforpaper)) {
            throw new \moodle_exception('missingfield', 'callforpaper');
        }

        if (!empty($field)) {
            if (is_object($field)) {
                $this->field = $field;  // Programmer knows what they are doing, we hope
            } else if (!$this->field = $DB->get_record('callforpaper_fields', array('id'=>$field))) {
                throw new \moodle_exception('invalidfieldid', 'callforpaper');
            }
            if (empty($callforpaper)) {
                if (!$this->callforpaper = $DB->get_record('callforpaper', array('id'=>$this->field->callforpaperid))) {
                    throw new \moodle_exception('invalidid', 'callforpaper');
                }
            }
        }

        if (empty($this->callforpaper)) {         // We need to define this properly
            if (!empty($callforpaper)) {
                if (is_object($callforpaper)) {
                    $this->callforpaper = $callforpaper;  // Programmer knows what they are doing, we hope
                } else if (!$this->callforpaper = $DB->get_record('callforpaper', array('id'=>$callforpaper))) {
                    throw new \moodle_exception('invalidid', 'callforpaper');
                }
            } else {                      // No way to define it!
                throw new \moodle_exception('missingcallforpaper', 'callforpaper');
            }
        }

        if ($cm) {
            $this->cm = $cm;
        } else {
            $this->cm = get_coursemodule_from_instance('callforpaper', $this->callforpaper->id);
        }

        if (empty($this->field)) {         // We need to define some default values
            $this->define_default_field();
        }

        $this->context = context_module::instance($this->cm->id);
    }

    /**
     * Return the field type name.
     *
     * @return string the filed type.
     */
    public function get_name(): string {
        return $this->field->name;
    }

    /**
     * Return if the field type supports preview.
     *
     * Fields without a preview cannot be displayed in the preset preview.
     *
     * @return bool if the plugin supports preview.
     */
    public function supports_preview(): bool {
        return false;
    }

    /**
     * Generate a fake callforpaper_content for this field to be used in preset previews.
     *
     * Call for paper plugins must override this method and support_preview in order to enable
     * preset preview for this field.
     *
     * @param int $recordid the fake record id
     * @return stdClass the fake record
     */
    public function get_callforpaper_content_preview(int $recordid): stdClass {
        $message = get_string('nopreviewavailable', 'mod_callforpaper', $this->field->name);
        return (object)[
            'id' => 0,
            'fieldid' => $this->field->id,
            'recordid' => $recordid,
            'content' => "<span class=\"nopreview\">$message</span>",
            'content1' => null,
            'content2' => null,
            'content3' => null,
            'content4' => null,
        ];
    }

    /**
     * Set the field to preview mode.
     *
     * @param bool $preview the new preview value
     */
    public function set_preview(bool $preview) {
        $this->preview = $preview;
    }

    /**
     * Get the field preview value.
     *
     * @return bool
     */
    public function get_preview(): bool {
        return $this->preview;
    }


    /**
     * This field just sets up a default field object
     *
     * @return bool
     */
    function define_default_field() {
        global $OUTPUT;
        if (empty($this->callforpaper->id)) {
            echo $OUTPUT->notification('Programmer error: callforpaperid not defined in field class');
        }
        $this->field = new stdClass();
        $this->field->id = 0;
        $this->field->callforpaperid = $this->callforpaper->id;
        $this->field->type   = $this->type;
        $this->field->param1 = '';
        $this->field->param2 = '';
        $this->field->param3 = '';
        $this->field->name = '';
        $this->field->description = '';
        $this->field->required = false;

        return true;
    }

    /**
     * Set up the field object according to callforpaper in an object.  Now is the time to clean it!
     *
     * @return bool
     */
    function define_field($callforpaper) {
        $this->field->type        = $this->type;
        $this->field->callforpaperid      = $this->callforpaper->id;

        $this->field->name        = trim($callforpaper->name);
        $this->field->description = trim($callforpaper->description);
        $this->field->required    = !empty($callforpaper->required) ? 1 : 0;

        if (isset($callforpaper->param1)) {
            $this->field->param1 = trim($callforpaper->param1);
        }
        if (isset($callforpaper->param2)) {
            $this->field->param2 = trim($callforpaper->param2);
        }
        if (isset($callforpaper->param3)) {
            $this->field->param3 = trim($callforpaper->param3);
        }
        if (isset($callforpaper->param4)) {
            $this->field->param4 = trim($callforpaper->param4);
        }
        if (isset($callforpaper->param5)) {
            $this->field->param5 = trim($callforpaper->param5);
        }

        return true;
    }

    /**
     * Insert a new field in the callforpaper
     * We assume the field object is already defined as $this->field
     *
     * @global object
     * @return bool
     */
    function insert_field() {
        global $DB, $OUTPUT;

        if (empty($this->field)) {
            echo $OUTPUT->notification('Programmer error: Field has not been defined yet!  See define_field()');
            return false;
        }

        $this->field->id = $DB->insert_record('callforpaper_fields',$this->field);

        // Trigger an event for creating this field.
        $event = \mod_callforpaper\event\field_created::create(array(
            'objectid' => $this->field->id,
            'context' => $this->context,
            'other' => array(
                'fieldname' => $this->field->name,
                'callforpaperid' => $this->callforpaper->id
            )
        ));
        $event->trigger();

        return true;
    }


    /**
     * Update a field in the callforpaper
     *
     * @global object
     * @return bool
     */
    function update_field() {
        global $DB;

        $DB->update_record('callforpaper_fields', $this->field);

        // Trigger an event for updating this field.
        $event = \mod_callforpaper\event\field_updated::create(array(
            'objectid' => $this->field->id,
            'context' => $this->context,
            'other' => array(
                'fieldname' => $this->field->name,
                'callforpaperid' => $this->callforpaper->id
            )
        ));
        $event->trigger();

        return true;
    }

    /**
     * Delete a field completely
     *
     * @global object
     * @return bool
     */
    function delete_field() {
        global $DB;

        if (!empty($this->field->id)) {
            $manager = manager::create_from_instance($this->callforpaper);

            // Get the field before we delete it.
            $field = $DB->get_record('callforpaper_fields', array('id' => $this->field->id));

            $this->delete_content();
            $DB->delete_records('callforpaper_fields', array('id'=>$this->field->id));

            // Trigger an event for deleting this field.
            $event = \mod_callforpaper\event\field_deleted::create(array(
                'objectid' => $this->field->id,
                'context' => $this->context,
                'other' => array(
                    'fieldname' => $this->field->name,
                    'callforpaperid' => $this->callforpaper->id
                 )
            ));

            if (!$manager->has_fields() && $manager->has_records()) {
                $DB->delete_records('callforpaper_records', ['callforpaperid' => $this->callforpaper->id]);
            }

            $event->add_record_snapshot('callforpaper_fields', $field);
            $event->trigger();
        }

        return true;
    }

    /**
     * Print the relevant form element in the ADD template for this field
     *
     * @global object
     * @param int $recordid
     * @return string
     */
    function display_add_field($recordid=0, $formdata=null) {
        global $DB, $OUTPUT;

        if ($formdata) {
            $fieldname = 'field_' . $this->field->id;
            $content = $formdata->$fieldname;
        } else if ($recordid) {
            $content = $DB->get_field('callforpaper_content', 'content', array('fieldid'=>$this->field->id, 'recordid'=>$recordid));
        } else {
            $content = '';
        }

        // beware get_field returns false for new, empty records MDL-18567
        if ($content===false) {
            $content='';
        }

        $str = '<div title="' . s($this->field->description) . '">';
        $str .= '<label for="field_'.$this->field->id.'"><span class="accesshide">'.s($this->field->name).'</span>';
        if ($this->field->required) {
            $image = $OUTPUT->pix_icon('req', get_string('requiredelement', 'form'));
            $str .= html_writer::div($image, 'inline-req');
        }
        $str .= '</label><input class="basefieldinput form-control d-inline mod-callforpaper-input" ' .
                'type="text" name="field_' . $this->field->id . '" ' .
                'id="field_' . $this->field->id . '" value="' . s($content) . '" />';
        $str .= '</div>';

        return $str;
    }

    /**
     * Print the relevant form element to define the attributes for this field
     * viewable by teachers only.
     *
     * @global object
     * @global object
     * @return void Output is echo'd
     */
    function display_edit_field() {
        global $CFG, $DB, $OUTPUT;

        if (empty($this->field)) {   // No field has been defined yet, try and make one
            $this->define_default_field();
        }

        // Throw an exception if field type doen't exist. Anyway user should never access to edit a field with an unknown fieldtype.
        if ($this->type === 'unknown') {
            throw new \moodle_exception(get_string('missingfieldtype', 'callforpaper', (object)['name' => $this->field->name]));
        }

        echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');

        echo '<form id="editfield" action="'.$CFG->wwwroot.'/mod/callforpaper/field.php" method="post">'."\n";
        echo '<input type="hidden" name="d" value="'.$this->callforpaper->id.'" />'."\n";
        if (empty($this->field->id)) {
            echo '<input type="hidden" name="mode" value="add" />'."\n";
        } else {
            echo '<input type="hidden" name="fid" value="'.$this->field->id.'" />'."\n";
            echo '<input type="hidden" name="mode" value="update" />'."\n";
        }
        echo '<input type="hidden" name="type" value="'.$this->type.'" />'."\n";
        echo '<input name="sesskey" value="'.sesskey().'" type="hidden" />'."\n";

        echo $OUTPUT->heading($this->name(), 3);

        $filepath = $CFG->dirroot . '/mod/callforpaper/field/' . $this->type . '/mod.html';
        $templatename = 'callforpaperfield_' . $this->type . '/' . $this->type;

        try {
            $templatefilepath = \core\output\mustache_template_finder::get_template_filepath($templatename);
            $templatefileexists = true;
        } catch (moodle_exception $e) {
            if (!file_exists($filepath)) {
                // Neither file exists.
                throw new \moodle_exception(get_string('missingfieldtype', 'callforpaper', (object)['name' => $this->field->name]));
            }
            $templatefileexists = false;
        }

        if ($templatefileexists) {
            // Give out templated Bootstrap formatted form fields.
            $callforpaper = $this->get_field_params();
            echo $OUTPUT->render_from_template($templatename, $callforpaper);
        } else {
            // Fall back to display mod.html for backward compatibility.
            require_once($filepath);
        }

        $actionbuttons = html_writer::start_div();
        $actionbuttons .= html_writer::tag('input', null, [
            'type' => 'submit',
            'name' => 'cancel',
            'value' => get_string('cancel'),
            'class' => 'btn btn-secondary mx-1'
        ]);
        $actionbuttons .= html_writer::tag('input', null, [
            'type' => 'submit',
            'value' => get_string('save'),
            'class' => 'btn btn-primary mx-1'
        ]);
        $actionbuttons .= html_writer::end_div();

        $stickyfooter = new core\output\sticky_footer($actionbuttons);
        echo $OUTPUT->render($stickyfooter);

        echo '</form>';

        echo $OUTPUT->box_end();
    }

    /**
     * Validates params of fieldinput callforpaper. Overwrite to validate fieldtype specific callforpaper.
     *
     * You are expected to return an array like ['paramname' => 'Error message for paramname param'] if there is an error,
     * return an empty array if everything is fine.
     *
     * @param stdClass $fieldinput The field input callforpaper to check
     * @return array $errors if empty validation was fine, otherwise contains one or more error messages
     */
    public function validate(stdClass $fieldinput): array {
        return [];
    }

    /**
     * Return the callforpaper_content of the field, or generate it if it is in preview mode.
     *
     * @param int $recordid the record id
     * @return stdClass|bool the record callforpaper or false if none
     */
    protected function get_callforpaper_content(int $recordid) {
        global $DB;
        if ($this->preview) {
            return $this->get_callforpaper_content_preview($recordid);
        }
        return $DB->get_record(
            'callforpaper_content',
            ['fieldid' => $this->field->id, 'recordid' => $recordid]
        );
    }

    /**
     * Display the content of the field in browse mode
     *
     * @global object
     * @param int $recordid
     * @param object $template
     * @return bool|string
     */
    function display_browse_field($recordid, $template) {
        $content = $this->get_callforpaper_content($recordid);
        if (!$content || !isset($content->content)) {
            return '';
        }
        $options = new stdClass();
        if ($this->field->param1 == '1') {
            // We are autolinking this field, so disable linking within us.
            $options->filter = false;
        }
        $options->para = false;
        $format = !empty($content->content1) && !empty(trim($content->content1)) ? $content->content1 : null;
        $str = format_text($content->content, $format, $options);
        return $str;
    }

    /**
     * Update the content of one callforpaper field in the callforpaper_content table
     * @global object
     * @param int $recordid
     * @param mixed $value
     * @param string $name
     * @return bool
     */
    function update_content($recordid, $value, $name=''){
        global $DB;

        $content = new stdClass();
        $content->fieldid = $this->field->id;
        $content->recordid = $recordid;
        $content->content = clean_param($value, PARAM_NOTAGS);

        if ($oldcontent = $DB->get_record('callforpaper_content', array('fieldid'=>$this->field->id, 'recordid'=>$recordid))) {
            $content->id = $oldcontent->id;
            return $DB->update_record('callforpaper_content', $content);
        } else {
            return $DB->insert_record('callforpaper_content', $content);
        }
    }

    /**
     * Delete all content associated with the field
     *
     * @global object
     * @param int $recordid
     * @return bool
     */
    function delete_content($recordid=0) {
        global $DB;

        if ($recordid) {
            $conditions = array('fieldid'=>$this->field->id, 'recordid'=>$recordid);
        } else {
            $conditions = array('fieldid'=>$this->field->id);
        }

        $rs = $DB->get_recordset('callforpaper_content', $conditions);
        if ($rs->valid()) {
            $fs = get_file_storage();
            foreach ($rs as $content) {
                $fs->delete_area_files($this->context->id, 'mod_callforpaper', 'content', $content->id);
            }
        }
        $rs->close();

        return $DB->delete_records('callforpaper_content', $conditions);
    }

    /**
     * Check if a field from an add form is empty
     *
     * @param mixed $value
     * @param mixed $name
     * @return bool
     */
    function notemptyfield($value, $name) {
        return !empty($value);
    }

    /**
     * Just in case a field needs to print something before the whole form
     */
    function print_before_form() {
    }

    /**
     * Just in case a field needs to print something after the whole form
     */
    function print_after_form() {
    }


    /**
     * Returns the sortable field for the content. By default, it's just content
     * but for some plugins, it could be content 1 - content4
     *
     * @return string
     */
    function get_sort_field() {
        return 'content';
    }

    /**
     * Returns the SQL needed to refer to the column.  Some fields may need to CAST() etc.
     *
     * @param string $fieldname
     * @return string $fieldname
     */
    function get_sort_sql($fieldname) {
        return $fieldname;
    }

    /**
     * Returns the name/type of the field
     *
     * @return string
     */
    function name() {
        return get_string('fieldtypelabel', "callforpaperfield_$this->type");
    }

    /**
     * Prints the respective type icon
     *
     * @global object
     * @return string
     */
    function image() {
        global $OUTPUT;

        return $OUTPUT->image_icon('icon', $this->type, 'callforpaperfield_' . $this->type);
    }

    /**
     * Per default, it is assumed that fields support text exporting.
     * Override this (return false) on fields not supporting text exporting.
     *
     * @return bool true
     */
    function text_export_supported() {
        return true;
    }

    /**
     * Per default, it is assumed that fields do not support file exporting. Override this (return true)
     * on fields supporting file export. You will also have to implement export_file_value().
     *
     * @return bool true if field will export a file, false otherwise
     */
    public function file_export_supported(): bool {
        return false;
    }

    /**
     * Per default, does not return a file (just null).
     * Override this in fields class, if you want your field to export a file content.
     * In case you are exporting a file value, export_text_value() should return the corresponding file name.
     *
     * @param stdClass $record
     * @return null|string the file content as string or null, if no file content is being provided
     */
    public function export_file_value(stdClass $record): null|string {
        return null;
    }

    /**
     * Per default, a field does not support the import of files.
     *
     * A field type can overwrite this function and return true. In this case it also has to implement the function
     * import_file_value().
     *
     * @return false means file imports are not supported
     */
    public function file_import_supported(): bool {
        return false;
    }

    /**
     * Returns a stored_file object for exporting a file of a given record.
     *
     * @param int $contentid content id
     * @param string $filecontent the content of the file as string
     * @param string $filename the filename the file should have
     */
    public function import_file_value(int $contentid, string $filecontent, string $filename): void {
        return;
    }

    /**
     * Per default, return the record's text value only from the "content" field.
     * Override this in fields class if necessary.
     *
     * @param stdClass $record
     * @return string
     */
    public function export_text_value(stdClass $record) {
        if ($this->text_export_supported()) {
            return $record->content;
        }
        return '';
    }

    /**
     * @param string $relativepath
     * @return bool false
     */
    function file_ok($relativepath) {
        return false;
    }

    /**
     * Returns the priority for being indexed by globalsearch
     *
     * @return int
     */
    public static function get_priority() {
        return static::$priority;
    }

    /**
     * Returns the presentable string value for a field content.
     *
     * The returned string should be plain text.
     *
     * @param stdClass $content
     * @return string
     */
    public static function get_content_value($content) {
        return trim($content->content, "\r\n ");
    }

    /**
     * Return the plugin configs for external functions,
     * in some cases the configs will need formatting or be returned only if the current user has some capabilities enabled.
     *
     * @return array the list of config parameters
     * @since Moodle 3.3
     */
    public function get_config_for_external() {
        // Return all the field configs to null (maybe there is a private key for a service or something similar there).
        $configs = [];
        for ($i = 1; $i <= 10; $i++) {
            $configs["param$i"] = null;
        }
        return $configs;
    }

    /**
     * Function to let field define their parameters.
     *
     * This method that should be overridden by the callforpaperfield plugins
     * when they need to define their callforpaper.
     *
     * @return array
     */
    protected function get_field_params(): array {
        // Name and description of the field.
        $callforpaper = [
            'name' => $this->field->name,
            'description' => $this->field->description,
        ];

        // Whether the field is required.
        if (isset($this->field->required)) {
            $callforpaper['required'] = $this->field->required;
        }

        // Add all the field parameters.
        for ($i = 1; $i <= 10; $i++) {
            if (isset($this->field->{"param$i"})) {
                $callforpaper["param$i"] = $this->field->{"param$i"};
            }
        }

        return $callforpaper;
    }

}


/**
 * Given a template and a callforpaperid, generate a default case template
 *
 * @param stdClass $callforpaper the mod_callforpaper record.
 * @param string $template the template name
 * @param int $recordid the entry record
 * @param bool $form print a form instead of callforpaper
 * @param bool $update if the function update the $callforpaperobject or not
 * @return string the template content or an empty string if no content is available (for instance, when callforpaper has no fields).
 */
function callforpaper_generate_default_template(&$callforpaper, $template, $recordid = 0, $form = false, $update = true) {
    global $DB;

    if (!$callforpaper|| !$template) {
        return '';
    }

    // These templates are empty by default (they have no content).
    $emptytemplates = [
        'csstemplate',
        'jstemplate',
        'listtemplateheader',
        'listtemplatefooter',
        'rsstitletemplate',
        'reviewerlisttemplate',
        'reviewerlisttemplateheader',
        'reviewerlisttemplatefooter',
        'slottemplate',
    ];
    if (in_array($template, $emptytemplates)) {
        return '';
    }

    $manager = manager::create_from_instance($callforpaper);
    if (empty($manager->get_fields())) {
        // No template will be returned if there are no fields.
        return '';
    }

    $templateclass = \mod_callforpaper\template::create_default_template($manager, $template, $form);
    $templatecontent = $templateclass->get_template_content();

    if ($update) {
        // Update the callforpaper instance.
        $newcallforpaper = new stdClass();
        $newcallforpaper->id = $callforpaper->id;
        $newcallforpaper->{$template} = $templatecontent;
        $DB->update_record('callforpaper', $newcallforpaper);
        $callforpaper->{$template} = $templatecontent;
    }

    return $templatecontent;
}

/**
 * Build the form elements to manage tags for a record.
 *
 * @param int|bool $recordid
 * @param string[] $selected raw tag names
 * @return string
 */
function callforpaper_generate_tag_form($recordid = false, $selected = []) {
    global $CFG, $DB, $OUTPUT, $PAGE;

    $tagtypestoshow = \core_tag_area::get_showstandard('mod_callforpaper', 'callforpaper_records');
    $showstandard = ($tagtypestoshow != core_tag_tag::HIDE_STANDARD);
    $typenewtags = ($tagtypestoshow != core_tag_tag::STANDARD_ONLY);

    $str = html_writer::start_tag('div', array('class' => 'callforpapertagcontrol'));

    $namefield = empty($CFG->keeptagnamecase) ? 'name' : 'rawname';

    $tagcollid = \core_tag_area::get_collection('mod_callforpaper', 'callforpaper_records');
    $tags = [];
    $selectedtags = [];

    if ($showstandard) {
        $tags += $DB->get_records_menu('tag', array('isstandard' => 1, 'tagcollid' => $tagcollid),
            $namefield, 'id,' . $namefield . ' as fieldname');
    }

    if ($recordid) {
        $selectedtags += core_tag_tag::get_item_tags_array('mod_callforpaper', 'callforpaper_records', $recordid);
    }

    if (!empty($selected)) {
        list($sql, $params) = $DB->get_in_or_equal($selected, SQL_PARAMS_NAMED);
        $params['tagcollid'] = $tagcollid;
        $sql = "SELECT id, $namefield FROM {tag} WHERE tagcollid = :tagcollid AND rawname $sql";
        $selectedtags += $DB->get_records_sql_menu($sql, $params);
    }

    $tags += $selectedtags;

    $str .= '<select class="form-select" name="tags[]" id="tags" multiple>';
    foreach ($tags as $tagid => $tag) {
        $selected = key_exists($tagid, $selectedtags) ? 'selected' : '';
        $str .= "<option value='$tag' $selected>$tag</option>";
    }
    $str .= '</select>';

    if (has_capability('moodle/tag:manage', context_system::instance()) && $showstandard) {
        $url = new moodle_url('/tag/manage.php', array('tc' => core_tag_area::get_collection('mod_callforpaper',
            'callforpaper_records')));
        $str .= ' ' . $OUTPUT->action_link($url, get_string('managestandardtags', 'tag'));
    }

    $PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', $params = array(
            '#tags',
            $typenewtags,
            '',
            get_string('entertags', 'tag'),
            false,
            $showstandard,
            get_string('noselection', 'form')
        )
    );

    $str .= html_writer::end_tag('div');

    return $str;
}


/**
 * Search for a field name and replaces it with another one in all the
 * form templates. Set $newfieldname as '' if you want to delete the
 * field from the form.
 *
 * @global object
 * @param object $callforpaper
 * @param string $searchfieldname
 * @param string $newfieldname
 * @return bool
 */
function callforpaper_replace_field_in_templates($callforpaper, $searchfieldname, $newfieldname) {
    global $DB;

    $newcallforpaper = (object)['id' => $callforpaper->id];
    $update = false;
    $templates = ['listtemplate', 'singletemplate', 'asearchtemplate', 'addtemplate', 'rsstemplate'];
    foreach ($templates as $templatename) {
        if (empty($callforpaper->$templatename)) {
            continue;
        }
        $search = [
            '[[' . $searchfieldname . ']]',
            '[[' . $searchfieldname . '#id]]',
            '[[' . $searchfieldname . '#name]]',
            '[[' . $searchfieldname . '#description]]',
        ];
        if (empty($newfieldname)) {
            $replace = ['', '', '', ''];
        } else {
            $replace = [
                '[[' . $newfieldname . ']]',
                '[[' . $newfieldname . '#id]]',
                '[[' . $newfieldname . '#name]]',
                '[[' . $newfieldname . '#description]]',
            ];
        }
        $newcallforpaper->{$templatename} = str_ireplace($search, $replace, $callforpaper->{$templatename} ?? '');
        $update = true;
    }
    if (!$update) {
        return true;
    }
    return $DB->update_record('callforpaper', $newcallforpaper);
}


/**
 * Appends a new field at the end of the form template.
 *
 * @global object
 * @param object $callforpaper
 * @param string $newfieldname
 * @return bool if the field has been added or not
 */
function callforpaper_append_new_field_to_templates($callforpaper, $newfieldname): bool {
    global $DB, $OUTPUT;

    $newcallforpaper = (object)['id' => $callforpaper->id];
    $update = false;
    $templates = ['singletemplate', 'addtemplate', 'rsstemplate'];
    foreach ($templates as $templatename) {
        if (empty($callforpaper->$templatename)
            || strpos($callforpaper->$templatename, "[[$newfieldname]]") !== false
            || strpos($callforpaper->$templatename, "##otherfields##") !== false
        ) {
            continue;
        }
        $newcallforpaper->$templatename = $callforpaper->$templatename;
        $fields = [[
            'fieldname' => '[[' . $newfieldname . '#name]]',
            'fieldcontent' => '[[' . $newfieldname . ']]',
        ]];
        $newcallforpaper->$templatename .= $OUTPUT->render_from_template(
            'mod_callforpaper/fields_otherfields',
            ['fields' => $fields, 'classes' => 'added_field']
        );
        $update = true;
    }
    if (!$update) {
        return false;
    }
    return $DB->update_record('callforpaper', $newcallforpaper);
}


/**
 * given a field name
 * this function creates an instance of the particular subfield class
 *
 * @global object
 * @param string $name
 * @param object $callforpaper
 * @return object|bool
 */
function callforpaper_get_field_from_name($name, $callforpaper){
    global $DB;

    $field = $DB->get_record('callforpaper_fields', array('name'=>$name, 'callforpaperid'=>$callforpaper->id));

    if ($field) {
        return callforpaper_get_field($field, $callforpaper);
    } else {
        return false;
    }
}

/**
 * given a field id
 * this function creates an instance of the particular subfield class
 *
 * @global object
 * @param int $fieldid
 * @param object $callforpaper
 * @return bool|object
 */
function callforpaper_get_field_from_id($fieldid, $callforpaper){
    global $DB;

    $field = $DB->get_record('callforpaper_fields', ['id' => $fieldid, 'callforpaperid' => $callforpaper->id]);

    if ($field) {
        return callforpaper_get_field($field, $callforpaper);
    } else {
        return false;
    }
}

/**
 * given a field id
 * this function creates an instance of the particular subfield class
 *
 * @global object
 * @param string $type
 * @param object $callforpaper
 * @return object
 */
function callforpaper_get_field_new($type, $callforpaper) {
    global $CFG;

    $type = clean_param($type, PARAM_ALPHA);
    $filepath = $CFG->dirroot.'/mod/callforpaper/field/'.$type.'/field.class.php';
    // It should never access this method if the subfield class doesn't exist.
    if (!file_exists($filepath)) {
        throw new \moodle_exception('invalidfieldtype', 'callforpaper');
    }
    require_once($filepath);
    $newfield = 'callforpaper_field_'.$type;
    $newfield = new $newfield(0, $callforpaper);
    return $newfield;
}

/**
 * returns a subclass field object given a record of the field, used to
 * invoke plugin methods
 * input: $param $field - record from db
 *
 * @global object
 * @param stdClass $field the field record
 * @param stdClass $callforpaper the callforpaper instance
 * @param stdClass|null $cm optional course module callforpaper
 * @return callforpaper_field_base the field object instance or callforpaper_field_base if unkown type
 */
function callforpaper_get_field(stdClass $field, stdClass $callforpaper, ?stdClass $cm=null): callforpaper_field_base {
    global $CFG;
    if (!isset($field->type)) {
        return new callforpaper_field_base($field);
    }
    $field->type = clean_param($field->type, PARAM_ALPHA);
    $filepath = $CFG->dirroot.'/mod/callforpaper/field/'.$field->type.'/field.class.php';
    if (!file_exists($filepath)) {
        return new callforpaper_field_base($field);
    }
    require_once($filepath);
    $newfield = 'callforpaper_field_'.$field->type;
    $newfield = new $newfield($field, $callforpaper, $cm);
    return $newfield;
}


/**
 * Given record object (or id), returns true if the record belongs to the current user
 *
 * @global object
 * @global object
 * @param mixed $record record object or id
 * @return bool
 */
function callforpaper_isowner($record) {
    global $USER, $DB;

    if (!isloggedin()) { // perf shortcut
        return false;
    }

    if (!is_object($record)) {
        if (!$record = $DB->get_record('callforpaper_records', array('id'=>$record))) {
            return false;
        }
    }

    return ($record->userid == $USER->id);
}

/**
 * has a user reached the max number of entries?
 *
 * @param object $callforpaper
 * @return bool
 */
function callforpaper_atmaxentries($callforpaper){
    if (!$callforpaper->maxentries){
        return false;

    } else {
        return (callforpaper_numentries($callforpaper) >= $callforpaper->maxentries);
    }
}

/**
 * returns the number of entries already made by this user
 *
 * @global object
 * @global object
 * @param object $callforpaper
 * @return int
 */
function callforpaper_numentries($callforpaper, $userid=null) {
    global $USER, $DB;
    if ($userid === null) {
        $userid = $USER->id;
    }
    $sql = 'SELECT COUNT(*) FROM {callforpaper_records} WHERE callforpaperid=? AND userid=?';
    return $DB->count_records_sql($sql, array($callforpaper->id, $userid));
}

/**
 * function that takes in a callforpaperid and adds a record
 * this is used everytime an add template is submitted
 *
 * @global object
 * @global object
 * @param object $callforpaper
 * @param int $groupid
 * @param int $userid
 * @param bool $approved If specified, and the user has the capability to approve entries, then this value
 *      will be used as the approved status of the new record
 * @return bool
 */
function callforpaper_add_record($callforpaper, $groupid = 0, $userid = null, bool $approved = true) {
    global $USER, $DB;

    $cm = get_coursemodule_from_instance('callforpaper', $callforpaper->id);
    $context = context_module::instance($cm->id);

    $record = new stdClass();
    $record->userid = $userid ?? $USER->id;
    $record->callforpaperid = $callforpaper->id;
    $record->groupid = $groupid;
    $record->timecreated = $record->timemodified = time();
    if (has_capability('mod/callforpaper:approve', $context)) {
        $record->approved = $approved;
    } else {
        $record->approved = 0;
    }
    $record->id = $DB->insert_record('callforpaper_records', $record);

    // Trigger an event for creating this record.
    $event = \mod_callforpaper\event\record_created::create(array(
        'objectid' => $record->id,
        'context' => $context,
        'other' => array(
            'callforpaperid' => $callforpaper->id
        )
    ));
    $event->trigger();

    $course = get_course($cm->course);
    callforpaper_update_completion_state($callforpaper, $course, $cm);

    return $record->id;
}

/**
 * check the multple existence any tag in a template
 *
 * check to see if there are 2 or more of the same tag being used.
 *
 * @global object
 * @param int $callforpaperid,
 * @param string $template
 * @return bool
 */
function callforpaper_tags_check($callforpaperid, $template) {
    global $DB, $OUTPUT;

    // first get all the possible tags
    $fields = $DB->get_records('callforpaper_fields', array('callforpaperid'=>$callforpaperid));
    // then we generate strings to replace
    $tagsok = true; // let's be optimistic
    foreach ($fields as $field){
        $pattern="/\[\[" . preg_quote($field->name, '/') . "\]\]/i";
        if (preg_match_all($pattern, $template, $dummy)>1){
            $tagsok = false;
            echo $OUTPUT->notification('[['.$field->name.']] - '.get_string('multipletags','callforpaper'));
        }
    }
    // else return true
    return $tagsok;
}

/**
 * Adds an instance of a callforpaper
 *
 * @param stdClass $callforpaper
 * @param mod_callforpaper_mod_form $mform
 * @return int intance id
 */
function callforpaper_add_instance($callforpaper, $mform = null) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/callforpaper/locallib.php');

    if (empty($callforpaper->assessed)) {
        $callforpaper->assessed = 0;
    }

    if (empty($callforpaper->ratingtime) || empty($callforpaper->assessed)) {
        $callforpaper->assesstimestart  = 0;
        $callforpaper->assesstimefinish = 0;
    }

    $callforpaper->timemodified = time();

    $callforpaper->id = $DB->insert_record('callforpaper', $callforpaper);

    // Add calendar events if necessary.
    callforpaper_set_events($callforpaper);
    if (!empty($callforpaper->completionexpected)) {
        \core_completion\api::update_completion_date_event($callforpaper->coursemodule, 'callforpaper', $callforpaper->id, $callforpaper->completionexpected);
    }

    callforpaper_grade_item_update($callforpaper);

    return $callforpaper->id;
}

/**
 * updates an instance of a callforpaper
 *
 * @global object
 * @param object $callforpaper
 * @return bool
 */
function callforpaper_update_instance($callforpaper) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/callforpaper/locallib.php');

    $callforpaper->timemodified = time();
    if (!empty($callforpaper->instance)) {
        $callforpaper->id = $callforpaper->instance;
    }

    if (empty($callforpaper->assessed)) {
        $callforpaper->assessed = 0;
    }

    if (empty($callforpaper->ratingtime) or empty($callforpaper->assessed)) {
        $callforpaper->assesstimestart  = 0;
        $callforpaper->assesstimefinish = 0;
    }

    if (empty($callforpaper->notification)) {
        $callforpaper->notification = 0;
    }

    $DB->update_record('callforpaper', $callforpaper);

    // Add calendar events if necessary.
    callforpaper_set_events($callforpaper);
    $completionexpected = (!empty($callforpaper->completionexpected)) ? $callforpaper->completionexpected : null;
    \core_completion\api::update_completion_date_event($callforpaper->coursemodule, 'callforpaper', $callforpaper->id, $completionexpected);

    callforpaper_grade_item_update($callforpaper);

    return true;

}

/**
 * deletes an instance of a callforpaper
 *
 * @global object
 * @param int $id
 * @return bool
 */
function callforpaper_delete_instance($id) {    // takes the callforpaperid
    global $DB, $CFG;

    if (!$callforpaper = $DB->get_record('callforpaper', array('id'=>$id))) {
        return false;
    }

    $cm = get_coursemodule_from_instance('callforpaper', $callforpaper->id);
    $context = context_module::instance($cm->id);

    // Delete all information related to fields.
    $fields = $DB->get_records('callforpaper_fields', ['callforpaperid' => $id]);
    foreach ($fields as $field) {
        $todelete = callforpaper_get_field($field, $callforpaper, $cm);
        $todelete->delete_field();
    }

    // Remove old calendar events.
    $events = $DB->get_records('event', array('modulename' => 'callforpaper', 'instance' => $id));
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    // cleanup gradebook
    callforpaper_grade_item_delete($callforpaper);

    // Delete the instance itself
    // We must delete the module record after we delete the grade item.
    $result = $DB->delete_records('callforpaper', array('id'=>$id));

    return $result;
}

/**
 * returns a summary of callforpaper activity of this user
 *
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $callforpaper
 * @return object|null
 */
function callforpaper_user_outline($course, $user, $mod, $callforpaper) {
    global $DB, $CFG;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'callforpaper', $callforpaper->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }


    if ($countrecords = $DB->count_records('callforpaper_records', array('callforpaperid'=>$callforpaper->id, 'userid'=>$user->id))) {
        $result = new stdClass();
        $result->info = get_string('numrecords', 'callforpaper', $countrecords);
        $lastrecord   = $DB->get_record_sql('SELECT id,timemodified FROM {callforpaper_records}
                                              WHERE callforpaperid = ? AND userid = ?
                                           ORDER BY timemodified DESC', array($callforpaper->id, $user->id), true);
        $result->time = $lastrecord->timemodified;
        if ($grade) {
            if (!$grade->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
                $result->info .= ', ' . get_string('gradenoun') . ': ' . $grade->str_long_grade;
            } else {
                $result->info = get_string('gradenoun') . ': ' . get_string('hidden', 'grades');
            }
        }
        return $result;
    } else if ($grade) {
        $result = (object) [
            'time' => grade_get_date_for_user_grade($grade, $user),
        ];
        if (!$grade->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
            $result->info = get_string('gradenoun') . ': ' . $grade->str_long_grade;
        } else {
            $result->info = get_string('gradenoun') . ': ' . get_string('hidden', 'grades');
        }

        return $result;
    }
    return NULL;
}

/**
 * Prints all the records uploaded by this user
 *
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $callforpaper
 */
function callforpaper_user_complete($course, $user, $mod, $callforpaper) {
    global $DB, $CFG, $OUTPUT;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'callforpaper', $callforpaper->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        if (!$grade->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
            echo $OUTPUT->container(get_string('gradenoun') . ': ' . $grade->str_long_grade);
            if ($grade->str_feedback) {
                echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
            }
        } else {
            echo $OUTPUT->container(get_string('gradenoun') . ': ' . get_string('hidden', 'grades'));
        }
    }
    $records = $DB->get_records(
        'callforpaper_records',
        ['callforpaperid' => $callforpaper->id, 'userid' => $user->id],
        'timemodified DESC'
    );
    if ($records) {
        $manager = manager::create_from_instance($callforpaper);
        $parser = $manager->get_template('singletemplate');
        echo $parser->parse_entries($records);
    }
}

/**
 * Return grade for given user or all users.
 *
 * @global object
 * @param object $callforpaper
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function callforpaper_get_user_grades($callforpaper, $userid=0) {
    global $CFG;

    require_once($CFG->dirroot.'/rating/lib.php');

    $ratingoptions = new stdClass;
    $ratingoptions->component = 'mod_callforpaper';
    $ratingoptions->ratingarea = 'entry';
    $ratingoptions->modulename = 'callforpaper';
    $ratingoptions->moduleid   = $callforpaper->id;

    $ratingoptions->userid = $userid;
    $ratingoptions->aggregationmethod = $callforpaper->assessed;
    $ratingoptions->scaleid = $callforpaper->scale;
    $ratingoptions->itemtable = 'callforpaper_records';
    $ratingoptions->itemtableusercolumn = 'userid';

    $rm = new rating_manager();
    return $rm->get_user_grades($ratingoptions);
}

/**
 * Update activity grades
 *
 * @category grade
 * @param object $callforpaper
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone
 */
function callforpaper_update_grades($callforpaper, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if (!$callforpaper->assessed) {
        callforpaper_grade_item_update($callforpaper);

    } else if ($grades = callforpaper_get_user_grades($callforpaper, $userid)) {
        callforpaper_grade_item_update($callforpaper, $grades);

    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = NULL;
        callforpaper_grade_item_update($callforpaper, $grade);

    } else {
        callforpaper_grade_item_update($callforpaper);
    }
}

/**
 * Update/create grade item for given callforpaper
 *
 * @category grade
 * @param stdClass $callforpaper A callforpaper instance with extra cmidnumber property
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return object grade_item
 */
function callforpaper_grade_item_update($callforpaper, $grades=NULL) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $params = array('itemname'=>$callforpaper->name, 'idnumber'=>$callforpaper->cmidnumber);

    if (!$callforpaper->assessed or $callforpaper->scale == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;

    } else if ($callforpaper->scale > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $callforpaper->scale;
        $params['grademin']  = 0;

    } else if ($callforpaper->scale < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$callforpaper->scale;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/callforpaper', $callforpaper->course, 'mod', 'callforpaper', $callforpaper->id, 0, $grades, $params);
}

/**
 * Delete grade item for given callforpaper
 *
 * @category grade
 * @param object $callforpaperobject
 * @return object grade_item
 */
function callforpaper_grade_item_delete($callforpaper) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/callforpaper', $callforpaper->course, 'mod', 'callforpaper', $callforpaper->id, 0, NULL, array('deleted'=>1));
}

// junk functions

/**
 * Return rating related permissions
 *
 * @param string $contextid the context id
 * @param string $component the component to get rating permissions for
 * @param string $ratingarea the rating area to get permissions for
 * @return array an associative array of the user's rating permissions
 */
function callforpaper_rating_permissions($contextid, $component, $ratingarea) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($component != 'mod_callforpaper' || $ratingarea != 'entry') {
        return null;
    }
    return array(
        'view'    => has_capability('mod/callforpaper:viewrating',$context),
        'viewany' => has_capability('mod/callforpaper:viewanyrating',$context),
        'viewall' => has_capability('mod/callforpaper:viewallratings',$context),
        'rate'    => has_capability('mod/callforpaper:rate',$context)
    );
}

/**
 * Validates a submitted rating
 * @param array $params submitted callforpaper
 *            context => object the context in which the rated items exists [required]
 *            itemid => int the ID of the object being rated
 *            scaleid => int the scale from which the user can select a rating. Used for bounds checking. [required]
 *            rating => int the submitted rating
 *            rateduserid => int the id of the user whose items have been rated. NOT the user who submitted the ratings. 0 to update all. [required]
 *            aggregation => int the aggregation method to apply when calculating grades ie RATING_AGGREGATE_AVERAGE [required]
 * @return boolean true if the rating is valid. Will throw rating_exception if not
 */
function callforpaper_rating_validate($params) {
    global $DB, $USER;

    // Check the component is mod_callforpaper
    if ($params['component'] != 'mod_callforpaper') {
        throw new rating_exception('invalidcomponent');
    }

    // Check the ratingarea is entry (the only rating area in callforpaper module)
    if ($params['ratingarea'] != 'entry') {
        throw new rating_exception('invalidratingarea');
    }

    // Check the rateduserid is not the current user .. you can't rate your own entries
    if ($params['rateduserid'] == $USER->id) {
        throw new rating_exception('nopermissiontorate');
    }

    $callforpapersql = "SELECT d.id as callforpaperid, d.scale, d.course, r.userid as userid, d.approval, r.approved, r.timecreated, d.assesstimestart, d.assesstimefinish, r.groupid
                  FROM {callforpaper_records} r
                  JOIN {callforpaper} d ON r.callforpaperid = d.id
                 WHERE r.id = :itemid";
    $callforpaperparams = array('itemid'=>$params['itemid']);
    if (!$info = $DB->get_record_sql($callforpapersql, $callforpaperparams)) {
        //item doesn't exist
        throw new rating_exception('invaliditemid');
    }

    if ($info->scale != $params['scaleid']) {
        //the scale being submitted doesnt match the one in the callforpaper
        throw new rating_exception('invalidscaleid');
    }

    //check that the submitted rating is valid for the scale

    // lower limit
    if ($params['rating'] < 0  && $params['rating'] != RATING_UNSET_RATING) {
        throw new rating_exception('invalidnum');
    }

    // upper limit
    if ($info->scale < 0) {
        //its a custom scale
        $scalerecord = $DB->get_record('scale', array('id' => -$info->scale));
        if ($scalerecord) {
            $scalearray = explode(',', $scalerecord->scale);
            if ($params['rating'] > count($scalearray)) {
                throw new rating_exception('invalidnum');
            }
        } else {
            throw new rating_exception('invalidscaleid');
        }
    } else if ($params['rating'] > $info->scale) {
        //if its numeric and submitted rating is above maximum
        throw new rating_exception('invalidnum');
    }

    if ($info->approval && !$info->approved) {
        //callforpaper requires approval but this item isnt approved
        throw new rating_exception('nopermissiontorate');
    }

    // check the item we're rating was created in the assessable time window
    if (!empty($info->assesstimestart) && !empty($info->assesstimefinish)) {
        if ($info->timecreated < $info->assesstimestart || $info->timecreated > $info->assesstimefinish) {
            throw new rating_exception('notavailable');
        }
    }

    $course = $DB->get_record('course', array('id'=>$info->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('callforpaper', $info->callforpaperid, $course->id, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // if the supplied context doesnt match the item's context
    if ($context->id != $params['context']->id) {
        throw new rating_exception('invalidcontext');
    }

    // Make sure groups allow this user to see the item they're rating
    $groupid = $info->groupid;
    if ($groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
        if (!groups_group_exists($groupid)) { // Can't find group
            throw new rating_exception('cannotfindgroup');//something is wrong
        }

        if (!groups_is_member($groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
            // do not allow rating of posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
            throw new rating_exception('notmemberofgroup');
        }
    }

    return true;
}

/**
 * Can the current user see ratings for a given itemid?
 *
 * @param array $params submitted callforpaper
 *            contextid => int contextid [required]
 *            component => The component for this module - should always be mod_callforpaper [required]
 *            ratingarea => object the context in which the rated items exists [required]
 *            itemid => int the ID of the object being rated [required]
 *            scaleid => int scale id [optional]
 * @return bool
 * @throws coding_exception
 * @throws rating_exception
 */
function mod_callforpaper_rating_can_see_item_ratings($params) {
    global $DB;

    // Check the component is mod_callforpaper.
    if (!isset($params['component']) || $params['component'] != 'mod_callforpaper') {
        throw new rating_exception('invalidcomponent');
    }

    // Check the ratingarea is entry (the only rating area in callforpaper).
    if (!isset($params['ratingarea']) || $params['ratingarea'] != 'entry') {
        throw new rating_exception('invalidratingarea');
    }

    if (!isset($params['itemid'])) {
        throw new rating_exception('invaliditemid');
    }

    $callforpapersql = "SELECT d.id as callforpaperid, d.course, r.groupid
                  FROM {callforpaper_records} r
                  JOIN {callforpaper} d ON r.callforpaperid = d.id
                 WHERE r.id = :itemid";
    $callforpaperparams = array('itemid' => $params['itemid']);
    if (!$info = $DB->get_record_sql($callforpapersql, $callforpaperparams)) {
        // Item doesn't exist.
        throw new rating_exception('invaliditemid');
    }

    // User can see ratings of all participants.
    if ($info->groupid == 0) {
        return true;
    }

    $course = $DB->get_record('course', array('id' => $info->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('callforpaper', $info->callforpaperid, $course->id, false, MUST_EXIST);

    // Make sure groups allow this user to see the item they're rating.
    return groups_group_visible($info->groupid, $course, $cm);
}


/**
 * function that takes in the current callforpaper, number of items per page,
 * a search string and prints a preference box in view.php
 *
 * This preference box prints a searchable advanced search template if
 *     a) A template is defined
 *  b) The advanced search checkbox is checked.
 *
 * @global object
 * @global object
 * @param object $callforpaper
 * @param int $perpage
 * @param string $search
 * @param string $sort
 * @param string $order
 * @param array $search_array
 * @param int $advanced
 * @param string $mode
 * @return void
 */
function callforpaper_print_preference_form($callforpaper, $perpage, $search, $sort='', $order='ASC', $search_array = '', $advanced = 0, $mode= ''){
    global $DB, $PAGE, $OUTPUT;

    $cm = get_coursemodule_from_instance('callforpaper', $callforpaper->id);
    $context = context_module::instance($cm->id);
    echo '<div class="callforpaperpreferences my-5">';
    echo '<form id="options" action="view.php" method="get">';
    echo '<div class="d-flex flex-wrap align-items-center gap-1">';
    echo '<input type="hidden" name="d" value="'.$callforpaper->id.'" />';
    if ($mode =='asearch') {
        $advanced = 1;
        echo '<input type="hidden" name="mode" value="list" />';
    }
    echo '<label for="pref_perpage">'.get_string('pagesize','callforpaper').'</label> ';
    $pagesizes = array(2=>2,3=>3,4=>4,5=>5,6=>6,7=>7,8=>8,9=>9,10=>10,15=>15,
                       20=>20,30=>30,40=>40,50=>50,100=>100,200=>200,300=>300,400=>400,500=>500,1000=>1000);
    echo html_writer::select($pagesizes, 'perpage', $perpage, false, array('id' => 'pref_perpage',
        'class' => 'form-select me-1'));

    if ($advanced) {
        $regsearchclass = 'search_none';
        $advancedsearchclass = 'search_inline';
    } else {
        $regsearchclass = 'search_inline';
        $advancedsearchclass = 'search_none';
    }
    echo '<div id="reg_search" class="' . $regsearchclass . ' me-1" >';
    echo '<label for="pref_search" class="me-1">' . get_string('search') . '</label><input type="text" ' .
         'class="form-control d-inline-block align-middle w-auto me-1" size="16" name="search" id= "pref_search" value="' . s($search) . '" /></div>';
    echo '<label for="pref_sortby">'.get_string('sortby').'</label> ';
    // foreach field, print the option
    echo '<select name="sort" id="pref_sortby" class="form-select me-1">';
    if ($fields = $DB->get_records('callforpaper_fields', array('callforpaperid'=>$callforpaper->id), 'name')) {
        echo '<optgroup label="'.get_string('fields', 'callforpaper').'">';
        foreach ($fields as $field) {
            if ($field->id == $sort) {
                echo '<option value="'.$field->id.'" selected="selected">'.s($field->name).'</option>';
            } else {
                echo '<option value="'.$field->id.'">'.s($field->name).'</option>';
            }
        }
        echo '</optgroup>';
    }
    $options = array();
    $options[CALLFORPAPER_REVIEWCOUNT]  = get_string('reviewcount', 'callforpaper');
    $options[CALLFORPAPER_TIMEADDED]    = get_string('timeadded', 'callforpaper');
    $options[CALLFORPAPER_TIMEMODIFIED] = get_string('timemodified', 'callforpaper');
    $options[CALLFORPAPER_FIRSTNAME]    = get_string('authorfirstname', 'callforpaper');
    $options[CALLFORPAPER_LASTNAME]     = get_string('authorlastname', 'callforpaper');
    if ($callforpaper->approval and has_capability('mod/callforpaper:approve', $context)) {
        $options[CALLFORPAPER_APPROVED] = get_string('approved', 'callforpaper');
    }
    echo '<optgroup label="'.get_string('other', 'callforpaper').'">';
    foreach ($options as $key => $name) {
        if ($key == $sort) {
            echo '<option value="'.$key.'" selected="selected">'.$name.'</option>';
        } else {
            echo '<option value="'.$key.'">'.$name.'</option>';
        }
    }
    echo '</optgroup>';
    echo '</select>';
    echo '<label for="pref_order" class="accesshide">'.get_string('order').'</label>';
    echo '<select id="pref_order" name="order" class="form-select me-1">';
    if ($order == 'ASC') {
        echo '<option value="ASC" selected="selected">'.get_string('ascending','callforpaper').'</option>';
    } else {
        echo '<option value="ASC">'.get_string('ascending','callforpaper').'</option>';
    }
    if ($order == 'DESC') {
        echo '<option value="DESC" selected="selected">'.get_string('descending','callforpaper').'</option>';
    } else {
        echo '<option value="DESC">'.get_string('descending','callforpaper').'</option>';
    }
    echo '</select>';

    if ($advanced) {
        $checked = ' checked="checked" ';
    }
    else {
        $checked = '';
    }
    $PAGE->requires->js('/mod/callforpaper/callforpaper.js');
    echo '<input type="hidden" name="advanced" value="0" />';
    echo '<input type="hidden" name="filter" value="1" />';
    echo '<input type="checkbox" id="advancedcheckbox" name="advanced" value="1" ' . $checked . ' ' .
         'onchange="showHideAdvSearch(this.checked);" class="mx-1" />' .
         '<label for="advancedcheckbox">' . get_string('advancedsearch', 'callforpaper') . '</label>';
    echo '<div id="advsearch-save-sec" class="ms-3 '. $regsearchclass . '">';
    echo '<input type="submit" class="btn btn-secondary" value="' . get_string('savesettings', 'callforpaper') . '" />';
    echo '</div>';
    echo '</div>';

    echo '<br />';
    echo '<div class="' . $advancedsearchclass . '" id="callforpaper_adv_form">';
    echo '<table class="table-reboot">';

    // print ASC or DESC
    echo '<tr><td colspan="2">&nbsp;</td></tr>';
    $i = 0;

    // Determine if we are printing all fields for advanced search, or the template for advanced search
    // If a template is not defined, use the deafault template and display all fields.
    $asearchtemplate = $callforpaper->asearchtemplate;
    if (empty($asearchtemplate)) {
        $asearchtemplate = callforpaper_generate_default_template($callforpaper, 'asearchtemplate', 0, false, false);
    }

    static $fields = array();
    static $callforpaperid = null;

    if (empty($callforpaperid)) {
        $callforpaperid = $callforpaper->id;
    } else if ($callforpaperid != $callforpaper->id) {
        $fields = array();
    }

    if (empty($fields)) {
        $fieldrecords = $DB->get_records('callforpaper_fields', array('callforpaperid'=>$callforpaper->id));
        foreach ($fieldrecords as $fieldrecord) {
            $fields[]= callforpaper_get_field($fieldrecord, $callforpaper);
        }
    }

    // Replacing tags
    $patterns = array();
    $replacement = array();

    // Then we generate strings to replace for normal tags
    $otherfields = [];
    foreach ($fields as $field) {
        $fieldname = $field->field->name;
        $fieldname = preg_quote($fieldname, '/');
        $searchfield = callforpaper_get_field_from_id($field->field->id, $callforpaper);

        if ($searchfield->type === 'unknown') {
            continue;
        }
        if (!empty($search_array[$field->field->id]->callforpaper)) {
            $searchinput = $searchfield->display_search_field($search_array[$field->field->id]->callforpaper);
        } else {
            $searchinput = $searchfield->display_search_field();
        }
        $patterns[] = "/\[\[$fieldname\]\]/i";
        $replacement[] = $searchinput;
        // Extra field information.
        $patterns[] = "/\[\[$fieldname#name\]\]/i";
        $replacement[] = $field->field->name;
        $patterns[] = "/\[\[$fieldname#description\]\]/i";
        $replacement[] = $field->field->description;
        // Other fields.
        if (strpos($asearchtemplate, "[[" . $field->field->name . "]]") === false) {
            $otherfields[] = [
                'fieldname' => $searchfield->field->name,
                'fieldcontent' => $searchinput,
            ];
        }
    }
    $patterns[] = "/##otherfields##/";
    if (!empty($otherfields)) {
        $replacement[] = $OUTPUT->render_from_template(
            'mod_callforpaper/fields_otherfields',
            ['fields' => $otherfields]
        );
    } else {
        $replacement[] = '';
    }

    $fn = !empty($search_array[CALLFORPAPER_FIRSTNAME]->callforpaper) ? $search_array[CALLFORPAPER_FIRSTNAME]->callforpaper : '';
    $ln = !empty($search_array[CALLFORPAPER_LASTNAME]->callforpaper) ? $search_array[CALLFORPAPER_LASTNAME]->callforpaper : '';
    $patterns[]    = '/##firstname##/';
    $replacement[] = '<label class="accesshide" for="u_fn">' . get_string('authorfirstname', 'callforpaper') . '</label>' .
                     '<input type="text" class="form-control" size="16" id="u_fn" name="u_fn" value="' . s($fn) . '" />';
    $patterns[]    = '/##lastname##/';
    $replacement[] = '<label class="accesshide" for="u_ln">' . get_string('authorlastname', 'callforpaper') . '</label>' .
                     '<input type="text" class="form-control" size="16" id="u_ln" name="u_ln" value="' . s($ln) . '" />';

    if (core_tag_tag::is_enabled('mod_callforpaper', 'callforpaper_records')) {
        $patterns[] = "/##tags##/";
        $selectedtags = isset($search_array[CALLFORPAPER_TAGS]->rawtagnames) ? $search_array[CALLFORPAPER_TAGS]->rawtagnames : [];
        $replacement[] = callforpaper_generate_tag_form(false, $selectedtags);
    }

    // actual replacement of the tags

    $options = new stdClass();
    $options->para=false;
    $options->noclean=true;
    echo '<tr><td>';
    echo preg_replace($patterns, $replacement, format_text($asearchtemplate, FORMAT_HTML, $options));
    echo '</td></tr>';

    echo '<tr><td colspan="4"><br/>' .
         '<input type="submit" class="btn btn-primary me-1" value="' . get_string('savesettings', 'callforpaper') . '" />' .
         '<input type="submit" class="btn btn-secondary" name="resetadv" value="' . get_string('resetsettings', 'callforpaper') . '" />' .
         '</td></tr>';
    echo '</table>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '<hr/>';
}

/**
 * @global object
 * @global object
 * @param object $callforpaper
 * @param object $record
 * @param bool $print if the result must be printed or returner.
 * @return void Output echo'd
 */
function callforpaper_print_ratings($callforpaper, $record, bool $print = true) {
    global $OUTPUT;
    $result = '';
    if (!empty($record->rating)){
        $result = $OUTPUT->render($record->rating);
    }
    if (!$print) {
        return $result;
    }
    echo $result;
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function callforpaper_get_view_actions() {
    return array('view');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function callforpaper_get_post_actions() {
    return array('add','update','record delete');
}

/**
 * @param string $name
 * @param int $callforpaperid
 * @param int $fieldid
 * @return bool
 */
function callforpaper_fieldname_exists($name, $callforpaperid, $fieldid = 0) {
    global $DB;

    if (!is_numeric($name)) {
        $like = $DB->sql_like('df.name', ':name', false);
    } else {
        $like = "df.name = :name";
    }
    $params = array('name'=>$name);
    if ($fieldid) {
        $params['callforpaperid']   = $callforpaperid;
        $params['fieldid1'] = $fieldid;
        $params['fieldid2'] = $fieldid;
        return $DB->record_exists_sql("SELECT * FROM {callforpaper_fields} df
                                        WHERE $like AND df.callforpaperid = :callforpaperid
                                              AND ((df.id < :fieldid1) OR (df.id > :fieldid2))", $params);
    } else {
        $params['callforpaperid']   = $callforpaperid;
        return $DB->record_exists_sql("SELECT * FROM {callforpaper_fields} df
                                        WHERE $like AND df.callforpaperid = :callforpaperid", $params);
    }
}

/**
 * @param array $fieldinput
 */
function callforpaper_convert_arrays_to_strings(&$fieldinput) {
    foreach ($fieldinput as $key => $val) {
        if (is_array($val)) {
            $str = '';
            foreach ($val as $inner) {
                $str .= $inner . ',';
            }
            $str = substr($str, 0, -1);

            $fieldinput->$key = $str;
        }
    }
}


/**
 * Converts a callforpaper (module instance) to use the Roles System
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses CAP_PREVENT
 * @uses CAP_ALLOW
 * @param object $callforpaper a callforpaper object with the same attributes as a record
 *                     from the callforpaper callforpaper table
 * @param int $callforpapermodid the id of the callforpaper module, from the modules table
 * @param array $teacherroles array of roles that have archetype teacher
 * @param array $studentroles array of roles that have archetype student
 * @param array $guestroles array of roles that have archetype guest
 * @param int $cmid the course_module id for this callforpaper instance
 * @return boolean callforpaper module was converted or not
 */
function callforpaper_convert_to_roles($callforpaper, $teacherroles=array(), $studentroles=array(), $cmid=NULL) {
    global $CFG, $DB, $OUTPUT;

    if (!isset($callforpaper->participants) && !isset($callforpaper->assesspublic)
            && !isset($callforpaper->groupmode)) {
        // We assume that this callforpaper has already been converted to use the
        // Roles System. above fields get dropped the callforpaper module has been
        // upgraded to use Roles.
        return false;
    }

    if (empty($cmid)) {
        // We were not given the course_module id. Try to find it.
        if (!$cm = get_coursemodule_from_instance('callforpaper', $callforpaper->id)) {
            echo $OUTPUT->notification('Could not get the course module for the callforpaper');
            return false;
        } else {
            $cmid = $cm->id;
        }
    }
    $context = context_module::instance($cmid);


    // $callforpaper->participants:
    // 1 - Only teachers can add entries
    // 3 - Teachers and students can add entries
    switch ($callforpaper->participants) {
        case 1:
            foreach ($studentroles as $studentrole) {
                assign_capability('mod/callforpaper:writeentry', CAP_PREVENT, $studentrole->id, $context->id);
            }
            foreach ($teacherroles as $teacherrole) {
                assign_capability('mod/callforpaper:writeentry', CAP_ALLOW, $teacherrole->id, $context->id);
            }
            break;
        case 3:
            foreach ($studentroles as $studentrole) {
                assign_capability('mod/callforpaper:writeentry', CAP_ALLOW, $studentrole->id, $context->id);
            }
            foreach ($teacherroles as $teacherrole) {
                assign_capability('mod/callforpaper:writeentry', CAP_ALLOW, $teacherrole->id, $context->id);
            }
            break;
    }

    // $callforpaper->assessed:
    // 2 - Only teachers can rate posts
    // 1 - Everyone can rate posts
    // 0 - No one can rate posts
    switch ($callforpaper->assessed) {
        case 0:
            foreach ($studentroles as $studentrole) {
                assign_capability('mod/callforpaper:rate', CAP_PREVENT, $studentrole->id, $context->id);
            }
            foreach ($teacherroles as $teacherrole) {
                assign_capability('mod/callforpaper:rate', CAP_PREVENT, $teacherrole->id, $context->id);
            }
            break;
        case 1:
            foreach ($studentroles as $studentrole) {
                assign_capability('mod/callforpaper:rate', CAP_ALLOW, $studentrole->id, $context->id);
            }
            foreach ($teacherroles as $teacherrole) {
                assign_capability('mod/callforpaper:rate', CAP_ALLOW, $teacherrole->id, $context->id);
            }
            break;
        case 2:
            foreach ($studentroles as $studentrole) {
                assign_capability('mod/callforpaper:rate', CAP_PREVENT, $studentrole->id, $context->id);
            }
            foreach ($teacherroles as $teacherrole) {
                assign_capability('mod/callforpaper:rate', CAP_ALLOW, $teacherrole->id, $context->id);
            }
            break;
    }

    // $callforpaper->assesspublic:
    // 0 - Students can only see their own ratings
    // 1 - Students can see everyone's ratings
    switch ($callforpaper->assesspublic) {
        case 0:
            foreach ($studentroles as $studentrole) {
                assign_capability('mod/callforpaper:viewrating', CAP_PREVENT, $studentrole->id, $context->id);
            }
            foreach ($teacherroles as $teacherrole) {
                assign_capability('mod/callforpaper:viewrating', CAP_ALLOW, $teacherrole->id, $context->id);
            }
            break;
        case 1:
            foreach ($studentroles as $studentrole) {
                assign_capability('mod/callforpaper:viewrating', CAP_ALLOW, $studentrole->id, $context->id);
            }
            foreach ($teacherroles as $teacherrole) {
                assign_capability('mod/callforpaper:viewrating', CAP_ALLOW, $teacherrole->id, $context->id);
            }
            break;
    }

    if (empty($cm)) {
        $cm = $DB->get_record('course_modules', array('id'=>$cmid));
    }

    switch ($cm->groupmode) {
        case NOGROUPS:
            break;
        case SEPARATEGROUPS:
            foreach ($studentroles as $studentrole) {
                assign_capability('moodle/site:accessallgroups', CAP_PREVENT, $studentrole->id, $context->id);
            }
            foreach ($teacherroles as $teacherrole) {
                assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $teacherrole->id, $context->id);
            }
            break;
        case VISIBLEGROUPS:
            foreach ($studentroles as $studentrole) {
                assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $studentrole->id, $context->id);
            }
            foreach ($teacherroles as $teacherrole) {
                assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $teacherrole->id, $context->id);
            }
            break;
    }
    return true;
}

/**
 * Prints the heads for a page
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $callforpaper
 * @param string $currenttab
 * @param string $actionbar
 */
function callforpaper_print_header($course, $cm, $callforpaper, $currenttab='', string $actionbar = '') {

    global $CFG, $displaynoticegood, $displaynoticebad, $OUTPUT, $PAGE, $USER;

    echo $OUTPUT->header();

    echo $actionbar;

    // Print any notices

    if (!empty($displaynoticegood)) {
        echo $OUTPUT->notification($displaynoticegood, 'notifysuccess');    // good (usually green)
    } else if (!empty($displaynoticebad)) {
        echo $OUTPUT->notification($displaynoticebad);                     // bad (usuually red)
    }
}

/**
 * Can user add more entries?
 *
 * @param object $callforpaper
 * @param mixed $currentgroup
 * @param int $groupmode
 * @param stdClass $context
 * @return bool
 */
function callforpaper_user_can_add_entry($callforpaper, $currentgroup, $groupmode, $context = null) {
    global $DB;

    // Don't let add entry to a callforpaper that has no fields.
    if (!$DB->record_exists('callforpaper_fields', ['callforpaperid' => $callforpaper->id])) {
        return false;
    }

    if (empty($context)) {
        $cm = get_coursemodule_from_instance('callforpaper', $callforpaper->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
    }

    if (has_capability('mod/callforpaper:manageentries', $context)) {
        // no entry limits apply if user can manage

    } else if (!has_capability('mod/callforpaper:writeentry', $context)) {
        return false;

    } else if (callforpaper_atmaxentries($callforpaper)) {
        return false;
    } else if (callforpaper_in_readonly_period($callforpaper)) {
        // Check whether we're in a read-only period
        return false;
    }

    if (!$groupmode or has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($currentgroup) {
        return groups_is_member($currentgroup);
    } else {
        //else it might be group 0 in visible mode
        if ($groupmode == VISIBLEGROUPS){
            return true;
        } else {
            return false;
        }
    }
}

/**
 * Check whether the current user is allowed to manage the given record considering manageentries capability,
 * callforpaper_in_readonly_period() result, ownership (determined by callforpaper_isowner()) and manageapproved setting.
 * @param mixed $record record object or id
 * @param object $callforpaper data object
 * @param object $context context object
 * @return bool returns true if the user is allowd to edit the entry, false otherwise
 */
function callforpaper_user_can_manage_entry($record, $callforpaper, $context) {
    global $DB;

    if (has_capability('mod/callforpaper:manageentries', $context)) {
        return true;
    }

    // Check whether this activity is read-only at present.
    $readonly = callforpaper_in_readonly_period($callforpaper);

    if (!$readonly) {
        // Get record object from db if just id given like in callforpaper_isowner.
        // ...done before calling callforpaper_isowner() to avoid querying db twice.
        if (!is_object($record)) {
            if (!$record = $DB->get_record('callforpaper_records', array('id' => $record))) {
                return false;
            }
        }
        if (callforpaper_isowner($record)) {
            if ($callforpaper->approval && $record->approved) {
                return $callforpaper->manageapproved == 1;
            } else {
                return true;
            }
        }
    }

    return false;
}

/**
 * Check whether the specified callforpaper activity is currently in a read-only period
 *
 * @param object $callforpaper
 * @return bool returns true if the time fields in $callforpaperindicate a read-only period; false otherwise
 */
function callforpaper_in_readonly_period($callforpaper) {
    $now = time();
    if (!$callforpaper->timeviewfrom && !$callforpaper->timeviewto) {
        return false;
    } else if (($callforpaper->timeviewfrom && $now < $callforpaper->timeviewfrom) || ($callforpaper->timeviewto && $now > $callforpaper->timeviewto)) {
        return false;
    }
    return true;
}

/**
 * @global object
 * @global object
 * @param object $course
 * @param int $userid
 * @param string $shortname
 * @return string
 */
function callforpaper_preset_path($course, $userid, $shortname) {
    global $USER, $CFG;

    $context = context_course::instance($course->id);

    $userid = (int)$userid;

    $path = null;
    if ($userid > 0 && ($userid == $USER->id || has_capability('mod/callforpaper:viewalluserpresets', $context))) {
        $path = $CFG->dataroot.'/callforpaper/preset/'.$userid.'/'.$shortname;
    } else if ($userid == 0) {
        $path = $CFG->dirroot.'/mod/callforpaper/preset/'.$shortname;
    } else if ($userid < 0) {
        $path = $CFG->tempdir.'/callforpaper/'.-$userid.'/'.$shortname;
    }

    return $path;
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the callforpaper.
 *
 * @param MoodleQuickForm $mform form passed by reference
 */
function callforpaper_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'callforpaperheader', get_string('modulenameplural', 'callforpaper'));
    $mform->addElement('static', 'callforpaperdelete', get_string('delete'));
    $mform->addElement('checkbox', 'reset_callforpaper', get_string('deleteallentries','callforpaper'));

    $mform->addElement('checkbox', 'reset_callforpaper_notenrolled', get_string('deletenotenrolled', 'callforpaper'));
    $mform->disabledIf('reset_callforpaper_notenrolled', 'reset_callforpaper', 'checked');

    $mform->addElement('checkbox', 'reset_callforpaper_ratings', get_string('deleteallratings'));
    $mform->disabledIf('reset_callforpaper_ratings', 'reset_callforpaper', 'checked');

    $mform->addElement('checkbox', 'reset_callforpaper_comments', get_string('deleteallcomments'));
    $mform->disabledIf('reset_callforpaper_comments', 'reset_callforpaper', 'checked');

    $mform->addElement('checkbox', 'reset_callforpaper_tags', get_string('removeallcallforpapertags', 'callforpaper'));
    $mform->disabledIf('reset_callforpaper_tags', 'reset_callforpaper', 'checked');
}

/**
 * Course reset form defaults.
 * @return array
 */
function callforpaper_reset_course_form_defaults($course) {
    return array('reset_callforpaper'=>0, 'reset_callforpaper_ratings'=>1, 'reset_callforpaper_comments'=>1, 'reset_callforpaper_notenrolled'=>0);
}

/**
 * Removes all grades from gradebook
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type optional type
 */
function callforpaper_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $sql = "SELECT d.*, cm.idnumber as cmidnumber, d.course as courseid
              FROM {callforpaper} d, {course_modules} cm, {modules} m
             WHERE m.name='callforpaper' AND m.id=cm.module AND cm.instance=d.id AND d.course=?";

    if ($callforpapers = $DB->get_records_sql($sql, array($courseid))) {
        foreach ($callforpapers as $callforpaper) {
            callforpaper_grade_item_update($callforpaper, 'reset');
        }
    }
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * callforpaper responses for course $callforpaper->courseid.
 *
 * @global object
 * @global object
 * @param object $callforpaper the callforpaper submitted from the reset course.
 * @return array status array
 */
function callforpaper_reset_userdata($callforpaper) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/filelib.php');
    require_once($CFG->dirroot.'/rating/lib.php');

    $componentstr = get_string('modulenameplural', 'callforpaper');
    $status = [];

    $allrecordssql = "SELECT r.id
                        FROM {callforpaper_records} r
                             INNER JOIN {callforpaper} d ON r.callforpaperid = d.id
                       WHERE d.course = ?";

    $allcallforpaperssql = "SELECT d.id
                      FROM {callforpaper} d
                     WHERE d.course=?";

    $rm = new rating_manager();
    $ratingdeloptions = new stdClass;
    $ratingdeloptions->component = 'mod_callforpaper';
    $ratingdeloptions->ratingarea = 'entry';

    // Set the file storage - may need it to remove files later.
    $fs = get_file_storage();

    // Delete entries if requested.
    if (!empty($callforpaper->reset_data)) {
        $DB->delete_records_select('comments', "itemid IN ($allrecordssql) AND commentarea='callforpaper_entry'", [$callforpaper->courseid]);
        $DB->delete_records_select('callforpaper_content', "recordid IN ($allrecordssql)", [$callforpaper->courseid]);
        $DB->delete_records_select('callforpaper_records', "callforpaperid IN ($allcallforpaperssql)", [$callforpaper->courseid]);

        if ($callforpapers = $DB->get_records_sql($allcallforpaperssql, [$callforpaper->courseid])) {
            foreach ($callforpapers as $callforpaperid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('callforpaper', $callforpaperid)) {
                    continue;
                }
                $callforpapercontext = context_module::instance($cm->id);

                // Delete any files that may exist.
                $fs->delete_area_files($callforpapercontext->id, 'mod_callforpaper', 'content');

                $ratingdeloptions->contextid = $callforpapercontext->id;
                $rm->delete_ratings($ratingdeloptions);

                core_tag_tag::delete_instances('mod_callforpaper', null, $callforpapercontext->id);
            }
        }

        if (empty($callforpaper->reset_gradebook_grades)) {
            // Remove all grades from gradebook.
            callforpaper_reset_gradebook($callforpaper->courseid);
        }
        $status[] = [
            'component' => $componentstr,
            'item' => get_string('deleteallentries', 'callforpaper'),
            'error' => false,
        ];
    }

    // Remove entries by users not enrolled into course.
    if (!empty($callforpaper->reset_callforpaper_notenrolled)) {
        $recordssql = "SELECT r.id, r.userid, r.callforpaperid, u.id AS userexists, u.deleted AS userdeleted
                         FROM {callforpaper_records} r
                              JOIN {callforpaper} d ON r.callforpaperid = d.id
                              LEFT JOIN {user} u ON r.userid = u.id
                        WHERE d.course = ? AND r.userid > 0";

        $course_context = context_course::instance($callforpaper->courseid);
        $notenrolled = [];
        $fields = [];
        $rs = $DB->get_recordset_sql($recordssql, [$callforpaper->courseid]);
        foreach ($rs as $record) {
            if (array_key_exists($record->userid, $notenrolled) or !$record->userexists or $record->userdeleted
              or !is_enrolled($course_context, $record->userid)) {
                // Delete ratings.
                if (!$cm = get_coursemodule_from_instance('callforpaper', $record->callforpaperid)) {
                    continue;
                }
                $callforpapercontext = context_module::instance($cm->id);
                $ratingdeloptions->contextid = $callforpapercontext->id;
                $ratingdeloptions->itemid = $record->id;
                $rm->delete_ratings($ratingdeloptions);

                // Delete any files that may exist.
                if ($contents = $DB->get_records('callforpaper_content', ['recordid' => $record->id], '', 'id')) {
                    foreach ($contents as $content) {
                        $fs->delete_area_files($callforpapercontext->id, 'mod_callforpaper', 'content', $content->id);
                    }
                }
                $notenrolled[$record->userid] = true;

                core_tag_tag::remove_all_item_tags('mod_callforpaper', 'callforpaper_records', $record->id);

                $DB->delete_records('comments', ['itemid' => $record->id, 'commentarea' => 'callforpaper_entry']);
                $DB->delete_records('callforpaper_content', ['recordid' => $record->id]);
                $DB->delete_records('callforpaper_records', ['id' => $record->id]);
            }
        }
        $rs->close();
        $status[] = [
            'component' => $componentstr,
            'item' => get_string('deletenotenrolled', 'callforpaper'),
            'error' => false,
        ];
    }

    // Remove all ratings.
    if (!empty($callforpaper->reset_callforpaper_ratings)) {
        if ($callforpapers = $DB->get_records_sql($allcallforpaperssql, [$callforpaper->courseid])) {
            foreach ($callforpapers as $callforpaperid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('callforpaper', $callforpaperid)) {
                    continue;
                }
                $callforpapercontext = context_module::instance($cm->id);

                $ratingdeloptions->contextid = $callforpapercontext->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        if (empty($callforpaper->reset_gradebook_grades)) {
            // Remove all grades from gradebook.
            callforpaper_reset_gradebook($callforpaper->courseid);
        }

        $status[] = [
            'component' => $componentstr,
            'item' => get_string('deleteallratings'),
            'error' => false,
        ];
    }

    // Remove all comments.
    if (!empty($callforpaper->reset_callforpaper_comments)) {
        $DB->delete_records_select('comments', "itemid IN ($allrecordssql) AND commentarea='callforpaper_entry'", [$callforpaper->courseid]);
        $status[] = [
            'component' => $componentstr,
            'item' => get_string('deleteallcomments'),
            'error' => false,
        ];
    }

    // Remove all the tags.
    if (!empty($callforpaper->reset_callforpaper_tags)) {
        if ($callforpapers = $DB->get_records_sql($allcallforpaperssql, [$callforpaper->courseid])) {
            foreach ($callforpapers as $callforpaperid => $unused) {
                if (!$cm = get_coursemodule_from_instance('callforpaper', $callforpaperid)) {
                    continue;
                }

                $context = context_module::instance($cm->id);
                core_tag_tag::delete_instances('mod_callforpaper', null, $context->id);

            }
        }
        $status[] = [
            'component' => $componentstr,
            'item' => get_string('removeallcallforpapertags', 'callforpaper'),
            'error' => false,
        ];
    }

    // Updating dates - shift may be negative too.
    if ($callforpaper->timeshift) {
        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        shift_course_mod_dates(
            'callforpaper',
            [
                'timeavailablefrom',
                'timeavailableto',
                'timeviewfrom',
                'timeviewto',
                'assesstimestart',
                'assesstimefinish',
            ],
            $callforpaper->timeshift,
            $callforpaper->courseid,
        );
        $status[] = [
            'component' => $componentstr,
            'item' => get_string('date'),
            'error' => false,
        ];
    }

    return $status;
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function callforpaper_get_extra_capabilities() {
    return ['moodle/rating:view', 'moodle/rating:viewany', 'moodle/rating:viewall', 'moodle/rating:rate',
            'moodle/comment:view', 'moodle/comment:post', 'moodle/comment:delete'];
}

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function callforpaper_supports($feature) {
    return match ($feature) {
        FEATURE_GROUPS => true,
        FEATURE_GROUPINGS => true,
        FEATURE_MOD_INTRO => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_COMPLETION_HAS_RULES => true,
        FEATURE_GRADE_HAS_GRADE => true,
        FEATURE_GRADE_OUTCOMES => true,
        FEATURE_RATE => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_COMMENT => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_COLLABORATION,
        default => null,
    };
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Lists all browsable file areas
 *
 * @package  mod_callforpaper
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function callforpaper_get_file_areas($course, $cm, $context) {
    return array('content' => get_string('areacontent', 'mod_callforpaper'));
}

/**
 * File browsing support for callforpaper module.
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param cm_info $cm
 * @param context $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info_stored file_info_stored instance or null if not found
 */
function callforpaper_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }

    if (!isset($areas[$filearea])) {
        return null;
    }

    if (is_null($itemid)) {
        require_once($CFG->dirroot.'/mod/callforpaper/locallib.php');
        return new callforpaper_file_info_container($browser, $course, $cm, $context, $areas, $filearea);
    }

    if (!$content = $DB->get_record('callforpaper_content', array('id'=>$itemid))) {
        return null;
    }

    if (!$field = $DB->get_record('callforpaper_fields', array('id'=>$content->fieldid))) {
        return null;
    }

    if (!$record = $DB->get_record('callforpaper_records', array('id'=>$content->recordid))) {
        return null;
    }

    if (!$callforpaper = $DB->get_record('callforpaper', array('id'=>$field->callforpaperid))) {
        return null;
    }

    //check if approved
    if ($callforpaper->approval and !$record->approved and !callforpaper_isowner($record) and !has_capability('mod/callforpaper:approve', $context)) {
        return null;
    }

    // group access
    if ($record->groupid) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
            if (!groups_is_member($record->groupid)) {
                return null;
            }
        }
    }

    $fieldobj = callforpaper_get_field($field, $callforpaper, $cm);

    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!$fieldobj->file_ok($filepath.$filename)) {
        return null;
    }

    $fs = get_file_storage();
    if (!($storedfile = $fs->get_file($context->id, 'mod_callforpaper', $filearea, $itemid, $filepath, $filename))) {
        return null;
    }

    // Checks to see if the user can manage files or is the owner.
    // TODO MDL-33805 - Do not use userid here and move the capability check above.
    if (!has_capability('moodle/course:managefiles', $context) && $storedfile->get_userid() != $USER->id) {
        return null;
    }

    $urlbase = $CFG->wwwroot.'/pluginfile.php';

    return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemid, true, true, false, false);
}

/**
 * Serves the callforpaper attachments. Implements needed access control ;-)
 *
 * @package  mod_callforpaper
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function callforpaper_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    if ($filearea === 'content') {
        $contentid = (int)array_shift($args);

        if (!$content = $DB->get_record('callforpaper_content', array('id'=>$contentid))) {
            return false;
        }

        if (!$field = $DB->get_record('callforpaper_fields', array('id'=>$content->fieldid))) {
            return false;
        }

        if (!$record = $DB->get_record('callforpaper_records', array('id'=>$content->recordid))) {
            return false;
        }

        if (!$callforpaper = $DB->get_record('callforpaper', array('id'=>$field->callforpaperid))) {
            return false;
        }

        if ($callforpaper->id != $cm->instance) {
            // hacker attempt - context does not match the contentid
            return false;
        }

        //check if approved
        if ($callforpaper->approval and !$record->approved and !callforpaper_isowner($record) and !has_capability('mod/callforpaper:approve', $context)) {
            return false;
        }

        // group access
        if ($record->groupid) {
            $groupmode = groups_get_activity_groupmode($cm, $course);
            if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                if (!groups_is_member($record->groupid)) {
                    return false;
                }
            }
        }

        $fieldobj = callforpaper_get_field($field, $callforpaper, $cm);

        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_callforpaper/content/$content->id/$relativepath";

        if (!$fieldobj->file_ok($relativepath)) {
            return false;
        }

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }

        // finally send the file
        send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!
    }

    return false;
}


function callforpaper_extend_navigation($navigation, $course, $module, $cm) {
    global $CFG, $OUTPUT, $USER, $DB;
    require_once($CFG->dirroot . '/mod/callforpaper/locallib.php');

    $rid = optional_param('rid', 0, PARAM_INT);

    $callforpaper = $DB->get_record('callforpaper', array('id'=>$cm->instance));
    $currentgroup = groups_get_activity_group($cm);
    $groupmode = groups_get_activity_groupmode($cm);

     $numentries = callforpaper_numentries($callforpaper);
    $canmanageentries = has_capability('mod/callforpaper:manageentries', context_module::instance($cm->id));

    if ($callforpaper->entriesleft = callforpaper_get_entries_left_to_add($callforpaper, $numentries, $canmanageentries)) {
        $entriesnode = $navigation->add(get_string('entrieslefttoadd', 'callforpaper', $callforpaper));
        $entriesnode->add_class('note');
    }

    $navigation->add(get_string('list', 'callforpaper'), new moodle_url('/mod/callforpaper/view.php', array('d'=>$cm->instance)));
    if (!empty($rid)) {
        $navigation->add(get_string('single', 'callforpaper'), new moodle_url('/mod/callforpaper/view.php', array('d'=>$cm->instance, 'rid'=>$rid)));
    } else {
        $navigation->add(get_string('single', 'callforpaper'), new moodle_url('/mod/callforpaper/view.php', array('d'=>$cm->instance, 'mode'=>'single')));
    }
    $navigation->add(get_string('search', 'callforpaper'), new moodle_url('/mod/callforpaper/view.php', array('d'=>$cm->instance, 'mode'=>'asearch')));
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $callforpapernode The node to add module settings to
 */
function callforpaper_extend_settings_navigation(settings_navigation $settings, navigation_node $callforpapernode) {
    global $DB, $CFG, $USER;

    $callforpaper = $DB->get_record('callforpaper', array("id" => $settings->get_page()->cm->instance));

    $currentgroup = groups_get_activity_group($settings->get_page()->cm);
    $groupmode = groups_get_activity_groupmode($settings->get_page()->cm);

    // Took out participation list here!
    if (callforpaper_user_can_add_entry($callforpaper, $currentgroup, $groupmode, $settings->get_page()->cm->context)) {
        if (empty($editentry)) { //TODO: undefined
            $addstring = get_string('add', 'callforpaper');
        } else {
            $addstring = get_string('editentry', 'callforpaper');
        }
        $addentrynode = $callforpapernode->add($addstring,
            new moodle_url('/mod/callforpaper/edit.php', array('d' => $settings->get_page()->cm->instance)));
        $addentrynode->set_show_in_secondary_navigation(false);
    }

    if (has_capability(CALLFORPAPER_CAP_EXPORT, $settings->get_page()->cm->context)) {
        // The capability required to Export callforpaper records is centrally defined in 'lib.php'
        // and should be weaker than those required to edit Templates, Fields and Presets.
        $exportentriesnode = $callforpapernode->add(get_string('exportentries', 'callforpaper'),
            new moodle_url('/mod/callforpaper/export.php', array('d' => $callforpaper->id)));
        $exportentriesnode->set_show_in_secondary_navigation(false);
    }
    if (has_capability('mod/callforpaper:manageentries', $settings->get_page()->cm->context)) {
        $importentriesnode = $callforpapernode->add(get_string('importentries', 'callforpaper'),
            new moodle_url('/mod/callforpaper/import.php', array('d' => $callforpaper->id)));
        $importentriesnode->set_show_in_secondary_navigation(false);
    }

    if (has_capability('mod/callforpaper:reviewentry', $settings->get_page()->cm->context)) {
        $reviewentriesnode = $callforpapernode->add(
            get_string('reviewentries', 'callforpaper'),
            new moodle_url('/mod/callforpaper/reviewview.php', array('d' => $callforpaper->id)),
            navigation_node::TYPE_CUSTOM,
            null,
            'reviewentries',
        );
    }

    if (has_capability('mod/callforpaper:managetemplates', $settings->get_page()->cm->context)) {
        $currenttab = '';
        if ($currenttab == 'list') {
            $defaultemplate = 'listtemplate';
        } else if ($currenttab == 'add') {
            $defaultemplate = 'addtemplate';
        } else if ($currenttab == 'asearch') {
            $defaultemplate = 'asearchtemplate';
        } else {
            $defaultemplate = 'singletemplate';
        }

        $callforpapernode->add(
            get_string('presets', 'callforpaper'),
            new moodle_url('/mod/callforpaper/preset.php', array('d' => $callforpaper->id)),
            navigation_node::TYPE_CUSTOM,
            null,
            'presets',
        );
        $callforpapernode->add(
            get_string('fields', 'callforpaper'),
            new moodle_url('/mod/callforpaper/field.php', array('d' => $callforpaper->id)),
            navigation_node::TYPE_CUSTOM,
            null,
            'fields',
        );
        $callforpapernode->add(
            get_string('templates', 'callforpaper'),
            new moodle_url('/mod/callforpaper/templates.php', array('d' => $callforpaper->id)),
            navigation_node::TYPE_CUSTOM,
            null,
            'templates',
        );
    }

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->callforpaper_enablerssfeeds) && $callforpaper->rssarticles > 0) {
        require_once("$CFG->libdir/rsslib.php");

        $string = get_string('rsstype', 'callforpaper');

        $url = new moodle_url(rss_get_url($settings->get_page()->cm->context->id, $USER->id, 'mod_callforpaper', $callforpaper->id));
        $callforpapernode->add($string, $url, settings_navigation::TYPE_SETTING, null, null, new pix_icon('i/rss', ''));
    }
}

/**
 * Running addtional permission check on plugin, for example, plugins
 * may have switch to turn on/off comments option, this callback will
 * affect UI display, not like pluginname_comment_validate only throw
 * exceptions.
 * Capability check has been done in comment->check_permissions(), we
 * don't need to do it again here.
 *
 * @package  mod_callforpaper
 * @category comment
 *
 * @param stdClass $comment_param {
 *              context  => context the context object
 *              courseid => int course id
 *              cm       => stdClass course module object
 *              commentarea => string comment area
 *              itemid      => int itemid
 * }
 * @return array
 */
function callforpaper_comment_permissions($comment_param) {
    global $CFG, $DB;
    if (!$record = $DB->get_record('callforpaper_records', array('id'=>$comment_param->itemid))) {
        throw new comment_exception('invalidcommentitemid');
    }
    if (!$callforpaper = $DB->get_record('callforpaper', array('id'=>$record->callforpaperid))) {
        throw new comment_exception('invalidid', 'callforpaper');
    }
    if ($callforpaper->comments) {
        return array('post'=>true, 'view'=>true);
    } else {
        return array('post'=>false, 'view'=>false);
    }
}

/**
 * Validate comment parameter before perform other comments actions
 *
 * @package  mod_callforpaper
 * @category comment
 *
 * @param stdClass $comment_param {
 *              context  => context the context object
 *              courseid => int course id
 *              cm       => stdClass course module object
 *              commentarea => string comment area
 *              itemid      => int itemid
 * }
 * @return boolean
 */
function callforpaper_comment_validate($comment_param) {
    global $DB;
    // validate comment area
    if ($comment_param->commentarea != 'callforpaper_entry') {
        throw new comment_exception('invalidcommentarea');
    }
    // validate itemid
    if (!$record = $DB->get_record('callforpaper_records', array('id'=>$comment_param->itemid))) {
        throw new comment_exception('invalidcommentitemid');
    }
    if (!$callforpaper = $DB->get_record('callforpaper', array('id'=>$record->callforpaperid))) {
        throw new comment_exception('invalidid', 'callforpaper');
    }
    if (!$course = $DB->get_record('course', array('id'=>$callforpaper->course))) {
        throw new comment_exception('coursemisconf');
    }
    if (!$cm = get_coursemodule_from_instance('callforpaper', $callforpaper->id, $course->id)) {
        throw new comment_exception('invalidcoursemodule');
    }
    if (!$callforpaper->comments) {
        throw new comment_exception('commentsoff', 'callforpaper');
    }
    $context = context_module::instance($cm->id);

    //check if approved
    if ($callforpaper->approval and !$record->approved and !callforpaper_isowner($record) and !has_capability('mod/callforpaper:approve', $context)) {
        throw new comment_exception('notapprovederror', 'callforpaper');
    }

    // group access
    if ($record->groupid) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
            if (!groups_is_member($record->groupid)) {
                throw new comment_exception('notmemberofgroup');
            }
        }
    }
    // validate context id
    if ($context->id != $comment_param->context->id) {
        throw new comment_exception('invalidcontext');
    }
    // validation for comment deletion
    if (!empty($comment_param->commentid)) {
        if ($comment = $DB->get_record('comments', array('id'=>$comment_param->commentid))) {
            if ($comment->commentarea != 'callforpaper_entry') {
                throw new comment_exception('invalidcommentarea');
            }
            if ($comment->contextid != $comment_param->context->id) {
                throw new comment_exception('invalidcontext');
            }
            if ($comment->itemid != $comment_param->itemid) {
                throw new comment_exception('invalidcommentitemid');
            }
        } else {
            throw new comment_exception('invalidcommentid');
        }
    }
    return true;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function callforpaper_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-callforpaper-*'=>get_string('page-mod-callforpaper-x', 'callforpaper'));
    return $module_pagetype;
}

/**
 * Get all of the record ids from a callforpaper activity.
 *
 * @param int    $callforpaperid      The callforpaperid of the callforpaper module.
 * @param object $selectcallforpaper  Contains an additional sql statement for the
 *                            where clause for group and approval fields.
 * @param array  $params      Parameters that coincide with the sql statement.
 * @return array $idarray     An array of record ids
 */
function callforpaper_get_all_recordids($callforpaperid, $selectcallforpaper = '', $params = null) {
    global $DB;
    $initsql = 'SELECT r.id
                  FROM {callforpaper_records} r
                 WHERE r.callforpaperid = :callforpaperid';
    if ($selectcallforpaper != '') {
        $initsql .= $selectcallforpaper;
        $params = array_merge(array('callforpaperid' => $callforpaperid), $params);
    } else {
        $params = array('callforpaperid' => $callforpaperid);
    }
    $initsql .= ' GROUP BY r.id';
    $initrecord = $DB->get_recordset_sql($initsql, $params);
    $idarray = array();
    foreach ($initrecord as $callforpaper) {
        $idarray[] = $callforpaper->id;
    }
    // Close the record set and free up resources.
    $initrecord->close();
    return $idarray;
}

/**
 * Get the ids of all the records that match that advanced search criteria
 * This goes and loops through each criterion one at a time until it either
 * runs out of records or returns a subset of records.
 *
 * @param array $recordids    An array of record ids.
 * @param array $searcharray  Contains information for the advanced search criteria
 * @param int $callforpaperid         The callforpaper id of the callforpaper.
 * @return array $recordids   An array of record ids.
 */
function callforpaper_get_advance_search_ids($recordids, $searcharray, $callforpaperid) {
    // Check to see if we have any record IDs.
    if (empty($recordids)) {
        // Send back an empty search.
        return array();
    }
    $searchcriteria = array_keys($searcharray);
    // Loop through and reduce the IDs one search criteria at a time.
    foreach ($searchcriteria as $key) {
        $recordids = callforpaper_get_recordids($key, $searcharray, $callforpaperid, $recordids);
        // If we don't have anymore IDs then stop.
        if (!$recordids) {
            break;
        }
    }
    return $recordids;
}

/**
 * Gets the record IDs given the search criteria
 *
 * @param string $alias       Record alias.
 * @param array $searcharray  Criteria for the search.
 * @param int $callforpaperid         Call for paper ID for the callforpaper
 * @param array $recordids    An array of record IDs.
 * @return array $nestarray   An arry of record IDs
 */
function callforpaper_get_recordids($alias, $searcharray, $callforpaperid, $recordids) {
    global $DB;
    $searchcriteria = $alias;   // Keep the criteria.
    $nestsearch = $searcharray[$alias];
    // searching for content outside of mdl_callforpaper_content
    if ($alias < 0) {
        $alias = '';
    }
    list($insql, $params) = $DB->get_in_or_equal($recordids, SQL_PARAMS_NAMED);
    $nestselect = 'SELECT c' . $alias . '.recordid
                     FROM {callforpaper_content} c' . $alias . '
               INNER JOIN {callforpaper_fields} f
                       ON f.id = c' . $alias . '.fieldid
               INNER JOIN {callforpaper_records} r
                       ON r.id = c' . $alias . '.recordid
               INNER JOIN {user} u
                       ON u.id = r.userid ';
    $nestwhere = 'WHERE r.callforpaperid = :callforpaperid
                    AND c' . $alias .'.recordid ' . $insql . '
                    AND ';

    $params['callforpaperid'] = $callforpaperid;
    if (count($nestsearch->params) != 0) {
        $params = array_merge($params, $nestsearch->params);
        $nestsql = $nestselect . $nestwhere . $nestsearch->sql;
    } else if ($searchcriteria == CALLFORPAPER_TIMEMODIFIED) {
        $nestsql = $nestselect . $nestwhere . $nestsearch->field . ' >= :timemodified GROUP BY c' . $alias . '.recordid';
        $params['timemodified'] = $nestsearch->callforpaper;
    } else if ($searchcriteria == CALLFORPAPER_TAGS) {
        if (empty($nestsearch->rawtagnames)) {
            return [];
        }
        $i = 0;
        $tagwhere = [];
        $tagselect = '';
        foreach ($nestsearch->rawtagnames as $tagrawname) {
            $tagselect .= " INNER JOIN {tag_instance} ti_$i
                                    ON ti_$i.component = 'mod_callforpaper'
                                   AND ti_$i.itemtype = 'callforpaper_records'
                                   AND ti_$i.itemid = r.id
                            INNER JOIN {tag} t_$i
                                    ON ti_$i.tagid = t_$i.id ";
            $tagwhere[] = " t_$i.rawname = :trawname_$i ";
            $params["trawname_$i"] = $tagrawname;
            $i++;
        }
        $nestsql = $nestselect . $tagselect . $nestwhere . implode(' AND ', $tagwhere);
    } else {    // First name or last name.
        $thing = $DB->sql_like($nestsearch->field, ':search1', false);
        $nestsql = $nestselect . $nestwhere . $thing . ' GROUP BY c' . $alias . '.recordid';
        $params['search1'] = "%$nestsearch->callforpaper%";
    }
    $nestrecords = $DB->get_recordset_sql($nestsql, $params);
    $nestarray = array();
    foreach ($nestrecords as $callforpaper) {
        $nestarray[] = $callforpaper->recordid;
    }
    // Close the record set and free up resources.
    $nestrecords->close();
    return $nestarray;
}

/**
 * Returns an array with an sql string for advanced searches and the parameters that go with them.
 *
 * @param int $sort            CALLFORPAPER_*
 * @param stdClass $callforpaper      Call for paper module object
 * @param array $recordids     An array of record IDs.
 * @param string $selectcallforpaper   Information for the where and select part of the sql statement.
 * @param string $sortorder    Additional sort parameters
 * @return array sqlselect     sqlselect['sql'] has the sql string, sqlselect['params'] contains an array of parameters.
 */
function callforpaper_get_advanced_search_sql($sort, $callforpaper, $recordids, $selectcallforpaper, $sortorder) {
    global $DB;

    $userfieldsapi = \core_user\fields::for_userpic()->excluding('id');
    $namefields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

    if ($sort == 0) {
        $nestselectsql = 'SELECT r.id, r.approved, r.timecreated, r.timemodified, r.userid, ' . $namefields . '
                        FROM {callforpaper_content} c,
                             {callforpaper_records} r,
                             {user} u ';
        $groupsql = ' GROUP BY r.id, r.approved, r.timecreated, r.timemodified, r.userid, u.firstname, u.lastname, ' . $namefields;
    } else {
        // Sorting through 'Other' criteria
        if ($sort <= 0) {
            switch ($sort) {
                case CALLFORPAPER_LASTNAME:
                    $sortcontentfull = "u.lastname";
                    break;
                case CALLFORPAPER_FIRSTNAME:
                    $sortcontentfull = "u.firstname";
                    break;
                case CALLFORPAPER_APPROVED:
                    $sortcontentfull = "r.approved";
                    break;
                case CALLFORPAPER_TIMEMODIFIED:
                    $sortcontentfull = "r.timemodified";
                    break;
                case CALLFORPAPER_TIMEADDED:
                default:
                    $sortcontentfull = "r.timecreated";
            }
        } else {
            $sortfield = callforpaper_get_field_from_id($sort, $callforpaper);
            $sortcontent = $DB->sql_compare_text('c.' . $sortfield->get_sort_field());
            $sortcontentfull = $sortfield->get_sort_sql($sortcontent);
        }

        $nestselectsql = 'SELECT r.id, r.approved, r.timecreated, r.timemodified, r.userid, ' . $namefields . ',
                                 ' . $sortcontentfull . '
                              AS sortorder
                            FROM {callforpaper_content} c,
                                 {callforpaper_records} r,
                                 {user} u ';
        $groupsql = ' GROUP BY r.id, r.approved, r.timecreated, r.timemodified, r.userid, ' . $namefields . ', ' .$sortcontentfull;
    }

    // Default to a standard Where statement if $selectcallforpaper is empty.
    if ($selectcallforpaper == '') {
        $selectcallforpaper = 'WHERE c.recordid = r.id
                         AND r.callforpaperid = :callforpaperid
                         AND r.userid = u.id ';
    }

    // Find the field we are sorting on
    if ($sort > 0 or callforpaper_get_field_from_id($sort, $callforpaper)) {
        $selectcallforpaper .= ' AND c.fieldid = :sort AND s.recordid = r.id';
        $nestselectsql .= ',{callforpaper_content} s ';
    }

    // If there are no record IDs then return an sql statment that will return no rows.
    if (count($recordids) != 0) {
        list($insql, $inparam) = $DB->get_in_or_equal($recordids, SQL_PARAMS_NAMED);
    } else {
        list($insql, $inparam) = $DB->get_in_or_equal(array('-1'), SQL_PARAMS_NAMED);
    }
    $nestfromsql = $selectcallforpaper . ' AND c.recordid ' . $insql . $groupsql;
    $sqlselect['sql'] = "$nestselectsql $nestfromsql $sortorder";
    $sqlselect['params'] = $inparam;
    return $sqlselect;
}

/**
 * Delete a record entry.
 *
 * @param int $recordid The ID for the record to be deleted.
 * @param object $callforpaper The callforpaper object for this activity.
 * @param int $courseid ID for the current course (for logging).
 * @param int $cmid The course module ID.
 * @return bool True if the record deleted, false if not.
 */
function callforpaper_delete_record($recordid, $callforpaper, $courseid, $cmid) {
    global $DB, $CFG;

    if ($deleterecord = $DB->get_record('callforpaper_records', array('id' => $recordid))) {
        if ($deleterecord->callforpaperid == $callforpaper->id) {
            if ($contents = $DB->get_records('callforpaper_content', array('recordid' => $deleterecord->id))) {
                foreach ($contents as $content) {
                    if ($field = callforpaper_get_field_from_id($content->fieldid, $callforpaper)) {
                        $field->delete_content($content->recordid);
                    }
                }
                $DB->delete_records('callforpaper_content', array('recordid'=>$deleterecord->id));
                $DB->delete_records('callforpaper_records', array('id'=>$deleterecord->id));

                // Delete cached RSS feeds.
                if (!empty($CFG->enablerssfeeds)) {
                    require_once($CFG->dirroot.'/mod/callforpaper/rsslib.php');
                    callforpaper_rss_delete_file($callforpaper);
                }

                core_tag_tag::remove_all_item_tags('mod_callforpaper', 'callforpaper_records', $recordid);

                // Trigger an event for deleting this record.
                $event = \mod_callforpaper\event\record_deleted::create(array(
                    'objectid' => $deleterecord->id,
                    'context' => context_module::instance($cmid),
                    'courseid' => $courseid,
                    'other' => array(
                        'callforpaperid' => $deleterecord->callforpaperid
                    )
                ));
                $event->add_record_snapshot('callforpaper_records', $deleterecord);
                $event->trigger();
                $course = get_course($courseid);
                $cm = get_coursemodule_from_instance('callforpaper', $callforpaper->id, 0, false, MUST_EXIST);
                callforpaper_update_completion_state($callforpaper, $course, $cm);

                return true;
            }
        }
    }

    return false;
}

/**
 * Check for required fields, and build a list of fields to be updated in a
 * submission.
 *
 * @param $mod stdClass The current recordid - provided as an optimisation.
 * @param $fields array The field callforpaper
 * @param $callforpaperrecord stdClass The submitted callforpaper.
 * @return stdClass containing:
 * * string[] generalnotifications Notifications for the form as a whole.
 * * string[] fieldnotifications Notifications for a specific field.
 * * bool validated Whether the field was validated successfully.
 * * callforpaper_field_base[] fields The field objects to be update.
 */
function callforpaper_process_submission(stdClass $mod, $fields, stdClass $callforpaperrecord) {
    $result = new stdClass();

    // Empty form checking - you can't submit an empty form.
    $emptyform = true;
    $requiredfieldsfilled = true;
    $fieldsvalidated = true;

    // Store the notifications.
    $result->generalnotifications = array();
    $result->fieldnotifications = array();

    // Store the instantiated classes as an optimisation when processing the result.
    // This prevents the fields being re-initialised when updating.
    $result->fields = array();

    $submitteddata = array();
    foreach ($callforpaperrecord as $fieldname => $fieldvalue) {
        if (strpos($fieldname, '_')) {
            $namearray = explode('_', $fieldname, 3);
            $fieldid = $namearray[1];
            if (!isset($submitteddata[$fieldid])) {
                $submitteddata[$fieldid] = array();
            }
            if (count($namearray) === 2) {
                $subfieldid = 0;
            } else {
                $subfieldid = $namearray[2];
            }

            $fieldcallforpaper = new stdClass();
            $fieldcallforpaper->fieldname = $fieldname;
            $fieldcallforpaper->value = $fieldvalue;
            $submitteddata[$fieldid][$subfieldid] = $fieldcallforpaper;
        }
    }

    // Check all form fields which have the required are filled.
    foreach ($fields as $fieldrecord) {
        // Check whether the field has any callforpaper.
        $fieldhascontent = false;

        $field = callforpaper_get_field($fieldrecord, $mod);
        if (isset($submitteddata[$fieldrecord->id])) {
            // Field validation check.
            if (method_exists($field, 'field_validation')) {
                $errormessage = $field->field_validation($submitteddata[$fieldrecord->id]);
                if ($errormessage) {
                    $result->fieldnotifications[$field->field->name][] = $errormessage;
                    $fieldsvalidated = false;
                }
            }
            foreach ($submitteddata[$fieldrecord->id] as $fieldname => $value) {
                if ($field->notemptyfield($value->value, $value->fieldname)) {
                    // The field has content and the form is not empty.
                    $fieldhascontent = true;
                    $emptyform = false;
                }
            }
        }

        // If the field is required, add a notification to that effect.
        if ($field->field->required && !$fieldhascontent) {
            if (!isset($result->fieldnotifications[$field->field->name])) {
                $result->fieldnotifications[$field->field->name] = array();
            }
            $result->fieldnotifications[$field->field->name][] = get_string('errormustsupplyvalue', 'callforpaper');
            $requiredfieldsfilled = false;
        }

        // Update the field.
        if (isset($submitteddata[$fieldrecord->id])) {
            foreach ($submitteddata[$fieldrecord->id] as $value) {
                $result->fields[$value->fieldname] = $field;
            }
        }
    }

    if ($emptyform) {
        // The form is empty.
        $result->generalnotifications[] = get_string('emptyaddform', 'callforpaper');
    }

    $result->validated = $requiredfieldsfilled && !$emptyform && $fieldsvalidated;

    return $result;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every callforpaper event in the site is checked, else
 * only callforpaper events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param int $courseid
 * @param int|stdClass $instance Call for paper module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function callforpaper_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/callforpaper/locallib.php');

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('callforpaper', array('id' => $instance), '*', MUST_EXIST);
        }
        callforpaper_set_events($instance);
        return true;
    }

    if ($courseid) {
        if (! $callforpaper = $DB->get_records("callforpaper", array("course" => $courseid))) {
            return true;
        }
    } else {
        if (! $callforpaper = $DB->get_records("callforpaper")) {
            return true;
        }
    }

    foreach ($callforpaper as $datum) {
        callforpaper_set_events($datum);
    }
    return true;
}

/**
 * Fetch the configuration for this callforpaper activity.
 *
 * @param   stdClass    $callforpaper   The object returned from the callforpaper for this instance
 * @param   string      $key        The name of the key to retrieve. If none is supplied, then all configuration is returned
 * @param   mixed       $default    The default value to use if no value was found for the specified key
 * @return  mixed                   The returned value
 */
function callforpaper_get_config($callforpaper, $key = null, $default = null) {
    if (!empty($callforpaper->config)) {
        $config = json_decode($callforpaper->config);
    } else {
        $config = new stdClass();
    }

    if ($key === null) {
        return $config;
    }

    if (property_exists($config, $key)) {
        return $config->$key;
    }
    return $default;
}

/**
 * Update the configuration for this callforpaper activity.
 *
 * @param   stdClass    $callforpaper   The object returned from the callforpaper for this instance
 * @param   string      $key        The name of the key to set
 * @param   mixed       $value      The value to set for the key
 */
function callforpaper_set_config(&$callforpaper, $key, $value) {
    // Note: We must pass $callforpaper by reference because there may be subsequent calls to update_record and these should
    // not overwrite the configuration just set.
    global $DB;

    $config = callforpaper_get_config($callforpaper);

    if (!isset($config->$key) || $config->$key !== $value) {
        $config->$key = $value;
        $callforpaper->config = json_encode($config);
        $DB->set_field('callforpaper', 'config', $callforpaper->config, ['id' => $callforpaper->id]);
    }
}
/**
 * Sets the automatic completion state for this callforpaper item based on the
 * count of on its entries.
 * @since Moodle 3.3
 * @param object $callforpaper The callforpaper object for this activity
 * @param object $course Course
 * @param object $cm course-module
 */
function callforpaper_update_completion_state($callforpaper, $course, $cm) {
    // If completion option is enabled, evaluate it and return true/false.
    $completion = new completion_info($course);
    if ($callforpaper->completionentries && $completion->is_enabled($cm)) {
        $numentries = callforpaper_numentries($callforpaper);
        // Check the number of entries required against the number of entries already made.
        if ($numentries >= $callforpaper->completionentries) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        } else {
            $completion->update_state($cm, COMPLETION_INCOMPLETE);
        }
    }
}

/**
 * Get icon mapping for font-awesome.
 */
function mod_callforpaper_get_fontawesome_icon_map() {
    return [
        'mod_callforpaper:t/review' => 'fa-hand-holding-heart'
    ];
}

/*
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module callforpaper
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function callforpaper_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/callforpaper/locallib.php');

    $updates = course_check_module_updates_since($cm, $from, array(), $filter);

    // Check for new entries.
    $updates->entries = (object) array('updated' => false);

    $callforpaper = $DB->get_record('callforpaper', array('id' => $cm->instance), '*', MUST_EXIST);
    $searcharray = [];
    $searcharray[CALLFORPAPER_TIMEMODIFIED] = new stdClass();
    $searcharray[CALLFORPAPER_TIMEMODIFIED]->sql     = '';
    $searcharray[CALLFORPAPER_TIMEMODIFIED]->params  = array();
    $searcharray[CALLFORPAPER_TIMEMODIFIED]->field   = 'r.timemodified';
    $searcharray[CALLFORPAPER_TIMEMODIFIED]->callforpaper    = $from;

    $currentgroup = groups_get_activity_group($cm);
    // Teachers should retrieve all entries when not in separate groups.
    if (has_capability('mod/callforpaper:manageentries', $cm->context) && groups_get_activity_groupmode($cm) != SEPARATEGROUPS) {
        $currentgroup = 0;
    }
    list($entries, $maxcount, $totalcount, $page, $nowperpage, $sort, $mode) =
        callforpaper_search_entries($callforpaper, $cm, $cm->context, 'list', $currentgroup, '', null, null, 0, 0, true, $searcharray);

    if (!empty($entries)) {
        $updates->entries->updated = true;
        $updates->entries->itemids = array_keys($entries);
    }

    return $updates;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_callforpaper_core_calendar_provide_event_action(calendar_event $event,
                                                     \core_calendar\action_factory $factory,
                                                     int $userid = 0) {
    global $USER;

    if (!$userid) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['callforpaper'][$event->instance];

    if (!$cm->uservisible) {
        // The module is not visible to the user for any reason.
        return null;
    }

    $now = time();

    if (!empty($cm->customdata['timeavailableto']) && $cm->customdata['timeavailableto'] < $now) {
        // The module has closed so the user can no longer submit anything.
        return null;
    }

    // The module is actionable if we don't have a start time or the start time is
    // in the past.
    $actionable = (empty($cm->customdata['timeavailablefrom']) || $cm->customdata['timeavailablefrom'] <= $now);

    return $factory->create_instance(
        get_string('add', 'callforpaper'),
        new \moodle_url('/mod/callforpaper/view.php', array('id' => $cm->id)),
        1,
        $actionable
    );
}

/**
 * Add a get_coursemodule_info function in case any callforpaper type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function callforpaper_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionentries, timeavailablefrom, timeavailableto';
    if (!$callforpaper = $DB->get_record('callforpaper', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $callforpaper->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('callforpaper', $callforpaper, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionentries'] = $callforpaper->completionentries;
    }
    // Other properties that may be used in calendar or on dashboard.
    if ($callforpaper->timeavailablefrom) {
        $result->customdata['timeavailablefrom'] = $callforpaper->timeavailablefrom;
    }
    if ($callforpaper->timeavailableto) {
        $result->customdata['timeavailableto'] = $callforpaper->timeavailableto;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_callforpaper_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionentries':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionentriesdesc', 'callforpaper', $val);
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

/**
 * This function calculates the minimum and maximum cutoff values for the timestart of
 * the given event.
 *
 * It will return an array with two values, the first being the minimum cutoff value and
 * the second being the maximum cutoff value. Either or both values can be null, which
 * indicates there is no minimum or maximum, respectively.
 *
 * If a cutoff is required then the function must return an array containing the cutoff
 * timestamp and error string to display to the user if the cutoff value is violated.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The due date must be after the sbumission start date'],
 *     [1506741172, 'The due date must be before the cutoff date']
 * ]
 *
 * @param calendar_event $event The calendar event to get the time range for
 * @param stdClass $instance The module instance to get the range from
 * @return array
 */
function mod_callforpaper_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $instance) {
    $mindate = null;
    $maxdate = null;

    if ($event->eventtype == CALLFORPAPER_EVENT_TYPE_OPEN) {
        // The start time of the open event can't be equal to or after the
        // close time of the callforpaper activity.
        if (!empty($instance->timeavailableto)) {
            $maxdate = [
                $instance->timeavailableto,
                get_string('openafterclose', 'callforpaper')
            ];
        }
    } else if ($event->eventtype == CALLFORPAPER_EVENT_TYPE_CLOSE) {
        // The start time of the close event can't be equal to or earlier than the
        // open time of the callforpaper activity.
        if (!empty($instance->timeavailablefrom)) {
            $mindate = [
                $instance->timeavailablefrom,
                get_string('closebeforeopen', 'callforpaper')
            ];
        }
    }

    return [$mindate, $maxdate];
}

/**
 * This function will update the callforpaper module according to the
 * event that has been modified.
 *
 * It will set the timeopen or timeclose value of the callforpaper instance
 * according to the type of event provided.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event
 * @param stdClass $callforpaper The module instance to get the range from
 */
function mod_callforpaper_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $callforpaper) {
    global $DB;

    if (empty($event->instance) || $event->modulename != 'callforpaper') {
        return;
    }

    if ($event->instance != $callforpaper->id) {
        return;
    }

    if (!in_array($event->eventtype, [CALLFORPAPER_EVENT_TYPE_OPEN, CALLFORPAPER_EVENT_TYPE_CLOSE])) {
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;

    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if ($event->eventtype == CALLFORPAPER_EVENT_TYPE_OPEN) {
        // If the event is for the callforpaper activity opening then we should
        // set the start time of the callforpaper activity to be the new start
        // time of the event.
        if ($callforpaper->timeavailablefrom != $event->timestart) {
            $callforpaper->timeavailablefrom = $event->timestart;
            $callforpaper->timemodified = time();
            $modified = true;
        }
    } else if ($event->eventtype == CALLFORPAPER_EVENT_TYPE_CLOSE) {
        // If the event is for the callforpaper activity closing then we should
        // set the end time of the callforpaper activity to be the new start
        // time of the event.
        if ($callforpaper->timeavailableto != $event->timestart) {
            $callforpaper->timeavailableto = $event->timestart;
            $modified = true;
        }
    }

    if ($modified) {
        $callforpaper->timemodified = time();
        $DB->update_record('callforpaper', $callforpaper);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}

/**
 * Callback to fetch the activity event type lang string.
 *
 * @param string $eventtype The event type.
 * @return lang_string The event type lang string.
 */
function mod_callforpaper_core_calendar_get_event_action_string(string $eventtype): string {
    $modulename = get_string('modulename', 'callforpaper');

    switch ($eventtype) {
        case CALLFORPAPER_EVENT_TYPE_OPEN:
            $identifier = 'calendarstart';
            break;
        case CALLFORPAPER_EVENT_TYPE_CLOSE:
            $identifier = 'calendarend';
            break;
        default:
            return get_string('requiresaction', 'calendar', $modulename);
    }

    return get_string($identifier, 'callforpaper', $modulename);
}
