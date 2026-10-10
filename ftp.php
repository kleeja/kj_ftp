<?php
/**
 *
 * @package Kleeja
 * @copyright (c) 2007 Kleeja.net
 * @license ./docs/license.txt
 *
 */

//no for directly open
if (!defined('IN_COMMON')) {
    exit();
}

/**
 * a connection to the FTP server of an account, the paths it takes are relative to the root folder of the account
 */
class kleeja_ftp
{
    /**
     * seconds to wait for the server before the connection fails
     */
    public $timeout = 60;

    /**
     * the root folder of the account on the server, without a slash at its end
     */
    public $root = '';

    /**
     * the connection, false when it is closed
     * @var \FTP\Connection|false
     */
    private $handler = false;

    /**
     * the unique name of the account, files keep it in their folder: ftp://{name}:{folder}
     */
    private $unique_name = '';

    /**
     * Connect to the FTP server and sign in
     *
     * @param  string          $host     FTP server address
     * @param  string          $user     FTP server username
     * @param  string          $password FTP server password
     * @param  int             $port     FTP server port
     * @param  string          $rootPath the folder to work in, created when it is missing
     * @param  bool            $passive  passive mode, the server opens the port of the data
     * @param  bool            $ssl      explicit TLS (FTPS)
     * @param  int             $timeout  seconds
     * @return bool|kleeja_ftp false when it can not connect or sign in
     */
    public function open(
        $host,
        $user,
        $password,
        $port = 21,
        $rootPath = '',
        $passive = true,
        $ssl = false,
        $timeout = 60,
    ) {
        $this->timeout = max(5, (int) $timeout);

        if ($ssl) {
            $this->handler = function_exists('ftp_ssl_connect')
                ? @ftp_ssl_connect($host, (int) $port, $this->timeout)
                : false;
        } else {
            $this->handler = @ftp_connect($host, (int) $port, $this->timeout);
        }

        if (!$this->handler) {
            return false;
        }

        if (!@ftp_login($this->handler, $user, $password)) {
            $this->close();

            return false;
        }

        // the server accepts the passive mode only after the login
        @ftp_pasv($this->handler, (bool) $passive);

        $this->root = rtrim(trim($rootPath), '/');

        if ($this->root !== '' && !$this->is_dir('')) {
            $this->create_folder('');
        }

        return $this;
    }

    /**
     * Close the connection
     */
    public function close()
    {
        if ($this->handler) {
            @ftp_close($this->handler);
        }

        $this->handler = false;
    }

    /**
     * Change the permissions of a file or folder, servers that don't support it ignore it
     * @param  string $file
     * @param  int    $perm
     * @return bool
     */
    public function chmod($file, $perm = 0644)
    {
        return @ftp_chmod($this->handler, $perm, $this->_fixpath($file)) !== false;
    }

    /**
     * Is there a file at this path, a folder is not a file for SIZE
     * @param  string $file
     * @return bool
     */
    public function file_exists($file)
    {
        return @ftp_size($this->handler, $this->_fixpath($file)) > -1;
    }

    /**
     * Is there a folder at this path
     * @param  string $dir
     * @return bool
     */
    public function is_dir($dir)
    {
        $current = @ftp_pwd($this->handler);

        if (!@ftp_chdir($this->handler, $this->_fixpath($dir))) {
            return false;
        }

        if ($current !== false) {
            @ftp_chdir($this->handler, $current);
        }

        return true;
    }

    /**
     * the path of a file of Kleeja on the server, in the root folder
     * @param  string $path
     * @return string
     */
    private function _fixpath($path)
    {
        $path = trim($path, '/');

        if ($this->root === '') {
            return $path === '' ? '.' : $path;
        }

        return $this->root . ($path === '' ? '' : '/' . $path);
    }

    /**
     * Delete a file
     * @param  string $file
     * @return bool
     */
    public function delete($file)
    {
        return @ftp_delete($this->handler, $this->_fixpath($file));
    }

    /**
     * Create a file with this content
     * @param  string $filePath
     * @param  string $content
     * @return bool
     */
    public function write($filePath, $content)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $content);
        rewind($stream);

        $done = @ftp_fput($this->handler, $this->_fixpath($filePath), $stream, FTP_BINARY);

        fclose($stream);

        return $done;
    }

    /**
     * Upload a local file to the server
     * @param  string $local_file
     * @param  string $server_file
     * @param  bool   $deleteLocal delete the local file after it
     * @return bool
     */
    public function upload($local_file, $server_file, $deleteLocal = true)
    {
        $done = @ftp_put($this->handler, $this->_fixpath($server_file), $local_file, FTP_BINARY);

        if ($deleteLocal && file_exists($local_file)) {
            @unlink($local_file);
        }

        return $done;
    }

    /**
     * Rename a file
     * @param  string $old_file
     * @param  string $new_file
     * @return bool
     */
    public function rename($old_file, $new_file)
    {
        return @ftp_rename($this->handler, $this->_fixpath($old_file), $this->_fixpath($new_file));
    }

    /**
     * Create a folder
     * @param  string $dir
     * @param  int    $perm
     * @return bool
     */
    public function create_folder($dir, $perm = 0755)
    {
        if (@ftp_mkdir($this->handler, $this->_fixpath($dir)) === false) {
            return false;
        }

        $this->chmod($dir, $perm);

        return true;
    }

    /**
     * Delete an empty folder
     * @param  string $dir
     * @return bool
     */
    public function delete_folder($dir)
    {
        return @ftp_rmdir($this->handler, $this->_fixpath($dir));
    }

    /**
     * @param string $unique_name
     */
    public function setUniqueName($unique_name)
    {
        $this->unique_name = $unique_name;
    }

    /**
     * @return string
     */
    public function getUniqueName()
    {
        return $this->unique_name;
    }
}
