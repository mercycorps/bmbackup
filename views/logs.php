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

if (empty($contents))
{
    $item = array(
        'title' => 'No Logs',
        'action' => NULL,
        'anchors' => NULL,
        'details' => array('No logs available')
    );

    $items[] = $item;
} else { 
    $last_line = count($contents);
    $j = 0;

    for ($i = 0; $i < count($contents); $i++)
    {
        $last_line--;
        if (preg_match('/\bbmbackup\b/', $contents[$last_line]) && $j < 10)
        {
            if (preg_match('/\bsuccessful\b/', $contents[$last_line]) ||
            preg_match('/bmbackup failed:/', $contents[$last_line]) ||
            preg_match('/bmbackup warning:/', $contents[$last_line]))
            {
                $j++;
                $logs[$i] = $contents[$last_line]; 
            }
        }
    }

    foreach ($logs as $log)
    {
            $item = array(
                'title' => $log,
                'action' => NULL,
                'anchors' => NULL,
                'details' => array($log) 
            );
            $items[] = $item;
    }
}
///////////////////////////////////////////////////////////////////////////////
// Summary table
///////////////////////////////////////////////////////////////////////////////

$options['no_action'] = TRUE;
$options['sort'] = FALSE;

echo summary_table(
    lang('bmbackup_logs'),
    NULL,
    array('Log Message'),
    $items,
    $options
);