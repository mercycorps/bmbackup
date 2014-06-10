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
// C L A S S
///////////////////////////////////////////////////////////////////////////////
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
        $views = array('bmbackup/archives', 'bmbackup/usb', 'bmbackup/schedule', 'bmbackup/email', 'bmbackup/logs');
        $this->page->view_forms($views, lang('bmbackup_app_name'));
    }
}
