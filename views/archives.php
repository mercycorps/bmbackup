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
        $dev = substr($archive, 5);
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
///////////////////////////////////////////////////////////////////////////////
// Summary table
///////////////////////////////////////////////////////////////////////////////
$options['sort'] = FALSE;

echo summary_table(
    lang('bmbackup_archives'),
    array(),
    array('Archive Name'),
    $items,
    $options
);