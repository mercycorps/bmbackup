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
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////
namespace clearos\apps\bmbackup;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////
$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////
clearos_load_language('configuration_backup');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------
use \clearos\apps\base\Engine as Engine;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\tasks\Cron as Cron;
use \clearos\framework\Logger as Logger;
use \clearos\apps\openldap\LDAP_Driver as LDAP_Driver;
use \clearos\apps\log_viewer\Log_Viewer as Log_Viewer;

clearos_load_library('base/Engine');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('base/Shell');
clearos_load_library('tasks/Cron');
clearos_load_library('framework/Logger');
clearos_load_library('openldap/LDAP_Driver');
clearos_load_library('log_viewer/Log_Viewer');

// Exceptions
//-----------
use \Exception as Exception;
use \clearos\apps\base\Engine_Exception as Engine_Exception;
use \clearos\apps\base\File_Not_Found_Exception as File_Not_Found_Exception;
use \clearos\apps\base\Folder_Not_Found_Exception as Folder_Not_Found_Exception;
use \clearos\apps\base\Validation_Exception as Validation_Exception;
use \clearos\apps\tasks\Cron_Configlet_Not_Found_Exception as Cron_Configlet_Not_Found_Exception;

clearos_load_library('base/Engine_Exception');
clearos_load_library('base/File_Not_Found_Exception');
clearos_load_library('base/Folder_Not_Found_Exception');
clearos_load_library('base/Validation_Exception');
clearos_load_library('tasks/Cron_Configlet_Not_Found_Exception');

class Bmbackup extends Engine
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const INITIALIZED_FILE = '/mnt/backup/INITIALIZED';
    const PATH_SCSI_DEVICES = '/sys/bus/scsi/devices';
    const PATH_USB_DEVICES = '/sys/bus/usb/devices';
    const USB_PARTITION_PATH = '/etc/clearos/bmbackup.d/usb.conf';
    const ETC_MTAB = '/etc/mtab';
    const PATH_ARCHIVE = '/mnt/backup';
    const CRON_FILE = '/etc/cron.d/app-bmbackup';
    const CRON_CONFIG_FILE_NAME = 'app-bmbackup';
    const CRON_SCRIPT_PATH = '/usr/clearos/sandbox/usr/bin/php /usr/clearos/apps/bmbackup/deploy/bmbackup.php > /dev/null 2>&1';
    const NO_NOTIFICATIONS = 0;
    const ALL_NOTIFICATIONS = 1;
    const ERROR_NOTIFICATIONS = 2;
    const EMAIL_CONFIG_FILE = '/etc/clearos/bmbackup.d/email.conf';
    const FILE_CONFIG = 'backup.conf';
    const CMD_TAR = '/bin/tar';
    const CMD_PHP = '/usr/bin/php';
    
    
    protected $mount_point = NULL;
    var $error_msg = array();

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Scan the system and return an array of all attached USB devices
     *
     * @param None
     * 
     * @access public
     * @return array of USB devices
     */
    function get_detected_devices()
    {
        $devices = $this->_get_devices();

        foreach ($devices as $device => $info) {
            //if the bus is not usb, ignore it
            if ($info['bus'] !='usb') {
                continue;
            }
            
            //if no partitions exist, then disk is not ready
            if ($info['partition'][0]) {
                $dev = $info['partition'][0];
                $diskpartition = 1;
            } else {
                $dev = $info['device'];
                $diskpartition = 0;
                $status = 'NOT READY';
            }

            $shell = new Shell;
            
            //if partition exist, then mount, otherwise skip
            if ($diskpartition == 1) {
                try {
                    $shell->execute('/bin/mkdir', '-p /mnt/backup', TRUE);
                } catch (Exception $e) {
                    throw new Exception(clearos_exception_message($e), CLEAROS_ERROR);
                }
                
                if (!$this->_is_mounted($dev)) {
                    try {
                        $shell->execute('/bin/mount -t ext4', $dev . ' /mnt/backup', TRUE);
                    } catch (Exception $e) {
                        clearos_log("bmbackup", "Unable to mount $dev");
                    }
                }
                
                $file = new File(self::INITIALIZED_FILE);
                if (!$file->exists()) {
                    $status = 'NOT READY';
                } else {
                    $status = 'READY';
                    $archives = $this->get_archives_list(self::PATH_ARCHIVE);
                    $devices[$device]['archives'] = $archives;
                }
                
                try {
                    $shell->execute('/bin/umount', $dev, TRUE);
                } catch (Exception $e) {
                    clearos_log("bmbackup", "Unable to umount $dev");
                }
            }
            $devices[$device]['status'] = $status;
        }
        return $devices;
    }

    /**
     * Initializes a USB disk by partitioning it, formatting it, and touching a file.
     *
     * @param string $dev device name
     * 
     * @access private
     * @return boolean
     */
    function initialize_usb_disk($dev)
    {
        $device = '/dev/' . $dev;

        // Quick check to make sure this is a properly formatted device name.
        if (preg_match('/\/dev\/sd\w\d/', $device)) {
            $device = substr_replace($device, '', -1); //Remove last char if $device is a partition.
        }
        
        $shell = new Shell;

        //un-mount the device if it is already mounted.
        if ($this->_is_mounted($device)) {
            $shell->execute('/bin/umount', $device, TRUE);
        }

        // check to make sure the partitioning is successful.
        try {
            $shell->execute('/sbin/sfdisk', "-q -f $device < " . self::USB_PARTITION_PATH . ' > /tmp/out.log 2> /tmp/err.log', TRUE);
        } catch (Exception $e) {
            clearos_log("bmbackup", $e);
            throw new Exception(clearos_exception_message($e), CLEAROS_ERROR);
            return false;
        }
        
        // Make device into partition
        $device = $device . '1'; 

        // Format the disk
        try {
            $shell->execute('/sbin/mkfs.ext4', $device, TRUE);
        } catch (Exception $e) {
            throw new Exception(clearos_exception_message($e), CLEAROS_ERROR);
            return false;
        }

        // create the mount point if it does not already exists
        try {
            $shell->execute('/bin/mkdir', '-p /mnt/backup', TRUE);
        } catch (Exception $e) {
            throw new Exception(clearos_exception_message($e), CLEAROS_ERROR);
            return false;
        }
        
        // Mount the disk to verify everything went well.
        try {
            $shell->execute('/bin/mount', "$device /mnt/backup", TRUE);
        } catch (Exception $e) {
            throw new Exception(clearos_exception_message($e), CLEAROS_ERROR);
            return false;
        }


        // create the initialized file.
        try {
            $shell->execute('/bin/touch', self::INITIALIZED_FILE, TRUE);
        } catch (Exception $e) {
            throw new Exception(clearos_exception_message($e), CLEAROS_ERROR);
            return false;
        }
        
        // unmount the disk when done doing all of the above
        try {
            $shell->execute('/bin/umount', $device, TRUE);
        } catch (Exception $e) {
            throw new Exception(clearos_exception_message($e), CLEAROS_ERROR);
            return false;
        }
        return true;
    }

    /**
     * Makes an entry in /etc/cron.d/ folder for bmbackup to run on a regular schedule
     *
     * @param integer $hour hour to have the cron job run
     * 
     * @access public
     * @return None
     */
    function update_cron_tab($hour)
    {
        $cron = new Cron;

        if ($hour == 24) {
            if ($cron->exists_configlet(self::CRON_CONFIG_FILE_NAME)) {
                $cron->delete_configlet(self::CRON_CONFIG_FILE_NAME);
            }
        } else  {
            if ($cron->exists_configlet(self::CRON_CONFIG_FILE_NAME)) {
                $file = new File(self::CRON_FILE, TRUE);
                $hr = $file->get_contents(-1);
                $file->replace_lines('/^\d+\s+(\d+).*$/', "0 $hour * * * root " . self::CRON_SCRIPT_PATH . "\n");
            } else {
                $cron->add_configlet_by_parts(self::CRON_CONFIG_FILE_NAME, 0, $hour, '*', '*', '*', 'root', self::CRON_SCRIPT_PATH);
            }
        }
    }

    /**
     * Updates the config settings for email notification
     *
     * @param integer $notification_level how many notifications would you like to receive
     * @param string $email_address email address to receive notification
     * 
     * @access public
     * @return None
     */
    function update_email_notification_settings($notification_level, $email_address)
    {
        $file = new File(self::EMAIL_CONFIG_FILE, TRUE);

        if ($file->exists() && $notification_level == self::NO_NOTIFICATIONS) {
            $file->delete();
        } else if ($file->exists() && $notification_level !== self::NO_NOTIFICATIONS) {
            $file->replace_one_line('/^[0-2]/', $notification_level . "\n");
            $file->replace_one_line('/.*\@/', $email_address);
        } else {
            $file->create('root', 'root', '644');
            $file->add_lines($notification_level . "\n" . $email_address);
        }
    }

    /**
     * Scans the folder at PATH_ARCHIVE and returns all the available archives
     *
     * @param string $path the path to scan for retrieving a list of archives
     * 
     * @access public
     * @return array of archives
     */
    function get_archives_list($path)
    {
        $archives = array();
        try {
            $folder = new Folder($path);

            if (!$folder->exists()) {
                throw new Folder_Not_Found_Exception($path, CLEAROS_ERROR);
            }

            $contents = $folder->get_listing();
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        if (!$contents) {
            return $archives;
        }
        
        foreach ($contents as $value) {
            if (!preg_match('/tar.gz$/', $value)) {
                continue;
            }
           $archives[] = $value;
        }
        return array_reverse($archives);
    }

    /**
     * Restores a backup from the backup device
     *
     * @param string $filename the name of the archive to restore
     * @param string $dev the name of the device to restore backup archive from.
     * 
     * @access public
     * @return None
     */
    function restore_backup($filename, $dev)
    {
        $shell = new Shell;
        $device = "/dev/" . $dev . "1";
        try {
            $shell->execute('/bin/mount', $device . " " . self::PATH_ARCHIVE, TRUE);

            if (preg_match('/^Configuration.*/', $filename)) {
                $this->_restore_configuration(self::PATH_ARCHIVE, $filename);
            } else if (preg_match('/^Home.*/', $filename)) {
                $this->_restore_home(self::PATH_ARCHIVE, $filename);
            } else if (preg_match('/^Flexshare.*/', $filename)) {
                $this->_restore_flexshare(self::PATH_ARCHIVE, $filename);
            }

            $shell->execute('/bin/umount', $device, TRUE);
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    /////////////////////////////////////////////////////////////////////////////// 
   
   /**
     * Scans the system returning USB storage devices that are attached to it.
     * 
     * @param None
     * 
     * @access private
     * @return array list of detected device names
     */
    final private function _get_devices()
    {
        $devices = array();

        // Find USB devices that match: %d-%d
        $entries = $this->_scan_directory(self::PATH_USB_DEVICES, '/^\d-\d$/');
        
        if (!empty($entries)) {
            $devices_set_1 = $this->_get_devices_helper($entries);
            $devices[] = $devices_set_1[0];
        }
        
        // Some USB devices are detected in a slightly different way:
        // Find USB devices that match: %d-%d.%d
        $entries2 = $this->_scan_directory(self::PATH_USB_DEVICES, '/^\d-\d\.\d$/');
        if (!empty($entries2)) {
            $devices_set_2 = $this->_get_devices_helper($entries2);
            $devices[] = $devices_set_2[0];
        }

        if (count($devices)) {
            // Create a hashed array of all device nodes that match: /dev/s*
            // XXX: This can be fairly expensive, takes a few seconds to run.
            if (!($ph = popen('/usr/bin/stat -c 0x%t:0x%T:%n /dev/s*', 'r')))
                throw new Engine_Exception("Error running stat command");

            $nodes = array();
            $major = '';
            $minor = '';

            while (!feof($ph)) {
                $buffer = chop(fgets($ph, 4096));

                if (sscanf($buffer, '%x:%x:', $major, $minor) != 2)
                    continue;

                if ($major == 0)
                    continue;

                $nodes["$major:$minor"] = substr($buffer, strrpos($buffer, ':') + 1);
            }

            if (pclose($ph) != 0) {
                throw new Engine_Exception("Error running stat command");
            }

            // Hopefully we can now find the TRUE device name for each
            // storage device found above.  Validation continues...
            foreach ($devices as $key => $device) {
                if (!isset($nodes[$device['nodes']])) {
                    unset($devices[$key]);
                    continue;
                }

                // Set the block device
                $devices[$key]['device'] = $nodes[$device['nodes']];
                
                $device_name = basename($nodes[$device['nodes']]);
                
                // Here we are looking for detected partitions
                $partitions = $this->_scan_directory($device['path'] . "/block/" . $device_name, '/^' . $device_name . '\d$/');
                if (! empty($partitions)) {
                    foreach($partitions as $partition)
                        $devices[$key]['partition'][] = dirname($nodes[$device['nodes']]) . '/' . $partition;
                }

                unset($devices[$key]['path']);
                unset($devices[$key]['nodes']);
            }
        }
        return $devices;
    }

    final private function _get_devices_helper($entries)
    {
        $devices = array();
        
        // Walk through the expected USB -> SCSI /sys paths.
        foreach ($entries as $entry) {
            
            $path = self::PATH_USB_DEVICES . "/$entry";

            $devid = $this->_scan_directory($path, "/^$entry:\d\.\d$/");
            if (empty($devid))
                continue;

            if (count($devid) != 1)
                continue;

            $path .= '/' . $devid[0];

            $host = $this->_scan_directory($path, '/^host\d+$/');
            if (empty($host))
                continue;

            if (count($host) != 1)
                continue;

            $path .= '/' . $host[0];

            $usb_storage = $path . "/scsi_host/" . $host[0];
            
            if (!($fh = fopen("$usb_storage/proc_name", 'r'))) {
                clearos_log("bmbackup", "skipping because proc_name non-existent!");
                continue;
            }
                
            //$proc_name = file_get_contents("$usb_storage/proc_name");
            $proc_name = stream_get_contents($fh, -1);

            if (trim($proc_name) <> "usb-storage") {
                clearos_log("bmbackup", "skipping because proc_name NON EQUAL TO 'usb-storage': " . $proc_name);
                continue;
            }

            $target = $this->_scan_directory($path, '/^target\d+:\d:\d$/');
            if (empty($target))
                continue;
          
            if (count($target) != 1)
                continue;

            $path .= '/' . $target[0];

            $lun = $this->_scan_directory($path, '/^\d+:\d:\d:0$/');

            if (empty($lun))
                continue;

            if (count($lun) != 1)
                continue;

            $path .= '/' . $lun[0];

            $dev = $this->_scan_directory("$path/block", '/^s/');
            if (empty($dev))
                continue;

            if (count($dev) != 1)
                continue;

            // Validate USB mass-storage device
            if (!($fh = fopen("$path/vendor", 'r')))
                continue;

            $device['vendor'] = chop(fgets($fh, 4096));
            fclose($fh);

            if (!($fh = fopen("$path/model", 'r')))
                continue;

            $device['model'] = chop(fgets($fh, 4096));
            fclose($fh);

            if (!($fh = fopen("$path/block/". $dev[0] . "/dev", 'r'))) {
                continue;
            }

            $device['nodes'] = chop(fgets($fh, 4096));
            fclose($fh);
            $device['path'] = $path;
            $device['bus'] = 'usb';

            // Valid device found (almost, continues below)...
            $devices[] = $device;
        }
        return $devices;
    }

    /**
     * Checks if a storage device is already mounted
     * 
     * @param string $device storage_device_name
     * 
     * @access private
     * @return boolean value; true indicating device is mounted
     */
    final private function _is_mounted($device)
    {
        if (!($fh = fopen(self::ETC_MTAB, 'r')))
            return FALSE;

        while (!feof($fh)) {
            $buffer = chop(fgets($fh, 4096));
            if (!strlen($buffer)) break;
            list($name, $this->mount_point) = explode( ' ', $buffer);
            if ($name == $device) {
                fclose($fh);
                return TRUE;
            }
        }

        $this->mount_point = NULL;

        fclose($fh);
        return FALSE;
    }

    /**
     * Scans a directory returning files that match the pattern.
     *
     * @param string $directory directory
     * @param string $pattern   file pattern
     *
     * @access private
     * @return array list of files
     */
    private function _scan_directory($directory, $pattern)
    {
        if (!file_exists($directory) || !($dh = opendir($directory)))
            return array();

        $matches = array();

        while (($file = readdir($dh)) !== FALSE) {
            if (!preg_match($pattern, $file))
                continue;
            $matches[] = $file;
        }

        closedir($dh);
        sort($matches);

        return $matches;
    }
    

    /**
     * Restores all of the conifugration files that are defined in the Bmbackup::PATH_ARCHIVE manifest file.
     *
     * @param string $path the path 
     */
    final private function _restore_configuration($path, $archive)
    {
        $fullpath = "$path/$archive";

        $this->_verify_config_archive($fullpath);
        $file = new File($fullpath);
        print("OK" . "\n". $fullpath);

        try {
            if (!$file->exists()) {
                $shell->execute('/bin/umount', $path);
                throw new File_Not_Found_Exception("The configuration backup file could not be found" . $fullpath, CLEAROS_ERROR);
            }
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
        print("almost there \n");

        try {
            $shell = new Shell;
            if ($shell->execute(self::CMD_TAR, "-C / -xpzf $fullpath", TRUE) != 0) {
                $shell->execute('/bin/umount', self::PATH_ARCHIVE, TRUE);
                throw new Engine_Exception($shell->get_first_output_line(), CLEAROS_ERROR);
            }
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        // Reload the LDAP database and reset LDAP-related daemons
        //--------------------------------------------------------
        if (clearos_library_installed('openldap/LDAP_Driver')) {
            clearos_load_library('openldap/LDAP_Driver');
            $openldap = new LDAP_Driver();
            $openldap->import();
        }
    }

    /**
     * Restores home directories using the homebackup tar file from the backup device
     *
     * @param string $path the path where the backup file is stored
     * @param string $archive the name of the backup file to restore
     */
    final private function _restore_home($path, $archive)
    {
        $shell = new Shell;
        $fullpath = $path . "/" . $archive;
        $file = new File($fullpath);
        try {
            if (! $file->exists()) {
                $shell->execute('/bin/umount', self::PATH_ARCHIVE, TRUE);
                throw new File_Not_Found_Exception("The file, " . $fullpath . " does not exist", CLEAROS_ERROR);
                return False;
            }
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
            return False;
        }
        
        $folder = new Folder('/home');
        $is_home_empty = $folder->get_listing();
        
        try {
            if ($is_home_empty) {
                $shell->execute('/bin/umount', self::PATH_ARCHIVE, TRUE); 
                throw new Engine_Exception('Restore Not Allowed: User Home Directories already exist.', CLEAROS_ERROR);
            }
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
        
        try {
            if ($shell->execute(self::CMD_TAR, "-C / -xpzf " . $fullpath, TRUE)) {
                $shell->execute('/bin/umount', self::PATH_ARCHIVE, TRUE);
                throw new Engine_Exception("could not run the CMD_TAR command", CLEAROS_ERROR);
                return False;
            }
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
            return False;
        }
        return True;
    }

    /**
     * Restores flexshare directories using the flexhsare tar file from the backup device
     *
     * @param string $path the path where the backup file is stored
     * @param string $archive the name of the backup file to restore
     */
    final private function _restore_flexshare($path, $archive)
    {
        $shell = new Shell;
        $fullpath = "$path/$archive";
        $file = new File($fullpath);
       
        try {
            if (!$file->exists()) {
                $shell->execute('/bin/umount', self::PATH_ARCHIVE, TRUE);
                throw new File_Not_Found_Exception("Could not find the file, " . $fullpath, CLEAROS_ERROR);
            }
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        $folder = new Folder('/var/flexshare/shares');
        $is_flex_empty = $folder->get_listing();

        try {
            if ($is_flex_empty) {
                $shell->execute('/bin/umount', self::PATH_ARCHIVE, TRUE);
                throw new Engine_Exception('Flexshare Restore Not Allowed: Flexshare Directories already exist.', CLEAROS_ERROR);
            }
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        try {
            if ($shell->execute(self::CMD_TAR, "-C / -xpzf $fullpath", TRUE)) {
                $shell->execute('/bin/umount', self::PATH_ARCHIVE, TRUE);
                throw new Engine_Exception("Unable to untar the backup file, " . $fullpath, CLEAROS_ERROR);
            }
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    final private function _verify_config_archive($fullpath)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Validate
        //---------

        $file = new File($fullpath);

        if (!$file->exists()) {
            throw new File_Not_Found_Exception("Unable to find the file, " . $fullpath, CLEAROS_ERROR);
        }

        // Check for /etc/release file (not stored in old versions)
        //---------------------------------------------------------
        
        $shell = new Shell;
        $shell->execute(self::CMD_TAR, "-tzvf $fullpath", TRUE);
        $files = $shell->get_output();

        $release_found = FALSE;

        foreach ($files as $file) {
            if (preg_match("/ etc\/clearos-release$/", $file))
                $release_found = TRUE;
        }

        if (! $release_found) {
            throw new Engine_Exception(lang('bmbackup_release_missing'), CLEAROS_ERROR);
        }
        
        // Check to see if release file matches
        //-------------------------------------
        $retval = $shell->execute(self::CMD_TAR, "-O -C /var/tmp -xzf $fullpath etc/clearos-release", TRUE);

        $archive_version = trim($shell->get_first_output_line());

        $file = new File('/etc/clearos-release');
        $current_version = trim ($file->get_contents());
        
        if ($current_version != $archive_version) {
            $err = lang('bmbackup_release_mismatch') . " ($archive_version)";
            throw new Engine_Exception($err, CLEAROS_ERROR);
        }
    }
    
    /**
     *
     *
     */
    function get_log_summary()
    {
        /*
        $system_log_file = new File('/var/log/system', TRUE);
        $contents = $system_log_file->get_contents_as_array();
        $j = 0;
        for ($i = 1; $i < $last_line; $i++) {
            $last_line--;
            if (preg_match('/\bbmbackup\b/', $contents[$last_line]) && $j < 10) {
                if (
                    preg_match('/\bsuccessful\b/', $contents[$last_line]) || 
                    preg_match('/bmbackup failed:/', $contents[$last_line]) || 
                    preg_match('/bmbackup warning:/', $contents[$last_line])
                ) {
                    $j++;
                }
            }
        }
        */
        $system_log = new Log_Viewer;
        $contents = $system_log->get_log_entries('system', 'bmbackup');
        return $contents;
    }
}
