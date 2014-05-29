<?php
use \clearos\apps\bmbackup\Bmbackup as Bmbackup;

class Usb extends ClearOS_Controller
{
    function index()
    {
        // Load dependencies
        // --------------------
        $this->lang->load('bmbackup');
        $this->load->library('bmbackup/Bmbackup');
        #$this->load->library('base/Engine_Exception');

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
    
    function initialize($dev) {
        $this->load->library('bmbackup/Bmbackup');
        
        $initialized = $this->bmbackup->initialize_usb_disk($dev);
        if ($initialized == true) {
            redirect("/bmbackup");
        } else {
            clearos_log("bmbackup", "something went wrong");
        }
    }
}