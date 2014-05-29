<?php
use \clearos\apps\bmbackup\Bmbackup as Bmbackup;
class Logs extends ClearOS_Controller
{
    function index()
    {
        // Load dependencies
        //------------------
        $this->lang->load('bmbackup');
        $this->load->library('bmbackup/Bmbackup');
//        $this->load->library('base/File');
         
        // Load views
        //-----------
        $contents = $this->bmbackup->get_log_summary();
        $data['contents'] = $contents;
        
        $this->page->view_form('logs', $data, lang('bmbackup_logs'));
    }
}
