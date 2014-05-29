#!/usr/bin/php 
<?php
namespace clearos\apps\bmbackup;
///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------
use \clearos\apps\base\Engine as Engine;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\network\Hostname as Hostname;
use \clearos\framework\Logger as Logger;
use \clearos\apps\openldap\LDAP_Driver as LDAP_Driver;
use \clearos\apps\mail_notification\Mail_Notification as Mailer;

require_once('/usr/clearos/apps/base/libraries/Engine.php');
require_once('/usr/clearos/apps/base/libraries/File.php');
require_once('/usr/clearos/apps/base/libraries/Folder.php');
require_once('/usr/clearos/apps/base/libraries/Shell.php');
require_once('/usr/clearos/apps/network/libraries/Hostname.php');
require_once('/usr/clearos/framework/shared/libraries/Logger.php');
require_once('/usr/clearos/apps/openldap/libraries/LDAP_Driver.php');
require_once('/usr/clearos/apps/mail_notification/libraries/Mail_Notification.php');

// External Libraries
//-------------------
include_once('/usr/clearos/sandbox/usr/share/pear/Swift/lib/Swift.php');
include_once('/usr/clearos/sandbox/usr/share/pear/Swift/lib/Swift/File.php');
include_once('/usr/clearos/sandbox/usr/share/pear/Swift/lib/Swift/Connection/SMTP.php');

///////////////////////////////////////////////////////////////////////////////
// C O N S T A N T S
///////////////////////////////////////////////////////////////////////////////

const INITIALIZED_FILE = '/mnt/backup/INITIALIZED';
const SCSI_DEVICES = '/sys/bus/scsi/devices';
const PATH_ARCHIVE = '/mnt/backup';
const NO_NOTIFICATIONS = 0;
const ALL_NOTIFICATIONS = 1;
const ERROR_NOTIFICATIONS = 2;
const SERVICE = '/sbin/service'; 
const ETC_MTAB = '/etc/mtab';
const EMAIL_CONFIG_FILE = '/etc/clearos/bmbackup.d/email.conf';
const FILE_CONFIG = '/usr/clearos/apps/bmbackup/deploy/backup.conf';

///////////////////////////////////////////////////////////////////////////////
// B A C K U P   S C R I P T
///////////////////////////////////////////////////////////////////////////////

list($notification_level, $email_address) = get_email_notification_settings();

if (!$storage_devices = get_devices()){
    exit();
}

$i = count($storage_devices) -1;
$j = 0;

while ($i >= 0) {
    $storage_devices2[$j] = $storage_devices[$j]['partition'][0];
    $i = $i - 1;
    $j = $j + 1;
}

if (!$usb_disks = get_usb($storage_devices2)) {
    exit();
}

// loop through USB disks and perform a backup
foreach ($usb_disks as $dev)
{
    if (!check_usb($dev)) {
        continue;
    }

    echo "Backup on : $dev started! \n";

    $shell = new Shell;

    // get the names of previous backups on the device
    if (!$archives = get_backup_file_names(PATH_ARCHIVE)) {
        _log_error('bmbackup warning: could not retrieve old backup file names');
    } else {
        // delete bad backup files, if any...
        foreach ($archives as $archive) 
        {
            $file = new File(PATH_ARCHIVE . '/' . $archive . 'BAD');
            if ($file->exists()) {
                $file->delete();
                $my_archive = new File(PATH_ARCHIVE . '/' . $archive);
                $my_archive->delete();
            }
        }
    } // end if get_backup_file_names

    $archives = get_backup_file_names(PATH_ARCHIVE);

    $i = 0;
    while ($i < count($archives)) {
        if (preg_match('/^Home/', $archives[$i])) {
            $home_backup_file_name = $archives[$i];
        } else if (preg_match('/^Configuration/', $archives[$i])) {
            $config_backup_file_name = $archives[$i];
        } else if (preg_match('/^Flexshare/', $archives[$i])) {
            $flexshare_backup_file_name = $archives[$i];
        }
        $i = $i +1;
    }

    // prepare config backup
    if (! $config_manifest = config_backup()) {
        umount_usb($dev);
        continue;
    }

    // backup config files
    if (! backup('Configuration_Backup', $config_manifest)) {
        umount_usb($dev);
        continue;
    }

    // the old config backup should be deleted...
    if (isset($config_backup_file_name))
    {
        $old_config_backup_file_name = new File (PATH_ARCHIVE . '/' . $config_backup_file_name);
        if ($old_config_backup_file_name->delete()) {
            _log_error('bmbackup warning: the old config backup could not be deleted');
        }
    }

    // backup home directory
    if (! backup('Home_Directory_Backup', '/home/*')) {
        umount_usb($dev);
        continue;
    }

    // after successful home backup, delete its old one
    if (isset($home_backup_file_name))
    {
        $old_home_backup_file_name = new File(PATH_ARCHIVE . '/' . $home_backup_file_name);
        if ($old_home_backup_file_name->delete()) {
            _log_error('bmbackup warning: the old home backup could not be deleted');
        }
    }

    // check to see if flexshares should be backed up...
    $folder = new Folder('/var/flexshare/shares');
    $are_files_available = $folder->get_listing();

    if ($are_files_available) {
        //backup flexshare
        if(! backup('Flexshare_Backup', '/var/flexshare')) {
            umount_usb($dev);
            continue;
        }
    }
    
        // after successful flexshare backup, delete the old one
    if (isset($flexshare_backup_file_name))
    {
        $old_flexshare_backup = new File(PATH_ARCHIVE . '/' . $flexshare_backup_file_name);
        if ($old_flexshare_backup->delete()) {
            _log_error('bmbackup warning: the old flexshare backup could not be deleted');
        }
    }
    

    // unmount the usb disk after all backups are successful.
    if (!umount_usb($dev)) {
        _log_error('bmbackup warning: failed to umount after successful backup');
    }

    // if everything goes well, then log a success message.
    _log_error('bmbackup successful');
}

///////////////////////////////////////////////////////////////////////////////
// M E T H O D S
///////////////////////////////////////////////////////////////////////////////
function get_email_notification_settings()
{
    $contents = array();
    $file = new File(EMAIL_CONFIG_FILE);
    if ($file->exists()) {
        return $file->get_contents_as_array();
    }
    return $contents = array(NO_NOTIFICATIONS, '');
}

function get_backup_file_names($path)
{
    $archives = array();
    $folder = new Folder($path);
    if (! $folder) {
        _log_error('could not get previous backup filenames');
        return null;
    }
    $contents = $folder->get_listing();
    if (!$contents) {
        _log_error('could not get previous backup filenames');
        return null;
    }  
    foreach ($contents as $value) {
        if (! preg_match('/tar.gz$/', $value)) {
            continue;
        }
        $archives[] = $value;
        }
        return $archives;
}

function get_devices()
{
    $devices = array();

    // Find SCSI devices that match: %d:%d:%d:%d
    $entries = _scan_dir(SCSI_DEVICES, '/^\d+:\d:\d:\d$/');

    // Scan all SCSI devices.
    if ($entries !== FALSE) {
        foreach ($entries as $entry) {
            $block = 'block';
            $path = SCSI_DEVICES . "/$entry";
            if (($dev = _scan_dir("$path/block", '/^dev$/')) !== FALSE) {
                if (($block_devices = _scan_dir("$path", '/^block$/')) === FALSE) continue;
                $block = $block_devices[0];
                if (($devid = _scan_dir("$path/$block", '/^sd\w/')) === FALSE) continue;
                $block .= "/$devid[0]";
                if (($dev = _scan_dir("$path/$block", '/^dev$/')) === FALSE) continue;
            }
            if (count($dev) != 1) continue;

            // Validate SCSI storage device
            if (!($fh = fopen("$path/$block/device/scsi_level", 'r'))) continue;
            $scsi_level = chop(fgets($fh, 4096));
            fclose($fh);
            if ($scsi_level != 3) continue;
            $device['bus'] = 'usb';

            if (!($fh = fopen("$path/vendor", 'r'))) continue;
            $device['vendor'] = chop(fgets($fh, 4096));
            fclose($fh);
            if (!($fh = fopen("$path/model", 'r'))) continue;
            $device['model'] = chop(fgets($fh, 4096));
            //$device['product'] = $device['model'];
            fclose($fh);
            if (!($fh = fopen("$path/$block/dev", 'r'))) continue;
            $device['nodes'] = chop(fgets($fh, 4096));
            fclose($fh);
            $device['path'] = "$path/$block";

            $devices[] = $device;
        }
    }

    if (count($devices)) {
        // Create a hashed array of all device nodes that match: /dev/s*
        // XXX: This can be fairly expensive, takes a few seconds to run.
        if (!($ph = popen('stat -c 0x%t:0x%T:%n /dev/s*', 'r')))
            throw new Exception("Error running stat command", CLEAROS_WARNING);

        $nodes = array();
        $major = '';
        $minor = '';

        while (!feof($ph)) {
            $buffer = chop(fgets($ph, 4096));
            if (sscanf($buffer, '%x:%x:', $major, $minor) != 2) continue;
            if ($major == 0) continue;
            $nodes["$major:$minor"] = substr($buffer, strrpos($buffer, ':') + 1);
        }

        // Clean exit?
        if (pclose($ph) != 0)
            throw new Exception("Error running stat command", CLEAROS_WARNING);
            
        // Hopefully we can now find the TRUE device name for each
        // storage device found above.  Validation continues...
        foreach ($devices as $key => $device) {
            if (!isset($nodes[$device['nodes']])) {
                unset($devices[$key]);
                continue;
            }
               
            // Set the block device
            $devices[$key]['device'] = $nodes[$device['nodes']];
                
            // Here we are looking for detected partitions
            if (($partitions = _scan_dir($device['path'], '/^' . basename($nodes[$device['nodes']]) . '\d$/')) !== FALSE && count($partitions) > 0) {
                foreach($partitions as $partition)
                $devices[$key]['partition'][] = dirname($nodes[$device['nodes']]) . '/' . $partition;
            }
               
            unset($devices[$key]['path']);
            unset($devices[$key]['nodes']);
        }
    }
    return $devices;
}

function get_usb($storage_devices)
{
    $usb_disks = array();
    $i = 0;
    foreach ($storage_devices as $device)
    {
        if (preg_match('/\/dev\/scd\d/', $device) || preg_match('/\/dev\/hd\w\d/', $device))
        {
            continue;
        }
        $usb_disks[$i++] = $device;
    }
    return $usb_disks;
}

function check_usb($dev)
{
    $shell = new Shell;
    
    // unmount the device if it is mounted
    if (_is_mounted($dev))
        $shell->execute('/bin/umount', $dev, true);

    // Perform file system check on the backup device
    if ($shell->execute('/sbin/fsck', "-f -y $dev", true))
    {
        $output = $shell->get_output();
        $error_message = '';
        foreach ($output as $line)
        {
            $error_message .= $line;
        }
        echo $error_message;
        _log_error("bmbackup failed: $error_message");
        return FALSE;
    }

    // mount the backup device.
    if ($shell->execute('/bin/mount -t ext3', "$dev /mnt/backup", true))
    {
        $output = $shell->get_output();
        $error_message = '';
        foreach ($output as $line)
        {
            $error_message .= $line;
        }
        _log_error("bmbackup failed: $error_message");
        $shell->execute('/bin/umount', '/mnt/backup', true);
        return FALSE;
    }

    // verify that the backup disk is initialized
    $file = new File(INITIALIZED_FILE);

    if (!$file->exists())
    {
        $output = $shell->get_output();
        $error_message = '';
        foreach ($output as $line)
        {
            $error_message .= $line;
        }
        _log_error("bmbackup failed: <b> $dev </b> not initialized $error_message");
        umount_usb($dev);
        return FALSE;
    }
    return TRUE;
}
 
function config_backup()
{
    $shell = new Shell;

    //backup configuration files
    if (! $config_manifest = _read_config()) {
        _log_error('bmbackup failed: could not read configuration files');
        return null;
    }
    // Dump the current LDAP database
    if (clearos_library_installed('openldap/LDAP_Driver')) {
        clearos_load_library('openldap/LDAP_Driver');

        $openldap = new LDAP_Driver();
        $openldap->export();
    } else {
        _log_error('bmbackup failed: could not dump ldap to backup');
        return null;
    }
    return $config_manifest;
}

function umount_usb($dev)
{
    $shell = new Shell;
    if ($shell->execute('/bin/umount', $dev, true))
    {
        $output = $shell->get_output();
        $error_message = '';
        foreach ($output as $line)
        {
            $error_message .= $line;
        }
        _log_error("bmbackup failed: $error_message");
        return FALSE;
    }
    return TRUE;
}

function _read_config()
{
    $files = array();

    $config = new File(FILE_CONFIG);

    $contents = $config->get_contents_as_array();

    foreach ($contents as $line)
    {
        if (preg_match('/^\s*#/', $line))
            continue;

        $files[] = $line;
    }
    
    $files_manifest = '';
    foreach ($files as $file)
    {
        $files_manifest .= "$file ";
    }

    $files_manifest = rtrim($files_manifest);
    
    return $files_manifest;
}

function _is_mounted($device)
{
    $mount_point = NULL;
    if (!($fh = fopen(ETC_MTAB, 'r')))
        return FALSE;

    while (!feof($fh)) {
        $buffer = chop(fgets($fh, 4096));
        if (!strlen($buffer)) break;
        list($name, $mount_point) = explode( ' ', $buffer);
        if ($name == $device) {
            fclose($fh);
            return TRUE;
        }
    }

    $mount_point = NULL;

    fclose($fh);
    return FALSE;
}

/**
 * Scan a directory returning files that match the pattern.
 *
 * @param string $dir     directory
 * @param string $pattern pattern
 *
 * @return array
 */

function _scan_dir($dir, $pattern)
{
    if (!($dh = opendir($dir))) return FALSE;
    $matches = array();
    while (($file = readdir($dh)) !== FALSE) 
    {
        if (!preg_match($pattern, $file)) 
            continue;
        $matches[] = $file;
    }
    closedir($dh);
    sort($matches);
    return $matches;
}

function backup($backup_type, $files_manifest)
{
    $shell = new Shell;

    $hostname = new Hostname;

    $prefix = $backup_type;
    $prefix .= '-';
    $prefix .= $hostname->get_actual();
    $prefix .= '-';

    $filename = $prefix . strftime('%m-%d-%Y-%H-%M-%S', time()) . '.tar.gz';

    // Create the backup
    $attr = '--exclude=*.rpmnew --exclude=*.rpmsave --exclude=*blackists* --ignore-failed-read -cpzf ';

    $args = PATH_ARCHIVE . '/' . $filename . ' ' . $files_manifest;

    $shell->execute('/bin/tar', $attr . $args, true);
    $archive = new file(PATH_ARCHIVE . '/' . $filename);
    if (!$archive) {
        _log_error('bmbackup failed: the tar file does not exist: ' . PATH_ARCHIVE . ' ' . $archive);
        return false;
    }

    $archive->chmod(600);

    return true;
}
function _log_error($logmsg)
{
    global $notification_level;

    Logger::syslog('bmbackup', $logmsg);

    if ($notification_level == NO_NOTIFICATIONS) {
        return;
    } else if ($notification_level = ALL_NOTIFICATIONS) {
        _send($logmsg);
    } else {
        if (preg_match('/bmbackup failed:/', $logmsg) || preg_match('/bmbackup warning:/', $logmsg)) {
            _send($logmsg);
        }
    }
}

function _send($email_body)
{
    global $email_address;

    $mailer = new Mailer();
    $hostname = new Hostname();
    $to_add = $email_address;
    $subject = 'Baremetal Backup Notification - ' . $hostname->get();
//    mail($to_add, $subject, $email_body);
    $mailer->add_recipient($to_add);
    $mailer->set_message_subject($subject);
    $mailer->set_message_body($email_body);
    $mailer->set_sender('backup@' . $hostname->get_domain());
    $mailer->send();
}
// vim: syntax=php ts=2
?>
