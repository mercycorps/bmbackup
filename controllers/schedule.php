<?php
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

        if ($this->input->post('update_schedule'))
        {
            try {
                $this->bmbackup->update_cron_tab($this->input->post('hour'));
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }
        
        //$shell = new Shell;
       /* if ($this->input->post('backup_now'))
        {
            try{
                $shell->execute('/usr/bin/php', Bmbackup::CRON_SCRIPT_PATH, true);
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }
       */

        // Load schedule setting
        //----------------------

        $shell = new Shell;
        $file = new File(bmbackup::CRON_FILE, TRUE);
        if (!$file->exists())
        {
            $hour = 24;
        } else 
        {
            $hr = $file->get_contents(-1);
            preg_match('/^\d+\s+(\d+).*$/', $hr, $matches);
            $hour = $matches[1];
        }

        // Load views
        //-----------
        $data['hour']=$hour;

        $this->page->view_form('schedule', $data, lang('bmbackup_app_name'));
    }
/*****
    function backup()
    {
       $this->lang->load('bmbackup');
       $confirm_uri = '/app/bmbackup/bmbackup';
       $cancel_uri = '/app/bmbackup';
       
       $this->page->view_confirm(lang('bmbackup_confirm_backup'), $confirm_uri, $cancel_uri);
    }
*****/    
}
