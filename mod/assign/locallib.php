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
 * This file contains the definition for the class assignment
 *
 * This class provides all the functionality for the new assign module.
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Assignment submission statuses
 */
define('ASSIGN_SUBMISSION_STATUS_DRAFT', 'draft'); // student thinks it is a draft
define('ASSIGN_SUBMISSION_STATUS_SUBMITTED', 'submitted'); // student thinks it is finished

/**
 * Search filters for grading page 
 */
define('ASSIGN_FILTER_SUBMITTED', 'submitted');
define('ASSIGN_FILTER_REQUIRE_GRADING', 'require_grading');

/**
 * File areas for the assignment
 */

/**
 * File areas for assignment portfolio if enabled
 */
define('ASSIGN_FILEAREA_PORTFOLIO_FILES', 'portfolio_files');


/** Include accesslib.php */
require_once($CFG->libdir.'/accesslib.php');
/** Include formslib.php */
require_once($CFG->libdir.'/formslib.php');
/** Include plagiarismlib.php */
require_once($CFG->libdir . '/plagiarismlib.php');
/** Include repository/lib.php */
require_once($CFG->dirroot . '/repository/lib.php');
/** Include local mod_form.php */
require_once('mod_form.php');
/** Include portfoliolib.php */
require_once($CFG->libdir . '/portfoliolib.php');
/** gradelib.php */
require_once($CFG->libdir.'/gradelib.php');
/** grading lib.php */
require_once($CFG->dirroot.'/grade/grading/lib.php');
/** Include feedback_plugin.php */
require_once($CFG->dirroot.'/mod/assign/feedback_plugin.php');
/** Include submission_plugin.php */
require_once($CFG->dirroot.'/mod/assign/submission_plugin.php');
/** Include renderable.php */
require_once($CFG->dirroot.'/mod/assign/renderable.php');
/** Include grading_table.php */
require_once($CFG->dirroot.'/mod/assign/grading_table.php');

//send files to event system for plagiarism detection 
/** Include eventslib.php */
require_once($CFG->libdir.'/eventslib.php');


/*
 * Standard base class for mod_assign (assignment types).
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignment {
   
    
    /** @var stdClass the assignment record that contains the global settings for this assign instance */
    private $instance;

    /** @var context the context of the course module for this assign instance (or just the course if we are 
        creating a new one) */
    private $context;

    /** @var stdClass the course this assign instance belongs to */
    private $course;
    
    /** @var assign_renderer the custom renderer for this module */
    private $output;
    
    /** @var stdClass the course module for this assign instance */
    private $coursemodule;

    /** @var array cache for things like the coursemodule name or the scale menu - only lives for a single 
        request */
    private $cache;

    /** @var array list of the installed submission plugins */
    private $submissionplugins;
    
    /** @var array list of the installed feedback plugins */
    private $feedbackplugins;

    /** @var string action to be used to return to this page (without repeating any form submissions etc.) */
    private $returnaction = 'view';
    
    /** @var array params to be used to return to this page */
    private $returnparams = array();

    /** @var string modulename prevents excessive calls to get_string */
    private static $modulename = '';

    /** @var string modulenameplural prevents excessive calls to get_string */
    private static $modulenameplural = '';
    
    /**
     * Constructor for the base assign class
     *
     * @param mixed context|null $context the course module context (or the course context if the coursemodule has not been created yet)
     * @param mixed stdClass|null $coursemodule the current course module if it was already loaded - otherwise this class will load one from the context as required
     * @param mixed stdClass|null $course the current course  if it was already loaded - otherwise this class will load one from the context as required
     */
    public function __construct($coursemodulecontext, $coursemodule, $course) {
        global $PAGE;
        
        $this->context = $coursemodulecontext;
        $this->coursemodule = $coursemodule; 
        $this->course = $course; 
        $this->cache = array(); // temporary cache only lives for a single request - used to reduce db lookups

        $this->submissionplugins = $this->load_plugins('assignsubmission');
        $this->feedbackplugins = $this->load_plugins('assignfeedback');
        $this->output = $PAGE->get_renderer('mod_assign');
    }

    /**
     * Set the action and parameters that can be used to return to the current page
     *
     * @param string $action The action for the current page
     * @param array $params An array of name value pairs which form the parameters to return to the current page
     * @return void
     */
    public function register_return_link($action, $params) {
        $this->returnaction = $action;
        $this->returnparams = $params;
    }

    /** 
     * Return an action that can be used to get back to the current page
     * @return string action
     */
    public function get_return_action() {
        return $this->returnaction;
    }

    /** 
     * Return a list of parameters that can be used to get back to the current page
     * @return array params
     */
    public function get_return_params() {
        return $this->returnparams;
    }
    
    /** 
     * Set the submitted form data
     * @param stdClass data The form data (instance)
     */
    public function set_instance(stdClass $data) {
        return $this->instance = $data;
    }
    
    /** 
     * Set the context
     * @param context context The new context
     */
    public function set_context(context $context) {
        return $this->context = $context;
    }
    
    /** 
     * Set the course data
     * @param stdClass course The course data
     */
    public function set_course(stdClass $course) {
        return $this->course = $course;
    }

    /** 
     * get list of feedback plugins installed
     * @return array 
     */
    public function get_feedback_plugins() {
        return $this->feedbackplugins;
    }
    
    /** 
     * get list of submission plugins installed
     * @return array 
     */
    public function get_submission_plugins() {
        return $this->submissionplugins;
    }
    
    /** 
     * is_blind_marking
     * @return bool 
     */
    public function is_blind_marking() {
        return $this->get_instance()->blindmarking && !$this->get_instance()->revealidentities;
    }

    /**
     * get a specific submission plugin by its type
     * @param string $type
     * @return mixed assignment_plugin|null
     */
    private function get_plugin_by_type($subtype, $type) {
        $shortsubtype = substr($subtype, strlen('assign'));
        $name = $shortsubtype . 'plugins';
        $pluginlist = $this->$name;
        foreach ($pluginlist as $plugin) {
            if ($plugin->get_type() == $type) {
                return $plugin;
            }
        }
        return null;
    }

    /**
     * Get a feedback plugin by type
     * @param string $type - The type of plugin e.g comments
     * @return mixed assignment_feedback_plugin|null
     */
    public function get_feedback_plugin_by_type($type) {
        return $this->get_plugin_by_type('assignfeedback', $type);
    }

    /**
     * Get a submission plugin by type
     * @param string $type - The type of plugin e.g comments
     * @return mixed assignment_submission_plugin|null
     */
    public function get_submission_plugin_by_type($type) {
        return $this->get_plugin_by_type('assignsubmission', $type);
    }

    /**
     * Load the plugins from the sub folders under subtype
     * @param string subtype - either submission or feedback
     * @return array - The sorted list of plugins
     */
    private function load_plugins($subtype) {
        global $CFG;
        $result = array();

        $names = get_plugin_list($subtype);

        foreach ($names as $name => $path) {
            if (file_exists($path . '/lib.php')) {
                require_once($path . '/lib.php');

                $shortsubtype = substr($subtype, strlen('assign'));
                $pluginclass = 'assignment_' . $shortsubtype . '_' . $name;
                
                $plugin = new $pluginclass($this, $name);

                if ($plugin instanceof assignment_plugin) {
                    $idx = $plugin->get_sort_order();
                    while (array_key_exists($idx, $result)) $idx +=1;
                    $result[$idx] = $plugin;
                }
            }
        }
        ksort($result);
        return $result;
    }

    
    /**
     * Display the assignment, used by view.php
     *
     * The assignment is displayed differently depending on your role, 
     * the settings for the assignment and the status of the assignment.
     * @param string $action The current action if any.
     * @return void
     */
    public function view($action='') {

        $o = '';
        $mform = null;

        // handle form submissions first
        if ($action == 'savesubmission') {
            $action = 'editsubmission';
            if ($this->process_save_submission($mform)) {
                $action = 'view';
            }
         } else if ($action == 'lock') {
            $this->process_lock();
            $action = 'grading';
         } else if ($action == 'reverttodraft') {
            $this->process_revert_to_draft();
            $action = 'grading';
         } else if ($action == 'unlock') {
            $this->process_unlock();
            $action = 'grading';
         } else if ($action == 'confirmsubmit') {
            $this->process_submit_assignment_for_grading();
            // save and show next button
        } else if ($action == 'submitgrade') {
            if (optional_param('saveandshownext', null, PARAM_ALPHA)) {
                //save and show next
                $action = 'grade';
                if ($this->process_save_grade($mform)) {
                    $action = 'nextgrade';
                }
            } else if (optional_param('nosaveandnext', null, PARAM_ALPHA)) { 
                //show next button
                $action = 'nextgrade';
            } else if (optional_param('savegrade', null, PARAM_ALPHA)) {
                //save changes button
                $action = 'grade';
                if ($this->process_save_grade($mform)) {
                    $action = 'grading';
                }
            } else {
                //cancel button
                $action = 'grading';
            }
        } else if ($action == 'saveoptions') {
            $this->process_save_grading_options();
            $action = 'grading';
        }else if ($action == 'saveextension') {
            $action = 'grantextension';
            if ($this->process_save_extension($mform)) {
                $action = 'grading';
            }
        } else if ($action == 'revealidentitiesconfirm') {
            $this->process_reveal_identities();
            $action = 'grading';
        }

        $returnparams = array('rownum'=>optional_param('rownum', 0, PARAM_INT));
        $this->register_return_link($action, $returnparams);
        
        // now show the right view page
        if ($action == 'nextgrade') {
            $o .= $this->view_next_single_grade();
        } else if ($action == 'grantextension') {
            $o .= $this->view_grant_extension($mform);
        } else if ($action == 'grade') {
            $o .= $this->view_single_grade_page($mform);
        } else if ($action == 'viewpluginassignfeedback') {
            $o .= $this->view_plugin_content('assignfeedback');
        } else if ($action == 'viewpluginassignsubmission') {
            $o .= $this->view_plugin_content('assignsubmission');
        } else if ($action == 'editsubmission') {
            $o .= $this->view_edit_submission_page($mform);
        } else if ($action == 'submit') {
            $o .= $this->view_confirm_submit_page();
        } else if ($action == 'grading') {
            $o .= $this->view_grading_page();
        } else if ($action == 'downloadall') {
            $o .= $this->download_submissions();
        } else if ($action == 'uploadgrades') {
            $o .= $this->view_upload_grades_page();
        } else if ($action == 'plugingradingpage') {
            $o .= $this->view_plugin_grading_page();
        } else if ($action == 'uploadgradesfile') {
            $o .= $this->view_import_grades_page();
         } else if ($action == 'revealidentities') {
            $o .= $this->view_reveal_identities_confirm();
         } else if ($action == 'importgradesfile') {
            $o .= $this->process_import_grades();
        } else {
            $o .= $this->view_submission_page();
        }
       
        return $o;
    }

    
    /**
     * Add this instance to the database
     * 
     * @global DB
     * @param stdClass formdata The data submitted from the form
     * @param bool callplugins This is used to skip the plugin code
     * when upgrading an old assignment to a new one (the plugins get called manually)
     * @return mixed false if an error occurs or the int id of the new instance
     */
    public function add_instance(stdClass $formdata, $callplugins) {
        global $DB;

        $err = '';

        // add the database record
        $update = new stdClass();
        $update->name = $formdata->name;
        $update->timemodified = time();
        $update->timecreated = time();
        $update->course = $formdata->course;
        $update->courseid = $formdata->course;
        $update->intro = $formdata->intro;
        $update->introformat = $formdata->introformat;
        $update->alwaysshowdescription = $formdata->alwaysshowdescription;
        $update->preventlatesubmissions = $formdata->preventlatesubmissions;
        $update->requiresubmissionstatement = $formdata->requiresubmissionstatement;
        $update->submissiondrafts = $formdata->submissiondrafts;
        $update->sendnotifications = $formdata->sendnotifications;
        $update->sendlatenotifications = $formdata->sendlatenotifications;
        $update->duedate = $formdata->duedate;
        $update->finaldate = $formdata->finaldate;
        $update->allowsubmissionsfromdate = $formdata->allowsubmissionsfromdate;
        $update->grade = $formdata->grade;
        $update->blindmarking = $formdata->blindmarking;
        $update->teamsubmission = $formdata->teamsubmission;
        $update->requireallteammemberssubmit = $formdata->requireallteammemberssubmit;
        $update->teamsubmissiongroupingid = $formdata->teamsubmissiongroupingid;
        
        $returnid = $DB->insert_record('assign', $update);
        $this->instance = $DB->get_record('assign', array('id'=>$returnid), '*', MUST_EXIST);
        // cache the course record
        $this->course = $DB->get_record('course', array('id'=>$formdata->course), '*', MUST_EXIST);

        if ($callplugins) {
            // call save_settings hook for submission plugins
            foreach ($this->submissionplugins as $plugin) {
                if (!$this->update_plugin_instance($plugin, $formdata)) {
                    print_error($plugin->get_error());
                    return false;
                }
            }
            foreach ($this->feedbackplugins as $plugin) {
                if (!$this->update_plugin_instance($plugin, $formdata)) {
                    print_error($plugin->get_error());
                    return false;
                }
            }

            // in the case of upgrades the coursemodule has not been set so we need to wait before calling these two
            // add event to the calendar
            $this->update_calendar($formdata->coursemodule);
            // add the item in the gradebook
            $this->update_gradebook(false, $formdata->coursemodule);
        
        }
        
        $update = new stdClass();
        $update->id = $this->get_instance()->id;
        $update->nosubmissions = (!$this->is_any_submission_plugin_enabled()) ? 1: 0;
        $DB->update_record('assign', $update);
        
        return $returnid;
    }

    /**
     * Delete all grades from the gradebook for this assignment
     *
     * @global stdClass CFG
     * @return bool
     */
    private function delete_grades() {
        global $CFG;

        return grade_update('mod/assign', $this->get_course()->id, 'mod', 'assign', $this->get_instance()->id, 0, NULL, array('deleted'=>1)) == GRADE_UPDATE_OK;
    }
    
    /**
     * Delete this instance from the database
     * 
     * @global moodle_database DB
     * @return bool false if an error occurs
     */
    public function delete_instance() {
        global $DB;
        $result = true;

        foreach ($this->submissionplugins as $plugin) {
            if (!$plugin->delete_instance()) {
                print_error($plugin->get_error());
                $result = false;
            }
        }
        foreach ($this->feedbackplugins as $plugin) {
            if (!$plugin->delete_instance()) {
                print_error($plugin->get_error());
                $result = false;
            }
        }
        
        // delete files associated with this assignment
        $fs = get_file_storage();
        if (! $fs->delete_area_files($this->context->id) ) {
            $result = false;
        }
        
        // delete_records will throw an exception if it fails - so no need for error checking here

        $DB->delete_records('assign_submission', array('assignment'=>$this->get_instance()->id));
        $DB->delete_records('assign_grades', array('assignment'=>$this->get_instance()->id));
        $DB->delete_records('assign_plugin_config', array('assignment'=>$this->get_instance()->id));

        // delete items from the gradebook
        if (! $this->delete_grades()) {
            $result = false;
        }
        
        // delete the instance
        $DB->delete_records('assign', array('id'=>$this->get_instance()->id));
        
        return $result;
    }

    /**
     * Update the settings for a single plugin
     * 
     * @global DB
     * @param assignment_plugin $plugin The plugin to update
     * @param stdClass $formdata The form data
     * @return bool false if an error occurs
     */
    private function update_plugin_instance(assignment_plugin $plugin, stdClass $formdata) {
        if ($plugin->is_visible()) {
            $enabledname = $plugin->get_subtype() . '_' . $plugin->get_type() . '_enabled';
            if ($formdata->$enabledname) {
                $plugin->enable();
                if (!$plugin->save_settings($formdata)) {
                    print_error($plugin->get_error());
                    return false;
                }
            } else {
                $plugin->disable();
            }
        }
        return true;
    }

    /**
     * Update the gradebook information for this assignment
     * 
     * @global stdClass $CFG
     * @param bool $reset If true, will reset all grades in the gradbook for this assignment
     * @param int coursemoduleid This is required because it might not exist in the database yet
     * @return bool
     */
    public function update_gradebook($reset, $coursemoduleid) {
        global $CFG;

        $params = array('itemname'=>$this->get_instance()->name, 'idnumber'=>$coursemoduleid);

        if ($this->get_instance()->grade > 0) {
            $params['gradetype'] = GRADE_TYPE_VALUE;
            $params['grademax']  = $this->get_instance()->grade;
            $params['grademin']  = 0;

        } else if ($assignment->grade < 0) {
            $params['gradetype'] = GRADE_TYPE_SCALE;
            $params['scaleid']   = -$this->get_instance()->grade;

        } else {
            $params['gradetype'] = GRADE_TYPE_TEXT; // allow text comments only
        }

        if ($reset) {
            $params['reset'] = true;
        }

        return grade_update('mod/assign', $this->get_course()->id, 'mod', 'assign', $this->get_instance()->id, 0, NULL, $params);
    }


    /**
     * Update the calendar entries for this assignment
     *
     * @global moodle_database $DB
     * @global stdClass $CFG
     * @param int coursemoduleid - Required to pass this in because it might not exist in the database yet
     * @return bool
     */
    public function update_calendar($coursemoduleid) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/calendar/lib.php');

        // special case for add_instance as the coursemodule has not been set yet. 
        
        if ($this->get_instance()->duedate) {
            $event = new stdClass();

            if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'assign', 'instance'=>$this->get_instance()->id))) {

                $event->name        = $this->get_instance()->name;
                
                $event->description = format_module_intro('assign', $this->get_instance(), $coursemoduleid);
                $event->timestart   = $this->get_instance()->duedate;

                $calendarevent = calendar_event::load($event->id);
                $calendarevent->update($event);
            } else {
                $event = new stdClass();
                $event->name        = $this->get_instance()->name;
                $event->description = format_module_intro('assign', $this->get_instance(), $coursemoduleid);
                $event->courseid    = $this->get_instance()->course;
                $event->groupid     = 0;
                $event->userid      = 0;
                $event->modulename  = 'assign';
                $event->instance    = $this->get_instance()->id;
                $event->eventtype   = 'due';
                $event->timestart   = $this->get_instance()->duedate;
                $event->timeduration = 0;

                calendar_event::create($event);
            }
        } else {
            $DB->delete_records('event', array('modulename'=>'assign', 'instance'=>$this->get_instance()->id));
        }
    }


    /**
     * Update this instance in the database
     * 
     * @global moodle_database DB
     * @param stdClass formdata - the data submitted from the form
     * @return bool false if an error occurs
     */
    public function update_instance($formdata) {
        global $DB;

        $update = new stdClass();
        $update->id = $formdata->instance;
        $update->name = $formdata->name;
        $update->timemodified = time();
        $update->course = $formdata->course;
        $update->intro = $formdata->intro;
        $update->introformat = $formdata->introformat;
        $update->alwaysshowdescription = $formdata->alwaysshowdescription;
        $update->preventlatesubmissions = $formdata->preventlatesubmissions;
        $update->requiresubmissionstatement = $formdata->requiresubmissionstatement;
        $update->submissiondrafts = $formdata->submissiondrafts;
        $update->sendnotifications = $formdata->sendnotifications;
        $update->sendlatenotifications = $formdata->sendlatenotifications;
        $update->duedate = $formdata->duedate;
        $update->finaldate = $formdata->finaldate;
        $update->allowsubmissionsfromdate = $formdata->allowsubmissionsfromdate;
        $update->grade = $formdata->grade;
        $update->blindmarking = $formdata->blindmarking;
        $update->teamsubmission = $formdata->teamsubmission;
        $update->requireallteammemberssubmit = $formdata->requireallteammemberssubmit;
        $update->teamsubmissiongroupingid = $formdata->teamsubmissiongroupingid;
        
        $result = $DB->update_record('assign', $update);
        $this->instance = $DB->get_record('assign', array('id'=>$update->id), '*', MUST_EXIST);
        
        // load the assignment so the plugins have access to it

        // call save_settings hook for submission plugins
        foreach ($this->submissionplugins as $plugin) {
            if (!$this->update_plugin_instance($plugin, $formdata)) {
                print_error($plugin->get_error());
                return false;
            }
        }
        foreach ($this->feedbackplugins as $plugin) {
            if (!$this->update_plugin_instance($plugin, $formdata)) {
                print_error($plugin->get_error());
                return false;
            }
        }

        
        // update all the calendar events 
        $this->update_calendar($this->get_course_module()->id);

        $this->update_gradebook(false, $this->get_course_module()->id);

        $update = new stdClass();
        $update->id = $this->get_instance()->id;
        $update->nosubmissions = (!$this->is_any_submission_plugin_enabled()) ? 1: 0;
        $DB->update_record('assign', $update);





        return $result;
    }

    /**
     * add elements in grading plugin form 
     * 
     * @param mixed stdClass|null $grade
     * @param MoodleQuickForm $mform
     * @param stdClass $data 
     * @return void
     */
    private function add_plugin_grade_elements($grade, MoodleQuickForm $mform, stdClass $data) {
        foreach ($this->feedbackplugins as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                $mform->addElement('header', 'header_' . $plugin->get_type(), $plugin->get_name());
                if (!$plugin->get_form_elements($grade, $mform, $data)) {
                    $mform->removeElement('header_' . $plugin->get_type());
                }
            }
        }
    }
    
    

    /**
     * Add one plugins settings to edit plugin form 
     *
     * @param assignment_plugin $plugin The plugin to add the settings from
     * @param MoodleQuickForm $mform The form to add the configuration settings to. This form is modified directly (not returned)
     * @return void  
     */
    private function add_plugin_settings(assignment_plugin $plugin, MoodleQuickForm $mform) {
        if ($plugin->is_visible()) {
            // enabled
            //tied disableIf rule to this select element
            $mform->addElement('selectyesno', $plugin->get_subtype() . '_' . $plugin->get_type() . '_enabled', $plugin->get_name());
            $mform->setDefault($plugin->get_subtype() . '_' . $plugin->get_type() . '_enabled', $plugin->is_enabled());

            $plugin->get_settings($mform);

        }

    }


    /**
     * Add settings to edit plugin form 
     *
     * @param MoodleQuickForm $mform The form to add the configuration settings to. This form is modified directly (not returned)
     * @return void
     */
    public function add_all_plugin_settings(MoodleQuickForm $mform) {
        $mform->addElement('header', 'general', get_string('submissionsettings', 'assign'));
        
        foreach ($this->submissionplugins as $plugin) {
            $this->add_plugin_settings($plugin, $mform);
            
        }
        $mform->addElement('header', 'general', get_string('feedbacksettings', 'assign'));
        foreach ($this->feedbackplugins as $plugin) {
            $this->add_plugin_settings($plugin, $mform);
        }
    }
    
    final private static function get_static_module_name_plural() {
        if (isset(self::$modulenameplural)) {
            return self::$modulenameplural;
        }
        self::$modulenameplural = get_string('modulenameplural', 'assign');
        return self::$modulenameplural;
    }

    /**
     * Allow each plugin an opportunity to update the defaultvalues
     * passed in to the settings form (needed to set up draft areas for
     * editor and filemanager elements)
     * @param array $defaultvalues
     */
    public function plugin_data_preprocessing(&$defaultvalues) {
        foreach ($this->submissionplugins as $plugin) {
            if ($plugin->is_visible()) {
                $plugin->data_preprocessing(&$defaultvalues);
            }
        }
        foreach ($this->feedbackplugins as $plugin) {
            if ($plugin->is_visible()) {
                $plugin->data_preprocessing(&$defaultvalues);
            }
        }
    }

    /**
     * Get the name of the current module. 
     *
     * @return string the module name (Assignment)
     */
    protected function get_module_name() {
        if (isset(self::$modulename)) {
            return self::$modulename;
        }
        self::$modulename = get_string('modulename', 'assign');
        return self::$modulename;
    }
    
    /**
     * Get the plural name of the current module.
     *
     * @return string the module name plural (Assignments)
     */
    protected function get_module_name_plural() {
        if (isset(self::$modulenameplural)) {
            return self::$modulenameplural;
        }
        self::$modulenameplural = get_string('modulenameplural', 'assign');
        return self::$modulenameplural;
    }

    public function has_instance() {
        return $this->instance || $this->get_course_module();
    }

    /**
     * Get the settings for the current instance of this assignment
     *
     * @global moodle_database $DB
     * @return stdClass The settings
     */
    public function get_instance() {
        global $DB;
        if ($this->instance) {
            return $this->instance;
        }
        if ($this->get_course_module()) {
            $this->instance = $DB->get_record('assign', array('id' => $this->get_course_module()->instance), '*', MUST_EXIST);
        }
        if (!$this->instance) {
            throw new coding_exception('Improper use of the assignment class. Cannot load the assignment record.');
        }
        return $this->instance;
    }
    
    /**
     * Get the context of the current course
     * @return mixed context|null The course context
     */
    public function get_course_context() {
        if (!$this->context && !$this->course) {
            throw new coding_exception('Improper use of the assignment class. Cannot load the course context.');
        }
        if ($this->context) {
            return $this->context->get_course_context();
        } else {
            return context_course::instance($this->course->id);
        } 
    }

    
    /**
     * Get the current course module
     *
     * @return mixed stdClass|null The course module
     */
    public function get_course_module() {
        if ($this->coursemodule) {
            return $this->coursemodule;
        }
        if (!$this->context) {
            return null;
        }

        if ($this->context->contextlevel == CONTEXT_MODULE) {
            $this->coursemodule = get_coursemodule_from_id('assign', $this->context->instanceid, 0, false, MUST_EXIST);
            return $this->coursemodule;
        }
        return null;
    }
    
    /**
     * Get context module
     * 
     * @return context 
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Get the current course
     * @global moodle_database $DB
     * @return mixed stdClass|null The course
     */
    public function get_course() {
        global $DB;
        if ($this->course) {
            return $this->course;
        }

        if (!$this->context) {
            return null;
        }
        $this->course = $DB->get_record('course', array('id' => $this->get_course_context()->instanceid), '*', MUST_EXIST);
        return $this->course;
    }
    
    /**
     *  Return a grade in user-friendly form, whether it's a scale or not
     *
     * @global object $DB
     * @param mixed int|null $grade 
     * @return string User-friendly representation of grade
     */
    public function display_grade($grade) {
        global $DB;

        static $scalegrades = array();

                                        

        if ($this->get_instance()->grade >= 0) {    // Normal number
            if ($grade == -1 || $grade === null) {
                return '-';
            } else {
                return format_float(($grade)) .' / '. format_float($this->get_instance()->grade);
            }

        } else {                                // Scale
            if (empty($this->cache['scale'])) {
                if ($scale = $DB->get_record('scale', array('id'=>-($this->get_instance()->grade)))) {
                    $this->cache['scale'] = make_menu_from_list($scale->scale);
                } else {
                    return '-';
                }
            }
            $scaleid = (int)$grade;
            if (isset($this->cache['scale'][$scaleid])) {
                return $this->cache['scale'][$scaleid];
            }
            return '-';
        }
    }
           
    /**
     * Load a list of users enrolled in the current course with the specified permission and group (0 for no group)
     * 
     * @param int $currentgroup
     * @return array List of user records 
     */
    public function list_participants($currentgroup, $idsonly) {
        if ($idsonly) { 
            return get_enrolled_users($this->context, "mod/assign:submit", $currentgroup, 'u.id');
        } else {
            return get_enrolled_users($this->context, "mod/assign:submit", $currentgroup);
        }

        /* Should do this!
            if (!empty($CFG->enablegroupmembersonly) and $cm->groupmembersonly) {
                if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                    $users = array_intersect($users, array_keys($groupingusers));
                }
            }
        */
    }

    /**
     * Load a count of users enrolled in the current course with the specified permission and group (0 for no group)
     * 
     * @param string $permission
     * @param int $currentgroup
     * @return int number of matching users
     */
    public function count_participants($currentgroup) {
        return count_enrolled_users($this->context, "mod/assign:submit", $currentgroup);
    }

    /**
     * Load a count of users enrolled in the current course with the specified permission and group (optional)
     *
     * @global moodle_database $DB
     * @param string $status The submission status - should match one of the constants 
     * @return int number of matching submissions
     */
    public function count_submissions_with_status($status) {
        global $DB;
        return $DB->count_records_sql("SELECT COUNT('x')
                                     FROM {assign_submission}
                                    WHERE assignment = ? AND
                                          status = ?", array($this->get_course_module()->instance, $status));
    }

    
    /**
     * Utility function get the userid based on the row number of the grading table.
     * This takes into account any active filters on the table.
     * 
     * @param int $num The row number of the user
     * @return mixed The user id of the matching user or false if there was an error
     */
    private function get_userid_for_row($num, &$last){
        if (!array_key_exists('userid_for_row', $this->cache)) {
            $this->cache['userid_for_row'] = array();
        }
        if (array_key_exists($num, $this->cache['userid_for_row'])) {
            list($userid, $last) = $this->cache['userid_for_row'][$num];
            return $userid;
        }
        
        $filter = get_user_preferences('assign_filter', '');
        $table = new grading_table($this, 0, $filter);

        $userid = $table->get_cell_data($num, 'userid', $last);
     
        $this->cache['userid_for_row'][$num] = array($userid, $last);
        return $userid;
    }
    
    /**
     * Return all grades
     *
     * @global moodle_database $DB;
     * @return array The grade objects indexed by id
     */
    private function get_all_grades() {
        global $DB;

        $sort = "u.lastname DESC";

        return $DB->get_records_sql("SELECT g.*
                                       FROM {assign_grades} g, {user} u
                                      WHERE u.id = g.userid
                                            AND g.assignment = ?
                                   ORDER BY $sort", array($this->get_instance()->id));

    }

    /**
     * Return all assignment submissions
     *
     * @global moodle_database $DB;
     * @return array The submission objects indexed by id
     */
    private function get_all_submissions() {
        global $DB;

        $sort = "u.lastname DESC";

        return $DB->get_records_sql("SELECT a.*
                                       FROM {assign_submission} a, {user} u
                                      WHERE u.id = a.userid
                                            AND a.assignment = ?
                                   ORDER BY $sort", array($this->get_instance()->id));

    }
     
    /**
     * Generate zip file from array of given files
     * 
     * @global stdClass $CFG
     * @param array $filesforzipping - array of files to pass into archive_to_pathname - this array is indexed by the final file name and each element in the array is an instance of a stored_file object
     * @return path of temp file - note this returned file does not have a .zip extension - it is a temp file.
     */
     private function pack_files($filesforzipping) {
         global $CFG;
         //create path for new zip file.
         $tempzip = tempnam($CFG->tempdir.'/', 'assignment_');
         //zip files
         $zipper = new zip_packer();
         if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
             return $tempzip;
         }
         return false;
    }

    /**
     *  Cron function to be run periodically according to the moodle cron
     *  Finds all assignment notifications that have yet to be mailed out, and mails them
     *
     * @global stdClass $CFG
     * @global stdClass $USER
     * @global stdClass $DB
     * @return bool
     */
    static function cron() {
        global $CFG, $DB;

        // only ever send a max of one days worth of updates
        $yesterday = time() - (24 * 3600);
        $timenow   = time();
        
        // email students about new feedback
        $submissions = $DB->get_records_sql("SELECT s.*, a.course, a.name, g.*, g.id as gradeid
                                   FROM {assign} a
                                        JOIN {assign_grades} g ON g.assignment = a.id
                                        LEFT JOIN {assign_submission} s ON s.assignment = a.id AND s.userid = g.userid
                                  WHERE g.timemodified >= ?
                                        AND g.timemodified <= ?
                                        AND g.mailed = 0", array($yesterday, $timenow));

        foreach ($submissions as $submission) {

            print("Processing assignment submission $submission->id\n");

            if (! $user = $DB->get_record("user", array("id"=>$submission->userid))) {
                echo "Could not find user $user->id\n";
                continue;
            }

            if (! $course = $DB->get_record("course", array("id"=>$submission->course))) {
                echo "Could not find course $submission->course\n";
                continue;
            }

            /// Override the language and timezone of the "current" user, so that
            /// mail is customised for the receiver.
            cron_setup_user($user, $course);

            $coursecontext = context_course::instance($submission->course);
            $courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));
            if (!is_enrolled($coursecontext, $user->id)) {
                echo fullname($user)." not an active participant in " . $courseshortname . "\n";
                continue;
            }
            
            if (! $grader = $DB->get_record("user", array("id"=>$submission->grader))) {
                echo "Could not find grader $submission->grader\n";
                continue;
            }

            if (! $mod = get_coursemodule_from_instance("assign", $submission->assignment, $course->id)) {
                echo "Could not find course module for assignment id $submission->assignment\n";
                continue;
            }

            $contextmodule = context_module::instance($mod->id);

            if (! $mod->visible) {    /// Hold mail notification for hidden assignments until later
                continue;
            }

            // need to send this to the student
            self::send_assignment_notification($grader, $user, 'feedbackavailable', 'assign_student_notification', $submission->lastmodified, $mod, $contextmodule, $course, get_string('modulename', 'assign'), $submission->name);

            $grade = new stdClass();
            $grade->id = $submission->gradeid;
            $grade->mailed = 1;
            $DB->update_record('assign_grades', $grade);

        }

        cron_setup_user();

        return true;
    }

    
    
    /**
     * Update a grade in the grade table for the assignment and in the gradebook
     *
     * @global moodle_database $DB
     * @param stdClass $grade a grade record keyed on id
     * @return bool true for success
     */
    private function update_grade($grade) {
        global $DB;

        $grade->timemodified = time();
 
        if ($grade->grade) {
            if ($this->get_instance()->grade > 0) {
                if (!is_numeric($grade->grade)) {
                    return false;
                } else if ($grade->grade > $this->get_instance()->grade) {
                    return false;
                } else if ($grade->grade < 0) {
                    return false;
                }
            } else {
                // this is a scale
                if ($scale = $DB->get_record('scale', array('id'=>-($this->get_instance()->grade)))) {
                    $scaleoptions = make_menu_from_list($scale->scale);
                    if (!array_key_exists((int)$grade->grade, $scaleoptions)) {
                        return false;
                    }
                }
            }
        }

        $result = $DB->update_record('assign_grades', $grade);
        if ($result) {
            $this->gradebook_item_update(null,$grade);
        }
        return $result;
    }
    
    /**
     * view the grant extension date page
     * 
     * Uses url parameters 'userid'
     * @global moodle_database $DB
     * @global stdClass $CFG
     * @return string
     */
    private function view_grant_extension($mform) {
        global $DB, $CFG;

        $o = '';
        
        $userid = required_param('userid', PARAM_INT);
        require_once($CFG->dirroot . '/mod/assign/extension_form.php');

        $grade = $this->get_user_grade($userid, false);

        $user = $DB->get_record('user', array('id'=>$userid), '*', MUST_EXIST);

        $data = new stdClass();
        
        if ($grade) {
            $data->extensionduedate = $grade->extensionduedate;
        } else {
            $data->extensionduedate = null;
        }
        $data->userid = $userid;
        
        $o .= $this->output->render(new assignment_header($this, true, get_string('grantextension', 'assign')));
        if (!$mform) {
            $mform = new mod_assign_extension_form(null, array($this, $user, $data));
        }
        $o .= $this->output->render($mform);
                 
        $o .= $this->view_footer();     
        return $o;
    }
    
    /**
     * display the submission that is used by a plugin  
     * Uses url parameters 'sid', 'gid' and 'plugin'
     * @global stdClass $CFG
     * @global stdClass $USER
     * @param string $plugintype 
     * @return string
     */
    private function view_plugin_content($pluginsubtype) {
        global $CFG, $USER;

        $o = '';
        
        $submissionid = optional_param('sid', 0, PARAM_INT);
        $gradeid = optional_param('gid', 0, PARAM_INT);
        $plugintype = required_param('plugin', PARAM_TEXT);
        $item = null;
        if ($pluginsubtype == 'assignsubmission') {
            $plugin = $this->get_submission_plugin_by_type($plugintype);
            if ($submissionid <= 0) {
                throw new coding_exception('Submission id should not be 0');
            }
            $item = $this->get_submission($submissionid);

            // permissions
            if ($item->userid != $USER->id) {
                require_capability('mod/assign:grade', $this->context);
            }
            $o .= $this->output->render(new assignment_header($this, true, $plugin->get_name()));
            $o .= $this->output->render(new submission_plugin_submission($this, $plugin, $item, submission_plugin_submission::FULL));
            $this->add_to_log('view submission', get_string('viewsubmissionforuser', 'assign', $item->userid));
        } else {
            $plugin = $this->get_feedback_plugin_by_type($plugintype);
            if ($gradeid <= 0) {
                throw new coding_exception('Grade id should not be 0');
            }
            $item = $this->get_grade($gradeid);
            // permissions
            if ($item->userid != $USER->id) {
                require_capability('mod/assign:grade', $this->context);
            }
            $o .= $this->output->render(new assignment_header($this, true, $plugin->get_name()));
            $o .= $this->output->render(new feedback_plugin_feedback($this, $plugin, $item, feedback_plugin_feedback::FULL));
            $this->add_to_log('view feedback', get_string('viewfeedbackforuser', 'assign', $item->userid));
        }

                 
        $o .= $this->view_return_links();
          
        $o .= $this->view_footer();     
        return $o;
    }
    
    /**
     * render the content in editor that is often used by plugin
     *  
     * @global stdClass $CFG
     * @param string $filearea
     * @param int  $submissionid
     * @param string $plugintype
     * @param string $editor
     * @return string
     */
    public function render_editor_content($filearea, $submissionid, $plugintype, $editor) {
        global $CFG;
        
        $result = '';
        
        $plugin = $this->get_submission_plugin_by_type($plugintype);
        
        $text = $plugin->get_editor_text($editor, $submissionid);
        $format = $plugin->get_editor_format($editor, $submissionid);
        
        $finaltext = file_rewrite_pluginfile_urls($text, 'pluginfile.php', $this->get_context()->id, 'mod_assign', $filearea, $submissionid);
        $result .= format_text($finaltext, $format, array('overflowdiv' => true, 'context' => $this->get_context()));

        

        if ($CFG->enableportfolios) {
            require_once($CFG->libdir . '/portfoliolib.php');
           
            $button = new portfolio_add_button();
            $button->set_callback_options('assign_portfolio_caller', array('cmid' => $this->get_course_module()->id, 'sid' => $submissionid, 'plugin' => $plugintype, 'editor' => $editor, 'area'=>$filearea), '/mod/assign/portfolio_callback.php');
            $fs = get_file_storage();

            if ($files = $fs->get_area_files($this->context->id, 'mod_assign',$filearea, $submissionid, "timemodified", false)) {
                $button->set_formats(PORTFOLIO_FORMAT_RICHHTML);
            } else {
                $button->set_formats(PORTFOLIO_FORMAT_PLAINHTML);
            }
            $result .= $button->to_html();
        }
        return $result;
    }
            

    /**
     * Display the page footer
     *
     * @return None
     */
    private function view_footer() {
        return $this->output->render_footer();
    }

    /**
     * Does this user have grade permission for this assignment
     *
     * @return bool
     */
    private function can_grade() {
        // Permissions check 
        if (!has_capability('mod/assign:grade', $this->context)) {
            return false;
        }

        return true;
    }

    /**
     * View a redirect to the next submission grading page
     * 
     * @uses die
     * @return never returns
     */
    private function view_next_single_grade() {
        $rnum = required_param('rownum', PARAM_INT);
        $rnum +=1;
        $last = false;
        $userid = $this->get_userid_for_row($rnum, $last);
        if (!$userid) {
            throw new coding_exception('Row is out of bounds for the current grading table: ' . $rnum);
        }
    
        redirect(new moodle_url('/mod/assign/view.php', array('id' => $this->get_course_module()->id, 'rownum'=> $rnum, 'action'=>'grade')));
        die();
    }
    
    /**
     * Download a zip file of all assignment submissions
     *
     * @global stdClass $CFG
     * @global moodle_database $DB
     * @return void
     */
    private function download_submissions() {
        global $CFG,$DB;
        
        // more efficient to load this here
        require_once($CFG->libdir.'/filelib.php');

        // load all submissions
        $submissions = $this->get_all_submissions();
        
        // build a list of files to zip
        $filesforzipping = array();
        $fs = get_file_storage();
        
        $groupmode = groups_get_activity_groupmode($this->get_course_module());
        $groupid = 0;   // All users
        $groupname = '';
        if ($groupmode) {
            $groupid = groups_get_activity_group($this->get_course_module(), true);
            $groupname = groups_get_group_name($groupid).'-';
        }
        $userids = $this->list_participants($groupid, true);

        // construct the zip file name
        $filename = str_replace(' ', '_', clean_filename($this->get_course()->shortname.'-'.$this->get_instance()->name.'-'.$groupname.$this->get_course_module()->id.".zip")); //name of new zip file.
    
        // get all the files for each submission
        if (!empty($submissions)) {
            foreach ($submissions as $submission) {
                $userid = $submission->userid; //get userid
                if ((groups_is_member($groupid,$userid) or !$groupmode or !$groupid)) {
                    // get the plugins to add their own files to the zip

                    $user = $DB->get_record('user', array('id'=>$userid), 'id, firstname, lastname');
                    // user may have been deleted?
                    if ($user) {
                        if (!$this->is_blind_marking()) {
                            $prefix = clean_filename(str_replace('_', '', fullname($user)) . '_' . $this->get_uniqueid_for_user($userid) . '_');
                        } else {
                            $prefix = clean_filename(get_string('participant', 'assign') . '_' . $this->get_uniqueid_for_user($userid) . '_');
                        }

                        foreach ($this->submissionplugins as $plugin) {
                            if ($plugin->is_enabled() && $plugin->is_visible()) {
                                $pluginfiles = $plugin->get_files($submission);
                        
                                foreach ($pluginfiles as $zipfilename => $file) {
                                    $filesforzipping[$prefix . get_string('pluginname', 'assignsubmission_' . $plugin->get_type()) . '_' . $zipfilename] = $file;
                                } 
                            }
                        }
                    }
                } 
            } // end of foreach loop
        }
        // get all the files for each grade/feedback

        
        foreach ($userids as $useridrecord) {
            $userid = $useridrecord->id;
            if ((groups_is_member($groupid,$userid) or !$groupmode or !$groupid)) {
                // get the plugins to add their own files to the zip

                $user = $DB->get_record('user', array('id'=>$userid), 'id, firstname, lastname');
                $grade = $this->get_user_grade($userid, false);
                // user may have been deleted?
                if ($user) {
                    if (!$this->is_blind_marking()) {
                        $prefix = clean_filename(str_replace('_', '', fullname($user)) . '_' . $this->get_uniqueid_for_user($userid) . '_');
                    } else {
                        $prefix = clean_filename(get_string('participant', 'assign') . '_' . $this->get_uniqueid_for_user($userid) . '_');
                    }

                    foreach ($this->feedbackplugins as $plugin) {
                        if ($plugin->is_enabled() && $plugin->is_visible()) {
                            $pluginfiles = $plugin->get_files($grade);
                        
                            foreach ($pluginfiles as $zipfilename => $file) {
                                $filesforzipping[$prefix . get_string('pluginname', 'assignfeedback_' . $plugin->get_type()) . '_' . $zipfilename] = $file;
                            } 
                        }
                    }
                }
          
            } 
        } // end of foreach loop
        if (empty($filesforzipping)) {
            print_error('errornosubmissions', 'assign');
            return;
        }

        if ($zipfile = $this->pack_files($filesforzipping)) {
            $this->add_to_log('download all submissions', get_string('downloadallsubmissions', 'assign'));
            send_temp_file($zipfile, $filename); //send file and delete after sending.
        }
    }

    /**
     * Util function to add a message to the log
     *
     * @global stdClass $USER
     * @param string $action The current action
     * @param string $info A detailed description of the change. But no more than 255 characters.
     * @param string $url The url to the assign module instance.
     * @return void
     */
    public function add_to_log($action = '', $info = '', $url='') {
        global $USER; 
    
        $fullurl = 'view.php?id=' . $this->get_course_module()->id;
        if ($url != '') {
            $fullurl .= '&' . $url;
        }

        add_to_log($this->get_course()->id, 'assign', $action, $fullurl, $info, $this->get_course_module()->id, $USER->id);
    }

    /**
     * Get a list of the users in the same group as this user
     *
     * @global moodle_database $DB
     * @global stdClass $USER
     * @param int $groupid The id of the group whose members we want or 0 for the default group
     * @param bool $idsonly Whether to retrieve only the user id's
     * @return array The users (possibly id's only)
     */
    public function get_submission_group_members($groupid, $onlyids) {
        $members = array();
        if ($groupid != 0) {
            if ($onlyids) {
                $allusers = groups_get_members($groupid, 'u.id');
            } else {
                $allusers = groups_get_members($groupid);
            }
            foreach ($allusers as $user) {
                if ($this->get_submission_group($user->id)) {
                    $members[] = $user;
                }
            }
        } else {
            $allusers = $this->list_participants(null, $onlyids);
            foreach ($allusers as $user) {
                if ($this->get_submission_group($user->id) == null) {
                    $members[] = $user;
                }
            }
        }
        return $members;
    }
    
    /**
     * Load the submission object for a particular group, optionally creating it if required
     *
     * This will create the user submission and the group submission if required
     *
     * @global moodle_database $DB
     * @global stdClass $USER
     * @param int $userid The id of the user whose submission we want or 0 in which case USER->id is used
     * @param bool $createnew optional Defaults to false. If set to true a new submission object will be created in the database
     * @return stdClass The submission
     */
    public function get_group_submission($userid, $groupid, $create) {
        global $DB;

        if ($groupid == 0) {
            $group = $this->get_submission_group($userid);
            if ($group) {
                $groupid = $group->id;
            }
        }

        if ($create) {
            // make sure there is a submission for this user
            $submission = $DB->get_record('assign_submission', array('assignment'=>$this->get_instance()->id, 'groupid'=>0, 'userid'=>$userid));
            if (!$submission) {
                $submission = new stdClass();
                $submission->assignment   = $this->get_instance()->id;
                $submission->userid       = $userid;
                $submission->groupid      = 0;
                $submission->timecreated  = time();
                $submission->timemodified = $submission->timecreated;
                
                if ($this->get_instance()->submissiondrafts) {
                    $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
                } else {
                    $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
                }
                $DB->insert_record('assign_submission', $submission);
            }
        }
        // now get the group submission
        $submission = $DB->get_record('assign_submission', array('assignment'=>$this->get_instance()->id, 'groupid'=>$groupid, 'userid'=>0));
             
        if ($submission) {
            return $submission;
        }
        if ($create) {
            $submission = new stdClass();
            $submission->assignment   = $this->get_instance()->id;
            $submission->userid       = 0;
            $submission->groupid       = $groupid;
            $submission->timecreated = time();
            $submission->timemodified = $submission->timecreated;
                
            if ($this->get_instance()->submissiondrafts) {
                $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
            } else {
                $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
            }
            $sid = $DB->insert_record('assign_submission', $submission);
            $submission->id = $sid;
            return $submission;
        }
        return false;
    }

    /**
     * This is used for team assignments to get the group for the specified user. 
     * If the user is a member of multiple or no groups this will return false
     *
     * @param int $userid The id of the user whose submission we want
     * @return mixed The group or false
     */
    public function get_submission_group($userid) {
        $groups = groups_get_all_groups($this->get_course()->id, $userid, $this->get_instance()->teamsubmissiongroupingid);
        if (count($groups) != 1) {
            return false;
        }
        return array_pop($groups);
    }
    
    /**
     * Load the submission object for a particular user, optionally creating it if required
     *
     * For team assignments there are 2 submissions - the student submission and the team submission
     * All files are associated with the team submission but the status of the students contribution is
     * recorded separately.
     *
     * @global moodle_database $DB
     * @global stdClass $USER
     * @param int $userid The id of the user whose submission we want or 0 in which case USER->id is used
     * @param bool $createnew optional Defaults to false. If set to true a new submission object will be created in the database
     * @return stdClass The submission
     */
    public function get_user_submission($userid, $create) {
        global $DB, $USER;

        if (!$userid) {
            $userid = $USER->id;
        }

        // if the userid is not null then use userid
        $submission = $DB->get_record('assign_submission', array('assignment'=>$this->get_instance()->id, 'userid'=>$userid, 'groupid'=>0));
         
        if ($submission) {
            return $submission;
        }
        if ($create) {
            $submission = new stdClass();
            $submission->assignment   = $this->get_instance()->id;
            $submission->userid       = $userid;
            $submission->timecreated = time();
            $submission->timemodified = $submission->timecreated;
            
            if ($this->get_instance()->submissiondrafts) {
                $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
            } else {
                $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
            }
            $sid = $DB->insert_record('assign_submission', $submission);
            $submission->id = $sid;
            return $submission;
        }
        return false;
    }
    
    /**
     * Load the submission object from it's id
     *
     * @global moodle_database $DB
     * @global stdClass $USER
     * @param int $submissionid The id of the submission we want
     * @return stdClass The submission
     */
    private function get_submission($submissionid) {
        global $DB;

        return $DB->get_record('assign_submission', array('assignment'=>$this->get_instance()->id, 'id'=>$submissionid), '*', MUST_EXIST);
    }
    
    /**
     * This will retrieve a grade object from the db, optionally creating it if required
     *
     * @global moodle_database $DB
     * @global stdClass $USER
     * @param int $userid The user we are grading
     * @param bool $create If true the grade will be created if it does not exist
     * @return stdClass The grade record
     */
    public function get_user_grade($userid, $create) {
        global $DB, $USER;

        if (!$userid) {
            $userid = $USER->id;
        }
            
        // if the userid is not null then use userid
        $grade = $DB->get_record('assign_grades', array('assignment'=>$this->get_instance()->id, 'userid'=>$userid));
         
        if ($grade) {
            return $grade;
        }
        if ($create) {
            $grade = new stdClass();
            $grade->assignment   = $this->get_instance()->id;
            $grade->userid       = $userid;
            $grade->timecreated = time();
            $grade->timemodified = $grade->timecreated;
            $grade->locked = 0;
            $grade->grade = -1;
            $grade->grader = $USER->id;
            $gid = $DB->insert_record('assign_grades', $grade);
            $grade->id = $gid;
            return $grade;
        }
        return false;
    }

    private function is_team_submissions() {
        return $this->get_instance()->teamsubmission;
    }
    
    /**
     * This will retrieve a grade object from the db
     *
     * @global moodle_database $DB
     * @param int $gradeid The id of the grade
     * @return stdClass The grade record
     */
    private function get_grade($gradeid) {
        global $DB;

        return $DB->get_record('assign_grades', array('assignment'=>$this->get_instance()->id, 'id'=>$gradeid), '*', MUST_EXIST);
    }
    
    /**
     * Print the grading page for a single user submission
     *
     * @global moodle_database $DB
     * @uses die
     * @return string
     */
    private function view_single_grade_page($mform) {
        global $DB, $CFG;

        $o = '';

        // Include grade form 
        require_once($CFG->dirroot . '/mod/assign/grade_form.php');
        
        // Need submit permission to submit an assignment
        require_capability('mod/assign:grade', $this->context);

        $o .= $this->output->render(new assignment_header($this, false, get_string('grading', 'assign')));
       
        $rownum = required_param('rownum', PARAM_INT);  
        $last = false;
        $userid = $this->get_userid_for_row($rownum, $last);

        if(!$userid){
            throw new coding_exception('Row is out of bounds for the current grading table: ' . $rownum);
        }
        $user = $DB->get_record('user', array('id' => $userid));
        if ($user) {
            $o .= $this->output->render(new user_summary($user, $this));
        }
        $submissiongroup = null;
        $teamsubmission = null;
        if ($this->is_team_submissions()) {
            $teamsubmission = $this->get_group_submission($userid, 0, false);
            $submissiongroup = $this->get_submission_group($userid);
        } 
        $submission = $this->get_user_submission($userid, false);
        // get the current grade
        $grade = $this->get_user_grade($userid, false);
        if ($this->can_view_submission($userid)) {
            $showedit = has_capability('mod/assign:submit', $this->context) &&
                         $this->submissions_open($userid) && ($this->is_any_submission_plugin_enabled());

            $showsubmit = $showedit && $submission && ($submission->status == ASSIGN_SUBMISSION_STATUS_DRAFT);
            $gradelocked = ($grade && $grade->locked) || $this->grading_disabled($userid);
            $extensionduedate = null;
            if ($grade) {
                $extensionduedate = $grade->extensionduedate;
            }
            $o .= $this->output->render(new submission_status($this, 
                                                              $submission, 
                                                              $teamsubmission, 
                                                              $submissiongroup, 
                                                              $gradelocked, 
                                                              $this->is_graded($userid), 
                                                              submission_status::GRADER_VIEW, 
                                                              false, 
                                                              false,
                                                              $extensionduedate));
        }
        if ($grade) {
            $data = new stdClass();
            if ($grade->grade >= 0) {
                $data->grade = $grade->grade;
            }
        } else {
            $data = new stdClass();
            $data->grade = '';
        }

        // now show the grading form
        if (!$mform) {
            $mform = new mod_assign_grade_form(null, array($this, $data, array('rownum'=>$rownum)));
        }
        $o .= $this->output->render(new grading_form($mform));

        $this->add_to_log('view grading form', get_string('viewgradingformforstudent', 'assign', array('id'=>$user->id, 'fullname'=>fullname($user))));
        
        $o .= $this->view_footer();
        return $o;
    }

   
     
    /**
     * View a link to go back to the previous page. Uses url parameters returnaction and returnparams.
     *
     * @return string
     */
    private function view_return_links() {
        
        $returnaction = optional_param('returnaction','', PARAM_ALPHA);
        $returnparams = optional_param('returnparams','', PARAM_TEXT);
        
        $params = array();
        parse_str($returnparams, $params);
        $params = array_merge( array('id' => $this->get_course_module()->id, 'action' => $returnaction), $params);
           
        return $this->output->single_button(new moodle_url('/mod/assign/view.php', $params), get_string('back', 'assign'), 'get');
        
    }
   
    /**
     * View the grading table of all submissions for this assignment
     *
     * @global stdClass $USER
     * @global stdClass $CFG
     * @return string
     */
    private function view_grading_table() {
        global $USER, $CFG;
        // Include grading options form 
        require_once($CFG->dirroot . '/mod/assign/grading_options_form.php');
        $o = '';

        $perpage = get_user_preferences('assign_perpage', 10);
        $filter = get_user_preferences('assign_filter', '');
        // print options  for changing the filter and changing the number of results per page
        $mform = new mod_assign_grading_options_form(null, array('cm'=>$this->get_course_module()->id, 'contextid'=>$this->context->id, 'userid'=>$USER->id), 'post', '', array('class'=>'gradingoptionsform'));


        $data = new stdClass();
        $data->perpage = $perpage;
        $data->filter = $filter;
        $mform->set_data($data);
        
        $o .= $this->output->render(new grading_options_form($mform));
        
        // plagiarism update status apearring in the grading book
        plagiarism_update_status($this->get_course(), $this->get_course_module());
        
        
        
        // load and print the table of submissions
        $o .= $this->output->render(new grading_table($this, $perpage, $filter));
        return $o;
    }
    
    /**
     * View plugin grading page.
     *
     * @global stdClass $CFG
     * @global stdClass $USER
     * @return string
     */
    private function view_plugin_grading_page() {
        global $CFG;

        $o = '';
        // Need submit permission to submit an assignment
        require_capability('mod/assign:grade', $this->context);

        $gradingaction = required_param('gradingaction', PARAM_ALPHA);
        $plugintype = required_param('plugin', PARAM_ALPHA);
        // only load this if it is 
        
        $plugin = $this->get_feedback_plugin_by_type($plugintype);
    
        $o .= $plugin->grading_page($gradingaction);

        $this->add_to_log('view submission grading table', get_string('viewplugingradingpage', 'assign', array('plugin'=>$plugintype, 'action'=>$gradingaction)));
        return $o;
    }
    
    /**
     * Process csv file into a list of grade updates
     * 
     * @global stdClass $CFG 
     * @global stdClass $USER 
     * @param  moodleform $mform
     * @return bool - was the form processed
     */
    private function process_import_grades() {
        global $CFG, $USER;
        $result = true;
        $error = '';
        $o = '';
        // Include grade form 
        $skippedmodified = 0;
        $skippednograde = 0;
        $skippedinvalidgrade = 0;
        $updatedgrade = 0;
        require_once($CFG->dirroot . '/mod/assign/importgradeslib.php');
        require_once($CFG->dirroot . '/lib/csvlib.class.php');
        
        require_capability('mod/assign:grade', $this->context);

        $importid = required_param('importid', PARAM_ALPHANUM);
        $gradeimporter = new mod_assign_gradeimporter($importid, $this);
        if (!$gradeimporter->init()) {
            $error = get_string('invalidgradeimport', 'assign');
            $gradeimporter->close(true);
            $result = false;
        }
        $importform = new mod_assign_importgrades_form(null, array($this, null, null));

        if ($importform->is_cancelled()) {
            $gradeimporter->close(true);
            return $this->view_grading_page();
        }

        $ignoremodified = optional_param('ignoremodified', 0, PARAM_BOOL);

        if ($result) {
            while ($record = $gradeimporter->next()) { 
                $usergrade = $this->get_user_grade($record->user->id, false);
                // Note: Do not count the seconds when comparing modified dates
                if (!$ignoremodified && ($usergrade && $usergrade->timemodified > ($record->modified + 60))) {
                    $skippedmodified += 1;
                } else if (!$record->grade || $record->grade == '-' || $record->grade < 0) {
                    $skippednograde += 1;
                } else {
                    $grade = $this->get_user_grade($record->user->id, true);

                    $grade->grade = $record->grade;
                    $grade->grader = $USER->id;
                    if ($this->update_grade($grade)) {
                        $updatedgrade += 1;
                        $this->add_to_log('grade updated by import', get_string('gradeupdatedbyimport', 'assign', array('id'=>$record->user->id, 'fullname'=>fullname($record->user))));
                    } else {
                        $skippedinvalidgrade += 1;
                    }

                }
                
            }

            $gradeimporter->close(true);
        }

        $o .= $this->output->render(new assignment_header($this, false, get_string('grading', 'assign')));
        if ($result) {
            $o .= $this->output->render(new mod_assign_grade_import_summary($skippedmodified, $skippednograde, $skippedinvalidgrade, $updatedgrade, $this));
        } else {
            $o .= $this->output->notification($error);
        }
        $o .= $this->view_footer();
    
        return $o;
    }
    

    /**
     * Process csv file into a list of grade updates
     * 
     * @global stdClass $CFG 
     * @param  moodleform $mform
     * @return bool - was the form processed
     */
    private function view_import_grades_page() {
        global $CFG;
        // Include grade form 
        require_once($CFG->dirroot . '/mod/assign/importgradeslib.php');
        require_once($CFG->dirroot . '/lib/csvlib.class.php');
        
        $o = '';
        // Need submit permission to submit an assignment
        require_capability('mod/assign:grade', $this->context);

        $gradesform = new mod_assign_uploadgrades_form(null, array($this, null));

        if ($gradesform->is_cancelled()) {
            return false;
        }

        // process the form
        $iid = csv_import_reader::get_new_iid('mod_assign');

        $csvdata = $gradesform->get_file_content('uploadgrades');

        $data = new stdClass();
        $data->importid = $iid;

        $importform = new mod_assign_importgrades_form(null, array($this, $csvdata, $data));
        
        // only load this if it is 

        $o .= $this->output->render(new assignment_header($this, false, get_string('grading', 'assign')));
        
        $o .= $this->output->render($importform);

        $o .= $this->view_footer();
        $this->add_to_log('view upload grades form', get_string('viewuploadgradesform', 'assign'));
        return $o;
    }
    
    /**
     * View upload grades page.
     *
     * @global stdClass $CFG
     * @global stdClass $USER
     * @return string
     */
    private function view_upload_grades_page() {
        global $CFG;

        $o = '';
        // Need submit permission to submit an assignment
        require_capability('mod/assign:grade', $this->context);
        require_once($CFG->dirroot . '/mod/assign/importgradeslib.php');

        // only load this if it is 

        $o .= $this->output->render(new assignment_header($this, false, get_string('grading', 'assign')));


        $gradesform = new mod_assign_uploadgrades_form(null, array($this, null));
        $o .= $this->output->render($gradesform);

        $o .= $this->view_footer();
        $this->add_to_log('view upload grades form', get_string('viewuploadgradesform', 'assign'));
        return $o;
    }

    /**
     * View entire grading page.
     *
     * @global stdClass $CFG
     * @global stdClass $USER
     * @return string
     */
    private function view_grading_page() {
        global $CFG;

        $o = '';
        // Need submit permission to submit an assignment
        require_capability('mod/assign:grade', $this->context);

        // only load this if it is 

        $o .= $this->output->render(new assignment_header($this, false, get_string('grading', 'assign')));
        $o .= groups_print_activity_menu($this->get_course_module(), $CFG->wwwroot . '/mod/assign/view.php?id=' . $this->get_course_module()->id.'&action=grading', true);
        

        $o .= $this->view_grading_table();

        $o .= $this->view_footer();
        $this->add_to_log('view submission grading table', get_string('viewsubmissiongradingtable', 'assign'));
        return $o;
    }

    /**
     * Capture the output of the plagiarism plugins disclosures and return it as a string
     * 
     * @return void
     */
    private function plagiarism_print_disclosure() {
        $o = '';
        ob_start();
        
        plagiarism_print_disclosure($this->get_course_module()->id);
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }

    /**
     * message for students when assignment submissions have been closed
     *
     * @return string
     */
    private function view_student_error_message() {
        global $CFG;

        $o = '';
        // Need submit permission to submit an assignment
        require_capability('mod/assign:submit', $this->context);

        $o .= $this->output->render(new assignment_header($this, true, get_string('editsubmission', 'assign')));

        $o .= $this->output->notification('This assignment is no longer accepting submissions');

        $o .= $this->view_footer();

        return $o;

    }

    /**
     * View confirm submit page.
     * 
     * @global stdClass $CFG
     * @return None
     */
    private function view_confirm_submit_page() {
        global $CFG;
        // Always require view permission to do anything
        require_capability('mod/assign:view', $this->context);
        // Need submit permission to submit an assignment
        require_capability('mod/assign:submit', $this->context);
        
        require_once($CFG->dirroot . '/mod/assign/confirm_submission_form.php');

        if (!$this->submissions_open()) {
            print_error('submissionsclosed', 'assign');
            return;
        }

        echo $this->output->render(new assignment_header($this, true));
        $data = new stdClass();
        $mform = new mod_assign_confirm_submission_form(null, array($this, $data));
        echo $this->output->render(new confirm_submission_form($mform));

        echo $this->view_footer();
        $this->add_to_log('view confirm submit assignment form', get_string('viewownsubmissionform', 'assign'));
    }
    
    /**
     * View edit submissions page.
     * 
     * @global stdClass $CFG
     * @return void
     */
    private function view_edit_submission_page($mform) {
        global $CFG;

        $o = '';
        // Include submission form 
        require_once($CFG->dirroot . '/mod/assign/submission_form.php');
        // Need submit permission to submit an assignment
        require_capability('mod/assign:submit', $this->context);

        if (!$this->submissions_open()) {
            $subclosed  = '';
            $subclosed .= $this->view_student_error_message();
            return $subclosed;
        }
        $o .= $this->output->render(new assignment_header($this, true, get_string('editsubmission', 'assign')));
        $o .= $this->plagiarism_print_disclosure();
        $data = new stdClass();

        if (!$mform) {
            $mform = new mod_assign_submission_form(null, array($this, $data));
        }

        $o .= $this->output->render(new edit_submission_form($mform));
    
        $o .= $this->view_footer();
        $this->add_to_log('view submit assignment form', get_string('viewownsubmissionform', 'assign'));
        
        return $o;
    }
    
    /**
     * See if this assignment has a grade yet
     *
     * @param int userid
     * @return bool
     */
    private function is_graded($userid) {
        $grade = $this->get_user_grade($userid, false);
        if ($grade) {
            return ($grade->grade != '');
        }
        return false;
    }


    /**
     * Perform an access check to see if the current $USER can view this users submission
     *
     * @global object $USER
     * @param int userid
     * @return bool
     */
    private function can_view_submission($userid) {
        global $USER;

        if (!is_enrolled($this->get_course_context(), $userid)) {
            return false;
        }
        if ($userid == $USER->id && !has_capability('mod/assign:submit', $this->context)) {
            return false;
        }
        if ($userid != $USER->id && !has_capability('mod/assign:grade', $this->context)) {
            return false;
        }
        return true;
    }
    
    /**
     * Show a confirmation page to make sure they want to release student identities
     *
     * @return string
     */
    private function view_reveal_identities_confirm() {
        global $CFG, $USER;

        require_capability('mod/assign:revealidentities', $this->get_context());
        
        $o = '';
        $o .= $this->output->render(new assignment_header($this, true));

        $confirmurl = new moodle_url('/mod/assign/view.php', array('id'=>$this->get_course_module()->id, 
                                                                    'action'=>'revealidentitiesconfirm',
                                                                    'sesskey'=>sesskey()));

        $cancelurl = new moodle_url('/mod/assign/view.php', array('id'=>$this->get_course_module()->id,
                                                                    'action'=>'grading'));

        $o .= $this->output->confirm(get_string('revealidentitiesconfirm', 'assign'), $confirmurl, $cancelurl);
        $o .= $this->view_footer();
        $this->add_to_log('view', get_string('viewrevealidentitiesconfirm', 'assign'));
        return $o;
    } 

    /**
     * View submissions page (contains details of current submission).
     *
     * @global stdClass $CFG
     * @global stdClass $USER
     * @return string
     */
    private function view_submission_page() {
        global $CFG, $USER;
        
        $o = '';
        $o .= $this->output->render(new assignment_header($this, true));

        if ($this->can_grade()) {
            $o .= $this->output->render(new grading_summary($this));
        }
        if ($this->can_view_submission($USER->id)) {
            $showedit = has_capability('mod/assign:submit', $this->context) &&
                         $this->submissions_open() && ($this->is_any_submission_plugin_enabled());

            $grade = $this->get_user_grade($USER->id, false);
            $submission = $this->get_user_submission($USER->id, false);
            $showsubmit = $showedit && $submission && ($submission->status == ASSIGN_SUBMISSION_STATUS_DRAFT);
            $gradelocked = ($grade && $grade->locked) || $this->grading_disabled($USER->id);

            $extensionduedate = null;   
            if ($grade) {
                $extensionduedate = $grade->extensionduedate; 
            }
            $submissiongroup = null;
            $teamsubmission = null;
            if ($this->is_team_submissions()) {
                $teamsubmission = $this->get_group_submission($USER->id, 0, false);
                $submissiongroup = $this->get_submission_group($USER->id);
            }

            $showsubmit = ($submission || $teamsubmission);
            if ($teamsubmission && ($teamsubmission->status == ASSIGN_SUBMISSION_STATUS_SUBMITTED)) {
                $showsubmit = false;
            }
            if ($submission && ($submission->status == ASSIGN_SUBMISSION_STATUS_SUBMITTED)) {
                $showsubmit = false;
            }
            $gradelocked = ($grade && $grade->locked) || $this->grading_disabled($USER->id);

            $o .= $this->output->render(new submission_status($this, 
                                                              $submission, 
                                                              $teamsubmission, 
                                                              $submissiongroup, 
                                                              $gradelocked, 
                                                              $this->is_graded($USER->id), 
                                                              submission_status::STUDENT_VIEW, 
                                                              $showedit, 
                                                              $showsubmit,
                                                              $extensionduedate));

            $o .= $this->output->render(new feedback_status($this, $grade, feedback_status::STUDENT_VIEW));
        }
        
            
        $o .= $this->view_footer();
        $this->add_to_log('view', get_string('viewownsubmissionstatus', 'assign'));
        return $o;
    } 
    
    /**
     * convert the final raw grade(s) in the  grading table for the gradebook 
     * 
     * @param stdClass $grade
     * @return array 
     */
    private function convert_grade_for_gradebook(stdClass $grade) {
        $gradebookgrade = array();
        
        // trying to match those array keys in grade update function in gradelib.php
        // with keys in th database table assign_grades
        // starting around line 262
        $gradebookgrade['rawgrade'] = $grade->grade;
        $gradebookgrade['userid'] = $grade->userid;
        $gradebookgrade['usermodified'] = $grade->grader;
        $gradebookgrade['datesubmitted'] = NULL;
        $gradebookgrade['dategraded'] = $grade->timemodified;
        if (isset($grade->feedbackformat)) {
            $gradebookgrade['feedbackformat'] = $grade->feedbackformat;
        }
        if (isset($grade->feedbacktext)) {
            $gradebookgrade['feedback'] = $grade->feedbacktext;
        }
       
        return $gradebookgrade;
    }
    
    /**
     * convert submission details for the gradebook  
     * 
     * @param stdClass $submission
     * @return array 
     */
    private function convert_submission_for_gradebook(stdClass $submission) {
        $gradebookgrade = array();
        
        
        $gradebookgrade['userid'] = $submission->userid;
        $gradebookgrade['usermodified'] = $submission->userid;
        $gradebookgrade['datesubmitted'] = $submission->timemodified;
       
        return $gradebookgrade;
    }

    /**
     * update grades in the gradebook
     * 
     * @param mixed stdClass|null $submission
     * @param mixed stdClass|null $grade
     * @return bool 
     */
    private function gradebook_item_update($submission=null, $grade=null) {
        
        if($submission != null){
            if ($submission->userid == 0) {
                // this is a group submission update
                $team = groups_get_members($submission->groupid, 'u.id');

                foreach ($team as $member) {
                    $submission->groupid = 0;
                    $submission->userid = $member->id;
                    $this->gradebook_item_update($submission, null); 
                }
                return;
            }
        }

        // do not push grade updates if blind marking is enabled because this would push student identities to the gradebook
        if ($this->is_blind_marking()) {
            return;
        }

        $params = array('itemname' => $this->get_instance()->name, 'idnumber' => $this->get_course_module()->id);

        if ($this->get_instance()->grade > 0) {
            $params['gradetype'] = GRADE_TYPE_VALUE;
            $params['grademax'] = $this->get_instance()->grade;
            $params['grademin'] = 0;
        } else if ($this->get_instance()->grade < 0) {
            $params['gradetype'] = GRADE_TYPE_SCALE;
            $params['scaleid'] = -$this->get_instance()->grade;
        } else {
            $params['gradetype'] = GRADE_TYPE_TEXT; // allow text comments only
        }
        
        if($submission != NULL){
            
            $gradebookgrade = $this->convert_submission_for_gradebook($submission);
            
            
        }else{
            
        
            $gradebookgrade = $this->convert_grade_for_gradebook($grade);
        }
        return grade_update('mod/assign', $this->get_course()->id, 'mod', 'assign', $this->get_instance()->id, 0, $gradebookgrade, $params);
    }

    /**
     * update team submission 
     * 
     * @global moodle_database $DB
     * @param stdClass $submission
     * @param int $userid
     * @param bool $updatetime
     * @return bool 
     */
    private function update_team_submission(stdClass $submission, $userid, $updatetime) {
        global $DB;

        if ($updatetime) {
            $submission->timemodified = time();
        }
        
        // first update the submission for the current user
       
        $mysubmission = $this->get_user_submission($userid, true);
        $mysubmission->status = $submission->status;

        $this->update_submission($mysubmission, 0, $updatetime, false);
            
        // now check the team settings to see if this assignment qualifies as submitted or draft
        $team = $this->get_submission_group_members($submission->groupid, true);

        $allsubmitted = true;
        $anysubmitted = false;
        foreach ($team as $member) {
            $membersubmission = $this->get_user_submission($member->id, false);

            if (!$membersubmission || $membersubmission->status != ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                $allsubmitted = false;
                break;
            } else {
                $anysubmitted = true;
            }
        }
        if ($this->get_instance()->requireallteammemberssubmit) {
            if ($allsubmitted) {
                $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
            } else {
                $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
            }
            $result= $DB->update_record('assign_submission', $submission);
        } else {
            if ($anysubmitted) {
                $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
            } else {
                $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
            }
            $result= $DB->update_record('assign_submission', $submission);
        }
        
        $this->gradebook_item_update($submission);
            
        return $result;
    }
    

    /**
     * update grades in the gradebook based on submission time 
     * 
     * @global moodle_database $DB
     * @param stdClass $submission
     * @param int $userid
     * @param bool $updatetime
     * @return bool 
     */
    private function update_submission(stdClass $submission, $userid, $updatetime, $teamsubmission) {
        global $DB;

        if ($teamsubmission) {
            return $this->update_team_submission($submission, $userid, $updatetime);
        }

        if ($updatetime) {
            $submission->timemodified = time();
        }

        $result= $DB->update_record('assign_submission', $submission);
        if ($result) {
            $this->gradebook_item_update($submission);
        }
        return $result;
    }

    /**
     * Is this assignment open for submissions?
     *
     * Check the due date, 
     * prevent late submissions, 
     * has this person already submitted, 
     * is the assignment locked?
     * 
     * @global stdClass $USER
     * @return bool 
     */
    private function submissions_open($userid = null) {
        global $USER;

        if (!$userid) {
            $userid = $USER->id;
        }
        $time = time();
        $dateopen = true;
        if ($this->get_instance()->preventlatesubmissions && $this->get_instance()->duedate) {
            $grade = $this->get_user_grade($userid, false);
            if ($grade && $grade->extensionduedate) { 
                $dateopen = ($this->get_instance()->allowsubmissionsfromdate <= $time && $time <= $grade->extensionduedate);
            } else {
                $dateopen = ($this->get_instance()->allowsubmissionsfromdate <= $time && $time <= $this->get_instance()->duedate);
            }
        } else {
            $dateopen = ($this->get_instance()->allowsubmissionsfromdate <= $time);
        }

        if (!$dateopen) {
            return false;
        }

        // now check if this user has already submitted etc.
        if (!is_enrolled($this->get_course_context(), $userid)) {
            return false;
        }
        if ($submission = $this->get_user_submission($userid, false)) {
            if ($this->get_instance()->submissiondrafts && $submission->status == ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                // drafts are tracked and the student has submitted the assignment
                return false;
            }
        }
        if ($grade = $this->get_user_grade($userid, false)) {
            if ($grade->locked) {
                return false;
            }
        }

        if ($this->grading_disabled($userid)) {
            return false;
        }

        return true;
    }
    
    /**
     * render the files in file area  
     * @global stdClass $USER
     * @param string $area
     * @param int $submissionid
     * @return string 
     */
    public function render_area_files($area, $submissionid) {
        global $USER;

        $fs = get_file_storage();
        $browser = get_file_browser();
        $files = $fs->get_area_files($this->get_context()->id, 'mod_assign', $area , $submissionid , "timemodified", false);              
        return $this->output->assign_files($this->context, $submissionid, $area);
        
    }

    /**
     * Returns a list of teachers that should be grading given submission
     *
     * @param int $userid
     * @return array
     */
    private function get_graders($userid) {
        //potential graders
        $potentialgraders = get_enrolled_users($this->context, "mod/assign:grade");

        $graders = array();
        if (groups_get_activity_groupmode($this->get_course_module()) == SEPARATEGROUPS) {   // Separate groups are being used
            if ($groups = groups_get_all_groups($this->get_course()->id, $userid)) {  // Try to find all groups
                foreach ($groups as $group) {
                    foreach ($potentialgraders as $grader) {
                        if ($grader->id == $userid) {
                            continue; // do not send self
                        }
                        if (groups_is_member($group->id, $grader->id)) {
                            $graders[$grader->id] = $grader;
                        }
                    }
                }
            } else {
                // user not in group, try to find graders without group
                foreach ($potentialgraders as $grader) {
                    if ($grader->id == $userid) {
                        continue; // do not send self
                    }
                    if (!groups_has_membership($this->get_course_module(), $grader->id)) {
                        $graders[$grader->id] = $grader;
                    }
                }
            }
        } else {
            foreach ($potentialgraders as $grader) {
                if ($grader->id == $userid) {
                    continue; // do not send self
                }
                // must be enrolled
                if (is_enrolled($this->get_course_context(), $grader->id)) {
                    $graders[$grader->id] = $grader;
                }
            }
        }
        return $graders;
    }

    /**
     * Format a notification based on it's type
     * 
     * @param string $messagetype
     * @param stdClass $info
     * @param stdClass $course
     * @param stdClass $context
     * @param string $modulename
     * @param string $assignmentname
     */
    private static function format_notification_message_text($messagetype, $info, $course, $context, $modulename, $assignmentname) {
        $posttext  = format_string($course->shortname, true, array('context' => $context->get_course_context())).' -> '.
                     $modulename.' -> '.
                     format_string($assignmentname, true, array('context' => $context))."\n";
        $posttext .= '---------------------------------------------------------------------'."\n";
        $posttext .= get_string($messagetype . 'text', "assign", $info)."\n";
        $posttext .= "\n---------------------------------------------------------------------\n";
        return $posttext;
    }
    
    /**
     * Format a notification based on it's type
     * 
     * @param string $messagetype
     * @param stdClass $info
     */
    private function format_notification_message_html($messagetype, $info, $course, $context, $modulename, $coursemodule, $assignmentname) {
        global $CFG;
        $posthtml  = '<p><font face="sans-serif">'.
                     '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.format_string($course->shortname, true, array('context' => $context->get_course_context())).'</a> ->'.
                     '<a href="'.$CFG->wwwroot.'/mod/assignment/index.php?id='.$course->id.'">'.$modulename.'</a> ->'.
                     '<a href="'.$CFG->wwwroot.'/mod/assignment/view.php?id='.$coursemodule->id.'">'.format_string($assignmentname, true, array('context' => $context)).'</a></font></p>';
        $posthtml .= '<hr /><font face="sans-serif">';
        $posthtml .= '<p>'.get_string($messagetype . 'html', 'assign', $info).'</p>';
        $posthtml .= '</font><hr />';
        return $posthtml;
    }
    
    /**
     * email someone about something (static so it can be called from cron)
     * 
     * @global stdClass $CFG
     * @global moodle_database $DB
     * @param stdClass $userfrom
     * @param stdClass $userto
     * @param string $messagetype
     * @param string $eventtype
     * @param int $updatetime
     * @param stdClass $coursemodule
     * @param stdClass $context
     * @param stdClass $course
     * @param string $modulename
     * @param string $moduleplural
     * @param string $assignmentname
     * @return void 
     */
    public static function send_assignment_notification($userfrom, $userto, $messagetype, $eventtype, 
                                                        $updatetime, $coursemodule, $context, $course, 
                                                        $modulename, $assignmentname) {
        global $CFG, $DB;

        $strnotification  = get_string('notification', 'assign');

        $info = new stdClass();
        $info->username = fullname($userfrom, true);
        $info->assignment = format_string($assignmentname,true, array('context'=>$context));
        $info->url = $CFG->wwwroot.'/mod/assign/view.php?id='.$coursemodule->id;
        $info->timeupdated = strftime('%c',$updatetime);

        $postsubject = get_string($messagetype . 'small', 'assign', $info);
        $posttext = assignment::format_notification_message_text($messagetype, $info, $course, $context, $modulename, $assignmentname);
        $posthtml = ($userto->mailformat == 1) ? assignment::format_notification_message_html($messagetype, $info, $course, $context, $modulename, $coursemodule, $assignmentname) : '';

        $eventdata = new stdClass();
        $eventdata->modulename       = 'assign';
        $eventdata->userfrom         = $userfrom;
        $eventdata->userto           = $userto;
        $eventdata->subject          = $postsubject;
        $eventdata->fullmessage      = $posttext;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml  = $posthtml;
        $eventdata->smallmessage     = $postsubject;

        $eventdata->name            = $eventtype;
        $eventdata->component       = 'mod_assign';
        $eventdata->notification    = 1;
        $eventdata->contexturl      = $info->url;
        $eventdata->contexturlname  = $info->assignment;

        message_send($eventdata);
        
    }

    /**
     * email someone about something
     * 
     * @global stdClass $CFG
     * @global moodle_database $DB
     * @param stdClass $submission
     * @return void 
     */
    public function send_notification($userfrom, $userto, $messagetype, $eventtype, $updatetime) {
        self::send_assignment_notification($userfrom, $userto, $messagetype, $eventtype, $updatetime, $this->get_course_module(), $this->get_context(), $this->get_course(), $this->get_module_name(), $this->get_instance()->name);
    }
    
    /**
     * email student upon successful submission
     * 
     * @global stdClass $CFG
     * @global moodle_database $DB
     * @param stdClass $submission
     * @return void 
     */
    private function email_student_submission_receipt(stdClass $submission) {
        global $CFG, $DB, $USER;

        if (!$CFG->mod_assign_submission_receipts) {          // No need to do anything
            return;
        }

        if ($this->is_team_submissions()) {
            $members = $this->get_submission_group_members($submission->groupid, false);
            foreach ($members as $member) {
                $this->send_notification($member, $member, 'teamsubmissionreceipt', 'assign_student_notification', $submission->timemodified); 
            }
        } else {
            $user = $DB->get_record('user', array('id'=>$submission->userid), '*', MUST_EXIST);
            $this->send_notification($user, $user, 'submissionreceipt', 'assign_student_notification', $submission->timemodified); 
        }

    }
    
    /**
     * email graders upon student submissions 
     * 
     * @global stdClass $CFG
     * @global stdClass $USER
     * @global moodle_database $DB
     * @param stdClass $submission
     * @return void 
     */
    private function email_graders(stdClass $submission) {
        global $CFG, $DB, $USER;

        $late = $this->get_instance()->duedate && ($this->get_instance()->duedate < time());

        if (!$this->get_instance()->sendnotifications && !($late && $this->get_instance()->sendlatenotifications)) {          // No need to do anything
            return;
        }

        if ($submission->userid) {
            $user = $DB->get_record('user', array('id'=>$submission->userid), '*', MUST_EXIST);
        } else {
            $user = $USER;
        }

        if ($teachers = $this->get_graders($user->id)) {

            $strassignments = $this->get_module_name_plural();
            $strassignment  = $this->get_module_name();
            $strsubmitted  = get_string('submitted', 'assign');

            foreach ($teachers as $teacher) {
                $this->send_notification($user, $teacher, 'gradersubmissionupdated', 'assign_grader_notification', $submission->timemodified); 
            }
        }
    }
    
    /**
     * assignment submission is processed before grading
     * 
     * @global stdClass $USER 
     * @global stdClass $CFG 
     * @return void
     */
    private function process_submit_assignment_for_grading() {
        global $USER, $CFG;
        
        // Need submit permission to submit an assignment
        require_capability('mod/assign:submit', $this->context);
        require_once($CFG->dirroot . '/mod/assign/confirm_submission_form.php');
        require_sesskey();
        
        $data = new stdClass();
        $mform = new mod_assign_confirm_submission_form(null, array($this, $data));
        
        $formdata = $mform->get_data();
        if (!$mform->is_cancelled()) {
            if ($this->is_team_submissions()) {
                $submission = $this->get_group_submission($USER->id, 0, true);
            } else {
                $submission = $this->get_user_submission($USER->id, true);
            }
        
            if ($submission->status != ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;

                $this->update_submission($submission, $USER->id, true, $this->is_team_submissions());
                if (isset($formdata->submissionstatement)) {
                    $this->add_to_log('submission statement accepted', get_string('submissionstatementacceptedlog', 'mod_assign', fullname($USER)));
                }
                $this->add_to_log('submit for grading', $this->format_submission_for_log($submission));
                $this->email_student_submission_receipt($submission);
                $this->email_graders($submission);
            }
        }
    }
    
    /**
     * save extension date 
     * 
     * @global moodle_database $DB
     * @global stdClass $CFG
     * @return void
     */
    private function process_save_extension(& $mform) {
        global $DB, $CFG;

        // Include extension form 
        require_once($CFG->dirroot . '/mod/assign/extension_form.php');
        
        // Need submit permission to submit an assignment
        require_capability('mod/assign:grantextension', $this->context);
        
        $userid = required_param('userid', PARAM_INT);
    
        $user = $DB->get_record('user', array('id'=>$userid), '*', MUST_EXIST);
        
        $mform = new mod_assign_extension_form(null, array($this, $user, null));
        
        if ($mform->is_cancelled()) {
            return true;
        }
        
        if ($formdata = $mform->get_data()) {
            $grade = $this->get_user_grade($userid, true);
            $grade->extensionduedate = $formdata->extensionduedate;
            $grade->timemodified = time();

            return $DB->update_record('assign_grades', $grade);
        }
        return false;
    }
    
    /**
     * reveal student identities to markers (and the gradebook)
     * 
     * @return void
     */
    private function process_reveal_identities() {
        global $DB, $CFG;

        require_capability('mod/assign:revealidentities', $this->context);
        if (!confirm_sesskey()) {
            return false;
        }

        // update the assignment record
        $update = new stdClass();
        $update->id = $this->get_instance()->id;
        $update->revealidentities = 1;
        $DB->update_record('assign', $update);
        $this->instance = null;
        

        // release the grades to the gradebook

        $grades = $DB->get_records('assign_grades', array('assignment'=>$this->get_instance()->id));
        $gradebookplugin = $CFG->mod_assign_feedback_plugin_for_gradebook;

        $plugin = $this->get_feedback_plugin_by_type($gradebookplugin);

        foreach ($grades as $grade) {
            // fetch any comments for this student
            if ($plugin && $plugin->is_enabled() && $plugin->is_visible()) {
                $grade->feedbacktext = $plugin->text_for_gradebook($grade);
                $grade->feedbackformat = $plugin->format_for_gradebook($grade);
            }
            $this->gradebook_item_update(NULL, $grade);
        }

        
    }
    
    /**
     * save grading options 
     * 
     * @global stdClass $USER
     * @global stdClass $CFG
     * @return void
     */
    private function process_save_grading_options() {
        global $USER, $CFG;

        // Include grading options form 
        require_once($CFG->dirroot . '/mod/assign/grading_options_form.php');
        
        // Need submit permission to submit an assignment
        require_capability('mod/assign:grade', $this->context);
        
        
        
        $mform = new mod_assign_grading_options_form(null, array('cm'=>$this->get_course_module()->id, 'contextid'=>$this->context->id, 'userid'=>$USER->id));
        
        if ($formdata = $mform->get_data()) {
            set_user_preference('assign_perpage', $formdata->perpage);
            set_user_preference('assign_filter', $formdata->filter);
        }
    }
    
   /**
    * Take a grade object and print a short summary for the log file. 
    * The size limit for the log file is 255 characters, so be careful not
    * to include too much information.
    * 
    * @global moodle_database $DB
    * @param stdClass $grade
    * @return string 
    */
    private function format_grade_for_log(stdClass $grade) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $grade->userid), '*', MUST_EXIST);
        
        $info = get_string('gradestudent', 'assign', array('id'=>$user->id, 'fullname'=>fullname($user)));
        if ($grade->grade != '') {
            $info .= get_string('grade', 'assign') . ': ' . $this->display_grade($grade->grade) . '. ';
        } else {
            $info .= get_string('nograde', 'assign');
        }
        if ($grade->locked) {
            $info .= get_string('submissionslocked', 'assign') . '. ';
        }
        return $info;
    }
    
    /**
     * Take a submission object and print a short summary for the log file. 
     * The size limit for the log file is 255 characters, so be careful not
     * to include too much information.
     * 
     * @param stdClass $submission
     * @return string 
     */
    private function format_submission_for_log(stdClass $submission) {
        $info = '';
        $info .= get_string('submissionstatus', 'assign') . ': ' . get_string('submissionstatus_' . $submission->status, 'assign') . '. <br>';
        // format_for_log here iterating every single log INFO  from either submission or grade in every assignment plugin

        foreach ($this->submissionplugins as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {


                $info .= "<br>" . $plugin->format_for_log($submission);
            }
        }


        return $info;
    }
    
    /**
     * save assignment submission
     * 
     * @global stdClass $USER
     * @global stdClass $CFG
     * @param  moodleform $mform
     * @return bool 
     */
    private function process_save_submission(&$mform) {
        global $USER, $CFG;
        
        // Include submission form 
        require_once($CFG->dirroot . '/mod/assign/submission_form.php');

        // Need submit permission to submit an assignment
        require_capability('mod/assign:submit', $this->context);
      
        $defaultdata = new stdClass();
        $mform = new mod_assign_submission_form(null, array($this, $defaultdata));
        if ($mform->is_cancelled()) {
            return true;
        }
        if ($data = $mform->get_data()) {               
            if ($this->is_team_submissions()) {
                $submission = $this->get_group_submission($USER->id, 0, true);
            } else {
                $submission = $this->get_user_submission($USER->id, true);
            }
            $grade = $this->get_user_grade($USER->id, false); // get the grade to check if it is locked
            if ($grade && $grade->locked) {
                print_error('submissionslocked', 'assign');
                return true;
            }
          
        
            foreach ($this->submissionplugins as $plugin) {
                if ($plugin->is_enabled()) {
                    if (!$plugin->save($submission, $data)) {
                        print_error($plugin->get_error());
                    }
                }
            }
           
            $this->update_submission($submission, $USER->id, true, $this->is_team_submissions());

            if (isset($data->submissionstatement)) {
                $this->add_to_log('submission statement accepted', get_string('submissionstatementacceptedlog', 'mod_assign', fullname($USER)));
            }

            // Logging
            $this->add_to_log('submit', $this->format_submission_for_log($submission));

            if (!$this->get_instance()->submissiondrafts) {
                $this->email_student_submission_receipt($submission);
                $this->email_graders($submission);
            }
            return true;
        }
        return false;
    }
    
    /**
     * count the number of files in the file area
     * 
     * @global stdClass $USER
     * @param int $userid
     * @param string $area
     * @return int  
     */
    private function count_files($userid = 0, $area = ASSIGN_FILEAREA_SUBMISSION_FILES) {
        global $USER;

        if (!$userid) {
            $userid = $USER->id;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_assign', $area, $userid, "id", false);

        return count($files);
    }

    /**
     * Determine if this users grade is locked or overridden
     * 
     * @global stdClass $CFG
     * @param int $userid - The student userid
     * @return bool $gradingdisabled
     */
    private function grading_disabled($userid) {
        global $CFG;
        
        $gradinginfo = grade_get_grades($this->get_course()->id, 'mod', 'assign', $this->get_instance()->id, array($userid));
        if (!$gradinginfo) {
            return false;
        }

        if (!isset($gradinginfo->items[0]->grades[$userid])) {
            return false;
        }
        $gradingdisabled = $gradinginfo->items[0]->grades[$userid]->locked || $gradinginfo->items[0]->grades[$userid]->overridden;
        return $gradingdisabled;
    }

    /**
     * Is advanced grading enabled and is there a form available?
     * 
     * @return bool
     */
    public function use_advanced_grading() {
        $gradingmanager = get_grading_manager($this->context, 'mod_assign', 'submissions');
        if ($gradingmethod = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller($gradingmethod);
            if ($controller->is_form_available()) {
                return true;
            }
        }
        return false;
    }


    /**
     * Get an instance of a grading form if advanced grading is enabled
     * This is specific to the assignment, marker and student
     * 
     * @global stdClass $CFG
     * @global stdClass $USER
     * @param int $userid - The student userid
     * @param bool $gradingdisabled
     * @return mixed gradingform_instance|null $gradinginstance
     */
    private function get_grading_instance($userid, $gradingdisabled) {
        global $CFG, $USER;

        $grade = $this->get_user_grade($userid, false);
        $grademenu = make_grades_menu($this->get_instance()->grade);

        $advancedgradingwarning = false;
        $gradingmanager = get_grading_manager($this->context, 'mod_assign', 'submissions');
        $gradinginstance = null;
        if ($gradingmethod = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller($gradingmethod);
            if ($controller->is_form_available()) {
                $itemid = null;
                if ($grade) {
                    $itemid = $grade->id;
                }
                if ($gradingdisabled && $itemid) {
                    $gradinginstance = ($controller->get_current_instance($USER->id, $itemid));
                } else if (!$gradingdisabled) {
                    $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                    $gradinginstance = ($controller->get_or_create_instance($instanceid, $USER->id, $itemid));
                }
            } else {
                $advancedgradingwarning = $controller->form_unavailable_notification();
            }
        }
        if ($gradinginstance) {
            $gradinginstance->get_controller()->set_grade_range($grademenu);
        }
        return $gradinginstance;
    }
    
    /**
     * add elements to grade form 
     * 
     * @global stdClass $USER
     * @global stdClass $CFG
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @param array $params 
     * @return void
     */
    public function add_grade_form_elements(MoodleQuickForm $mform, stdClass $data, $params) {
        global $USER, $CFG;
        $settings = $this->get_instance();

        $rownum = $params['rownum'];
        $last = false;
        $userid = $this->get_userid_for_row($rownum, $last);
        $grade = $this->get_user_grade($userid, false);
        
        // add advanced grading
        $gradingdisabled = $this->grading_disabled($userid);
        $gradinginstance = $this->get_grading_instance($userid, $gradingdisabled);

        if ($gradinginstance) {
            $gradingelement = $mform->addElement('grading', 'advancedgrading', get_string('grade').':', array('gradinginstance' => $gradinginstance));
            if ($gradingdisabled) {
                $gradingelement->freeze();
            } else {
                $mform->addElement('hidden', 'advancedgradinginstanceid', $gradinginstance->get_id());
            }
        } else {
            // use simple direct grading
            if ($this->get_instance()->grade > 0) {
                $mform->addElement('text', 'grade', get_string('gradeoutof', 'assign', $this->get_instance()->grade));
                $mform->setType('grade', PARAM_TEXT);
            } else {
                $grademenu = make_grades_menu($this->get_instance()->grade);
                $grademenu['-1'] = get_string('nograde');

                $mform->addElement('select', 'grade', get_string('grade').':', $grademenu);
                $mform->setType('grade', PARAM_INT);
            }
        }


        // plugins
        $this->add_plugin_grade_elements($grade, $mform, $data);

        
        // hidden params
        $mform->addElement('hidden', 'id', $this->get_course_module()->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'rownum', $params['rownum']);
        $mform->setType('rownum', PARAM_INT);

        if ($this->is_team_submissions()) {
            $mform->addElement('selectyesno', 'applytoall', get_string('applytoteam', 'assign'));
            $mform->setDefault('applytoall', 1);
        }
        
        $mform->addElement('hidden', 'action', 'submitgrade');
        $mform->setType('action', PARAM_ALPHA);
          
        $buttonarray=array();
       
        if (!$last){
            $buttonarray[] = $mform->createElement('submit', 'saveandshownext', get_string('savenext','assign')); 
            $buttonarray[] = $mform->createElement('submit', 'nosaveandnext', get_string('nosavebutnext', 'assign'));
        }
        $buttonarray[] = $mform->createElement('submit', 'savegrade', get_string('savechanges', 'assign'));        
        $buttonarray[] = $mform->createElement('cancel', 'cancelbutton', get_string('cancel','assign'));     
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');            
    }

    
    /**
     * add elements in submission plugin form 
     * 
     * @param mixed stdClass|null $submission
     * @param MoodleQuickForm $mform
     * @param stdClass $data 
     * @return void
     */
    private function add_plugin_submission_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        foreach ($this->submissionplugins as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible() && $plugin->allow_submissions()) {
                $mform->addElement('header', 'header_' . $plugin->get_type(), $plugin->get_name());
                if (!$plugin->get_form_elements($submission, $mform, $data)) {
                    $mform->removeElement('header_' . $plugin->get_type());
                }
            }
        }
    }
    
    /**
     * check if submission plugins installed are enabled 
     * 
     * @return bool
     */
    public function is_any_submission_plugin_enabled() {
        if (!isset($this->cache['any_submission_plugin_enabled'])) {
            $this->cache['any_submission_plugin_enabled'] = false;
            foreach ($this->submissionplugins as $plugin) {
                if ($plugin->is_enabled() && $plugin->is_visible() && $plugin->allow_submissions()) {
                    $this->cache['any_submission_plugin_enabled'] = true;
                    break;
                }
            }
        }

        return $this->cache['any_submission_plugin_enabled'];
        
    }

    /**
     * add elements to submission form 
     * @global stdClass $USER
     * @param MoodleQuickForm $mform
     * @param stdClass $data 
     * @return void
     */
    public function add_submission_form_elements(MoodleQuickForm $mform, stdClass $data) {
        global $USER;
        
        // online text submissions

        if ($this->is_team_submissions()) {
            $submission = $this->get_group_submission($USER->id, 0, false);
        } else {
            $submission = $this->get_user_submission($USER->id, false);
        }

        // submission statement
        $config = get_config('assign');
        if (($config->require_submission_statement || 
             $this->instance->requiresubmissionstatement) && 
            !$this->instance->submissiondrafts) {
            $mform->addElement('checkbox', 'submissionstatement', '', ' ' . $config->submission_statement);
            $mform->addRule('submissionstatement', get_string('required'), 'required', null, 'client');
        }
        
        $this->add_plugin_submission_elements($submission, $mform, $data);

        // hidden params
        $mform->addElement('hidden', 'id', $this->get_course_module()->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action', 'savesubmission');
        $mform->setType('action', PARAM_TEXT);
        // buttons
        
    }

    /**
     * revert to draft
     * Uses url parameter userid
     * @global stdClass $USER
     * @global moodle_database $DB
     * @return void
     */
    private function process_revert_to_draft() {
        global $USER, $DB;
        
        // Need grade permission
        require_capability('mod/assign:grade', $this->context);

        $userid = required_param('userid', PARAM_INT);

        if ($this->is_team_submissions()) {
            $submission = $this->get_group_submission($userid, 0, false);
        } else {
            $submission = $this->get_user_submission($userid, false);
        }
        if (!$submission) {
            return;
        }
        $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
        $this->update_submission($submission, $userid, false, $this->is_team_submissions());

        // update the modified time on the grade (grader modified)
        $grade = $this->get_user_grade($userid, true);
        $grade->grader = $USER->id;
        $this->update_grade($grade);

        $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

        $this->add_to_log('revert submission to draft', get_string('reverttodraftforstudent', 'assign', array('id'=>$user->id, 'fullname'=>fullname($user))));
        
    }
    
    /**
     * lock  the process
     * Uses url parameter userid
     * @global stdClass $USER
     * @global moodle_database $DB
     * @return void
     */
    private function process_lock() {
        global $USER, $DB;
        
        // Need grade permission
        require_capability('mod/assign:grade', $this->context);

        $userid = required_param('userid', PARAM_INT);

        $grade = $this->get_user_grade($userid, true);
        $grade->locked = 1;
        $grade->grader = $USER->id;
        $this->update_grade($grade);

        $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

        $this->add_to_log('lock submission', get_string('locksubmissionforstudent', 'assign', array('id'=>$user->id, 'fullname'=>fullname($user))));
    }
    
    /**
     * unlock the process
     * 
     * @global stdClass $USER
     * @global moodle_database $DB 
     * @return void
     */
    private function process_unlock() {
        global $USER, $DB;

        // Need grade permission
        require_capability('mod/assign:grade', $this->context);

        $userid = required_param('userid', PARAM_INT);

        $grade = $this->get_user_grade($userid, true);
        $grade->locked = 0;
        $grade->grader = $USER->id;
        $this->update_grade($grade);
        
        $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

        $this->add_to_log('unlock submission', get_string('unlocksubmissionforstudent', 'assign', array('id'=>$user->id, 'fullname'=>fullname($user))));
    }

    /**
     * Apply a grade from a grading form to a user (may be called multiple times for a group submission)
     *
     * @global stdClass $USER
     * @global stdClass $CFG
     * @global moodle_database $DB
     * @param stdClass $formdata - the data from the form
     * @param int $userid - the user to apply the grade to
     * @return void
     */
    private function apply_grade_to_user($formdata, $userid) {
        global $USER, $CFG, $DB;

        $grade = $this->get_user_grade($userid, true);
        $gradingdisabled = $this->grading_disabled($userid);
        $gradinginstance = $this->get_grading_instance($userid, $gradingdisabled);
        if ($gradinginstance) {
            $grade->grade = $gradinginstance->submit_and_get_grade($formdata->advancedgrading, $grade->id);
        } else {
            $grade->grade= grade_floatval($formdata->grade);
        }
        $grade->grader= $USER->id;

        $gradebookplugin = $CFG->mod_assign_feedback_plugin_for_gradebook;

        // call save in plugins
        foreach ($this->feedbackplugins as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                if (!$plugin->save($grade, $formdata)) {
                    $result = false;
                    print_error($plugin->get_error());
                }
                if (('assignfeedback_' . $plugin->get_type()) == $gradebookplugin) {
                    // this is the feedback plugin chose to push comments to the gradebook
                    $grade->feedbacktext = $plugin->text_for_gradebook($grade);
                    $grade->feedbackformat = $plugin->format_for_gradebook($grade);
                }
            }
        }
        $this->update_grade($grade);

        $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

        $this->add_to_log('grade submission', $this->format_grade_for_log($grade));
    }
  
    /**
     * save grade
     * 
     * @global stdClass $CFG 
     * @param  moodleform $mform
     * @return bool - was the grade saved
     */
    private function process_save_grade(&$mform) {
        global $CFG;
        // Include grade form 
        require_once($CFG->dirroot . '/mod/assign/grade_form.php');
        
        // Need submit permission to submit an assignment
        require_capability('mod/assign:grade', $this->context);

        $rownum = required_param('rownum', PARAM_INT);
        $last = false;
        $userid = $this->get_userid_for_row($rownum, $last);
        $data = new stdClass();
        $mform = new mod_assign_grade_form(null, array($this, $data, array('rownum'=>$rownum, 'last'=>false)));

        if ($formdata = $mform->get_data()) {
            if ($this->is_team_submissions() && $formdata->applytoall) {
                $groupid = 0;
                if ($this->get_submission_group($userid)) {
                    $groupid = $this->get_submission_group($userid)->id;
                }
                $members = $this->get_submission_group_members($groupid, true);
                foreach ($members as $member) {
                    // user may exist in multple groups (which should put them in the default group)
                    $this->apply_grade_to_user($formdata, $member->id);
                }
            } else {
                $this->apply_grade_to_user($formdata, $userid);
            }
             
        } else {
            return false;
        }
        return true;
    }

    /**
     * This function returns true if it can upgrade an assignment from the 2.2
     * module.
     * @param string $type The plugin type
     * @param int $version The plugin version
     * @return bool
     */
    public function can_upgrade($type, $version) {
        if ($type == 'offline' && $version >= 2011112900) {
            return true;
        }
        foreach ($this->submissionplugins as $plugin) {
            if ($plugin->can_upgrade($type, $version)) {
                return true;
            }
        }
        foreach ($this->feedbackplugins as $plugin) {
            if ($plugin->can_upgrade($type, $version)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Copy all the files from the old assignment files area to the new one.
     * This is used by the plugin upgrade code.
     * 
     * @param int $oldcontextid The old assignment context id
     * @param int $oldcomponent The old assignment component ('assignment')
     * @param int $oldfilearea The old assignment filearea ('submissions')
     * @param int $olditemid The old submissionid (can be null e.g. intro)
     * @param int $newcontextid The new assignment context id
     * @param int $newcomponent The new assignment component ('assignment')
     * @param int $newfilearea The new assignment filearea ('submissions')
     * @param int $newitemid The new submissionid (can be null e.g. intro)
     * @return int The number of files copied
     */
    public function copy_area_files_for_upgrade($oldcontextid, $oldcomponent, $oldfilearea, $olditemid, $newcontextid, $newcomponent, $newfilearea, $newitemid) {
        // Note, this code is based on some code in filestorage - but that code
        // deleted the old files (which we don't want)
        $count = 0;

        $fs = get_file_storage();
    
        $oldfiles = $fs->get_area_files($oldcontextid, $oldcomponent, $oldfilearea, $olditemid, 'id', false);
        foreach ($oldfiles as $oldfile) {
            $filerecord = new stdClass();
            $filerecord->contextid = $newcontextid;
            $filerecord->component = $newcomponent;
            $filerecord->filearea = $newfilearea;
            $filerecord->itemid = $newitemid;
            $fs->create_file_from_storedfile($filerecord, $oldfile);
            $count += 1;
        }

        return $count;
    }


    /**
     * Lookup this user id and return the unique id for this assignment
     * 
     * @global moodle_database $DB
     * @param int $userid The userid to lookup
     * @return int The unique id
     */
    public function get_uniqueid_for_user($userid) {
        global $DB;
        
        // search for a record
        if ($record = $DB->get_record('assign_user_mapping', array('assignment'=>$this->get_instance()->id, 'userid'=>$userid), 'id')) {
            return $record->id;
        }

        // create a record
        $record = new stdClass();
        $record->assignment = $this->get_instance()->id;
        $record->userid = $userid;
        
        return $DB->insert_record('assign_user_mapping', $record);
    }

    /**
     * Lookup this unique id and return the user id for this assignment
     * 
     * @global moodle_database $DB
     * @param int $uniqueid The uniqueid to lookup
     * @return int The user id
     */
    public function get_user_for_uniqueid($uniqueid) {
        global $DB;
        
        // search for a record
        if ($record = $DB->get_record('assign_user_mapping', array('id'=>$uniqueid, 'assignment'=>$this->get_instance()->id), 'userid')) {
            return $record->userid;
        }
        return false;
    }
}
