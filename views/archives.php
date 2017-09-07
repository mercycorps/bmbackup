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
// Items 
///////////////////////////////////////////////////////////////////////////////
$items = array();

// Handling for null/empty case
$check_empty = array_values($archives);
if (empty($check_empty[0]))
{
    $item = array(
        'title' => 'No archives',
        'action' => '',
        'anchors' => '',
        'details' => array('No archives available to be restored')
    );
    $items[] = $item;

} else { 
    foreach ($archives as $archive => $info)
    {
        $dev = substr($archive, 5);                                            //*** Is this needed?
        $i = 0;

        while ($i < count($info))
        {
            $item = array(
                'title' => $archive,
                'action' => '',
                'anchors' => anchor_custom("/app/bmbackup/archives/restore/$info[$i]/$dev", lang('bmbackup_restore'), 'high'),
                'details' => array($info[$i]) 
            );
            $i++;
            $items[] = $item;

        }
    }
}

$buttons = array(
    form_submit_custom('update_archives', lang('bmbackup_update_hour')),

);



///////////////////////////////////////////////////////////////////////////////
// Summary table
///////////////////////////////////////////////////////////////////////////////
$options['sort'] = FALSE;

         // *** 



echo summary_table(
    lang('bmbackup_archives_restore'),
    array(),                                                           // *** Contents Null? / other Summary
    array('Archive Name'),
    $items,
    $options
);

/*
$testCh = array(
    0 => 'checkbox-conf',
    1 => 'checkbox-home',
    2 => 'checkbox-flex',
    );
*/

/*
echo summary_table(
    lang('bmbackup_archives_backup'),
    array(),
    array('Archive Name'),
    $items,
    $options
);
*/


echo form_open('bmbackup');
echo form_header(lang('bmbackup_archives_backup'), array('id' => 'schedule_form'));

// Checkbox (name,value,default setting)
echo field_checkbox('checkbox_conf', $tempb[0], lang('bmbackup_conf'), FALSE);
echo field_checkbox('checkbox_home', $tempb[1], lang('bmbackup_home'), FALSE);
echo field_checkbox('checkbox_flex', $tempb[2], lang('bmbackup_flex'), FALSE);

echo field_button_set($buttons);


echo form_footer();
echo form_close();


//print_r("Test starts here: ");
//var_dump($items);
//var_dump($options);
