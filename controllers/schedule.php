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


        
        // Load schedule setting
        //----------------------

        $shell = new Shell;
        $file = new File(bmbackup::CRON_FILE, TRUE);
        if (!$file->exists()) {
            $hour = 24;
        } else {
            $hr = $file->get_contents(-1);
            preg_match('/^\d+\s+(\d+).*$/', $hr, $matches);
            $hour = $matches[1];

            $dw = $file->get_contents(-4);
            preg_match('/^\d+\s+\d+\s+\d+\s+\d+\s+(\d+).*$/', $dw, $matches);
            $dow = $matches[1];
        }

        // Load views
        //-----------
        $data['hour']=$hour;
        $data['params']=$params;                              // ***      

        $this->page->view_form('schedule', $data, lang('bmbackup_app_name'));
        

        // Checkbox array handling
        //-----------
        $arrFields = array('checkbox-day-su','checkbox-day-mo','checkbox-day-tu','checkbox-day-we','checkbox-day-th','checkbox-day-fr','checkbox-day-sa');

        foreach($arrFields as $field){
        $params[$field] = filter_input(INPUT_POST, $field, FILTER_DEFAULT);
        }

        //var_dump($params);                                // ***
        //var_dump($arrFields);                             // ***
        $DayName;
        
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

        //var_dump($DayName);

        //print_r("Print for checkbox su");
        //print_r($this->input->post('checkbox-day-su'));  

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

        //////

    }
  
}
