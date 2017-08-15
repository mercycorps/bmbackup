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

        // Handle form submit
        //-------------------
        if ($this->input->post('update_schedule')) {
            try {
                $this->bmbackup->update_cron_tab(
                    $this->input->post('drop-down-hour'), 
                    $this->input->post('drop-down-day'), 
                    $this->input->post('checkbox-day-su'), 
                    $this->input->post('checkbox-day-mo'), 
                    $this->input->post('checkbox-day-tu'), 
                    $this->input->post('checkbox-day-we'), 
                    $this->input->post('checkbox-day-th'), 
                    $this->input->post('checkbox-day-fr'), 
                    $this->input->post('checkbox-day-sa'));
                


            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }
        
        // Load schedule setting
        //----------------------

        $shell = new Shell;
        $file = new File(bmbackup::CRON_FILE, TRUE);
        if (!$file->exists()) {
            $hour = 24;
            $dow = 8;
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
        $data['dow']=$dow;

        $this->page->view_form('schedule', $data, lang('bmbackup_app_name'));       

    }
  
}
