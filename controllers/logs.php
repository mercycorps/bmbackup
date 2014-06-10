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
class Logs extends ClearOS_Controller
{
    function index()
    {
        // Load dependencies
        //------------------
        $this->lang->load('bmbackup');
        $this->load->library('bmbackup/Bmbackup');
         
        // Load views
        //-----------
        $contents = $this->bmbackup->get_log_summary();
        $data['contents'] = $contents;
        
        $this->page->view_form('logs', $data, lang('bmbackup_logs'));
    }
}
