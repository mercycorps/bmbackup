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
 
 ///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

class Archives extends ClearOS_Controller
{
    function index()
    {
        // Load dependencies
        //------------------
        $this->lang->load('bmbackup');
        $this->load->library('bmbackup/Bmbackup');

        // Load view data
        // ----------------
        try {
            $devices = $this->bmbackup->get_detected_devices();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
        
        $data['archives'] = array();
        
        foreach ($devices as $device => $info) {
            if ($info['status'] = 'READY') {
                $data['archives'] = array($info['device'] => $info['archives']);
            }
        }
        
        // Load views
        //-----------

        $this->page->view_form('archives', $data, lang('bmbackup_archives'));
    }

    function restore($filename, $dev)
    {
        $this->lang->load('bmbackup');
        $confirm_uri = "/app/bmbackup/archives/archive_restore/" . $filename . "/" . $dev;
        $cancel_uri = '/app/bmbackup';
        $items = array($filename);

        $this->page->view_confirm(lang('bmbackup_confirm_restore') . '<br>' . $filename, $confirm_uri, $cancel_uri, $items);
    }
    
    /**
     * Calls the restore_backup function in the library to restore the specified file from device.
     *
     * @param string $filename the name of the fle to restore from the backup device.
     * @param string $dev the name of the device to restore data from
     *
     */
    function archive_restore($filename, $dev){
        $this->load->library('bmbackup/Bmbackup');
        
        try {
            $this->bmbackup->restore_backup($filename, $dev);
            redirect('bmbackup');
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }
}
