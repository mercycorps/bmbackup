<?php
use \clearos\apps\bmbackup\Bmbackup as Bmbackup;
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
        
        //$devices = $this->bmbackup->get_detected_devices();
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
        $confirm_uri = "/app/bmbackup/archive_restore/$filename/$dev";
        $cancel_uri = '/app/bmbackup';
        $items = array($filename);

        $this->page->view_confirm(lang('bmbackup_confirm_restore') . '<br>' . $filename, $confirm_uri, $cancel_uri, $items);
    }
}
