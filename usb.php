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

class Usb extends ClearOS_Controller
{
    function index()
    {
        // Load dependencies
        // --------------------
        $this->lang->load('bmbackup');
        $this->load->library('bmbackup/Bmbackup');

        // Load view data
        // ----------------
        try {
            $data['devices'] = $this->bmbackup->get_detected_devices();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
        
        // Load view
        // ----------
        $this->page->view_form('usb', $data, lang('bmbackup_bmbackup'));
    }

    function initialize_usb($dev)
    {
        $this->lang->load('bmbackup');
        $confirm_uri = '/app/bmbackup/usb/initialize/' . $dev;
        $cancel_uri = '/app/bmbackup';
        $device = "/dev/$dev";
        //$items = array($device);

        $this->page->view_confirm(lang('bmbackup_confirm_initialize') . "<b> $device </b>?", $confirm_uri, $cancel_uri);
    }
    
    function initialize($dev) 
    {
        $this->load->library('bmbackup/Bmbackup');
        
        try {
            $this->bmbackup->initialize_usb_disk($dev);
            redirect('bmbackup');
        } catch (Exception $e) {
            $this->page->view_exception($e);
        }
    }
}