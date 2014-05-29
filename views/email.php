<?php

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

///////////////////////////////////////////////////////////////////////////////
// Load dependencies
///////////////////////////////////////////////////////////////////////////////

use \clearos\apps\bmbackup\Bmbackup as Bmbackup;

$this->lang->load('base');
$this->lang->load('bmbackup');
$this->load->library('bmbackup/Bmbackup');

///////////////////////////////////////////////////////////////////////////////
// Form Handler 
///////////////////////////////////////////////////////////////////////////////
$buttons = array(
    form_submit_custom('update_email', lang('bmbackup_update_notification'))
);

$notification_options = array();
$notification_options[Bmbackup::NO_NOTIFICATIONS] = 'Disabled';
$notification_options[Bmbackup::ALL_NOTIFICATIONS] = 'All Notifications';
$notification_options[Bmbackup::ERROR_NOTIFICATIONS] = 'Only Errors & Warnings';

///////////////////////////////////////////////////////////////////////////////
// Form
///////////////////////////////////////////////////////////////////////////////
echo form_open('bmbackup');
echo form_header(lang('bmbackup_email'), array('id' => 'email_form'));

echo field_dropdown('notification', $notification_options, $notification_level, lang('bmbackup_notification'), FALSE);
echo field_input('email', $email_address, lang('bmbackup_email_address'), FALSE);
echo field_button_set($buttons);

echo form_footer();
echo form_close();
