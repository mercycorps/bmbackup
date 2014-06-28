#!/usr/bin/php 
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
 
namespace clearos\apps\bmbackup;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

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
use \clearos\apps\bmbackup\Bmbackup as Bmbackup;

clearos_load_library('bmbackup/Bmbackup');
clearos_load_library('mail_notification/Mail_Notification');

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
const FILE_CONFIG = '/etc/clearos/bmbackup.d/backup.conf';
// const FILE_CONFIG = '/home/admin/apps/bmbackup/packaging/backup.conf';

///////////////////////////////////////////////////////////////////////////////
// B A C K U P   S C R I P T
///////////////////////////////////////////////////////////////////////////////

$bmbackup = new Bmbackup;

// set the nofication level and the email that should receive email address.
list($notification_level, $email_address) = get_email_notification_settings();

// If there are no USB devices attached then exit since backup wouldn't be able to proceed
if (!$storage_devices = $bmbackup->get_detected_devices()){
    log_error("bmbackup failed: No USB storage devices detected!");
    exit();
}

$i = count($storage_devices);
$j = 0;

$usb_disks = array();

while ($i > 0) {
    $usb_disks[$j] = $storage_devices[$j]['partition'][0];
    $i = $i - 1;
    $j = $j + 1;
}

// loop through USB disks and perform a backup
foreach ($usb_disks as $dev) {
    if (!check_usb($dev)) {
        continue;
    }

    $shell = new Shell;

    // get the names of previous backups on the device
    if (!$archives = get_backup_file_names($bmbackup::PATH_ARCHIVE)) {
        log_error("bmbackup warning: could not retrieve old backup file names");
    } else {
        // delete bad backup files, if any...
        foreach ($archives as $archive) {
            $file = new File(PATH_ARCHIVE . '/' . $archive . 'BAD');
            if ($file->exists()) {
                $file->delete();
                $my_archive = new File(PATH_ARCHIVE . '/' . $archive);
                $my_archive->delete();
            }
        }
    }

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
        log_error("bmbackup warning: continuing over $dev since can't prepare backpup config ");
        continue;
    }

    // backup config files
    if (! backup('Configuration_Backup', $config_manifest)) {
        umount_usb($dev);
        continue;
    }

    // the old config backup should be deleted...
    if (isset($config_backup_file_name)) {
        $old_config_backup_file_name = new File (PATH_ARCHIVE . '/' . $config_backup_file_name);
        if ($old_config_backup_file_name->delete()) {
            log_error("bmbackup warning: the old config backup could not be deleted");
        }
    }
    
    // backup home directory
    if (! backup('Home_Directory_Backup', '/home/*')) {
        umount_usb($dev);
        continue;
    }

    // after successful home backup, delete its old one
    if (isset($home_backup_file_name)) {
        $old_home_backup_file_name = new File(PATH_ARCHIVE . '/' . $home_backup_file_name);
        if ($old_home_backup_file_name->delete()) {
            log_error("bmbackup warning: the old home backup could not be deleted");
        }
    }

    // check to see if flexshares should be backed up...
    if (file_exists('/var/flexshare/shares')) {
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
        if (isset($flexshare_backup_file_name)) {
            $old_flexshare_backup = new File(PATH_ARCHIVE . '/' . $flexshare_backup_file_name);
            if ($old_flexshare_backup->delete()) {
                log_error("bmbackup warning: the old flexshare backup could not be deleted");
            }
        }
    }
    
    // unmount the usb disk after all backups are successful.
    if (!umount_usb($dev)) {
        log_error("bmbackup warning: failed to umount after successful backup");
    }

    // if everything goes well, then log a success message.
    log_error("bmbackup successful");
}

///////////////////////////////////////////////////////////////////////////////
// M E T H O D S
///////////////////////////////////////////////////////////////////////////////

/**
 * Retrieves email notification settings from the config file.
 *
 * @access public
 * @return array of the level of notification and email address
 */
function get_email_notification_settings()
{
    $contents = array();
    $file = new File(EMAIL_CONFIG_FILE);
    if ($file->exists()) {
        return $file->get_contents_as_array();
    }
    return $contents = array(NO_NOTIFICATIONS, '');
}

/**
 * Retrieves email notification settings from the config file.
 *
 * @access public
 * @return array of the level of notification and email address
 */
function get_backup_file_names($path)
{
    $archives = array();
    $folder = new Folder($path);
    if (! $folder) {
        log_error("bmbackup warning: could not get previous backup folder");
        return null;
    }
    $contents = $folder->get_listing();
    if (!$contents) {
        log_error("bmbackup warning: could not get previous backup file listing");
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

/**
 * Checks the USB disk to ensre it is mountable and passes file-system CHECK
 *
 * @param string $dev the usb device name
 * @return boolean value 
 */
function check_usb($dev)
{
    $shell = new Shell;
    
    // unmount the device if it is mounted
    if (_is_mounted($dev)) {
        $shell->execute('/bin/umount', $dev, true);
    }
    
    // Perform file system check on the backup device
    if ($shell->execute('/sbin/fsck', "-f -y $dev", true)) {
        log_error("bmbackup error: Unable run file-system-check on  $dev");
        return false;
    }

    if ($shell->execute('/bin/mount -t ext4', "$dev /mnt/backup", true)) {
        log_error("bmbackup error: Unable to mount $dev");
        return false;
    }

    // verify that the backup disk is initialized
    $file = new File(INITIALIZED_FILE);
    if (!$file->exists()) {
        log_error("bmbackup failed: <b> $dev </b> not initialized");
        umount_usb($dev);
        return false;
    }
    return true;
}
 
function config_backup()
{
    $shell = new Shell;

    //backup configuration files
    if (! $config_manifest = _read_config()) {
        log_error("bmbackup failed: could not read configuration files");
        return null;
    }
    // Dump the current LDAP database
    if (clearos_library_installed('openldap/LDAP_Driver')) {
        clearos_load_library('openldap/LDAP_Driver');

        $openldap = new LDAP_Driver();
        $openldap->export();
    } else {
        log_error("bmbackup failed: could not dump ldap to backup");
        return null;
    }
    return $config_manifest;
}

/**
 * Un-mounts the USB storage device passed in as argument.
 *
 * @param string $dev the name of the USB storage device
 * @return boolean depending on whether the unmount operation was successful
 */
function umount_usb($dev)
{
    $shell = new Shell;
    if ($shell->execute('/bin/umount', $dev, true)) {
        log_error("bmbackup failed: unable to umount the usb disk: " . $dev);
        return FALSE;
    }
    return TRUE;
}

/**
 * Reads all of the config files which are in the manifest file (FILE_CONFIG)
 *
 * @return array of all the files that it read based on the FILE_CONFIG manifest.
 */
function _read_config()
{
    $files = array();
    if (!file_exists(FILE_CONFIG)) {
        return null;
    }
    $config = new File(FILE_CONFIG);
    $contents = $config->get_contents_as_array();

    foreach ($contents as $line) {
        if (preg_match('/^\s*#/', $line)) {
            continue;
        }
        $files[] = $line;
    }
    
    $files_manifest = '';
    
    foreach ($files as $file) {
        $files_manifest .= "$file ";
    }
    
    $files_manifest = rtrim($files_manifest);
    return $files_manifest;
}

/**
 * Check if a device is already mounted.
 *
 * @param string $device the name of the device to check
 * @return boolean depending on whether the unmount operation was successful
 */
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
    while (($file = readdir($dh)) !== FALSE) {
        if (!preg_match($pattern, $file)) {
            continue;
        }
        $matches[] = $file;
    }
    closedir($dh);
    sort($matches);
    return $matches;
}

/**
 * Performs a backup of either configuration, home, or flexshare dirctories.
 *
 * @param string $backup_type the type of backup, i.e., config, home, or flexshare
 * @param string $files_manifest arry of files to backup in case of config backup
 * 
 * 
 */
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
        log_error("bmbackup failed: the tar file does not exist: " . PATH_ARCHIVE . " " . $archive);
        return false;
    }

    $archive->chmod(600);
    return true;
}

function log_error($msg)
{
    global $notification_level;
    clearos_log("bmbackup", $msg);
    if ($notification_level == NO_NOTIFICATIONS) {
        return;
    } else if ($notification_level == ALL_NOTIFICATIONS ) {
        _send($msg);
    } else {
        if (preg_match('/bmbackup failed:/', $msg) ||
            preg_match('/bmbackup error:/', $msg) ||
            preg_match('/bmbackup warning:/', $msg) ) {
            _send($msg);
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
// vim: syntax=php ts=4
?>
