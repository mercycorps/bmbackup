<?php

/**
 * Baremetal Backup And Restore controller.
 *
 * @category   Apps
 * @package    Baremetal_Backup_And_Restore
 * @subpackage Views
 * @author     Your name <your@e-mail>
 * @copyright  2013 Your name / Company
 * @license    Your license
 */

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Baremetal Backup And Restore controller.
 *
 * @category   Apps
 * @package    Baremetal_Backup_And_Restore
 * @subpackage Controllers
 * @author     Your name <your@e-mail>
 * @copyright  2013 Your name / Company
 * @license    Your license
 */

class Bmbackup extends ClearOS_Controller
{
    /**
     * Baremetal Backup And Restore default controller.
     *
     * @return view
     */

    function index()
    {
        // Load dependencies
        //------------------

        $this->lang->load('bmbackup');

        // Load views
        $views = array('bmbackup/usb', 'bmbackup/schedule', 'bmbackup/email', 'bmbackup/logs');
        $this->page->view_forms($views, lang('bmbackup_app_name'));

        //$this->page->view_form('bmbackup', NULL, lang('bmbackup_app_name'));
    }
}
