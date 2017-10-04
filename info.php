<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'bmbackup';
$app['version'] = '1.0.0';
$app['release'] = '1';
$app['vendor'] = 'Mercy Corps <mkhan@mercycorps.org>'; 
$app['packager'] = 'Mercy Corps <mkhan@mercycorps.org>'; 
$app['license'] = 'GPLv3'; 
$app['license_core'] = 'LGPLv3'; 
$app['description'] = lang('bmbackup_app_description');
$app['tooltip'] = lang('bmbackup_app_tooltip');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('bmbackup_app_name');
$app['category'] = lang('base_category_system');
$app['subcategory'] = lang('base_subcategory_backup');

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['core_directory_manifest'] = array(
    '/etc/clearos/bmbackup.d' => array('mode' => '755', 'owner' => 'webconfig', 'group' => 'webconfig')
);

$app['core_file_manifest'] = array( 
    'usb.conf' => array ( 
        'target' => '/etc/clearos/bmbackup.d/usb.conf',
        'mode' => '0644',
        'owner' => 'webconfig',
        'group' => 'webconfig',
        'config' => TRUE,
        'config_params' => 'noreplace'
    ),
    'backup.conf' => array ( 
        'target' => '/etc/clearos/bmbackup.d/backup.conf',
        'mode' => '0644',
        'owner' => 'webconfig',
        'group' => 'webconfig',
        'config' => TRUE,
        'config_params' => 'noreplace'
    ),
    'email.conf' => array ( 
        'target' => '/etc/clearos/bmbackup.d/email.conf',
        'mode' => '0644',
        'owner' => 'webconfig',
        'group' => 'webconfig',
        'config' => TRUE,
        'config_params' => 'noreplace'
    ),
);
