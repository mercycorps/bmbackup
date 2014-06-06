<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'bmbackup';
$app['version'] = '1.0.0';
$app['release'] = '1';
$app['vendor'] = 'Mercy Corps'; 
$app['packager'] = 'Mercy Corps'; 
$app['license'] = 'GPLv3'; 
$app['license_core'] = 'LGPLv3'; 
$app['description'] = lang('bmbackup_app_description');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('bmbackup_app_name');
$app['category'] = lang('base_category_system');
$app['subcategory'] = 'Developer'; // e.g. lang('base_subcategory_settings');

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['core_directory_manifest'] = array(
    '/etc/clearos/bmbackup.d' => array(),
);

$app['core_file_manifest'] = array( 
    'usb.conf' => array ( 
        'target' => '/etc/clearos/bmbackup.d/usb.conf',
        'mode' => '0644',
    ),
    'backup.conf' => array ( 
        'target' => '/etc/clearos/bmbackup.d/backup.conf',
        'mode' => '0644',
    ),
);
