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

class Email extends ClearOS_Controller
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

        if ($this->input->post('update_email'))
        {
            try {
                $this->bmbackup->update_email_notification_settings($this->input->post('notification'), $this->input->post('email'));
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }
        
        // Load notification setting
        //--------------------------
        $file = new File(Bmbackup::EMAIL_CONFIG_FILE, TRUE);
        
        if ($file->exists())
        {
            $contents = $file->get_contents_as_array();
            $notification_level = $contents[0];
            $email_address = $contents[1];
        } else {
            $notification_level = Bmbackup::NO_NOTIFICATIONS;
        }

        // Load views
        //-----------
        $data['notification_level'] = $notification_level;
        $data['email_address'] = $email_address;

        $this->page->view_form('email', $data, lang('bmbackup_app_name'));
    }
}
