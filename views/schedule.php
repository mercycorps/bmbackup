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
// Updating schedule in controllers/schedule.php as 'update schedule'
///////////////////////////////////////////////////////////////////////////////
$buttons = array(
    form_submit_custom('update_schedule', lang('bmbackup_update_hour'), lang('bmbackup_time_day')),

);


$times = array();
$wdays = array();

$times[24] = 'Disabled';

//AM Hours
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
    
    
$wdays[8] = 'Disabled';

$wdays[0] = 'Sunday'; 
$wdays[1] = 'Monday';
$wdays[2] = 'Tuesday';
$wdays[3] = 'Wednesday';
$wdays[4] = 'Thursday';
$wdays[5] = 'Friday';
$wdays[6] = 'Saturday';




///////////////////////////////////////////////////////////////////////////////
// Form
///////////////////////////////////////////////////////////////////////////////
echo form_open('bmbackup');
echo form_header(lang('bmbackup_schedule'), array('id' => 'schedule_form'));

//Drop Down for the hour section
echo field_dropdown('drop-down-hour', $times, $hour, lang('bmbackup_time_hour'), FALSE);
//Checkboxes for the Day of week section
echo field_checkbox('checkbox-day-su', $DayBi[0], lang('bmbackup_su'), FALSE);
echo field_checkbox('checkbox-day-mo', $DayBi[1], lang('bmbackup_mo'), FALSE);
echo field_checkbox('checkbox-day-tu', $DayBi[2], lang('bmbackup_tu'), FALSE);
echo field_checkbox('checkbox-day-we', $DayBi[3], lang('bmbackup_we'), FALSE);
echo field_checkbox('checkbox-day-th', $DayBi[4], lang('bmbackup_th'), FALSE);
echo field_checkbox('checkbox-day-fr', $DayBi[5], lang('bmbackup_fr'), FALSE);
echo field_checkbox('checkbox-day-sa', $DayBi[6], lang('bmbackup_sa'), FALSE);


echo field_button_set($buttons);

echo form_footer();
echo form_close();
