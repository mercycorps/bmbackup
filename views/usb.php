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

$this->lang->load('base');
$this->lang->load('bmbackup');

///////////////////////////////////////////////////////////////////////////////
// Items 
///////////////////////////////////////////////////////////////////////////////

$items = array();

foreach ($devices as $device => $info)
{
    $dev = substr($info['device'], 5);
//    $buttons = array(anchor_custom('/app/bmbackup/usb/', lang('bmbackup_initialize'), 'high');
    $item = array(
        'title' => $device,
        'action' => '',
        'anchors' => anchor_custom('/app/bmbackup/usb/initialize_usb/' . $dev, lang('bmbackup_initialize'), 'high'),
        'details' => array(
            $info['device'], 
            $info['vendor'] . ' ' . $info['model'],
            $info['status'],
            )
    );

    $items[] = $item;
}
///////////////////////////////////////////////////////////////////////////////
// Summary table
///////////////////////////////////////////////////////////////////////////////

echo summary_table(
    lang('bmbackup_usb'),
    array(),
    $headers,
    $items
);
