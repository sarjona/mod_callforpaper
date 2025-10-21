<?php
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_callforpaper_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $DB, $OUTPUT;

        $mform =& $this->_form;

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 1333), 'maxlength', 1333, 'client');

        $this->standard_intro_elements(get_string('intro', 'callforpaper'));

        $mform->addElement('text', 'maxreviewers', get_string('maxreviewers', 'callforpaper'));
        $mform->setType('maxreviewers', PARAM_INT);
        $mform->addRule('maxreviewers', null, 'numeric', null, 'client');
        $mform->addRule('maxreviewers', null, 'required', null, 'client');

        // ----------------------------------------------------------------------
        $mform->addElement('header', 'entrieshdr', get_string('entries', 'callforpaper'));

        $mform->addElement('selectyesno', 'approval', get_string('requireapproval', 'callforpaper'));
        $mform->addHelpButton('approval', 'requireapproval', 'callforpaper');

        $mform->addElement('selectyesno', 'manageapproved', get_string('manageapproved', 'callforpaper'));
        $mform->addHelpButton('manageapproved', 'manageapproved', 'callforpaper');
        $mform->setDefault('manageapproved', 1);
        $mform->hideIf('manageapproved', 'approval', 'eq', 0);

        $mform->addElement('selectyesno', 'comments', get_string('allowcomments', 'callforpaper'));
        if (empty($CFG->usecomments)) {
            $mform->hardFreeze('comments');
            $mform->setConstant('comments', 0);
        }

        $countoptions = array(0=>get_string('none'))+
                        (array_combine(range(1, CALLFORPAPER_MAX_ENTRIES), // Keys.
                                        range(1, CALLFORPAPER_MAX_ENTRIES))); // Values.
        /*only show fields if there are legacy values from
         *before completionentries was added*/
        if (!empty($this->current->requiredentries)) {
            $group = array();
            $group[] = $mform->createElement('select', 'requiredentries',
                    get_string('requiredentries', 'callforpaper'), $countoptions);
            $mform->addGroup($group, 'requiredentriesgroup', get_string('requiredentries', 'callforpaper'), array(''), false);
            $mform->addHelpButton('requiredentriesgroup', 'requiredentries', 'callforpaper');
            $mform->addElement('html', $OUTPUT->notification( get_string('requiredentrieswarning', 'callforpaper')));
        }

        $mform->addElement('select', 'requiredentriestoview', get_string('requiredentriestoview', 'callforpaper'), $countoptions);
        $mform->addHelpButton('requiredentriestoview', 'requiredentriestoview', 'callforpaper');

        $mform->addElement('select', 'maxentries', get_string('maxentries', 'callforpaper'), $countoptions);
        $mform->addHelpButton('maxentries', 'maxentries', 'callforpaper');

        // ----------------------------------------------------------------------
        $mform->addElement('header', 'availibilityhdr', get_string('availability'));

        $mform->addElement('date_time_selector', 'timeavailablefrom', get_string('availablefromdate', 'callforpaper'),
                           array('optional' => true));

        $mform->addElement('date_time_selector', 'timeavailableto', get_string('availabletodate', 'callforpaper'),
                           array('optional' => true));

        $mform->addElement('date_time_selector', 'timeviewfrom', get_string('viewfromdate', 'callforpaper'),
                           array('optional' => true));

        $mform->addElement('date_time_selector', 'timeviewto', get_string('viewtodate', 'callforpaper'),
                           array('optional' => true));

        // ----------------------------------------------------------------------
        if ($CFG->enablerssfeeds && $CFG->callforpaper_enablerssfeeds) {
            $mform->addElement('header', 'rsshdr', get_string('rss'));
            $mform->addElement('select', 'rssarticles', get_string('numberrssarticles', 'callforpaper') , $countoptions);
        }

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();

//-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

    /**
     * Enforce validation rules here
     *
     * @param array $callforpaper array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array
     **/
    public function validation($callforpaper, $files) {
        $errors = parent::validation($callforpaper, $files);

        // Check open and close times are consistent.
        if ($callforpaper['timeavailablefrom'] && $callforpaper['timeavailableto'] &&
                $callforpaper['timeavailableto'] < $callforpaper['timeavailablefrom']) {
            $errors['timeavailableto'] = get_string('availabletodatevalidation', 'callforpaper');
        }
        if ($callforpaper['timeviewfrom'] && $callforpaper['timeviewto'] &&
                $callforpaper['timeviewto'] < $callforpaper['timeviewfrom']) {
            $errors['timeviewto'] = get_string('viewtodatevalidation', 'callforpaper');
        }

        return $errors;
    }

    /**
     * Display module-specific activity completion rules.
     * Part of the API defined by moodleform_mod
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform = & $this->_form;
        $group = [];

        $suffix = $this->get_suffix();
        $completionentriesenabledel = 'completionentriesenabled' . $suffix;
        $group[] = $mform->createElement(
            'checkbox',
            $completionentriesenabledel,
            '',
            get_string('completionentriescount', 'callforpaper')
        );
        $completionentriesel = 'completionentries' . $suffix;
        $group[] = $mform->createElement(
            'text',
            $completionentriesel,
            get_string('completionentriescount', 'callforpaper'),
            ['size' => '1']
        );

        $completionentriesgroupel = 'completionentriesgroup' . $suffix;
        $mform->addGroup(
            $group,
            $completionentriesgroupel,
            '',
            [' '],
            false
        );
        $mform->hideIf($completionentriesel, $completionentriesenabledel, 'notchecked');
        $mform->setDefault($completionentriesel, 1);
        $mform->setType($completionentriesel, PARAM_INT);
        /* This ensures the elements are disabled unless completion rules are enabled */
        return [$completionentriesgroupel];
    }

    /**
     * Called during validation. Indicates if a module-specific completion rule is selected.
     *
     * @param array $callforpaper
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($callforpaper) {
        $suffix = $this->get_suffix();
        return (!empty($callforpaper['completionentriesenabled' . $suffix]) && $callforpaper['completionentries' . $suffix] != 0);
    }

      /**
       * Set up the completion checkbox which is not part of standard data.
       *
       * @param array $defaultvalues
       *
       */
    public function callforpaper_preprocessing(&$defaultvalues) {
        parent::callforpaper_preprocessing($defaultvalues);

        $suffix = $this->get_suffix();
        $completionentriesenabledel = 'completionentriesenabled' . $suffix;
        $completionentriesel = 'completionentries' . $suffix;
        $defaultvalues[$completionentriesenabledel] = !empty($defaultvalues[$completionentriesel]) ? 1 : 0;
        if (empty($defaultvalues[$completionentriesel])) {
            $defaultvalues[$completionentriesel] = 1;
        }
    }

    /**
     * Allows modules to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $callforpaper the form data to be modified.
     */
    public function callforpaper_postprocessing($callforpaper) {
        parent::callforpaper_postprocessing($callforpaper);
        if (!empty($callforpaper->completionunlocked)) {
            $suffix = $this->get_suffix();
            $completionel = 'completion' . $suffix;
            $completionentriesenabledel = 'completionentriesenabled' . $suffix;
            $autocompletion = !empty($callforpaper->{$completionel}) && $callforpaper->{$completionel} == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($callforpaper->{$completionentriesenabledel}) || !$autocompletion) {
                $completionentriesel = 'completionentries' . $suffix;
                $callforpaper->{$completionentriesel} = 0;
            }
        }
    }

}
