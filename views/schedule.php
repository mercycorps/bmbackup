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
// Load dependencies
///////////////////////////////////////////////////////////////////////////////

$this->lang->load('base');
$this->lang->load('bmbackup');

///////////////////////////////////////////////////////////////////////////////
// Form Handler 
///////////////////////////////////////////////////////////////////////////////
$buttons = array(
    form_submit_custom('update_schedule', lang('bmbackup_update_hour')),
//    anchor_custom('/app/bmbackup/schedule/backup', lang('bmbackup_backup'), 'high')
);

$times = array();
$times[0] = '12:00 AM';
$times[1] = '01:00 AM';
$times[2] = '02:00 AM';
$times[3] = '03:00 AM';
$times[4] = '04:00 AM';
$times[5] = '05:00 AM';
$times[6] = '06:00 AM';
$times[7] = '07:00 AM';
$times[8] = '08:00 AM';
$times[9] = '09:00 AM';
$times[10] = '10:00 AM';
$times[11] = '11:00 AM';

//PM Hours
$times[12] = '12:00 PM';
$times[13] = '01:00 PM';
$times[14] = '02:00 PM';
$times[15] = '03:00 PM';
$times[16] = '04:00 PM';
$times[17] = '05:00 PM';
$times[18] = '06:00 PM';
$times[19] = '07:00 PM';
$times[20] = '08:00 PM';
$times[21] = '09:00 PM';
$times[22] = '10:00 PM';
$times[23] = '11:00 PM';

$days = array();
$days[8] = 'Disabled';
$days[0] = 'Sunday';
$days[1] = 'Monday';
$days[2] = 'Tuesday';
$days[3] = 'Wednesday';
$days[4] = 'Thursday';
$days[5] = 'Friday';
$days[6] = 'Saturday';


///////////////////////////////////////////////////////////////////////////////
// Form
///////////////////////////////////////////////////////////////////////////////
echo form_open('bmbackup');
echo form_header(lang('bmbackup_schedule'), array('id' => 'schedule_form'));

//Drop Down for the day and hour sections
echo field_dropdown('hour', $times, $hour, lang('bmbackup_time_hour'), FALSE);
echo field_dropdown('Day', $days, $hour, lang('bmbackup_time_day'), FALSE);
echo field_button_set($buttons);

echo form_footer();
echo form_close();
