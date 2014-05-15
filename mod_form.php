<?php

/**
 * This file defines the main reengagement configuration form
 * It uses the standard core Moodle (>1.8) formslib. For
 * more info about them, please visit:
 *
 * http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * The form must provide support for, at least these fields:
 *   - name: text element of 64cc max
 *
 * Also, it's usual to use these fields:
 *   - intro: one htmlarea element to describe the activity
 *            (will be showed in the list of activities of
 *             reengagement type (index.php) and in the header
 *             of the reengagement main page (view.php).
 *   - introformat: The format used to write the contents
 *             of the intro field. It automatically defaults
 *             to HTML when the htmleditor is used and can be
 *             manually selected if the htmleditor is not used
 *             (standard formats are: MOODLE, HTML, PLAIN, MARKDOWN)
 *             See lib/weblib.php Constants and the format_text()
 *             function for more info
 */

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_reengagement_mod_form extends moodleform_mod {

    function definition() {

        global $COURSE;
        $mform =& $this->_form;

//-------------------------------------------------------------------------------
    /// Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

    /// Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('reengagementname', 'reengagement'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

    /// Adding the required "intro" field to hold the description of the instance
        $this->add_intro_editor(true, get_string('reengagementintro', 'reengagement'));


//-------------------------------------------------------------------------------
    /// Adding the rest of reengagement settings, spreeading all them into this fieldset
    /// or adding more fieldsets ('header' elements) if needed for better logic
        $mform->addElement('header', 'reengagementfieldset', get_string('reengagementfieldset', 'reengagement'));

        /// Adding email detail fields:
        $emailuseroptions = array(); // The sorts of emailing this module might do.
        $emailuseroptions[REENGAGEMENT_EMAILUSER_NEVER] = get_string('never', 'reengagement');
        $emailuseroptions[REENGAGEMENT_EMAILUSER_COMPLETION] = get_string('oncompletion', 'reengagement');
        $emailuseroptions[REENGAGEMENT_EMAILUSER_TIME] = get_string('afterdelay', 'reengagement');
        
        $mform->addElement('select', 'emailuser', get_string('emailuser', 'reengagement'), $emailuseroptions);
        $mform->addHelpButton('emailuser', 'emailuser','reengagement');

        // Add a group of controls to specify after how long an email should be sent.
        $emaildelay;
        $periods = array();
        $periods[60] = get_string('minutes','reengagement');
        $periods[3600] = get_string('hours','reengagement');
        $periods[86400] = get_string('days','reengagement');
        $periods[604800] = get_string('weeks','reengagement');
        $emaildelay[] = $mform->createElement('text', 'emailperiodcount', '', array('class="emailperiodcount"'));
        $emaildelay[] = $mform->createElement('select', 'emailperiod', '', $periods);
        $mform->addGroup($emaildelay, 'emaildelay', get_string('emaildelay','reengagement'), array(' '), false);
        $mform->addHelpButton('emaildelay', 'emaildelay', 'reengagement');
        $mform->setType('emailperiodcount', PARAM_INT);
        $mform->setDefault('emailperiodcount','1');
        $mform->setDefault('emailperiod','604800');

        $mform->addElement('editor', 'usertext', get_string('usertext', 'reengagement'),array('rows=5','cols=60'));
        $mform->setDefault('usertext', get_string('usertextdefaultvalue','reengagement'));
        $mform->setType('usertext', PARAM_RAW);
        $mform->addHelpButton('usertext', 'usertext', 'reengagement');

        $mform->addElement('advcheckbox', 'supressemail', get_string('supressemail', 'reengagement'));
        $mform->addHelpbutton('supressemail', 'supressemail', 'reengagement');
        $truemods = get_fast_modinfo($COURSE->id);
        $mods = array();
        $mods[0] = get_string('nosupresstarget', 'reengagement');
        foreach ($truemods->cms as $mod) {
            $mods[$mod->id] = $mod->name;
        }
        $mform->addElement('select', 'supresstarget', get_string('supresstarget', 'reengagement'), $mods);
        $mform->addHelpbutton('supresstarget', 'supresstarget', 'reengagement');

//-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();

    }
    function set_data ($toform) {
        // Form expects durations as a number of periods eg 5 minutes.
        // Process dbtime (seconds) into form-appropraite times.
        if (!empty($toform->duration)) {
            list ($periodcount, $period) = reengagement_get_readable_duration($toform->duration);
            $toform->period = $period;
            $toform->periodcount = $periodcount;
            unset($toform->duration);
        }
        if (!empty($toform->emaildelay)) {
            list ($periodcount, $period) = reengagement_get_readable_duration($toform->emaildelay);
            $toform->emailperiod = $period;
            $toform->emailperiodcount = $periodcount;
            unset($toform->emaildelay);
        }

        if (empty($toform->suppressemail)) {
            // Settings indicate that email shouldn't be suppressed based on another activites' completion.
            // Don't allow 'suppress target' ddb to specify any particular activity.
            $toform->suppresstarget = 0;
        }
        return parent::set_data($toform);
    }

    function get_data() {
        $fromform = parent::get_data();
        if (!empty($fromform)) {
            // Format, regulate module duration:
            if (isset($fromform->period) && isset($fromform->periodcount)) {
                $fromform->duration = $fromform->period * $fromform->periodcount;
            }
            if (empty($fromform->duration) || $fromform->duration < 300) {
                $fromform->duration = 300;
            }
            unset($fromform->period);
            unset($fromform->periodcount);
            // Format, regulate email notification delay:
            if (isset($fromform->emailperiod) && isset($fromform->emailperiodcount)) {
                $fromform->emaildelay = $fromform->emailperiod * $fromform->emailperiodcount;
            }
            if (empty($fromform->emaildelay) || $fromform->emaildelay < 300) {
                $fromform->emaildelay = 300;
            }
            unset($fromform->emailperiod);
            unset($fromform->emailperiodcount);
        }
        return $fromform;
    }
    /**
     * Can be overridden to add custom completion rules if the module wishes
     * them. If overriding this, you should also override completion_rule_enabled.
     * <p>
     * Just add elements to the form as needed and return the list of IDs. The
     * system will call disabledIf and handle other behaviour for each returned
     * ID.
     * @return array Array of string IDs of added items, empty array if none
     */
    function add_completion_rules() {
        $mform =& $this->_form;
        $periods = array();
        $periods[60] = get_string('minutes','reengagement');
        $periods[3600] = get_string('hours','reengagement');
        $periods[86400] = get_string('days','reengagement');
        $periods[604800] = get_string('weeks','reengagement');
        $duration[] = &$mform->createElement('text', 'periodcount', '', array('class="periodcount"'));
        $mform->setType('periodcount', PARAM_INT);
        #$mform->addRule('periodcount', get_string('errperiodcountnumeric', 'reengagement'), 'numeric', '', 'server', false, false);
        #$mform->addRule('periodcount', get_string('errperiodcountnumeric', 'reengagement'), 'numeric', '', 'client', false, false);
        $duration[] = &$mform->createElement('select', 'period', '', $periods);
        $mform->addGroup($duration, 'duration', get_string('reengagementduration','reengagement'), array(' '), false);
        $mform->addHelpButton('duration', 'duration', 'reengagement');
        $mform->setDefault('periodcount','1');
        $mform->setDefault('period','604800');
        return array('duration');
    }

    function completion_rule_enabled($data) {
        return true;
    }
}

?>
