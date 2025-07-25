<?php
/* ------------------------------ NOTICE ----------------------------------

  If you're seeing this text when browsing to the installer, it means your
  web server is not set up properly.

  Please contact your host and ask them to enable "PHP" processing on your
  account.
  ----------------------------- NOTICE --------------------------------- */

define('DUPLICATOR_PRO_INSTALLER_KB_IN_BYTES', 1024);
define('DUPLICATOR_PRO_INSTALLER_MB_IN_BYTES', 1024 * DUPLICATOR_PRO_INSTALLER_KB_IN_BYTES);
define('DUPLICATOR_PRO_GB_IN_BYTES', 1024 * DUPLICATOR_PRO_INSTALLER_MB_IN_BYTES);
define('DUPLICATOR_PRO_PHP_MAX_MEMORY', 4096 * DUPLICATOR_PRO_INSTALLER_MB_IN_BYTES);

date_default_timezone_set('UTC'); // Some machines don’t have this set so just do it here.
@ignore_user_abort(true);

if (!function_exists('wp_is_ini_value_changeable')) {

    function wp_is_ini_value_changeable($setting)
    {
        static $ini_all;
        if (!isset($ini_all)) {
            $ini_all = false;
            // Sometimes `ini_get_all()` is disabled via the `disable_functions` option for "security purposes".
            if (function_exists('ini_get_all')) {
                $ini_all = ini_get_all();
            }
        }
        if (isset($ini_all[$setting]['access']) && ( INI_ALL === ( $ini_all[$setting]['access'] & 7 ) || INI_USER === ( $ini_all[$setting]['access'] & 7 ) )) {
            return true;
        }
        if (!is_array($ini_all)) {
            return true;
        }
        return false;
    }
}

@set_time_limit(3600);
if (wp_is_ini_value_changeable('memory_limit')) {
    @ini_set('memory_limit', DUPLICATOR_PRO_PHP_MAX_MEMORY);
}
if (wp_is_ini_value_changeable('max_input_time')) {
    @ini_set('max_input_time', '-1');
}
if (wp_is_ini_value_changeable('pcre.backtrack_limit')) {
    @ini_set('pcre.backtrack_limit', PHP_INT_MAX);
}
if (wp_is_ini_value_changeable('default_socket_timeout')) {
    @ini_set('default_socket_timeout', 3600);
}

DUPX_Handler::init_error_handler();

/**
 * Bootstrap utility to exatract the core installer
 *
 * Standard: PSR-2
 *
 * @package SC\DUPX\Bootstrap
 * @link http://www.php-fig.org/psr/psr-2/
 *
 *  To force extraction mode:
 * 		installer.php?unzipmode=auto
 * 		installer.php?unzipmode=ziparchive
 * 		installer.php?unzipmode=shellexec
 */
/* * * CLASS DEFINITION START ** */

abstract class DUPX_Bootstrap_Zip_Mode
{

    const AutoUnzip  = 0;
    const ZipArchive = 1;
    const ShellExec  = 2;

}

class DUPX_Bootstrap
{
    //@@ Params get dynamically swapped when package is built
    const ARCHIVE_FILENAME   = '20200923_gaminggiaodiendanhgiagame_f7c5e7195c2f98bf2375_20200923025605_archive.zip';
    const ARCHIVE_SIZE       = '62206526';
    const INSTALLER_DIR_NAME = 'dup-installer';
    const PACKAGE_HASH       = 'f7c5e71-23025605';
    const VERSION            = '3.8.7';

    const MINIMUM_PHP_VERSION = '5.3.13';
    
    public $targetRoot          = null;
    public $origDupInstFolder   = null;
    public $targetDupInstFolder = null;
    public $targetDupInst       = null;
    public $isCustomDupFolder   = false;
    public $hasZipArchive       = false;
    public $hasShellExecUnzip   = false;
    public $mainInstallerURL;
    public $archiveExpectedSize = 0;
    public $archiveActualSize   = 0;
    public $archiveRatio        = 0;

    /**
     * 
     * @var self
     */
    private static $instance = null;

    /**
     * Instantiate the Bootstrap Object
     *
     * @return null
     */
    private function __construct()
    {
        $this->targetRoot        = self::setSafePath(dirname(__FILE__));
        // clean log file
        $this->log('', true);

        $this->origDupInstFolder = self::INSTALLER_DIR_NAME;
        $this->targetDupInstFolder = filter_input(INPUT_GET, 'dup_folder', FILTER_SANITIZE_STRING, array(
			"options" => array(
				"default" => self::INSTALLER_DIR_NAME,

			),
            'flags'   => FILTER_FLAG_STRIP_HIGH));

        $this->isCustomDupFolder = $this->origDupInstFolder !== $this->targetDupInstFolder;
        $this->targetDupInst = $this->targetRoot.'/'.$this->targetDupInstFolder;
        if ($this->isCustomDupFolder) {
            $this->extractionTmpFolder = $this->getTempDir($this->targetRoot);
        } else {
            $this->extractionTmpFolder = $this->targetRoot;
        }
        
        DUPX_CSRF::init($this->targetDupInst, self::PACKAGE_HASH);

        //ARCHIVE_SIZE will be blank with a root filter so we can estimate
        //the default size of the package around 17.5MB (18088000)
        $archiveActualSize         = @filesize(self::ARCHIVE_FILENAME);
        $archiveActualSize         = ($archiveActualSize !== false) ? $archiveActualSize : 0;
        $this->hasZipArchive       = class_exists('ZipArchive');
        $this->hasShellExecUnzip   = $this->getUnzipFilePath() != null ? true : false;
        $this->archiveExpectedSize = strlen(self::ARCHIVE_SIZE) ? self::ARCHIVE_SIZE : 0;
        $this->archiveActualSize   = $archiveActualSize;

        if ($this->archiveExpectedSize > 0) {
            $this->archiveRatio = (((1.0) * $this->archiveActualSize) / $this->archiveExpectedSize) * 100;
        } else {
            $this->archiveRatio = 100;
        }
    }

    /**
     *
     * @return self
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 
     * @param string $path
     * @return boolean/string
     */
    private function getTempDir($path)
    {
        $tempfile = tempnam($path, 'dup-installer_tmp_');
        if (file_exists($tempfile)) {
            unlink($tempfile);
            mkdir($tempfile);
            if (is_dir($tempfile)) {
                return $tempfile;
            }
        }
        return false;
    }
    
    public static function phpVersionCheck()
    {
        if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=')) {
            return true;
        }

        $match = null;
        if (preg_match("#^\d+(\.\d+)*#", PHP_VERSION, $match)) {
            $phpVersion = $match[0];
        } else {
            $phpVersion = PHP_VERSION;
        }
        ?><!DOCTYPE html>
        <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <meta name="robots" content="noindex,nofollow">
                <title>Duplicator Professional - issue</title>
            </head>
            <body>
                <div>
                    <h1>DUPLICATOR PRO ISSUE: PHP <?php echo self::MINIMUM_PHP_VERSION; ?> REQUIRED</h1>
                    <p>
                        This server is running PHP: <b><?php echo $phpVersion; ?></b>. <i>A minimum of <b>PHP <?php echo self::MINIMUM_PHP_VERSION; ?></b> is required</i>.<br><br>
                        <b>Contact your hosting provider or server administrator and let them know you would like to upgrade your PHP version.</b>
                    </p>
                </div>
            </body>
        </html>
        <?php
        die();
    }

    /**
     * Run the bootstrap process which includes checking for requirements and running
     * the extraction process
     *
     * @return null | string	Returns null if the run was successful otherwise an error message
     */
    public function run()
    {
        date_default_timezone_set('UTC'); // Some machines don't have this set so just do it here

        $this->log('==DUPLICATOR INSTALLER BOOTSTRAP v3.8.7==');
        $this->log('----------------------------------------------------');
        $this->log('Installer bootstrap start');

        $archive_filepath = $this->getArchiveFilePath();
        $archive_filename = self::ARCHIVE_FILENAME;

        $error               = null;
        $extract_installer   = true;
        $extract_success     = false;
        $archiveExpectedEasy = $this->readableByteSize($this->archiveExpectedSize);
        $archiveActualEasy   = $this->readableByteSize($this->archiveActualSize);

        //$archive_extension = strtolower(pathinfo($archive_filepath)['extension']);
        $archive_extension   = strtolower(pathinfo($archive_filepath, PATHINFO_EXTENSION));
        $installer_dir_found = (
            file_exists($this->targetDupInst) &&
            file_exists($this->targetDupInst."/main.installer.php") &&
            file_exists($this->targetDupInst."/dup-archive__".self::PACKAGE_HASH.".txt") &&
            file_exists($this->targetDupInst."/dup-database__".self::PACKAGE_HASH.".sql")
            );

        $manual_extract_found = (
            $installer_dir_found &&
            file_exists($this->targetRoot."/dup-wp-config-arc__".self::PACKAGE_HASH.".txt")
            );


        $isZip = ($archive_extension == 'zip');

        //MANUAL EXTRACTION NOT FOUND
        if (!$manual_extract_found) {

            //MISSING ARCHIVE FILE
            if (!file_exists($archive_filepath)) {
                $this->log("[ERROR] Archive file not found!");
                $archive_candidates = ($isZip) ? $this->getFilesWithExtension('zip') : $this->getFilesWithExtension('daf');
                $candidate_count    = count($archive_candidates);
                $candidate_html     = "- No {$archive_extension} files found -";

                if ($candidate_count >= 1) {
                    $candidate_html = "<ol>";
                    foreach ($archive_candidates as $archive_candidate) {
                        $candidate_html .= '<li class="diff-list"> '.$this->compareStrings($archive_filename, $archive_candidate).'</li>';
                    }
                    $candidate_html .= "</ol>";
                }

                $error = "<style>.diff-list font { font-weight: bold; }</style>"
                    ."<b>Archive not found!</b> The <i>'Required File'</i> below should be present in the <i>'Extraction Path'</i>.  "
                    ."The archive file name must be the <u>exact</u> name of the archive file placed in the extraction path character for character.<br/><br/>  "
                    ."If the file does not have the correct name then rename it to the <i>'Required File'</i> below.   When downloading the package files make "
                    ."sure both files are from the same package line in the packages view.  If the archive is not finished downloading please wait for it to complete.<br/><br/>"
                    ."<b>Required File:</b>  <span class='file-info'>{$archive_filename}</span> <br/>"
                    ."<b>Extraction Path:</b> <span class='file-info'>{$this->targetRoot}/</span><br/><br/>"
                    ."Potential archives found at extraction path: <br/>{$candidate_html}<br/><br/>";

                return $error;
            }

            $archive_size = self::ARCHIVE_SIZE;

            // Sometimes the self::ARCHIVE_SIZE is ''.
            if (!empty($archive_size) && !self::checkInputValidInt(self::ARCHIVE_SIZE)) {
                $no_of_bits = PHP_INT_SIZE * 8;
                $error      = 'Current is a '.$no_of_bits.'-bit SO. This archive is too large for '.$no_of_bits.'-bit PHP.'.'<br>';
                $this->log('[ERROR] '.$error);
                $error      .= 'Possibibles solutions:<br>';
                $error      .= '- Use the file filters to get your package lower to support this server or try the package on a Linux server.'.'<br>';
                $error      .= '- Perform a <a target="_blank" href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-installer-015-q">Manual Extract Install</a>'.'<br>';

                switch ($no_of_bits == 32) {
                    case 32:
                        $error .= '- Ask your host to upgrade the server to 64-bit PHP or install on another system has 64-bit PHP'.'<br>';
                        break;
                    case 64:
                        $error .= '- Ask your host to upgrade the server to 128-bit PHP or install on another system has 128-bit PHP'.'<br>';
                        break;
                }

                if (self::isWindows()) {
                    $error .= '- <a target="_blank" href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-052-q">Windows DupArchive extractor</a> to extract all files from the archive.'.'<br>';
                }

                return $error;
            }

            //SIZE CHECK ERROR
            if (($this->archiveRatio < 90) && ($this->archiveActualSize > 0) && ($this->archiveExpectedSize > 0)) {
                $this->log("ERROR: The expected archive size should be around [{$archiveExpectedEasy}].  The actual size is currently [{$archiveActualEasy}].");
                $this->log("ERROR: The archive file may not have fully been downloaded to the server");
                $percent = round($this->archiveRatio);

                $autochecked = isset($_POST['auto-fresh']) ? "checked='true'" : '';
                $error       = "<b>Archive file size warning.</b><br/> The expected archive size should be around <b class='pass'>[{$archiveExpectedEasy}]</b>.  "
                    ."The actual size is currently <b class='fail'>[{$archiveActualEasy}]</b>.  The archive file may not have fully been downloaded to the server.  "
                    ."Please validate that the file sizes are close to the same size and that the file has been completely downloaded to the destination server.  If the archive is still "
                    ."downloading then refresh this page to get an update on the download size.<br/><br/>";

                return $error;
            }
        }

        if ($installer_dir_found) {
            // INSTALL DIRECTORY: Check if its setup correctly AND we are not in overwrite mode
            if (isset($_GET['force-extract-installer']) && ('1' == $_GET['force-extract-installer'] || 'enable' == $_GET['force-extract-installer'] || 'false' == $_GET['force-extract-installer'])) {
                $this->log("Manual extract found with force extract installer get parametr");
                $extract_installer = true;
            } else {
                $extract_installer = false;
                $this->log("Manual extract found so not going to extract ".$this->targetDupInstFolder." dir");
            }
        } else {
            $extract_installer = true;
        }

        // if ($extract_installer && file_exists($this->targetDupInst)) {
        if (file_exists($this->targetDupInst)) {
            $this->log("EXTRACT ".$this->targetDupInstFolder." dir");
            $hash_pattern                 = '[a-z0-9][a-z0-9][a-z0-9][a-z0-9][a-z0-9][a-z0-9][a-z0-9]-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]';
            $file_patterns_with_hash_file = array(
                // file pattern => hash file
                'dup-archive__'.$hash_pattern.'.txt'        => 'dup-archive__'.self::PACKAGE_HASH.'.txt',
                'dup-database__'.$hash_pattern.'.sql'       => 'dup-database__'.self::PACKAGE_HASH.'.sql',
                'dup-installer-data__'.$hash_pattern.'.sql' => 'dup-installer-data__'.self::PACKAGE_HASH.'.sql',
                'dup-installer-log__'.$hash_pattern.'.txt'  => 'dup-installer-log__'.self::PACKAGE_HASH.'.txt',
                'dup-scan__'.$hash_pattern.'.json'          => 'dup-scan__'.self::PACKAGE_HASH.'.json',
                'dup-scanned-dirs__'.$hash_pattern.'.txt'   => 'dup-scanned-dirs__'.self::PACKAGE_HASH.'.txt',
                'dup-scanned-files__'.$hash_pattern.'.txt'  => 'dup-scanned-files__'.self::PACKAGE_HASH.'.txt',
            );
            foreach ($file_patterns_with_hash_file as $file_pattern => $hash_file) {
                $globs = glob($this->targetDupInst.'/'.$file_pattern);
                if (!empty($globs)) {
                    foreach ($globs as $glob) {
                        $file = basename($glob);
                        if ($file != $hash_file) {
                            if (unlink($glob)) {
                                $this->log('Successfully deleted the file '.$glob);
                            } else {
                                $error .= '[ERROR] Error deleting the file '.$glob.' Please manually delete it and try again.';
                                $this->log($error);
                            }
                        }
                    }
                }
            }
        }

        //ATTEMPT EXTRACTION:
        //ZipArchive and Shell Exec
        if ($extract_installer) {
            $this->log("Ready to extract the installer");

            $this->log("Checking permission of destination folder");
            $destination = $this->targetRoot;
            if (!is_writable($destination)) {
                $this->log("destination folder for extraction is not writable");
                if (self::chmod($destination, 'u+rwx')) {
                    $this->log("Permission of destination folder changed to u+rwx");
                } else {
                    $this->log("[ERROR] Permission of destination folder failed to change to u+rwx");
                }
            }

            if (!is_writable($destination)) {
                $this->log("WARNING: The {$destination} directory is not writable.");
                $error = "NOTICE: The {$destination} directory is not writable on this server please talk to your host or server admin about making ";
                $error .= "<a target='_blank' href='https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-055-q'>writable {$destination} directory</a> on this server. <br/>";
                return $error;
            }

            if ($isZip) {
                $zip_mode = $this->getZipMode();

                if (($zip_mode == DUPX_Bootstrap_Zip_Mode::AutoUnzip) || ($zip_mode == DUPX_Bootstrap_Zip_Mode::ZipArchive) && class_exists('ZipArchive')) {
                    if ($this->hasZipArchive) {
                        $this->log("ZipArchive exists so using that");
                        $extract_success = $this->extractInstallerZipArchive($archive_filepath, $this->origDupInstFolder, $this->extractionTmpFolder);

                        if ($extract_success) {
                            $this->log('Successfully extracted with ZipArchive');
                        } else {
                            if (0 == $this->installer_files_found) {
                                $error = "[ERROR] This archive is not properly formatted and does not contain a ".$this->origDupInstFolder." directory. Please make sure you are attempting to install the original archive and not one that has been reconstructed.";
                                $this->log($error);
                                return $error;
                            } else {
                                $error = '[ERROR] Error extracting with ZipArchive. ';
                                $this->log($error);
                            }
                        }
                    } else {
                        $this->log("WARNING: ZipArchive is not enabled.");
                        $error = "NOTICE: ZipArchive is not enabled on this server please talk to your host or server admin about enabling ";
                        $error .= "<a target='_blank' href='https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-060-q'>ZipArchive</a> on this server. <br/>";
                    }
                }

                if (!$extract_success) {
                    if (($zip_mode == DUPX_Bootstrap_Zip_Mode::AutoUnzip) || ($zip_mode == DUPX_Bootstrap_Zip_Mode::ShellExec)) {
                        $unzip_filepath = $this->getUnzipFilePath();
                        if ($unzip_filepath != null) {
                            $extract_success = $this->extractInstallerShellexec($archive_filepath, $this->origDupInstFolder, $this->extractionTmpFolder);
                            if ($extract_success) {
                                $this->log('Successfully extracted with Shell Exec');
                                $error = null;
                            } else {
                                $error .= '[ERROR] Error extracting with Shell Exec. Please manually extract archive then choose Advanced > Manual Extract in installer.';
                                $this->log($error);
                            }
                        } else {
                            $this->log('WARNING: Shell Exec Zip is not available');
                            $error .= "NOTICE: Shell Exec is not enabled on this server please talk to your host or server admin about enabling ";
                            $error .= "<a target='_blank' href='http://php.net/manual/en/function.shell-exec.php'>Shell Exec</a> on this server or manually extract archive then choose Advanced > Manual Extract in installer.";
                        }
                    }
                }

                // If both ZipArchive and ShellZip are not available, Error message should be combined for both
                if (!$extract_success && $zip_mode == DUPX_Bootstrap_Zip_Mode::AutoUnzip) {
                    $unzip_filepath = $this->getUnzipFilePath();
                    if (!class_exists('ZipArchive') && empty($unzip_filepath)) {
                        $this->log("WARNING: ZipArchive and Shell Exec are not enabled on this server.");
                        $error = "NOTICE: ZipArchive and Shell Exec are not enabled on this server please talk to your host or server admin about enabling ";
                        $error .= "<a target='_blank' href='https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-060-q'>ZipArchive</a> or <a target='_blank' href='http://php.net/manual/en/function.shell-exec.php'>Shell Exec</a> on this server or manually extract archive then choose Advanced > Manual Extract in installer.";
                    }
                }
            } else {
                DupArchiveMiniExpander::init(array($this, 'log'));
                try {
                    DupArchiveMiniExpander::expandDirectory($archive_filepath, $this->origDupInstFolder, $this->extractionTmpFolder);
                }
                catch (Exception $ex) {
                    $this->log("[ERROR] Error expanding installer subdirectory:".$ex->getMessage());
                    throw $ex;
                }
            }
            
            if ($this->isCustomDupFolder) {
                if (rename($this->extractionTmpFolder.'/'.$this->origDupInstFolder ,  $this->targetDupInst) === false) {
                    throw new Exception('Can\'t rename the tmp dup-installer folder');
                } else {
                    rmdir($this->extractionTmpFolder);
                }
            }

            $is_apache = (strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false || strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false);
            $is_nginx  = (strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false);

            $sapi_type                   = php_sapi_name();
            $php_ini_data                = array(
                'max_execution_time'     => 3600,
                'max_input_time'         => -1,
                'ignore_user_abort'      => 'On',
                'post_max_size'          => '4096M',
                'upload_max_filesize'    => '4096M',
                'memory_limit'           => DUPLICATOR_PRO_PHP_MAX_MEMORY,
                'default_socket_timeout' => 3600,
                'pcre.backtrack_limit'   => 99999999999,
            );
            $sapi_type_first_three_chars = substr($sapi_type, 0, 3);
            if ('fpm' === $sapi_type_first_three_chars) {
                $this->log("SAPI: FPM");
                if ($is_apache) {
                    $this->log('Server: Apache');
                } elseif ($is_nginx) {
                    $this->log('Server: Nginx');
                }

                if (($is_apache && function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules())) || $is_nginx) {
                    $htaccess_data = array();
                    foreach ($php_ini_data as $php_ini_key => $php_ini_val) {
                        if ($is_apache) {
                            $htaccess_data[] = 'SetEnv PHP_VALUE "'.$php_ini_key.' = '.$php_ini_val.'"';
                        } elseif ($is_nginx) {
                            if ('On' == $php_ini_val || 'Off' == $php_ini_val) {
                                $htaccess_data[] = 'php_flag '.$php_ini_key.' '.$php_ini_val;
                            } else {
                                $htaccess_data[] = 'php_value '.$php_ini_key.' '.$php_ini_val;
                            }
                        }
                    }

                    $htaccess_text      = implode("\n", $htaccess_data);
                    $htaccess_file_path = $this->targetDupInst.'/.htaccess';
                    $this->log("creating {$htaccess_file_path} with the content:");
                    $this->log($htaccess_text);
                    @file_put_contents($htaccess_file_path, $htaccess_text);
                }
            } elseif ('cgi' === $sapi_type_first_three_chars || 'litespeed' === $sapi_type) {
                if ('cgi' === $sapi_type_first_three_chars) {
                    $this->log("SAPI: CGI");
                } else {
                    $this->log("SAPI: litespeed");
                }
                if (version_compare(phpversion(), 5.5) >= 0 && (!$is_apache || 'litespeed' === $sapi_type)) {
                    $ini_data = array();
                    foreach ($php_ini_data as $php_ini_key => $php_ini_val) {
                        $ini_data[] = $php_ini_key.' = '.$php_ini_val;
                    }
                    $ini_text      = implode("\n", $ini_data);
                    $ini_file_path = $this->targetDupInst.'/.user.ini';
                    $this->log("creating {$ini_file_path} with the content:");
                    $this->log($ini_text);
                    @file_put_contents($ini_file_path, $ini_text);
                } else {
                    $this->log("No need to create ".$this->targetDupInstFolder."/.htaccess or ".$this->targetDupInstFolder."/.user.ini");
                }
            } else {
                $this->log("No need to create ".$this->targetDupInstFolder."/.htaccess or ".$this->targetDupInstFolder."/.user.ini");
                $this->log("ERROR:  SAPI: Unrecognized");
            }
        } else {
            $this->log("ERROR: Didn't need to extract the installer.");
        }

        if (empty($error)) {
            $config_files              = glob($this->targetDupInst.'/dup-archive__*.txt');
            $config_file_absolute_path = array_pop($config_files);
            if (!file_exists($config_file_absolute_path)) {
                $error = '<b>Archive config file not found in '.$this->targetDupInstFolder.' folder.</b> <br><br>';
                return $error;
            }
        }

        $is_https = $this->isHttps();

        if ($is_https) {
            $current_url = 'https://';
        } else {
            $current_url = 'http://';
        }

        if (($_SERVER['SERVER_PORT'] == 80) && ($is_https)) {
            // Fixing what appears to be a bad server setting
            $server_port = 443;
        } else {
            $server_port = $_SERVER['SERVER_PORT'];
        }

        // for ngrok url and Local by Flywheel Live URL
        if (isset($_SERVER['HTTP_X_ORIGINAL_HOST'])) {
            $host = $_SERVER['HTTP_X_ORIGINAL_HOST'];
        } else {
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']; //WAS SERVER_NAME and caused problems on some boxes
        }
        $current_url .= $host;
        if (strpos($current_url, ':') === false) {
            $current_url = $current_url.':'.$server_port;
        }
        if (!isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = substr($_SERVER['PHP_SELF'], 0);

            if (isset($_SERVER['QUERY_STRING']) AND $_SERVER['QUERY_STRING'] != "") {
                $_SERVER['REQUEST_URI'] .= '?'.$_SERVER['QUERY_STRING'];
            }
        }

        $current_url .= $_SERVER['REQUEST_URI'];
        $uri_start   = dirname($current_url);

        if ($error === null) {

            if (!file_exists($this->targetDupInst)) {

                $error = 'Can\'t extract installer directory. See <a target="_blank" href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-installer-022-q">this FAQ item</a> for details on how to resolve.</a>';
            }

            if ($error == null) {

                $bootloader_name        = basename(__FILE__);
                $this->mainInstallerURL = $uri_start.'/'.$this->targetDupInstFolder.'/main.installer.php';

                $this->archive    = $archive_filepath;
                $this->bootloader = $bootloader_name;

                $this->fixInstallerPerms($this->mainInstallerURL);
                // $this->mainInstallerURL = $this->mainInstallerURL . "?archive=$encoded_archive_path&bootloader=$bootloader_name&ctrl_action=ctrl-step0";

                if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
                    $this->mainInstallerURL .= '?'.$_SERVER['QUERY_STRING'];
                }

                $this->log("DONE: No detected errors so redirecting to the main installer. Main Installer URI = {$this->mainInstallerURL}");
            }
        }

        return $error;
    }

    /**
     * Indicates if site is running https or not
     *
     * @return bool  Returns true if https, false if not
     */
    public function isHttps()
    {
        $retVal = true;

        if (isset($_SERVER['HTTPS'])) {
            $retVal = ($_SERVER['HTTPS'] !== 'off');
        } else {
            $retVal = ($_SERVER['SERVER_PORT'] == 443);
        }

        // nginx
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $retVal = ($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
        }


        return $retVal;
    }

    /**
     *  Attempts to set the 'dup-installer' directory permissions
     *
     * @return null
     */
    private function fixInstallerPerms()
    {
        $file_perms = 'u+rw';
        $dir_perms  = 'u+rwx';

        $installer_dir_path = $this->targetDupInstFolder;

        $this->setPerms($installer_dir_path, $dir_perms, false);
        $this->setPerms($installer_dir_path, $file_perms, true);
    }

    /**
     * Set the permissions of a given directory and optionally all files
     *
     * @param string $directory		The full path to the directory where perms will be set
     * @param string $perms			The given permission sets to use such as '0755' or 'u+rw'
     * @param string $do_files		Also set the permissions of all the files in the directory
     *
     * @return null
     */
    private function setPerms($directory, $perms, $do_files)
    {
        if (!$do_files) {
            // If setting a directory hiearchy be sure to include the base directory
            $this->setPermsOnItem($directory, $perms);
        }

        $item_names = array_diff(scandir($directory), array('.', '..'));

        foreach ($item_names as $item_name) {
            $path = "$directory/$item_name";
            if (($do_files && is_file($path)) || (!$do_files && !is_file($path))) {
                $this->setPermsOnItem($path, $perms);
            }
        }
    }

    /**
     * Set the permissions of a single directory or file
     *
     * @param string $path			The full path to the directory or file where perms will be set
     * @param string $perms			The given permission sets to use such as '0755' or 'u+rw'
     *
     * @return bool		Returns true if the permission was properly set
     */
    private function setPermsOnItem($path, $perms)
    {
        $result        = self::chmod($path, $perms);
        $perms_display = decoct($perms);
        if ($result === false) {
            $this->log("ERROR: Couldn't set permissions of $path to {$perms_display}<br/>");
        } else {
            $this->log("Set permissions of $path to {$perms_display}<br/>");
        }
        return $result;
    }

    /**
     * Compare two strings and return html text which represts diff
     *
     * @param string $oldString
     * @param string $newString
     *
     * @return string Returns html text
     */
    private function compareStrings($oldString, $newString)
    {
        $ret = '';
        for ($i = 0; isset($oldString[$i]) || isset($newString[$i]); $i++) {
            if (!isset($oldString[$i])) {
                $ret .= '<font color="red">'.$newString[$i].'</font>';
                continue;
            }
            for ($char = 0; isset($oldString[$i][$char]) || isset($newString[$i][$char]); $char++) {

                if (!isset($oldString[$i][$char])) {
                    $ret .= '<font color="red">'.substr($newString[$i], $char).'</font>';
                    break;
                } elseif (!isset($newString[$i][$char])) {
                    break;
                }

                if (ord($oldString[$i][$char]) != ord($newString[$i][$char])) {
                    $ret .= '<font color="red">'.$newString[$i][$char].'</font>';
                } else {
                    $ret .= $newString[$i][$char];
                }
            }
        }
        return $ret;
    }

    /**
     * Logs a string to the dup-installer-bootlog__[HASH].txt file
     *
     * @param string $s			The string to log to the log file
     *
     * @return boog|int // This function returns the number of bytes that were written to the file, or FALSE on failure. 
     */
    public function log($s, $deleteOld = false)
    {
        static $logfile = null;
        if (is_null($logfile)) {
            $logfile = $this->targetRoot.'/dup-installer-bootlog__'.self::PACKAGE_HASH.'.txt';
        }
        if ($deleteOld && file_exists($logfile)) {
            @unlink($logfile);
        }
        $timestamp = date('M j H:i:s');
        return @file_put_contents($logfile, '['.$timestamp.'] '.$s."\n", FILE_APPEND);
    }

    /**
     * Extracts only the 'dup-installer' files using ZipArchive
     *
     * @param string $archive_filepath	The path to the archive file.
     *
     * @return bool		Returns true if the data was properly extracted
     */
    private function extractInstallerZipArchive($archive_filepath, $origDupInstFolder, $destination, $checkSubFolder = false)
    {
        $success              = true;
        $zipArchive           = new ZipArchive();
        $subFolderArchiveList = array();

        if (($zipOpenRes = $zipArchive->open($archive_filepath)) === true) {
            $this->log("Successfully opened $archive_filepath");
            $folder_prefix = $origDupInstFolder.'/';
            $this->log("Extracting all files from archive within ".$origDupInstFolder);

            $installer_files_found = 0;

            for ($i = 0; $i < $zipArchive->numFiles; $i++) {
                $stat = $zipArchive->statIndex($i);
                if ($checkSubFolder == false) {
                    $filenameCheck = $stat['name'];
                    $filename      = $stat['name'];
                    $tmpSubFolder  = null;
                } else {
                    $safePath = rtrim(self::setSafePath($stat['name']), '/');
                    $tmpArray = explode('/', $safePath);

                    if (count($tmpArray) < 2) {
                        continue;
                    }

                    $tmpSubFolder  = $tmpArray[0];
                    array_shift($tmpArray);
                    $filenameCheck = implode('/', $tmpArray);
                    $filename      = $stat['name'];
                }


                if ($this->startsWith($filenameCheck, $folder_prefix)) {
                    $installer_files_found ++;

                    if (!empty($tmpSubFolder) && !in_array($tmpSubFolder, $subFolderArchiveList)) {
                        $subFolderArchiveList[] = $tmpSubFolder;
                    }

                    if ($zipArchive->extractTo($destination, $filename) === true) {
                        $this->log("Success: {$filename} >>> {$destination}");
                    } else {
                        $this->log("[ERROR] Error extracting {$filename} from archive archive file");
                        $success = false;
                        break;
                    }
                }
            }

            if ($checkSubFolder && count($subFolderArchiveList) !== 1) {
                $this->log("Error: Multiple dup subfolder archive");
                $success = false;
            } else {
                if ($checkSubFolder) {
                    $this->moveUpfromSubFolder($destination.'/'.$subFolderArchiveList[0], true);
                }

                $lib_directory     = $destination.'/'.$origDupInstFolder.'/lib';
                $snaplib_directory = $lib_directory.'/snaplib';

                // If snaplib files aren't present attempt to extract and copy those
                if (!file_exists($snaplib_directory)) {
                    $folder_prefix = 'snaplib/';
                    $destination   = $lib_directory;

                    for ($i = 0; $i < $zipArchive->numFiles; $i++) {
                        $stat     = $zipArchive->statIndex($i);
                        $filename = $stat['name'];

                        if ($this->startsWith($filename, $folder_prefix)) {
                            $installer_files_found++;

                            if ($zipArchive->extractTo($destination, $filename) === true) {
                                $this->log("Success: {$filename} >>> {$destination}");
                            } else {
                                $this->log("[ERROR] Error extracting {$filename} from archive archive file");
                                $success = false;
                                break;
                            }
                        }
                    }
                }
            }

            if ($zipArchive->close() === true) {
                $this->log("Successfully closed archive file");
            } else {
                $this->log("[ERROR] Problem closing archive file");
                $success = false;
            }

            if ($success != false && $installer_files_found < 10) {
                if ($checkSubFolder) {
                    $this->log("[ERROR] Couldn't find the installer directory in the archive!");
                    $success = false;
                } else {
                    $this->log("[ERROR] Couldn't find the installer directory in archive root! Check subfolder");
                    $this->extractInstallerZipArchive($archive_filepath, $origDupInstFolder, $destination, true);
                }
            }
        } else {
            $this->log("[ERROR] Couldn't open archive archive file with ZipArchive CODE[".$zipOpenRes."]");
            $success = false;
        }

        return $success;
    }

    /**
     * return true if current SO is windows
     * 
     * @staticvar bool $isWindows
     * @return bool
     */
    public static function isWindows()
    {
        static $isWindows = null;
        if (is_null($isWindows)) {
            $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
        }
        return $isWindows;
    }

    /**
     * return current SO path path len
     * @staticvar int $maxPath
     * @return int
     */
    public static function maxPathLen()
    {
        static $maxPath = null;
        if (is_null($maxPath)) {
            if (defined('PHP_MAXPATHLEN')) {
                $maxPath = PHP_MAXPATHLEN;
            } else {
                // for PHP < 5.3.0
                $maxPath = self::isWindows() ? 260 : 4096;
            }
        }
        return $maxPath;
    }

    /**
     * this function make a chmod only if the are different from perms input and if chmod function is enabled
     *
     * this function handles the variable MODE in a way similar to the chmod of lunux
     * So the MODE variable can be
     * 1) an octal number (0755)
     * 2) a string that defines an octal number ("644")
     * 3) a string with the following format [ugoa]*([-+=]([rwx]*)+
     *
     * examples
     * u+rw         add read and write at the user
     * u+rw,uo-wx   add read and write ad the user and remove wx at groupd and other
     * a=rw         is equal at 666
     * u=rwx,go-rwx is equal at 700
     *
     * @param string $file
     * @param int|string $mode
     * @return boolean
     */
    public static function chmod($file, $mode)
    {
        if (!file_exists($file)) {
            return false;
        }

        $octalMode = 0;

        if (is_int($mode)) {
            $octalMode = $mode;
        } else if (is_string($mode)) {
            $mode = trim($mode);
            if (preg_match('/([0-7]{1,3})/', $mode)) {
                $octalMode = intval(('0'.$mode), 8);
            } else if (preg_match_all('/(a|[ugo]{1,3})([-=+])([rwx]{1,3})/', $mode, $gMatch, PREG_SET_ORDER)) {
                if (!function_exists('fileperms')) {
                    return false;
                }

                // start by file permission
                $octalMode = (fileperms($file) & 0777);

                foreach ($gMatch as $matches) {
                    // [ugo] or a = ugo
                    $group = $matches[1];
                    if ($group === 'a') {
                        $group = 'ugo';
                    }
                    // can be + - =
                    $action = $matches[2];
                    // [rwx]
                    $gPerms = $matches[3];

                    // reset octal group perms
                    $octalGroupMode = 0;

                    // Init sub perms
                    $subPerm = 0;
                    $subPerm += strpos($gPerms, 'x') !== false ? 1 : 0; // mask 001
                    $subPerm += strpos($gPerms, 'w') !== false ? 2 : 0; // mask 010
                    $subPerm += strpos($gPerms, 'r') !== false ? 4 : 0; // mask 100

                    $ugoLen = strlen($group);

                    if ($action === '=') {
                        // generate octal group permsissions and ugo mask invert
                        $ugoMaskInvert = 0777;
                        for ($i = 0; $i < $ugoLen; $i++) {
                            switch ($group[$i]) {
                                case 'u':
                                    $octalGroupMode = $octalGroupMode | $subPerm << 6; // mask xxx000000
                                    $ugoMaskInvert  = $ugoMaskInvert & 077;
                                    break;
                                case 'g':
                                    $octalGroupMode = $octalGroupMode | $subPerm << 3; // mask 000xxx000
                                    $ugoMaskInvert  = $ugoMaskInvert & 0707;
                                    break;
                                case 'o':
                                    $octalGroupMode = $octalGroupMode | $subPerm; // mask 000000xxx
                                    $ugoMaskInvert  = $ugoMaskInvert & 0770;
                                    break;
                            }
                        }
                        // apply = action
                        $octalMode = $octalMode & ($ugoMaskInvert | $octalGroupMode);
                    } else {
                        // generate octal group permsissions
                        for ($i = 0; $i < $ugoLen; $i++) {
                            switch ($group[$i]) {
                                case 'u':
                                    $octalGroupMode = $octalGroupMode | $subPerm << 6; // mask xxx000000
                                    break;
                                case 'g':
                                    $octalGroupMode = $octalGroupMode | $subPerm << 3; // mask 000xxx000
                                    break;
                                case 'o':
                                    $octalGroupMode = $octalGroupMode | $subPerm; // mask 000000xxx
                                    break;
                            }
                        }
                        // apply + or - action
                        switch ($action) {
                            case '+':
                                $octalMode = $octalMode | $octalGroupMode;
                                break;
                            case '-':
                                $octalMode = $octalMode & ~$octalGroupMode;
                                break;
                        }
                    }
                }
            }
        }

        // if input permissions are equal at file permissions return true without performing chmod
        if (function_exists('fileperms') && $octalMode === (fileperms($file) & 0777)) {
            return true;
        }

        if (!function_exists('chmod')) {
            return false;
        }

        return @chmod($file, $octalMode);
    }

    public static function checkInputValidInt($input)
    {
        return (filter_var($input, FILTER_VALIDATE_INT) === 0 || filter_var($input, FILTER_VALIDATE_INT));
    }

    /**
     * this function creates a folder if it does not exist and performs a chmod.
     * it is different from the normal mkdir function to which an umask is applied to the input permissions.
     * 
     * this function handles the variable MODE in a way similar to the chmod of lunux
     * So the MODE variable can be
     * 1) an octal number (0755)
     * 2) a string that defines an octal number ("644")
     * 3) a string with the following format [ugoa]*([-+=]([rwx]*)+
     *
     * @param string $path
     * @param int|string $mode
     * @param bool $recursive
     * @param resource $context // not used for windows bug
     * @return boolean bool TRUE on success or FALSE on failure.
     *
     * @todo check recursive true and multiple chmod
     */
    public static function mkdir($path, $mode = 0777, $recursive = false, $context = null)
    {
        if (strlen($path) > self::maxPathLen()) {
            throw new Exception('Skipping a file that exceeds allowed max path length ['.self::maxPathLen().']. File: '.$filepath);
        }

        if (!file_exists($path)) {
            if (!function_exists('mkdir')) {
                return false;
            }
            if (!@mkdir($path, 0777, $recursive)) {
                return false;
            }
        }

        return self::chmod($path, $mode);
    }

    /**
     * move all folder content up to parent
     *
     * @param string $subFolderName full path
     * @param boolean $deleteSubFolder if true delete subFolder after moved all
     * @return boolean
     * 
     */
    private function moveUpfromSubFolder($subFolderName, $deleteSubFolder = false)
    {
        if (!is_dir($subFolderName)) {
            return false;
        }

        $parentFolder = dirname($subFolderName);
        if (!is_writable($parentFolder)) {
            return false;
        }

        $success = true;
        if (($subList = glob(rtrim($subFolderName, '/').'/*', GLOB_NOSORT)) === false) {
            $this->log("[ERROR] Problem glob folder ".$subFolderName);
            return false;
        } else {
            foreach ($subList as $cName) {
                $destination = $parentFolder.'/'.basename($cName);
                if (file_exists($destination)) {
                    $success = self::deletePath($destination);
                }

                if ($success) {
                    $success = rename($cName, $destination);
                } else {
                    break;
                }
            }

            if ($success && $deleteSubFolder) {
                $success = self::deleteDirectory($subFolderName, true);
            }
        }

        if (!$success) {
            $this->log("[ERROR] Problem om moveUpfromSubFolder subFolder:".$subFolderName);
        }

        return $success;
    }

    /**
     * Extracts only the 'dup-installer' files using Shell-Exec Unzip
     *
     * @param string $archive_filepath	The path to the archive file.
     *
     * @return bool		Returns true if the data was properly extracted
     */
    private function extractInstallerShellexec($archive_filepath, $origDupInstFolder, $destination)
    {
        $success        = false;
        $this->log("Attempting to use Shell Exec");
        $unzip_filepath = $this->getUnzipFilePath();

        if ($unzip_filepath != null) {
            $unzip_command = "$unzip_filepath -q $archive_filepath ".$origDupInstFolder.'/* -d '.$destination.' 2>&1';
            $this->log("Executing $unzip_command");
            $stderr        = shell_exec($unzip_command);

            $lib_directory     = $destination.'/'.$origDupInstFolder.'/lib';
            $snaplib_directory = $lib_directory.'/snaplib';

            // If snaplib files aren't present attempt to extract and copy those
            if (!file_exists($snaplib_directory)) {
                $local_lib_directory = $destination.'/snaplib';
                $unzip_command       = "$unzip_filepath -q $archive_filepath snaplib/* -d '.$destination.' 2>&1";

                $this->log("Executing $unzip_command");
                $stderr .= shell_exec($unzip_command);
                self::mkdir($lib_directory, 'u+rwx');
                rename($local_lib_directory, $snaplib_directory);
            }

            if ($stderr == '') {
                $this->log("Shell exec unzip succeeded");
                $success = true;
            } else {
                $this->log("[ERROR] Shell exec unzip failed. Output={$stderr}");
            }
        }

        return $success;
    }

    /**
     * Attempts to get the archive file path
     *
     * @return string	The full path to the archive file
     */
    private function getArchiveFilePath()
    {
        if (isset($_GET['archive'])) {
            $archive_filepath = $_GET['archive'];
        } else {
            $archive_filename = self::ARCHIVE_FILENAME;
            $archive_filepath = $this->targetRoot.'/'.$archive_filename;
        }

        $this->log("Using archive $archive_filepath");
        return $archive_filepath;
    }

    /**
     * Gets the DUPX_Bootstrap_Zip_Mode enum type that should be used
     *
     * @return DUPX_Bootstrap_Zip_Mode	Returns the current mode of the bootstrapper
     */
    private function getZipMode()
    {
        $zip_mode = DUPX_Bootstrap_Zip_Mode::AutoUnzip;

        if (isset($_GET['zipmode'])) {
            $zipmode_string = $_GET['zipmode'];
            $this->log("Unzip mode specified in querystring: $zipmode_string");

            switch ($zipmode_string) {
                case 'autounzip':
                    $zip_mode = DUPX_Bootstrap_Zip_Mode::AutoUnzip;
                    break;

                case 'ziparchive':
                    $zip_mode = DUPX_Bootstrap_Zip_Mode::ZipArchive;
                    break;

                case 'shellexec':
                    $zip_mode = DUPX_Bootstrap_Zip_Mode::ShellExec;
                    break;
            }
        }

        return $zip_mode;
    }

    /**
     * Checks to see if a string starts with specific characters
     *
     * @return bool		Returns true if the string starts with a specific format
     */
    private function startsWith($haystack, $needle)
    {
        return $needle === "" || strrpos($haystack, $needle, - strlen($haystack)) !== false;
    }

    /**
     * Checks to see if the server supports issuing commands to shell_exex
     *
     * @return bool		Returns true shell_exec can be ran on this server
     */
    public function hasShellExec()
    {
        $cmds = array('shell_exec', 'escapeshellarg', 'escapeshellcmd', 'extension_loaded');

        //Function disabled at server level
        if (array_intersect($cmds, array_map('trim', explode(',', @ini_get('disable_functions')))))
            return false;

        //Suhosin: http://www.hardened-php.net/suhosin/
        //Will cause PHP to silently fail
        if (extension_loaded('suhosin')) {
            $suhosin_ini = @ini_get("suhosin.executor.func.blacklist");
            if (array_intersect($cmds, array_map('trim', explode(',', $suhosin_ini))))
                return false;
        }
        // Can we issue a simple echo command?
        if (!@shell_exec('echo duplicator'))
            return false;

        return true;
    }

    /**
     * Gets the possible system commands for unzip on Linux
     *
     * @return string		Returns unzip file path that can execute the unzip command
     */
    public function getUnzipFilePath()
    {
        $filepath = null;

        if ($this->hasShellExec()) {
            if (shell_exec('hash unzip 2>&1') == NULL) {
                $filepath = 'unzip';
            } else {
                $possible_paths = array(
                    '/usr/bin/unzip',
                    '/opt/local/bin/unzip',
                    '/bin/unzip',
                    '/usr/local/bin/unzip',
                    '/usr/sfw/bin/unzip',
                    '/usr/xdg4/bin/unzip',
                    '/opt/bin/unzip',
                    // RSR TODO put back in when we support shellexec on windows,
                );

                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        $filepath = $path;
                        break;
                    }
                }
            }
        }

        return $filepath;
    }

    /**
     * Display human readable byte sizes such as 150MB
     *
     * @param int $size		The size in bytes
     *
     * @return string A readable byte size format such as 100MB
     */
    public function readableByteSize($size)
    {
        try {
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
            for ($i = 0; $size >= 1024 && $i < 4; $i++)
                $size  /= 1024;
            return round($size, 2).$units[$i];
        }
        catch (Exception $e) {
            return "n/a";
        }
    }

    /**
     *  Returns an array of zip files found in the current executing directory
     *
     *  @return array of zip files
     */
    public function getFilesWithExtension($extension)
    {
        $files = array();
        foreach (glob("*.{$extension}") as $name) {
            if (file_exists($name)) {
                $files[] = $name;
            }
        }
        if (count($files) > 0) {
            return $files;
        }
        //FALL BACK: Windows XP has bug with glob,
        //add secondary check for PHP lameness
        if (($dh = opendir($this->targetRoot))) {
            while (false !== ($name = readdir($dh))) {
                $ext = substr($name, strrpos($name, '.') + 1);
                if (in_array($ext, array($extension))) {
                    $files[] = $name;
                }
            }
            closedir($dh);
        }

        return $files;
    }

    /**
     * Safely remove a directory and recursively if needed
     *
     * @param string $directory The full path to the directory to remove
     * @param string $recursive recursively remove all items
     *
     * @return bool Returns true if all content was removed
     */
    public static function deleteDirectory($directory, $recursive)
    {
        $success = true;

        $filenames = array_diff(scandir($directory), array('.', '..'));

        foreach ($filenames as $filename) {
            $fullPath = $directory.'/'.$filename;

            if (is_dir($fullPath)) {
                if ($recursive) {
                    $success = self::deleteDirectory($fullPath, true);
                }
            } else {
                $success = @unlink($fullPath);
                if ($success === false) {
                    $this->log('[ERROR] '.__FUNCTION__.": Problem deleting file:".$fullPath);
                }
            }

            if ($success === false) {
                $this->log("[ERROR] Problem deleting dir:".$directory);
                break;
            }
        }

        return $success && rmdir($directory);
    }

    /**
     * Safely remove a file or directory and recursively if needed
     *
     * @param string $directory The full path to the directory to remove
     *
     * @return bool Returns true if all content was removed
     */
    public static function deletePath($path)
    {
        $success = true;

        if (is_dir($path)) {
            $success = self::deleteDirectory($path, true);
        } else {
            $success = @unlink($path);

            if ($success === false) {
                $this->log('[ERROR] '.__FUNCTION__.": Problem deleting file:".$path);
            }
        }

        return $success;
    }

    /**
     *  Makes path safe for any OS for PHP
     *
     *  Paths should ALWAYS READ be "/"
     * 		uni:  /home/path/file.txt
     * 		win:  D:/home/path/file.txt
     *
     *  @param string $path		The path to make safe
     *
     *  @return string The original $path with a with all slashes facing '/'.
     */
    public static function setSafePath($path)
    {
        return str_replace("\\", "/", $path);
    }
}

class DUPX_Handler
{

    /**
     *
     * @var bool
     */
    private static $inizialized = false;

    /**
     * This function only initializes the error handler the first time it is called
     */
    public static function init_error_handler()
    {
        if (!self::$inizialized) {
            @set_error_handler(array(__CLASS__, 'error'));
            @register_shutdown_function(array(__CLASS__, 'shutdown'));
            self::$inizialized = true;
        }
    }

    /**
     * Error handler
     *
     * @param  integer $errno   Error level
     * @param  string  $errstr  Error message
     * @param  string  $errfile Error file
     * @param  integer $errline Error line
     * @return void
     */
    public static function error($errno, $errstr, $errfile, $errline)
    {
        switch ($errno) {
            case E_ERROR :
                $log_message = self::getMessage($errno, $errstr, $errfile, $errline);
                if (DUPX_Bootstrap::getInstance()->log($log_message) === false) {
                    $log_message = "Can\'t wrinte logfile\n\n".$log_message;
                }
                die('<pre>'.htmlspecialchars($log_message).'</pre>');
                break;
            case E_NOTICE :
            case E_WARNING :
            default :
                $log_message = self::getMessage($errno, $errstr, $errfile, $errline);
                DUPX_Bootstrap::getInstance()->log($log_message);
                break;
        }
    }

    private static function getMessage($errno, $errstr, $errfile, $errline)
    {
        $result = '[PHP ERR]';
        switch ($errno) {
            case E_ERROR :
                $result .= '[FATAL]';
                break;
            case E_WARNING :
                $result .= '[WARN]';
                break;
            case E_NOTICE :
                $result .= '[NOTICE]';
                break;
            default :
                $result .= '[ISSUE]';
                break;
        }
        $result .= ' MSG:';
        $result .= $errstr;
        $result .= ' [CODE:'.$errno.'|FILE:'.$errfile.'|LINE:'.$errline.']';
        return $result;
    }

    /**
     * Shutdown handler
     *
     * @return void
     */
    public static function shutdown()
    {
        if (($error = error_get_last())) {
            DUPX_Handler::error($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }
}

class DUPX_CSRF
{

    private static $packagHash = null;
    private static $mainFolder = null;

    /**
     * Session var name prefix
     * @var string
     */
    public static $prefix = '_DUPX_CSRF';

    /**
     * Stores all CSRF values: Key as CSRF name and Val as CRF value
     * @var array
     */
    private static $CSRFVars = null;

    public static function init($mainFolderm, $packageHash)
    {
        self::$mainFolder = $mainFolderm;
        self::$packagHash = $packageHash;
        self::$CSRFVars   = null;
    }

    /**
     * Set new CSRF
     * 
     * @param string $key CSRF Key
     * @param string $val CSRF Val
     * 
     * @return Void
     */
    public static function setKeyVal($key, $val)
    {
        $CSRFVars       = self::getCSRFVars();
        $CSRFVars[$key] = $val;
        self::saveCSRFVars($CSRFVars);
        self::$CSRFVars = null;
    }

    /**
     * Get CSRF value by passing CSRF key
     * 
     * @param string $key CSRF key
     * 
     * @return string|boolean If CSRF value set for give n Key, It returns CRF value otherise returns false
     */
    public static function getVal($key)
    {
        $CSRFVars = self::getCSRFVars();
        if (isset($CSRFVars[$key])) {
            return $CSRFVars[$key];
        } else {
            return false;
        }
    }

    /**
     * Generate DUPX_CSRF value for form
     *
     * @param	string	$form	 // Form name as session key
     * 
     * @return	string      // token
     */
    public static function generate($form = NULL)
    {
        $keyName = self::getKeyName($form);

        $existingToken = self::getVal($keyName);
        if (false !== $existingToken) {
            $token = $existingToken;
        } else {
            $token = DUPX_CSRF::token().DUPX_CSRF::fingerprint();
        }

        self::setKeyVal($keyName, $token);
        return $token;
    }

    /**
     * Check DUPX_CSRF value of form
     * 
     * @param	string	$token	- Token
     * @param	string	$form	- Form name as session key
     * @return	boolean
     */
    public static function check($token, $form = NULL)
    {
        if (empty($form)) {
            return false;
        }

        $keyName  = self::getKeyName($form);
        $CSRFVars = self::getCSRFVars();
        if (isset($CSRFVars[$keyName]) && $CSRFVars[$keyName] == $token) { // token OK
            return true;
        }
        return false;
    }

    /** Generate token
     * 
     * @return  string
     */
    protected static function token()
    {
        mt_srand((double) microtime() * 10000);
        $charid = strtoupper(md5(uniqid(rand(), TRUE)));
        return substr($charid, 0, 8).substr($charid, 8, 4).substr($charid, 12, 4).substr($charid, 16, 4).substr($charid, 20, 12);
    }

    /** Returns "digital fingerprint" of user
     * 
     * @return 	string 	- MD5 hashed data
     */
    protected static function fingerprint()
    {
        return strtoupper(md5(implode('|', array($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']))));
    }

    /**
     * Generate CSRF Key name
     * 
     * @param string $form the form name for which CSRF key need to generate
     * @return string CSRF key
     */
    private static function getKeyName($form)
    {
        return DUPX_CSRF::$prefix.'_'.$form;
    }

    /**
     * Get Package hash
     * 
     * @return string Package hash
     */
    private static function getPackageHash()
    {
        if (is_null(self::$packagHash)) {
            throw new Exception('Not init CSFR CLASS');
        }
        return self::$packagHash;
    }

    /**
     * Get file path where CSRF tokens are stored in JSON encoded format
     *
     * @return string file path where CSRF token stored 
     */
    private static function getFilePath()
    {
        if (is_null(self::$mainFolder)) {
            throw new Exception('Not init CSFR CLASS');
        }
        $dupInstallerfolderPath = self::$mainFolder;
        $packageHash            = self::getPackageHash();
        $fileName               = 'dup-installer-csrf__'.$packageHash.'.txt';
        $filePath               = $dupInstallerfolderPath.'/'.$fileName;
        return $filePath;
    }

    /**
     * Get all CSRF vars in array format
     * 
     * @return array Key as CSRF name and value as CSRF value
     */
    private static function getCSRFVars()
    {
        if (is_null(self::$CSRFVars)) {
            $filePath = self::getFilePath();
            if (file_exists($filePath)) {
                $contents = file_get_contents($filePath);
                if (empty($contents)) {
                    self::$CSRFVars = array();
                } else {
                    $CSRFobjs = json_decode($contents);
                    foreach ($CSRFobjs as $key => $value) {
                        self::$CSRFVars[$key] = $value;
                    }
                }
            } else {
                self::$CSRFVars = array();
            }
        }
        return self::$CSRFVars;
    }

    /**
     * Stores all CSRF vars
     * 
     * @param array $CSRFVars holds all CSRF key val
     * @return void
     */
    private static function saveCSRFVars($CSRFVars)
    {
		$contents = json_encode($CSRFVars);
		$filePath = self::getFilePath();
		file_put_contents($filePath, $contents);
    }
}
/* * * CLASS DEFINITION END ** */
DUPX_Bootstrap::phpVersionCheck();

try {
    $boot         = DUPX_Bootstrap::getInstance();
    $boot_error   = $boot->run();
    $auto_refresh = isset($_POST['auto-fresh']) ? true : false;
}
catch (Exception $e) {
    $boot_error = $e->getMessage();
}

if ($boot_error == null) {
    $secure_csrf_token = DUPX_CSRF::generate('secure');
    $ctrl_csrf_token   = DUPX_CSRF::generate('ctrl-step0');
    DUPX_CSRF::setKeyVal('archive', $boot->archive);
    DUPX_CSRF::setKeyVal('bootloader', $boot->bootloader);
    DUPX_CSRF::setKeyVal('package_hash', DUPX_Bootstrap::PACKAGE_HASH);
}
?>

<html>
<?php if ($boot_error == null) : ?>
        <head>
            <meta name="robots" content="noindex,nofollow">
            <title>Duplicator Pro Installer</title>
        </head>
        <body>
            <div style="text-align: center; margin-top: 100px; font-size: 20px;">
                Initializing Installer. Please wait...
            </div>
            <?php
            $id   = uniqid();
            $html = "<form id='{$id}' method='post' action='{$boot->mainInstallerURL}' />\n";
            $data = array(
                'ctrl_action'     => 'ctrl-step0',
                'ctrl_csrf_token' => $ctrl_csrf_token
            );
            foreach ($data as $name => $value) {
                if ('csrf_token' != $name) {
                    $_SESSION[$name] = $value;
                }
                $html .= "<input type='hidden' name='{$name}' value='{$value}' />\n";
            }
            $html .= "</form>\n";
            $html .= "<script>window.onload = function() { document.getElementById('{$id}').submit(); }</script>";
            echo $html;
            ?>
        </body>
<?php else : ?>
        <head>
            <style>
                body {font-family:Verdana,Arial,sans-serif; line-height:18px; font-size: 12px}
                h2 {font-size:20px; margin:5px 0 5px 0; border-bottom:1px solid #dfdfdf; padding:3px}
                div#content {border:1px solid #CDCDCD; width:750px; min-height:550px; margin:auto; margin-top:18px; border-radius:5px; box-shadow:0 8px 6px -6px #333; font-size:13px}
                div#content-inner {padding:10px 30px; min-height:550px}

                /* Header */
                table.header-wizard {border-top-left-radius:5px; border-top-right-radius:5px; width:100%; box-shadow:0 5px 3px -3px #999; background-color:#F1F1F1; font-weight:bold}
                table.header-wizard td.header {font-size:24px; padding:7px 0 7px 0; width:100%;}
                div.dupx-logfile-link {float:right; font-weight:normal; font-size:12px}
                .dupx-version {white-space:nowrap; color:#999; font-size:11px; font-style:italic; text-align:right;  padding:0 15px 5px 0; line-height:14px; font-weight:normal}
                .dupx-version a { color:#999; }

                div.errror-notice {text-align:center; font-style:italic; font-size:11px}
                div.errror-msg { color:maroon; padding: 10px 0 5px 0}
                .pass {color:green}
                .fail {color:red}
                span.file-info {font-size: 11px; font-style: italic}
                div.skip-not-found {padding:10px 0 5px 0;}
                div.skip-not-found label {cursor: pointer}
                table.settings {width:100%; font-size:12px}
                table.settings td {padding: 4px}
                table.settings td:first-child {font-weight: bold}
                .w3-light-grey,.w3-hover-light-grey:hover,.w3-light-gray,.w3-hover-light-gray:hover{color:#000!important;background-color:#f1f1f1!important}
                .w3-container:after,.w3-container:before,.w3-panel:after,.w3-panel:before,.w3-row:after,.w3-row:before,.w3-row-padding:after,.w3-row-padding:before,
                .w3-cell-row:before,.w3-cell-row:after,.w3-clear:after,.w3-clear:before,.w3-bar:before,.w3-bar:after
                {content:"";display:table;clear:both}
                .w3-green,.w3-hover-green:hover{color:#fff!important;background-color:#4CAF50!important}
                .w3-container{padding:0.01em 16px}
                .w3-center{display:inline-block;width:auto; text-align: center !important}
            </style>
        </head>
        <body>
            <div id="content">

                <table cellspacing="0" class="header-wizard">
                    <tr>
                        <td class="header"> &nbsp; Duplicator Pro - Bootloader</div</td>
                        <td class="dupx-version">
                            version: <?php echo htmlentities(DUPX_Bootstrap::VERSION); ?> <br/>
                            &raquo; <a target='_blank' href='dup-installer-bootlog__<?php echo DUPX_Bootstrap::PACKAGE_HASH; ?>.txt'>dup-installer-bootlog__[HASH].txt</a>
                        </td>
                    </tr>
                </table>

                <form id="error-form" method="post">
                    <div id="content-inner">
                        <h2 style="color:maroon">Setup Notice:</h2>
                        <div class="errror-notice">An error has occurred. In order to load the full installer please resolve the issue below.</div>
                        <div class="errror-msg">
    <?php echo $boot_error ?>
                        </div>
                        <br/><br/>

                        <h2>Server Settings:</h2>
                        <table class='settings'>
                            <tr>
                                <td>ZipArchive:</td>
                                <td><?php echo $boot->hasZipArchive ? '<i class="pass">Enabled</i>' : '<i class="fail">Disabled</i>'; ?> </td>
                            </tr>
                            <tr>
                                <td>ShellExec&nbsp;Unzip:</td>
                                <td><?php echo $boot->hasShellExecUnzip ? '<i class="pass">Enabled</i>' : '<i class="fail">Disabled</i>'; ?> </td>
                            </tr>
                            <tr>
                                <td>Extraction&nbsp;Path:</td>
                                <td><?php echo $boot->targetRoot; ?></td>
                            </tr>
                            <tr>
                                <td>Installer Path:</td>
                                <td><?php echo $boot->targetDupInstFolder; ?></td>
                            </tr>
                            <tr>
                                <td>Archive Name:</td>
                                <td><?php echo DUPX_Bootstrap::ARCHIVE_FILENAME ?></td>
                            </tr>
                            <tr>
                                <td>Archive Size:</td>
                                <td>
                                    <b>Expected Size:</b> <?php echo $boot->readableByteSize($boot->archiveExpectedSize); ?>  &nbsp;
                                    <b>Actual Size:</b>   <?php echo $boot->readableByteSize($boot->archiveActualSize); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Boot Log</td>
                                <td><a target='_blank' href='dup-installer-bootlog__<?php echo DUPX_Bootstrap::PACKAGE_HASH; ?>.txt'>dup-installer-bootlog__[HASH].txt</a></td>
                            </tr>
                        </table>
                        <br/><br/>

                        <div style="font-size:11px">
                            Please Note: Either ZipArchive or Shell Exec will need to be enabled for the installer to run automatically otherwise a manual extraction
                            will need to be performed.  In order to run the installer manually follow the instructions to
                            <a href='https://snapcreek.com/duplicator/docs/faqs-tech/#faq-installer-015-q' target='_blank'>manually extract</a> before running the installer.
                        </div>
                        <br/><br/>

                    </div>
                </form>

            </div>
        </body>

        <script>
            function AutoFresh() {
                document.getElementById('error-form').submit();
            }
    <?php if ($auto_refresh) : ?>
                var duration = 10000; //10 seconds
                var counter = 10;
                var countElement = document.getElementById('count-down');

                setTimeout(function () {
                    window.location.reload(1);
                }, duration);
                setInterval(function () {
                    counter--;
                    countElement.innerHTML = (counter > 0) ? counter.toString() : "0";
                }, 1000);

    <?php endif; ?>
        </script>


<?php endif; ?>


    
    <!--
    Used for integrity check do not remove:
    DUPLICATOR_PRO_INSTALLER_EOF  -->
</html>