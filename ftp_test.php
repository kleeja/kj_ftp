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
 * the connection test of an account, on the page of the accounts: each step checks one setting,
 * from the host to the public link, and says what is wrong and how to fix it.
 * It creates a folder in the root folder, uploads the FTP logo of Kleeja in it, opens it at its public link,
 * then deletes them, and the root folder too when the test had to create it
 */
class kj_ftp_test
{
    /**
     * the name of the logo on the server
     */
    const TEST_FILE = 'kleeja-ftp.png';

    /**
     * the steps in their order, with their group and the word of their title
     */
    const STEPS = [
        'extension' => ['settings', 'KJ_FTP_TEST_EXTENSION'],
        'php_tls' => ['settings', 'KJ_FTP_TEST_PHP_TLS'],
        'link_setting' => ['settings', 'KJ_FTP_OPT_LINK'],
        'active' => ['settings', 'KJ_FTP_OPT_ACTIVE'],
        'dns' => ['connection', 'KJ_FTP_OPT_HOST'],
        'port' => ['connection', 'KJ_FTP_OPT_PORT'],
        'tls' => ['connection', 'KJ_FTP_OPT_SSL'],
        'login' => ['connection', 'KJ_FTP_TEST_LOGIN'],
        'data' => ['connection', 'KJ_FTP_TEST_DATA'],
        'root' => ['files', 'KJ_FTP_OPT_ROOT'],
        'mkdir' => ['files', 'KJ_FTP_TEST_MKDIR'],
        'upload' => ['files', 'KJ_FTP_TEST_UPLOAD'],
        'link' => ['files', 'KJ_FTP_TEST_LINK'],
        'delete' => ['files', 'KJ_FTP_TEST_DELETE'],
        'rmdir' => ['files', 'KJ_FTP_TEST_RMDIR'],
        'folder' => ['files', 'KJ_FTP_TEST_FOLDER'],
    ];

    /**
     * the account, its texts decoded
     */
    private array $account;

    /**
     * the results by step: [status, detail, hint], status is ok, info, warning or error
     */
    private array $results = [];

    /**
     * @var \FTP\Connection|false
     */
    private $conn = false;

    private int $timeout;

    /**
     * the last warning of PHP, the answer of the server is in it
     */
    private string $last_error = '';

    /**
     * the root folder, without a slash at its end, and the folder that the account opens in
     */
    private string $root = '';
    private string $login_folder = '';

    /**
     * what the test made on the server, deleted at its end
     */
    private bool $root_created = false;
    private string $test_folder = '';
    private bool $test_file = false;

    /**
     * @param array $row a row of the accounts table
     */
    public function __construct(array $row)
    {
        $this->account = [
            'name' => (string) $row['name'],
            'host' => kleeja_html_decode($row['host']),
            'username' => kleeja_html_decode($row['username']),
            'password' => kleeja_html_decode($row['password']),
            'port' => (int) $row['port'] ?: 21,
            'root' => kleeja_html_decode($row['root']),
            'link' => trim(kleeja_html_decode($row['link'])),
            'passive' => (int) $row['passive'] === 1,
            'ssl' => (int) $row['ssl'] === 1,
            'active' => (int) $row['active'] === 1,
        ];

        // the test waits less than an upload would, the page shows nothing while it runs
        $this->timeout = max(5, min(15, (int) $row['timeout'] ?: 15));
        $this->root = rtrim(trim($this->account['root']), '/');
    }

    /**
     * run the steps, a step that fails stops the ones that need it
     * @return array the steps, each one with its group, title, status, detail and hint
     */
    public function run(): array
    {
        @set_time_limit(180);

        try {
            if ($this->check_settings() && $this->check_server() && $this->check_login()) {
                $data = $this->check_data();

                if ($this->check_root() && $this->check_mkdir()) {
                    if ($data && $this->check_upload()) {
                        $this->check_link();
                        $this->check_delete();
                    }

                    $this->check_rmdir();
                }

                if (!isset($this->results['root']) || $this->results['root'][0] !== 'error') {
                    $this->check_folder();
                }
            }
        } finally {
            $this->clean();
        }

        return $this->steps();
    }

    /**
     * the steps as the template shows them, the first step of a group starts it
     * @return array
     */
    private function steps(): array
    {
        $steps = [];
        $group = '';

        foreach (self::STEPS as $key => [$step_group, $title]) {
            // the FTPS of PHP matters only for an account that uses it
            if ($key === 'php_tls' && !isset($this->results[$key])) {
                continue;
            }

            [$status, $detail, $hint] = $this->results[$key] ?? ['skipped', $this->word('KJ_FTP_TEST_SKIPPED'), ''];

            $steps[] = [
                'group' => $step_group !== $group ? $this->word('KJ_FTP_TEST_GROUP_' . strtoupper($step_group)) : '',
                'title' => $this->word($title),
                'status' => $status,
                'detail' => $detail,
                'hint' => $hint,
            ];

            $group = $step_group;
        }

        return $steps;
    }

    /**
     * the settings themselves, before anything is sent
     * @return bool can the test connect
     */
    private function check_settings(): bool
    {
        global $config;

        $go_on = true;

        if (!function_exists('ftp_connect')) {
            $this->add('extension', 'error', $this->word('KJ_FTP_NO_EXTENSION'));
            $go_on = false;
        } else {
            $this->add('extension', 'ok', $this->word('KJ_FTP_TEST_ENABLED'));
        }

        if ($go_on && $this->account['ssl']) {
            if (!function_exists('ftp_ssl_connect') || !extension_loaded('openssl')) {
                $this->add(
                    'php_tls',
                    'error',
                    $this->word('KJ_FTP_TEST_PHP_TLS_ERROR'),
                    $this->word('KJ_FTP_TEST_PHP_TLS_HINT'),
                );
                $go_on = false;
            } else {
                $this->add('php_tls', 'ok', $this->word('KJ_FTP_TEST_ENABLED'));
            }
        }

        // the public URL, the links of the files start with it
        $link = getKleejaFtpLink($this->account['name'], '');

        if ($this->account['link'] === '') {
            $this->add(
                'link_setting',
                'warning',
                sprintf($this->word('KJ_FTP_TEST_LINK_EMPTY'), $this->code(rtrim($link, '/'))),
                $this->word('KJ_FTP_TEST_LINK_EMPTY_HINT'),
            );
        } elseif (
            stripos((string) $config['siteurl'], 'https://') === 0 &&
            stripos($this->account['link'], 'http://') === 0
        ) {
            $this->add(
                'link_setting',
                'warning',
                $this->word('KJ_FTP_TEST_LINK_HTTP'),
                $this->word('KJ_FTP_TEST_LINK_HTTP_HINT'),
            );
        } else {
            $this->add('link_setting', 'ok', $this->code($this->account['link']));
        }

        $this->add(
            'active',
            $this->account['active'] ? 'ok' : 'info',
            $this->word($this->account['active'] ? 'KJ_FTP_TEST_ACTIVE_OK' : 'KJ_FTP_TEST_INACTIVE'),
        );

        return $go_on;
    }

    /**
     * the host, the port and the FTPS of the server, on a connection of the test itself,
     * which reads the greeting of the server and asks for TLS without signing in
     * @return bool can the test sign in
     */
    private function check_server(): bool
    {
        $host = $this->account['host'];
        $port = $this->account['port'];

        // the placeholder of a new account
        if ($host === '' || $host === 'example.com') {
            $this->add('dns', 'error', $this->word('KJ_FTP_TEST_HOST_EXAMPLE'), $this->word('KJ_FTP_TEST_DNS_HINT'));

            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->add('dns', 'ok', sprintf($this->word('KJ_FTP_TEST_DNS_IP'), $this->code($host)));
        } else {
            $addresses = array_merge(
                @gethostbynamel($host) ?: [],
                array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
            );

            if (!$addresses) {
                $this->add(
                    'dns',
                    'error',
                    sprintf($this->word('KJ_FTP_TEST_DNS_ERROR'), $this->code($host)),
                    $this->word('KJ_FTP_TEST_DNS_HINT'),
                );

                return false;
            }

            $this->add(
                'dns',
                'ok',
                sprintf($this->word('KJ_FTP_TEST_DNS_OK'), $this->code($host), $this->code(implode(', ', $addresses))),
            );
        }

        $target = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? "[{$host}]" : $host;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            "tcp://{$target}:{$port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]),
        );

        if (!$socket) {
            if (stripos($errstr, 'refused') !== false) {
                $this->add(
                    'port',
                    'error',
                    sprintf($this->word('KJ_FTP_TEST_PORT_REFUSED'), $port),
                    $this->word('KJ_FTP_TEST_PORT_REFUSED_HINT'),
                );
            } elseif (stripos($errstr, 'timed out') !== false || $errno === 110) {
                $this->add(
                    'port',
                    'error',
                    sprintf($this->word('KJ_FTP_TEST_PORT_TIMEOUT'), $port, $this->timeout),
                    $this->word('KJ_FTP_TEST_PORT_TIMEOUT_HINT'),
                );
            } else {
                $this->add(
                    'port',
                    'error',
                    sprintf($this->word('KJ_FTP_TEST_PORT_ERROR'), $port, $this->code($errstr ?: (string) $errno)),
                    $this->word('KJ_FTP_TEST_PORT_TIMEOUT_HINT'),
                );
            }

            return false;
        }

        [$code, $greeting] = $this->reply($socket);

        if ($code !== 220) {
            fclose($socket);

            if (stripos($greeting, 'SSH-') === 0) {
                $this->add(
                    'port',
                    'error',
                    sprintf($this->word('KJ_FTP_TEST_PORT_SSH'), $port),
                    $this->word('KJ_FTP_TEST_PORT_SSH_HINT'),
                );
            } elseif ($code >= 400) {
                $this->add(
                    'port',
                    'error',
                    sprintf($this->word('KJ_FTP_TEST_PORT_BUSY'), $port, $this->code($greeting)),
                    $this->word('KJ_FTP_TEST_PORT_BUSY_HINT'),
                );
            } else {
                $this->add(
                    'port',
                    'error',
                    sprintf($this->word('KJ_FTP_TEST_PORT_SILENT'), $port),
                    $this->word($port === 990 ? 'KJ_FTP_TEST_PORT_IMPLICIT_HINT' : 'KJ_FTP_TEST_PORT_SILENT_HINT'),
                );
            }

            return false;
        }

        // the first line of the greeting says which server it is, the others are its notes
        $this->add(
            'port',
            'ok',
            sprintf(
                $this->word('KJ_FTP_TEST_PORT_OK'),
                $port,
                $this->code(preg_replace('/^\d{3}[ -]/', '', strtok($greeting, "\n"))),
            ),
        );

        // FTPS: the server is asked to encrypt this connection, as PHP asks it when it signs in
        fwrite($socket, "AUTH TLS\r\n");
        [$code, $answer] = $this->reply($socket);
        $tls = '';

        if ($code === 234) {
            $method = defined('STREAM_CRYPTO_METHOD_TLS_ANY_CLIENT')
                ? STREAM_CRYPTO_METHOD_TLS_ANY_CLIENT
                : STREAM_CRYPTO_METHOD_TLS_CLIENT;
            $encrypted = $this->call(fn() => stream_socket_enable_crypto($socket, true, $method)) === true;

            if ($encrypted) {
                $tls = (string) (stream_get_meta_data($socket)['crypto']['protocol'] ?? 'TLS');
                @fwrite($socket, "QUIT\r\n");
            }
        } else {
            @fwrite($socket, "QUIT\r\n");
        }

        fclose($socket);

        if ($this->account['ssl']) {
            if ($code !== 234) {
                $this->add(
                    'tls',
                    'error',
                    sprintf($this->word('KJ_FTP_TEST_TLS_UNSUPPORTED'), $this->code($answer ?: '—')),
                    $this->word('KJ_FTP_TEST_TLS_UNSUPPORTED_HINT'),
                );

                return false;
            }

            if ($tls === '') {
                $this->add(
                    'tls',
                    'error',
                    sprintf($this->word('KJ_FTP_TEST_TLS_FAILED'), $this->code($this->last_error ?: '—')),
                    $this->word('KJ_FTP_TEST_TLS_FAILED_HINT'),
                );

                return false;
            }

            $this->add('tls', 'ok', sprintf($this->word('KJ_FTP_TEST_TLS_OK'), $this->code($tls)));
        } elseif ($tls !== '') {
            $this->add(
                'tls',
                'warning',
                $this->word('KJ_FTP_TEST_TLS_AVAILABLE'),
                $this->word('KJ_FTP_TEST_TLS_AVAILABLE_HINT'),
            );
        } else {
            $this->add('tls', 'warning', $this->word('KJ_FTP_TEST_TLS_OFF'));
        }

        return true;
    }

    /**
     * sign in as the uploader does
     * @return bool
     */
    private function check_login(): bool
    {
        $this->conn = $this->call(
            fn() => $this->account['ssl']
                ? ftp_ssl_connect($this->account['host'], $this->account['port'], $this->timeout)
                : ftp_connect($this->account['host'], $this->account['port'], $this->timeout),
        );

        if (!$this->conn) {
            $this->add(
                'login',
                'error',
                sprintf($this->word('KJ_FTP_TEST_CONNECT_FAILED'), $this->code($this->last_error ?: '—')),
                $this->word('KJ_FTP_TEST_PORT_TIMEOUT_HINT'),
            );

            return false;
        }

        if (!$this->call(fn() => ftp_login($this->conn, $this->account['username'], $this->account['password']))) {
            $answer = $this->last_error ?: '—';

            // the servers that accept only encrypted sign-ins say it in their own words
            if (!$this->account['ssl'] && preg_match('/\b(TLS|SSL)\b|encrypt|secur/i', $answer)) {
                $this->add(
                    'login',
                    'error',
                    sprintf($this->word('KJ_FTP_TEST_LOGIN_TLS'), $this->code($answer)),
                    $this->word('KJ_FTP_TEST_TLS_AVAILABLE_HINT'),
                );
            } elseif ($this->account['ssl'] && preg_match('/handshake|SSL_|certificate/i', $answer)) {
                $this->add(
                    'login',
                    'error',
                    sprintf($this->word('KJ_FTP_TEST_TLS_FAILED'), $this->code($answer)),
                    $this->word('KJ_FTP_TEST_TLS_FAILED_HINT'),
                );
            } else {
                $this->add(
                    'login',
                    'error',
                    sprintf($this->word('KJ_FTP_TEST_LOGIN_FAILED'), $this->code($answer)),
                    $this->word('KJ_FTP_TEST_LOGIN_FAILED_HINT'),
                );
            }

            return false;
        }

        @ftp_set_option($this->conn, FTP_TIMEOUT_SEC, $this->timeout);
        @ftp_pasv($this->conn, $this->account['passive']);

        $this->login_folder = (string) @ftp_pwd($this->conn);

        $this->add(
            'login',
            'ok',
            sprintf(
                $this->word('KJ_FTP_TEST_LOGIN_OK'),
                $this->code($this->account['username']),
                $this->code($this->login_folder ?: '/'),
            ),
        );

        return true;
    }

    /**
     * the second connection that carries the files and the lists of folders, it is the one that firewalls block
     * @return bool
     */
    private function check_data(): bool
    {
        if (is_array($this->call(fn() => ftp_rawlist($this->conn, '.')))) {
            $this->add(
                'data',
                'ok',
                $this->word($this->account['passive'] ? 'KJ_FTP_TEST_DATA_PASSIVE_OK' : 'KJ_FTP_TEST_DATA_ACTIVE_OK'),
            );

            return true;
        }

        $answer = $this->code($this->last_error ?: '—');

        if (!$this->account['passive']) {
            $this->add(
                'data',
                'error',
                sprintf($this->word('KJ_FTP_TEST_DATA_ACTIVE_FAILED'), $answer),
                $this->word('KJ_FTP_TEST_DATA_ACTIVE_HINT'),
            );

            return false;
        }

        // a server behind a router can give its private address for the passive mode,
        // the test tries again with the address of the host to tell it
        $pasv = implode(' ', (array) @ftp_raw($this->conn, 'PASV'));
        $nat = false;

        if (defined('FTP_USEPASVADDRESS') && @ftp_set_option($this->conn, FTP_USEPASVADDRESS, false)) {
            @ftp_pasv($this->conn, true);
            $nat = is_array($this->call(fn() => ftp_rawlist($this->conn, '.')));
            @ftp_set_option($this->conn, FTP_USEPASVADDRESS, true);
            @ftp_pasv($this->conn, true);
        }

        if ($nat && preg_match('/\((\d+),(\d+),(\d+),(\d+),\d+,\d+\)/', $pasv, $m)) {
            $this->add(
                'data',
                'error',
                sprintf($this->word('KJ_FTP_TEST_DATA_PASSIVE_FAILED'), $answer),
                sprintf($this->word('KJ_FTP_TEST_DATA_NAT_HINT'), $this->code("{$m[1]}.{$m[2]}.{$m[3]}.{$m[4]}")),
            );
        } else {
            $this->add(
                'data',
                'error',
                sprintf($this->word('KJ_FTP_TEST_DATA_PASSIVE_FAILED'), $answer),
                $this->word('KJ_FTP_TEST_DATA_PASSIVE_HINT'),
            );
        }

        return false;
    }

    /**
     * the root folder, Kleeja creates it when it is missing, as the test does
     * @return bool
     */
    private function check_root(): bool
    {
        if ($this->root === '') {
            $this->add(
                'root',
                'ok',
                sprintf($this->word('KJ_FTP_TEST_ROOT_EMPTY'), $this->code($this->login_folder ?: '/')),
            );

            return true;
        }

        if ($this->is_dir($this->root)) {
            $this->add('root', 'ok', sprintf($this->word('KJ_FTP_TEST_ROOT_OK'), $this->code($this->root)));

            return true;
        }

        if ($this->call(fn() => ftp_mkdir($this->conn, $this->root)) !== false) {
            $this->root_created = true;
            $this->add(
                'root',
                'warning',
                sprintf($this->word('KJ_FTP_TEST_ROOT_CREATED'), $this->code($this->root)),
                $this->word('KJ_FTP_TEST_ROOT_CREATED_HINT'),
            );

            return true;
        }

        // the folders that the account sees, to find the right path among them
        $names = [];

        foreach ((array) @ftp_nlist($this->conn, '.') as $name) {
            $name = basename((string) $name);

            if ($name !== '.' && $name !== '..' && $name !== '') {
                $names[] = $name;
            }
        }

        $this->add(
            'root',
            'error',
            sprintf($this->word('KJ_FTP_TEST_ROOT_MISSING'), $this->code($this->root)),
            $names
                ? sprintf(
                    $this->word('KJ_FTP_TEST_ROOT_MISSING_LIST'),
                    $this->code($this->login_folder ?: '/'),
                    implode(' ', array_map([$this, 'code'], array_slice($names, 0, 20))),
                )
                : $this->word('KJ_FTP_TEST_ROOT_MISSING_HINT'),
        );

        return false;
    }

    /**
     * @return bool
     */
    private function check_mkdir(): bool
    {
        $folder = 'kleeja-ftp-test-' . substr(md5(uniqid('', true)), 0, 8);

        if ($this->call(fn() => ftp_mkdir($this->conn, $this->path($folder))) === false) {
            $this->add(
                'mkdir',
                'error',
                sprintf(
                    $this->word('KJ_FTP_TEST_MKDIR_FAILED'),
                    $this->code($this->path($folder)),
                    $this->code($this->last_error ?: '—'),
                ),
                $this->word('KJ_FTP_TEST_MKDIR_HINT'),
            );

            return false;
        }

        $this->test_folder = $folder;
        $this->add('mkdir', 'ok', sprintf($this->word('KJ_FTP_TEST_MKDIR_OK'), $this->code($this->path($folder))));

        return true;
    }

    /**
     * upload the FTP logo of Kleeja, and check that it arrived whole
     * @return bool
     */
    private function check_upload(): bool
    {
        $local = __DIR__ . '/icon.png';
        $remote = $this->path($this->test_folder . '/' . self::TEST_FILE);

        if (!$this->call(fn() => ftp_put($this->conn, $remote, $local, FTP_BINARY))) {
            $this->add(
                'upload',
                'error',
                sprintf($this->word('KJ_FTP_TEST_UPLOAD_FAILED'), $this->code($this->last_error ?: '—')),
                $this->word('KJ_FTP_TEST_UPLOAD_HINT'),
            );

            return false;
        }

        $this->test_file = true;
        $size = (int) @ftp_size($this->conn, $remote);

        // -1 is a server that doesn't give the size of files
        if ($size > -1 && $size !== filesize($local)) {
            $this->add(
                'upload',
                'error',
                sprintf($this->word('KJ_FTP_TEST_UPLOAD_DAMAGED'), $size, filesize($local)),
                $this->word('KJ_FTP_TEST_UPLOAD_DAMAGED_HINT'),
            );

            return false;
        }

        $this->add(
            'upload',
            'ok',
            sprintf($this->word('KJ_FTP_TEST_UPLOAD_OK'), readable_size(filesize($local)), $this->code($remote)),
        );

        return true;
    }

    /**
     * open the logo at the link that Kleeja gives it, as the files have
     * @return void
     */
    private function check_link(): void
    {
        $url = getKleejaFtpLink($this->account['name'], $this->test_folder, self::TEST_FILE);
        $remote = $this->path($this->test_folder . '/' . self::TEST_FILE);
        $wrong_link_hint = sprintf($this->word('KJ_FTP_TEST_LINK_HINT'), $this->code($remote));

        $response = $this->http_get($url);

        if ($response === null) {
            $this->add('link', 'info', $this->word('KJ_FTP_TEST_LINK_NO_HTTP'));

            return;
        }

        [$status, $body, $error_kind, $error] = $response;

        if ($error_kind === 'tls') {
            $this->add(
                'link',
                'error',
                sprintf($this->word('KJ_FTP_TEST_LINK_CERT'), $this->code($url), $this->code($error)),
                $this->word('KJ_FTP_TEST_LINK_CERT_HINT'),
            );
        } elseif ($error_kind !== '') {
            // a link that this server can't open may still open for the visitors
            $this->add(
                'link',
                'warning',
                sprintf($this->word('KJ_FTP_TEST_LINK_UNREACHABLE'), $this->code($url), $this->code($error)),
                $this->word('KJ_FTP_TEST_LINK_UNREACHABLE_HINT'),
            );
        } elseif ($status === 200 && $body === file_get_contents(__DIR__ . '/icon.png')) {
            $this->add('link', 'ok', sprintf($this->word('KJ_FTP_TEST_LINK_OK'), $this->code($url)));
        } elseif ($status === 200) {
            $this->add(
                'link',
                'error',
                sprintf($this->word('KJ_FTP_TEST_LINK_WRONG_FILE'), $this->code($url)),
                $wrong_link_hint,
            );
        } elseif ($status === 401 || $status === 403) {
            $this->add(
                'link',
                'error',
                sprintf($this->word('KJ_FTP_TEST_LINK_STATUS'), $this->code($url), $this->code('HTTP ' . $status)),
                $this->word('KJ_FTP_TEST_LINK_FORBIDDEN_HINT'),
            );
        } else {
            $this->add(
                'link',
                'error',
                sprintf($this->word('KJ_FTP_TEST_LINK_STATUS'), $this->code($url), $this->code('HTTP ' . $status)),
                $wrong_link_hint,
            );
        }
    }

    /**
     * @return void
     */
    private function check_delete(): void
    {
        $remote = $this->path($this->test_folder . '/' . self::TEST_FILE);

        if (!$this->call(fn() => ftp_delete($this->conn, $remote))) {
            $this->add(
                'delete',
                'error',
                sprintf($this->word('KJ_FTP_TEST_DELETE_FAILED'), $this->code($this->last_error ?: '—')),
                sprintf($this->word('KJ_FTP_TEST_DELETE_HINT'), $this->code($remote)),
            );

            return;
        }

        $this->test_file = false;
        $this->add('delete', 'ok', $this->word('KJ_FTP_TEST_DELETE_OK'));
    }

    /**
     * @return void
     */
    private function check_rmdir(): void
    {
        // a folder with the file in it can't be deleted, the step that failed says why
        if ($this->test_file) {
            return;
        }

        if (!$this->call(fn() => ftp_rmdir($this->conn, $this->path($this->test_folder)))) {
            $this->add(
                'rmdir',
                'error',
                sprintf($this->word('KJ_FTP_TEST_RMDIR_FAILED'), $this->code($this->last_error ?: '—')),
                sprintf($this->word('KJ_FTP_TEST_RMDIR_HINT'), $this->code($this->path($this->test_folder))),
            );

            return;
        }

        $this->test_folder = '';
        $this->add('rmdir', 'ok', $this->word('KJ_FTP_TEST_RMDIR_OK'));
    }

    /**
     * the upload folder of Kleeja on the server, and the files that protect it
     * @return void
     */
    private function check_folder(): void
    {
        global $config;

        $folder = $this->path(trim((string) $config['foldername'], '/'));

        if (!$this->is_dir($folder)) {
            $this->add('folder', 'info', sprintf($this->word('KJ_FTP_TEST_FOLDER_NEW'), $this->code($folder)));

            return;
        }

        $missing = array_filter(
            ['.htaccess', 'index.html'],
            fn($file) => @ftp_size($this->conn, $folder . '/' . $file) < 0,
        );

        if ($missing) {
            $this->add(
                'folder',
                'warning',
                sprintf(
                    $this->word('KJ_FTP_TEST_FOLDER_UNPROTECTED'),
                    $this->code($folder),
                    implode(' ', array_map([$this, 'code'], $missing)),
                ),
                $this->word('KJ_FTP_TEST_FOLDER_UNPROTECTED_HINT'),
            );

            return;
        }

        $this->add('folder', 'ok', sprintf($this->word('KJ_FTP_TEST_FOLDER_OK'), $this->code($folder)));
    }

    /**
     * delete what the test made, even when a step failed, then close the connection
     * @return void
     */
    private function clean(): void
    {
        if ($this->conn) {
            if ($this->test_file) {
                @ftp_delete($this->conn, $this->path($this->test_folder . '/' . self::TEST_FILE));
            }

            if ($this->test_folder !== '') {
                @ftp_rmdir($this->conn, $this->path($this->test_folder));
            }

            // empty, or it isn't deleted
            if ($this->root_created) {
                @ftp_rmdir($this->conn, $this->root);
            }

            @ftp_close($this->conn);
        }

        $this->conn = false;
    }

    /**
     * a path in the root folder, as the uploader writes it
     * @param  string $path
     * @return string
     */
    private function path(string $path): string
    {
        return ($this->root !== '' ? $this->root . '/' : '') . trim($path, '/');
    }

    /**
     * @param  string $dir
     * @return bool
     */
    private function is_dir(string $dir): bool
    {
        if (!@ftp_chdir($this->conn, $dir)) {
            return false;
        }

        @ftp_chdir($this->conn, $this->login_folder !== '' ? $this->login_folder : '/');

        return true;
    }

    /**
     * a reply of the server on the connection of the test, its lines and its code
     * @param  resource $socket
     * @return array    [code, text], code 0 when the server said nothing or isn't an FTP server
     */
    private function reply($socket): array
    {
        // FTP servers greet at once, a silent port is another service
        stream_set_timeout($socket, min($this->timeout, 5));

        $text = '';

        while (($line = @fgets($socket, 1024)) !== false) {
            $text .= $line;

            // the last line of a reply, a multiline reply starts with 220- and ends with 220
            if (preg_match('/^(\d{3}) /', $line, $match)) {
                return [(int) $match[1], trim($text)];
            }

            if (($text === $line && !preg_match('/^\d{3}-/', $line)) || strlen($text) > 4096) {
                break;
            }
        }

        return [0, trim($text)];
    }

    /**
     * open a web address, with curl, or with the http wrapper of PHP
     * @param  string     $url
     * @return array|null [status, body, error kind: dns, connect, timeout, tls or other, error], null when it can't
     */
    private function http_get(string $url): ?array
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_USERAGENT => 'Kleeja FTP test',
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            ]);

            $body = curl_exec($curl);
            $errno = curl_errno($curl);
            $error = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            $kind = match (true) {
                $errno === 0 => '',
                $errno === 6 => 'dns',
                $errno === 7 => 'connect',
                $errno === 28 => 'timeout',
                in_array($errno, [35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91], true) => 'tls',
                default => 'other',
            };

            return [$status, is_string($body) ? $body : '', $kind, $error];
        }

        if (!ini_get('allow_url_fopen')) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'ignore_errors' => true,
                'follow_location' => 1,
                'max_redirects' => 3,
                'user_agent' => 'Kleeja FTP test',
            ],
        ]);

        $body = $this->call(fn() => file_get_contents($url, false, $context));
        $status = 0;

        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
                $status = (int) $match[1];
            }
        }

        if ($body === false) {
            $kind = match (true) {
                (bool) preg_match('/getaddrinfo|name or service|resolve/i', $this->last_error) => 'dns',
                (bool) preg_match('/SSL|certificate|crypto/i', $this->last_error) => 'tls',
                (bool) preg_match('/timed out/i', $this->last_error) => 'timeout',
                default => 'connect',
            };

            return [$status, '', $kind, $this->clean_error($this->last_error)];
        }

        return [$status, $body, '', ''];
    }

    /**
     * run an FTP function, and keep the warning that PHP gives when it fails, it has the answer of the server
     * @param  callable $function
     * @return mixed
     */
    private function call(callable $function): mixed
    {
        $this->last_error = '';
        set_error_handler(function ($no, $message) {
            $this->last_error = $this->clean_error($message);

            return true;
        });

        try {
            return $function();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * a warning of PHP without the names of its functions: "ftp_login(): Login incorrect" is "Login incorrect"
     * @param  string $message
     * @return string
     */
    private function clean_error(string $message): string
    {
        return trim(preg_replace('/^(?:[a-z_]+\(\)(?: failed)?:\s*)+/i', '', $message));
    }

    /**
     * @param string $step
     * @param string $status ok, info, warning or error
     * @param string $detail HTML
     * @param string $hint   HTML, how to fix it
     */
    private function add(string $step, string $status, string $detail, string $hint = ''): void
    {
        $this->results[$step] = [$status, $detail, $hint];
    }

    /**
     * a value in the text: a host, a path, a link or an answer of the server, left to right in Arabic too
     * @param  string $value
     * @return string
     */
    private function code(string $value): string
    {
        return '<code dir="ltr">' . htmlspecialchars($value, ENT_QUOTES) . '</code>';
    }

    /**
     * @param  string $name
     * @return string
     */
    private function word(string $name): string
    {
        return kj_ftp_word($name);
    }
}
