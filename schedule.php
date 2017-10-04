<?php

/**
 * Baremetal Backup And Restore controller.
 *
 * @category   Apps
 * @package    baremetalbackup
 * @subpackage views
 * @author     Mahmood Khan <mkhan@mercycorps.org>
 * @copyright  2014 Mercy Corps
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */
 
use \clearos\apps\bmbackup\Bmbackup as Bmbackup;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Shell as Shell;

class Schedule extends ClearOS_Controller
{
    function index()
    {
        clearos_profile(__METHOD__, __LINE__);

        // Load dependencies
        //------------------

        $this->load->library('bmbackup/Bmbackup');
        $this->lang->load('bmbackup');
        $this->load->library('base/File');
        $this->load->library('base/Shell');
      

        // Load View Data: Checkbox array handling
        // View -> Controller
        //-----------

        //*** v
        $arrFields = array(
            0 => 'checkbox-day-su',
            1 => 'checkbox-day-mo',
            2 => 'checkbox-day-tu',
            3 => 'checkbox-day-we',
            4 => 'checkbox-day-th',
            5 => 'checkbox-day-fr',
            6 => 'checkbox-day-sa');

        foreach($arrFields as $field){
        $params[$field] = filter_input(INPUT_POST, $field, FILTER_DEFAULT);
        }
     
        if($params[$arrFields[0]] == 'on'){
            $DayName .= 'sun,';
        }
        if($params[$arrFields[1]] == 'on'){
            $DayName .= 'mon,';
        }
        if($params[$arrFields[2]] == 'on'){
            $DayName .= 'tue,';
        }
        if($params[$arrFields[3]] == 'on'){
            $DayName .= 'wed,';
        }
        if($params[$arrFields[4]] == 'on'){
            $DayName .= 'thu,';
        }
        if($params[$arrFields[5]] == 'on'){
            $DayName .= 'fri,';
        }
        if($params[$arrFields[6]] == 'on'){
            $DayName .= 'sat,';
        }
        //*** ^

        // Handle form submit
        //-------------------
        if ($this->input->post('update_schedule')) {
            try {
                $this->bmbackup->update_cron_tab(
                    $this->input->post('drop-down-hour'),
                    $DayName
                    );
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        // Load schedule setting
        // Cron File -> Controller 
        //----------------------

        $shell = new Shell;
        $file = new File(bmbackup::CRON_FILE, TRUE);
        if (!$file->exists()) {
            $hour = 24;
        } else {

            $pattern1 = '/^\d+\s+(\d+).*$/';
            $subject = $file->get_contents();
            preg_match($pattern1, $subject, $matches);
            $hour = $matches[1];

            //Successfully Grabs results from cron file to load
            $pattern2 = '#\b(sun|mon|tue|wed|thu|fri|sat)\b#'; 
            preg_match_all($pattern2, $subject, $matches, PREG_PATTERN_ORDER);
            $dow = $matches[1];

        }

        // Send Loaded information from Controller -> View.php
        //   Loading views for the drop down menu and for the Checkbox's
        // Dow and Hour are taken from Chron File then loaded into the 
        //   matching Form call
        //-----------
        $data['hour'] = $hour;

        for ($i=0; $i < 7; $i++) { 
            $data[$dow[$i]] = TRUE;
        }      

        // Load Views
        //-----------
        $this->page->view_form('schedule', $data, lang('bmbackup_app_name'));     

    }
  
}
