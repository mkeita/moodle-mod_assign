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
 * This file contains a renderer for the assignment class
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Include locallib.php */
require_once($CFG->dirroot . '/mod/assign/locallib.php');


/**
 * A custom renderer class that extends the plugin_renderer_base and
 * is used by the assign module.
 *
 * @package mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class mod_assign_renderer extends plugin_renderer_base {

    /**
     * rendering assignment files 
     * 
     * @param context $context
     * @param int $userid
     * @param string $filearea
     * @return string
     */
    public function assign_files(context $context, $userid, $filearea) {
        return $this->render(new assign_files($context, $userid, $filearea));
    }

    /**
     * rendering assignment files 
     * 
     * @param assign_files $tree
     * @return string
     */
    public function render_assign_files(assign_files $tree) {
        $module = array('name'=>'mod_assign_files', 'fullpath'=>'/mod/assign/assign.js', 'requires'=>array('yui2-treeview'));
        $this->htmlid = 'assign_files_tree_'.uniqid();
        $this->page->requires->js_init_call('M.mod_assign.init_tree', array(true, $this->htmlid));
        $html = '<div id="'.$this->htmlid.'">';
        $html .= $this->htmllize_tree($tree, $tree->dir);
        $html .= '</div>';

        if ($tree->portfolioform) {
            $html .= $tree->portfolioform;
        }
        return $html;
    }
    
    /**
     * Utility function to add a row of data to a table with 2 columns. Modified
     * the table param and does not return a value
     * 
     * @param html_table $table The table to append the row of data to
     * @param string $first The first column text
     * @param string $second The second column text
     * @return void
     */
    private function add_table_row_tuple(html_table $table, $first, $second) {
        $row = new html_table_row();
        $cell1 = new html_table_cell($first);
        $cell2 = new html_table_cell($second);
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;
    }
    
    /**
     * Render the grant extension form
     * @param mod_assign_extension_form $form The extension date form to render
     * @return string
     */
    public function render_mod_assign_extension_form(mod_assign_extension_form $form) {
        $o = '';
        
        $o .= $this->output->heading(get_string('grantextension', 'assign'), 3);
        $o .= $this->render(new user_summary($form->user, $form->assignment));

        $o .= $this->output->box_start('boxaligncenter grantextension');
        $o .= $this->moodleform($form);
        $o .= $this->output->box_end();
        return $o;
    }
    
    /**
     * Render the grading options form
     * @param grading_options_form $form The grading options form to render
     * @return string
     */
    public function render_grading_options_form(grading_options_form $form) {
        $o = '';
        $o .= $this->output->box_start('boxaligncenter gradingoptionsform');
        $o .= $this->moodleform($form->get_form());
        $o .= $this->output->box_end();
        return $o;
    }
    
    /**
     * Render the grading form
     * @param grading_form $form The grading form to render
     * @return string
     */
    public function render_grading_form(grading_form $form) {
        $o = '';
        $o .= $this->output->heading(get_string('grade'), 3);
        $o .= $this->output->box_start('boxaligncenter gradingform');
        $o .= $this->moodleform($form->get_form());
        $o .= $this->output->box_end();
        return $o;
    }
    
    /**
     * Render the import grades form
     * 
     * @param moodleform $form
     * @return string
     */
    public function render_mod_assign_importgrades_form(moodleform $form) {
        $o = '';

        $o .= $this->output->container_start('importgradeshelp');
        $o .= $this->output->box_start('generalbox');
        $o .= format_string(get_string('importgradeshelp', 'assign'), array('context'=>$form->assignment->get_context()));
        $o .= $this->output->box_end();
        $o .= $this->output->container_end();

        $o .= $this->moodleform($form);

        return $o;
    }

    /**
     * Render the upload grades form
     * 
     * @param moodleform $form
     * @return string
     */
    public function render_mod_assign_uploadgrades_form(moodleform $form) {
        $o = '';

        $o .= $this->output->container_start('uploadgradeshelp');
        $o .= $this->output->box_start('generalbox');
        $o .= format_text(get_string('uploadgradeshelp', 'assign'), array('context'=>$form->assignment->get_context()));
        $o .= $this->output->single_button(new moodle_url('/mod/assign/view.php', array('id' => $form->assignment->get_course_module()->id, 'action' => 'grading', 'download'=>'csv')), get_string('downloadlatestgradingspreadsheet', 'assign'), 'get');
        $o .= $this->output->box_end();
        $o .= $this->output->container_end();

        $o .= $this->moodleform($form);

        return $o;
    }
    
    /**
     * Render the user summary
     * 
     * @param user_summary $summary The user summary to render
     * @return string
     */
    public function render_user_summary(user_summary $summary) {
        $o = '';

        if (!$summary->get_user()) {
            return;
        }
        $o .= $this->output->container_start('usersummary');
        $o .= $this->output->box_start('boxaligncenter usersummarysection');
        if (!$summary->get_assignment()->is_blind_marking()) {
            $o .= $this->output->user_picture($summary->get_user());
            $o .= $this->output->spacer(array('width'=>30));
            $o .= $this->output->action_link(new moodle_url('/user/view.php', array('id' => $summary->get_user()->id, 'course'=>$summary->get_assignment()->get_course()->id)), fullname($summary->get_user(), has_capability('moodle/site:viewfullnames', $summary->get_assignment()->get_course_context())));
        } else {
            $o .= get_string('hiddenuser', 'assign', $summary->get_assignment()->get_uniqueid_for_user($summary->get_user()->id));
        }
        $o .= $this->output->box_end();
        $o .= $this->output->container_end();
        
        return $o;
    }

    /**
     * Page is done - render the footer
     * 
     * @return void
     */
    public function render_footer() {
        return $this->output->footer();
    }
    
    /**
     * render the confirm submission form
     *
     * @param confirm_submission_form $form
     * @return string
     */
    public function render_confirm_submission_form(confirm_submission_form $confirmform) {
        $o = '';
    
        $o .= $this->output->container_start('confirmsubmission');
        $o .= $this->output->heading(get_string('submission', 'assign'), 3);
        $o .= $this->output->box_start('boxaligncenter confirmsubmissionform');


        $o .= $this->moodleform($confirmform->get_form());
        
        $o .= $this->output->box_end();
        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * render the edit submission form
     *
     * @param edit_submission_form $form
     * @return string
     */
    public function render_edit_submission_form(edit_submission_form $editform) {
        $o = '';
    
        $o .= $this->output->container_start('editsubmission');
        $o .= $this->output->heading(get_string('submission', 'assign'), 3);
        $o .= $this->output->box_start('boxaligncenter editsubmissionform');



        $o .= $this->moodleform($editform->get_form());
        
        $o .= $this->output->box_end();
        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * render the header
     * 
     * @param assignment_header $header
     * @return string
     */
    public function render_assignment_header(assignment_header $header) {
        $o = '';

        if ($header->get_sub_page()) {
            $this->page->navbar->add($header->get_sub_page());
        }

        $this->page->set_title(get_string('pluginname', 'assign'));
        $this->page->set_heading($header->get_assignment()->get_instance()->name);

        $o .= $this->output->header();
        $o .= $this->output->heading($header->get_assignment()->get_instance()->name);

        if ($header->get_show_intro()) {
            if ($header->get_assignment()->get_instance()->alwaysshowdescription || 
                    time() > $header->get_assignment()->get_instance()->allowsubmissionsfromdate) {
                $o .= $this->output->box_start('generalbox boxaligncenter', 'intro');
                $o .= format_module_intro('assign', $header->get_assignment()->get_instance(), $header->get_assignment()->get_course_module()->id);
                $o .= $this->output->box_end();
            }
        }
        return $o;
    }
    
    /**
     * render a table containing the current status of the grading process
     * 
     * @param grading_summary $summary
     * @return string
     */
    public function render_grading_summary(grading_summary $summary) {
        // create a table for the data
        $o = '';
        $o .= $this->output->container_start('gradingsummary');
        $o .= $this->output->heading(get_string('gradingsummary', 'assign'), 3);
        $o .= $this->output->box_start('boxaligncenter gradingsummarytable');
        $t = new html_table();

        // status
        $this->add_table_row_tuple($t, get_string('numberofparticipants', 'assign'), 
                                   $summary->get_assignment()->count_participants(0));

        // drafts
        if ($summary->get_assignment()->get_instance()->submissiondrafts) {
            $this->add_table_row_tuple($t, get_string('numberofdraftsubmissions', 'assign'), 
                                       $summary->get_assignment()->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_DRAFT));
       }

        // submitted for grading
        if ($summary->get_assignment()->is_any_submission_plugin_enabled()) {
            $this->add_table_row_tuple($t, get_string('numberofsubmittedassignments', 'assign'), 
                                       $summary->get_assignment()->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED));
        }

        $time = time();
        if ($summary->get_assignment()->get_instance()->duedate) {
            // due date
            // submitted for grading
            $duedate = $summary->get_assignment()->get_instance()->duedate;
            $this->add_table_row_tuple($t, get_string('duedate', 'assign'), 
                                       userdate($duedate));

            // time remaining
            $due = '';
            if ($duedate < $time) {
                $due = get_string('assignmentisdue', 'assign');
            } else {
                $due = format_time($duedate - $time);
            }
            $this->add_table_row_tuple($t, get_string('timeremaining', 'assign'), $due);
    
            if ($duedate < $time) {
            
                $finaldate = $summary->get_assignment()->get_instance()->finaldate;
                if ($finaldate) {
                    if ($finaldate > $time) {
                        $late = get_string('latesubmissionsaccepted', 'assign');
                    } else {
                        $late = get_string('nomoresubmissionsaccepted', 'assign');
                    }
                    $this->add_table_row_tuple($t, get_string('latesubmissions', 'assign'), $late);
                }
            }
        }

        // all done - write the table
        $o .= html_writer::table($t);
        $o .= $this->output->box_end();
        
        // link to the grading page
        $o .= $this->output->single_button(new moodle_url('/mod/assign/view.php',
            array('id' => $summary->get_assignment()->get_course_module()->id ,'action'=>'grading')), get_string('viewgrading', 'assign'), 'get');

        // close the container and insert a spacer
        $o .= $this->output->container_end();

        return $o;
    }
    
    /**
     *  Return a grade in user-friendly form, whether it's a scale or not
     *
     * @global moodle_database $DB
     * @param mixed $grade null|int
     * @param assignment $assignment The assignment instance
     * @return string User-friendly representation of grade
     */
    private function format_grade($grade, assignment $assignment) {
        return $assignment->display_grade($grade);
    }
    
    /**
     * render a table containing all the current grades and feedback
     * 
     * @global moodle_database $DB
     * @global stdClass $CFG
     * @param feedback_status $status
     * @return string
     */
    public function render_feedback_status(feedback_status $status) {
        global $DB, $CFG;
        $o = '';
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->dirroot.'/grade/grading/lib.php');

        $assignmentgrade = $status->get_grade();      
        if (!$assignmentgrade) {
            return '';
        }

        $gradinginfo = grade_get_grades($status->get_assignment()->get_course()->id, 
                                        'mod', 
                                        'assign', 
                                        $status->get_assignment()->get_instance()->id, 
                                        $assignmentgrade->userid);

        $item = $gradinginfo->items[0];
        $grade = $item->grades[$assignmentgrade->userid];
             
        if ($grade->hidden or $grade->grade === false) { // hidden or error
            return '';
        }

        // check to see if all feedback plugins are empty 
        $emptyplugins = true;
        foreach ($status->get_assignment()->get_feedback_plugins() as $plugin) {
            if ($plugin->is_visible() && $plugin->is_enabled()) {
                if (!$plugin->is_empty($assignmentgrade)) {
                    $emptyplugins = false;
                }
            }
        }
     
        if ($grade->grade === null && $emptyplugins) {   /// Nothing to show yet
                   return '';
        }
       
        $gradeddate = $grade->dategraded;

        $o .= $this->output->container_start('feedback');
        $o .= $this->output->heading(get_string('feedback', 'assign'), 3);
        $o .= $this->output->box_start('boxaligncenter feedbacktable');
        $t = new html_table();
        
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('grade', 'assign'));

        $gradingmanager = get_grading_manager($status->get_assignment()->get_context(), 'mod_assign', 'submissions');
    
        if ($controller = $gradingmanager->get_active_controller()) {
            $controller->set_grade_range(make_grades_menu($status->get_assignment()->get_instance()->grade));
            $cell2 = new html_table_cell($controller->render_grade($this->page, $assignmentgrade->id, $item, $grade->str_long_grade, has_capability('mod/assign:grade', $status->get_assignment()->get_context())));
        } else {

            $cell2 = new html_table_cell($this->format_grade($grade->grade, $status->get_assignment()));
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
        
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('gradedon', 'assign'));
        $cell2 = new html_table_cell(userdate($gradeddate));
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
        
        if ($grader = $DB->get_record('user', array('id'=>$grade->usermodified))) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('gradedby', 'assign'));
            $cell2 = new html_table_cell($this->output->user_picture($grader) . $this->output->spacer(array('width'=>30)) . fullname($grader));
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }
    
        foreach ($status->get_assignment()->get_feedback_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible() && !$plugin->is_empty($status->get_grade())) {
                $row = new html_table_row();
                $cell1 = new html_table_cell($plugin->get_name());
                $pluginfeedback = new feedback_plugin_feedback($status->get_assignment(), $plugin, $status->get_grade(), feedback_plugin_feedback::SUMMARY);
                $cell2 = new html_table_cell($this->render($pluginfeedback));
                $row->cells = array($cell1, $cell2);
                $t->data[] = $row;
            }
        }
 

        $o .= html_writer::table($t);
        $o .= $this->output->box_end();
        
        $o .= $this->output->container_end();
        return $o;
    }

    /**
     * render a table containing the current status of the submission
     * 
     * @param submission_status $status
     * @return string
     */
    public function render_submission_status(submission_status $status) {
        $o = '';
        $o .= $this->output->container_start('submissionstatus');
        $o .= $this->output->heading(get_string('submissionstatusheading', 'assign'), 3);
        $time = time();

        if ($status->get_assignment()->get_instance()->allowsubmissionsfromdate &&
                $time <= $status->get_assignment()->get_instance()->allowsubmissionsfromdate) {
            $o .= $this->output->box_start('generalbox boxaligncenter submissionsalloweddates');
            if ($status->get_assignment()->get_instance()->alwaysshowdescription) {
                $o .= get_string('allowsubmissionsfromdatesummary', 'assign', userdate($status->get_assignment()->get_instance()->allowsubmissionsfromdate));
            } else {
                $o .= get_string('allowsubmissionsanddescriptionfromdatesummary', 'assign', userdate($status->get_assignment()->get_instance()->allowsubmissionsfromdate));
            }
            $o .= $this->output->box_end();
        } 
        $o .= $this->output->box_start('boxaligncenter submissionsummarytable');

        $t = new html_table();
        if ($status->get_assignment()->get_instance()->teamsubmission) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('submissionteam', 'assign'));
            $group = $status->get_submission_group();
            if ($group) {
                $cell2 = new html_table_cell(format_text($group->name));
            } else {
                $cell2 = new html_table_cell(get_string('defaultteam', 'assign'));
            }
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
            
        }

        if (!$status->get_assignment()->get_instance()->teamsubmission) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('submissionstatus', 'assign'));
            if ($status->get_submission()) {
                $cell2 = new html_table_cell(get_string('submissionstatus_' . $status->get_submission()->status, 'assign'));
                $cell2->attributes = array('class'=>'submissionstatus' . $status->get_submission()->status);
            } else {
                if (!$status->get_assignment()->is_any_submission_plugin_enabled()) {
                    $cell2 = new html_table_cell(get_string('noonlinesubmissions', 'assign'));
                } else {
                    $cell2 = new html_table_cell(get_string('nosubmission', 'assign'));
                }
            }
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;

        } else if ($status->get_assignment()->get_instance()->teamsubmission) {

            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('submissionstatus', 'assign'));
            if ($status->get_team_submission()) {
                $submissionsummary = get_string('submissionstatus_' . $status->get_team_submission()->status, 'assign');
                $groupid = 0;
                if ($status->get_submission_group()) {
                    $groupid = $status->get_submission_group()->id;
                }

                $members = $status->get_assignment()->get_submission_group_members($groupid, false);
                $userslist = array();
                $total = 0;
                $count = 0;
                foreach ($members as $member) {
                    $total += 1;
                    $submission = $status->get_assignment()->get_user_submission($member->id, false);
                    if (!$submission || $submission->status == ASSIGN_SUBMISSION_STATUS_DRAFT) {
                        $userslist[] = $this->output->action_link(new moodle_url('/user/view.php', array('id' => $member->id, 'course'=>$status->get_assignment()->get_course()->id)), fullname($member, has_capability('moodle/site:viewfullnames', $status->get_assignment()->get_course_context())));
                    } else {
                        $count += 1;
                    }
                }
                if (has_capability('moodle/site:viewfullnames', $status->get_assignment()->get_context())) {
    
                    $submissionsummary .= $this->output->container(get_string('userswhohavenotsubmitted', 'assign', join(', ', $userslist)));
                    
                } else {
                    $submissionsummary .= $this->output->container(get_string('userswhohavenotsubmittedsummary', 'assign', array('count'=>$count, 'total'=>$total)));
                }
                
                $cell2 = new html_table_cell($submissionsummary);
                $cell2->attributes = array('class'=>'submissionstatus' . $status->get_team_submission()->status);
            } else {
                if (!$status->get_assignment()->is_any_submission_plugin_enabled()) {
                    $cell2 = new html_table_cell(get_string('noonlinesubmissions', 'assign'));
                } else {
                    $cell2 = new html_table_cell(get_string('nosubmission', 'assign'));
                }
            }
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }
        
        // status
        if ($status->is_locked()) {
            $row = new html_table_row();
            $cell1 = new html_table_cell();
            $cell2 = new html_table_cell(get_string('submissionslocked', 'assign'));
            $cell2->attributes = array('class'=>'submissionlocked');
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }

        // grading status
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('gradingstatus', 'assign'));

        if ($status->is_graded()) {
            $cell2 = new html_table_cell(get_string('graded', 'assign'));
            $cell2->attributes = array('class'=>'submissiongraded');
        } else {
            $cell2 = new html_table_cell(get_string('notgraded', 'assign'));
            $cell2->attributes = array('class'=>'submissionnotgraded');
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;

        
        $duedate = $status->get_assignment()->get_instance()->duedate;
        
        if ($duedate > 0) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('duedate', 'assign'));
            $cell2 = new html_table_cell(userdate($duedate));
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;

            if ($status->get_extensionduedate()) {
                $row = new html_table_row();
                $cell1 = new html_table_cell(get_string('extensionduedate', 'assign'));
                $cell2 = new html_table_cell(userdate($status->get_extensionduedate()));
                $row->cells = array($cell1, $cell2);
                $t->data[] = $row;
                $duedate = $status->get_extensionduedate();
            }
            
            // time remaining
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('timeremaining', 'assign'));
            if ($duedate - $time <= 0) {
                if (!$status->get_submission() || $status->get_submission()->status != ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                    if ($status->get_assignment()->is_any_submission_plugin_enabled()) {
                        $cell2 = new html_table_cell(get_string('overdue', 'assign', format_time($time - $duedate)));
                        $cell2->attributes = array('class'=>'overdue');
                    } else {
                        $cell2 = new html_table_cell(get_string('duedatereached', 'assign'));
                    }
                } else {
                    if ($status->get_submission()->timemodified > $duedate) {
                        $cell2 = new html_table_cell(get_string('submittedlate', 'assign', format_time($status->get_submission()->timemodified - $duedate)));
                        $cell2->attributes = array('class'=>'latesubmission');
                    } else {
                        $cell2 = new html_table_cell(get_string('submittedearly', 'assign', format_time($status->get_submission()->timemodified - $duedate)));
                        $cell2->attributes = array('class'=>'earlysubmission');
                    }
                }
            } else {
                $cell2 = new html_table_cell(format_time($duedate - $time));
            }
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        } 

        if ($status->get_view() == submission_status::GRADER_VIEW) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('open', 'assign'));
            if ($status->can_edit()) {
                $cell2 = new html_table_cell(get_string('submissioneditable', 'assign'));
                $cell2->attributes = array('class'=>'submissioneditable');
            } else {
                $cell2 = new html_table_cell(get_string('submissionnoteditable', 'assign'));
                $cell2->attributes = array('class'=>'submissionnoteditable');
            }
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }

        // last modified 
        if ($status->get_submission()) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('timemodified', 'assign'));
            $cell2 = new html_table_cell(userdate($status->get_submission()->timemodified));
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;

            foreach ($status->get_assignment()->get_submission_plugins() as $plugin) {
                if ($plugin->is_enabled() && $plugin->is_visible() && !$plugin->is_empty($status->get_submission())) {
                    $row = new html_table_row();
                    $cell1 = new html_table_cell($plugin->get_name());
                    $pluginsubmission = new submission_plugin_submission($status->get_assignment(), $plugin, $status->get_submission(), submission_plugin_submission::SUMMARY);
                    $cell2 = new html_table_cell($this->render($pluginsubmission));
                    $row->cells = array($cell1, $cell2);
                    $t->data[] = $row;
                }
            }
        }

        
        $o .= html_writer::table($t);
        $o .= $this->output->box_end();
    
        // links
        if ($status->get_view() == submission_status::STUDENT_VIEW) {
            if ($status->can_edit()) {
                $o .= $this->output->single_button(new moodle_url('/mod/assign/view.php',
                    array('id' => $status->get_assignment()->get_course_module()->id, 'action' => 'editsubmission')), get_string('editsubmission', 'assign'), 'get');
            }

            if ($status->can_submit()) {
                // submission.php test
                $o .= $this->output->single_button(new moodle_url('/mod/assign/view.php',
                        array('id' => $status->get_assignment()->get_course_module()->id, 'action'=>'submit')), get_string('submitassignment', 'assign'), 'get');
            }
        }
        
        $o .= $this->output->container_end();
        return $o;
    }
    
    /**
     * render a submission plugin submission
     * 
     * @param submission_plugin_submission $submissionplugin
     * @return string
     */
    public function render_submission_plugin_submission(submission_plugin_submission $submissionplugin) {
        $o = '';

        if ($submissionplugin->get_view() == submission_plugin_submission::SUMMARY) {
            $icon = $this->output->pix_icon('t/preview', get_string('view' . substr($submissionplugin->get_plugin()->get_subtype(), strlen('assign')), 'assign'));
            $link = '';
            if ($submissionplugin->get_plugin()->show_view_link($submissionplugin->get_submission())) {
                $link = $this->output->action_link(
                                new moodle_url('/mod/assign/view.php', 
                                               array('id' => $submissionplugin->get_assignment()->get_course_module()->id, 
                                                     'sid'=>$submissionplugin->get_submission()->id, 
                                                     'plugin'=>$submissionplugin->get_plugin()->get_type(), 
                                                     'action'=>'viewplugin' . $submissionplugin->get_plugin()->get_subtype(), 
                                                     'returnaction'=>$submissionplugin->get_assignment()->get_return_action(), 
                                                     'returnparams'=>http_build_query($submissionplugin->get_assignment()->get_return_params()))), 
                                $icon);
            
                $link .= $this->output->spacer(array('width'=>15));
            }
            
            $o .= $link . $submissionplugin->get_plugin()->view_summary($submissionplugin->get_submission());
        }
        if ($submissionplugin->get_view() == submission_plugin_submission::FULL) {
            $o .= $this->output->box_start('boxaligncenter submissionfull');
            $o .= $submissionplugin->get_plugin()->view($submissionplugin->get_submission());
            $o .= $this->output->box_end();
        }

        return $o;
    }
    
    /**
     * render the grading table
     * 
     * @param grading_table $table
     * @return string
     */
    public function render_grading_table(grading_table $table) {
        $o = '';

        $o .= $this->output->box_start('boxaligncenter gradingtable');
        // need to get from prefs
        $o .= $this->flexible_table($table, $table->get_rows_per_page(), true);
        $o .= $this->output->box_end();

        $o .= $this->output->spacer(array('height'=>30));
        $contextname = print_context_name($table->get_assignment()->get_context());

        $o .= $this->output->container_start('gradingnavigation');
        $o .= $this->output->container_start('backlink');
        $o .= $this->output->action_link(new moodle_url('/mod/assign/view.php', array('id' => $table->get_assignment()->get_course_module()->id)), get_string('backto', '', $contextname));
        $o .= $this->output->container_end();
        if (has_capability('gradereport/grader:view', $table->get_assignment()->get_course_context()) && has_capability('moodle/grade:viewall', $table->get_assignment()->get_course_context())) {
            $o .= $this->output->container_start('gradebooklink');
            $o .= $this->output->action_link(new moodle_url('/grade/report/grader/index.php', array('id' => $table->get_assignment()->get_course()->id)), get_string('viewgradebook', 'assign'));
            $o .= $this->output->container_end();
        }
        if ($table->get_assignment()->is_any_submission_plugin_enabled()) {
            $o .= $this->output->container_start('downloadallsubmissionslink');
            $o .= $this->output->action_link(new moodle_url('/mod/assign/view.php', array('id' => $table->get_assignment()->get_course_module()->id, 'action' => 'downloadall')), get_string('downloadallsubmissions', 'assign'));
            $o .= $this->output->container_end();
        }
        $o .= $this->output->container_start('downloadallgrades');
        $o .= $this->output->action_link(new moodle_url('/mod/assign/view.php', array('id' => $table->get_assignment()->get_course_module()->id, 'action' => 'grading', 'download'=>'csv')), get_string('downloadallgrades', 'assign'));
        $o .= $this->output->container_end();
        
        if (!$table->get_assignment()->use_advanced_grading()) {
            $o .= $this->output->container_start('uploadgrades');
            $o .= $this->output->action_link(new moodle_url('/mod/assign/view.php', array('id' => $table->get_assignment()->get_course_module()->id, 'action' => 'uploadgrades')), get_string('uploadgrades', 'assign'));
            $o .= $this->output->container_end();
        }
        if ($table->get_assignment()->is_blind_marking() && has_capability('mod/assign:revealidentities', $table->get_assignment()->get_context())) {
            $o .= $this->output->container_start('revealidentities');
            $o .= $this->output->action_link(new moodle_url('/mod/assign/view.php', array('id' => $table->get_assignment()->get_course_module()->id, 'action' => 'revealidentities')), get_string('revealidentities', 'assign'));
            $o .= $this->output->container_end();
        }
        

        foreach ($table->get_assignment()->get_feedback_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                $pages = $plugin->additional_grading_pages();
                foreach ($pages as $page) {
                    $o .= $this->output->container_start('plugingradingpagelink');
                    $o .= $this->output->action_link(new moodle_url('/mod/assign/view.php', array('id' => $table->get_assignment()->get_course_module()->id, 'action' => 'plugingradingpage', 'gradingaction'=>$page, 'plugin'=>$plugin->get_type())), get_string('gradingpage' . $page, $plugin->get_subtype() . '_' . $plugin->get_type()));
                    $o .= $this->output->container_end();
                }
            }
        }

        $o .= $this->output->container_end();

        return $o;
    }
    
    /**
     * render the summary of a csv import operation
     * 
     * @param mod_assign_grade_import_summary
     * @return string
     */
    public function render_mod_assign_grade_import_summary(mod_assign_grade_import_summary $summary) {
        $o = '';

        $o .= $this->output->box_start('boxaligncenter generalbox gradeimportsummary');
        $o .= $this->output->container_start('gradeimportsummaryrow');
        $o .= get_string('updatedgrade', 'assign', array('count'=>$summary->get_updatedgrade()));
        $o .= $this->output->container_end();
        $o .= $this->output->container_start('gradeimportsummaryrow');
        $o .= get_string('skippednograde', 'assign', array('count'=>$summary->get_skippednograde()));
        $o .= $this->output->container_end();
        $o .= $this->output->container_start('gradeimportsummaryrow');
        $o .= get_string('skippedinvalidgrade', 'assign', array('count'=>$summary->get_skippedinvalidgrade()));
        $o .= $this->output->container_end();
        if (!optional_param('ignoremodified', 0, PARAM_BOOL)) {
            $o .= $this->output->container_start('gradeimportsummaryrow');
            $o .= get_string('skippedmodified', 'assign', array('count'=>$summary->get_skippedmodified()));
            $o .= $this->output->container_end();
        }
        $o .= $this->output->box_end();
        $o .= $this->output->container_start('backlink');
        $o .= $this->output->single_button(new moodle_url('/mod/assign/view.php', array('id' => $summary->get_assignment()->get_course_module()->id, 'action'=>'grading')), get_string('back'), 'get');
        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * render a feedback plugin feedback
     * 
     * @param feedback_plugin_feedback $feedbackplugin
     * @return string
     */
    public function render_feedback_plugin_feedback(feedback_plugin_feedback $feedbackplugin) {
        $o = '';

        if ($feedbackplugin->get_view() == feedback_plugin_feedback::SUMMARY) {
            $icon = $this->output->pix_icon('t/preview', get_string('view' . substr($feedbackplugin->get_plugin()->get_subtype(), strlen('assign')), 'assign'));
            $link = '';
            if ($feedbackplugin->get_plugin()->show_view_link($feedbackplugin->get_grade())) {
                $link = $this->output->action_link(
                                new moodle_url('/mod/assign/view.php', 
                                               array('id' => $feedbackplugin->get_assignment()->get_course_module()->id, 
                                                     'gid'=>$feedbackplugin->get_grade()->id, 
                                                     'plugin'=>$feedbackplugin->get_plugin()->get_type(), 
                                                     'action'=>'viewplugin' . $feedbackplugin->get_plugin()->get_subtype(), 
                                                     'returnaction'=>$feedbackplugin->get_assignment()->get_return_action(), 
                                                     'returnparams'=>http_build_query($feedbackplugin->get_assignment()->get_return_params()))), 
                                $icon);
                $link .= $this->output->spacer(array('width'=>15));
            }
            
            $o .= $link . $feedbackplugin->get_plugin()->view_summary($feedbackplugin->get_grade());
        }
        if ($feedbackplugin->get_view() == feedback_plugin_feedback::FULL) {
            $o .= $this->output->box_start('boxaligncenter feedbackfull');
            $o .= $feedbackplugin->get_plugin()->view($feedbackplugin->get_grade());
            $o .= $this->output->box_end();
        }

        return $o;
    }
    

        
    /**
     * Internal function - creates htmls structure suitable for YUI tree.
     * 
     * @global stdClass $CFG
     * @param assign_files $tree
     * @param array $dir
     * @return string 
     */
    protected function htmllize_tree(assign_files $tree, $dir) {
        global $CFG;
        $yuiconfig = array();
        $yuiconfig['type'] = 'html';

        if (empty($dir['subdirs']) and empty($dir['files'])) {
            return '';
        }

        $result = '<ul>';
        foreach ($dir['subdirs'] as $subdir) {
            $image = $this->output->pix_icon("f/folder", $subdir['dirname'], 'moodle', array('class'=>'icon'));
            $result .= '<li yuiConfig=\''.json_encode($yuiconfig).'\'><div>'.$image.' '.s($subdir['dirname']).'</div> '.$this->htmllize_tree($tree, $subdir).'</li>';
        }

        foreach ($dir['files'] as $file) {
            $filename = $file->get_filename();
            $icon = mimeinfo("icon", $filename);
            if ($CFG->enableplagiarism) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $plagiarsmlinks = plagiarism_get_links(array('userid'=>$file->get_userid(), 'file'=>$file, 'cmid'=>$tree->cm->id, 'course'=>$tree->course));
            } else {
                $plagiarsmlinks = '';
            }
            $image = $this->output->pix_icon("f/$icon", $filename, 'moodle', array('class'=>'icon'));
            $result .= '<li yuiConfig=\''.json_encode($yuiconfig).'\'><div>'.$image.' '.$file->fileurl.' '.$plagiarsmlinks.$file->portfoliobutton.'</div></li>';
        }

        $result .= '</ul>';

        return $result;
    }

    /**
     * Helper method dealing with the fact we can not just fetch the output of flexible_table
     *
     * @param flexible_table $table
     * @return string HTML
     */
    protected function flexible_table(flexible_table $table, $rowsperpage, $displaylinks) {

        $o = '';
        ob_start();
        $table->out($rowsperpage, $displaylinks);
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }

    /**
     * Helper method dealing with the fact we can not just fetch the output of moodleforms
     *
     * @param moodleform $mform
     * @return string HTML
     */
    protected function moodleform(moodleform $mform) {

        $o = '';
        ob_start();
        $mform->display();
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }
    
}
