<?php
//
// kj_ftp, its page in the control panel: ?cp=kj_ftp_options
//

// not for directly open
if (!defined('IN_ADMIN')) {
    exit();
}

//current template
$stylee = 'admin_kjftp';
$styleePath = __DIR__;

// template variables
$action = basename(ADMIN_PATH) . '?cp=kj_ftp_options';
$H_FORM_KEYS = kleeja_add_form_key('adm_kj_ftp');
$kj_ftp_test_url = $action . '&amp;case=test';
// the test button of an account runs the test at once, with a key of its own
$kj_ftp_test_run_url = $kj_ftp_test_url . '&amp;' . kleeja_add_form_key_get('adm_kj_ftp_test') . '&amp;account=';

// the tabs: the accounts, and the connection test
$kj_ftp_run_test = (ip('submit') && p('type') == 'test') || (g('case') == 'test' && ig('formkey'));
$kj_ftp_tab_test = g('case') == 'test' || $kj_ftp_run_test;

// the accounts have the passwords of the FTP servers, only a founder sees them
if ((int) $userinfo['founder'] !== 1) {
    kleeja_admin_err($lang['HV_NOT_PRVLG_ACCESS'], basename(ADMIN_PATH));
}

// the table of the accounts. An update of the plugin creates it on any page and can't say why it failed,
// so it is tried again here, where the error of the database can be shown
if (!kj_ftp_create_table()) {
    kleeja_admin_err(
        sprintf($olang['KJ_FTP_TABLE_ERROR'], htmlspecialchars((string) $SQL->get_error()[1], ENT_QUOTES)),
    );
}

/**
 * the connection test of an account, its steps are shown on its tab
 */
$kj_ftp_test_steps = [];
$kj_ftp_test_title = $kj_ftp_test_summary = $kj_ftp_test_status = '';

if ($kj_ftp_run_test) {
    if (ip('submit') ? !kleeja_check_form_key('adm_kj_ftp', 3600) : !kleeja_check_form_key_get('adm_kj_ftp_test')) {
        kleeja_admin_err($lang['INVALID_FORM_KEY'], $kj_ftp_test_url);
    }

    $test_id = ip('submit') ? p('id', 'int') : g('account', 'int');

    $result = $SQL->build([
        'SELECT' => 'k.*',
        'FROM' => "`{$dbprefix}kj_ftp_info` k",
        'WHERE' => 'k.id = :id',
        'BIND' => ['id' => $test_id],
    ]);

    $test_account = $SQL->fetch_array($result);
    $SQL->freeresult($result);

    if (!$test_account) {
        kleeja_admin_err($olang['KJ_FTP_ACCOUNT_NOT_FOUND'], $kj_ftp_test_url);
    }

    include_once __DIR__ . '/ftp_test.php';

    $kj_ftp_test_steps = (new kj_ftp_test($test_account))->run();

    // the icon and the color of each status
    $test_styles = [
        'ok' => ['circle-check', 'success'],
        'info' => ['circle-info', 'info'],
        'warning' => ['triangle-exclamation', 'warning'],
        'error' => ['circle-xmark', 'danger'],
        'skipped' => ['circle-minus', 'secondary'],
    ];

    foreach ($kj_ftp_test_steps as $i => $step) {
        [$kj_ftp_test_steps[$i]['icon'], $kj_ftp_test_steps[$i]['color']] = $test_styles[$step['status']];
    }

    $test_count = array_count_values(array_column($kj_ftp_test_steps, 'status'));
    $test_errors = $test_count['error'] ?? 0;
    $test_warnings = $test_count['warning'] ?? 0;

    $kj_ftp_test_status = $test_errors ? 'danger' : ($test_warnings ? 'warning' : 'success');
    $kj_ftp_test_summary = $test_errors
        ? sprintf($olang['KJ_FTP_TEST_RESULT_ERRORS'], $test_errors)
        : ($test_warnings
            ? sprintf($olang['KJ_FTP_TEST_RESULT_WARNINGS'], $test_warnings)
            : $olang['KJ_FTP_TEST_RESULT_OK']);
    $kj_ftp_test_title = sprintf(
        $olang['KJ_FTP_TEST_RESULTS'],
        '<span dir="ltr">' .
            kleeja_html_display($test_account['host']) .
            ':' .
            ((int) $test_account['port'] ?: 21) .
            '</span>',
    );
} elseif (ip('submit')) {
    if (!kleeja_check_form_key('adm_kj_ftp', 3600)) {
        kleeja_admin_err($lang['INVALID_FORM_KEY'], $action);
    }

    /**
     * add an account, inactive, its details are entered after it
     */
    if (p('type') == 'new') {
        $insert_query = [
            'INSERT' => '`name`, `host`, `active`',
            'INTO' => "{$dbprefix}kj_ftp_info",
            'VALUES' => ':name, :host, 0',
            'BIND' => ['name' => uniqid(), 'host' => 'example.com'],
        ];

        if (!$SQL->build($insert_query)) {
            kleeja_admin_err($lang['ERROR_TRY_AGAIN'], $action);
        }

        kleeja_admin_info($olang['KJ_FTP_ACCOUNT_ADDED'], $action . '&amp;account=' . $SQL->insert_id());
    }

    /**
     * save an account
     */
    $id = p('id', 'int');
    $back = $action . '&amp;account=' . $id;

    $result = $SQL->build([
        'SELECT' => 'k.id',
        'FROM' => "`{$dbprefix}kj_ftp_info` k",
        'WHERE' => 'k.id = :id',
        'BIND' => ['id' => $id],
    ]);

    $exists = $SQL->fetch_array($result);
    $SQL->freeresult($result);

    if (!$exists) {
        kleeja_admin_err($olang['KJ_FTP_ACCOUNT_NOT_FOUND'], $action);
    }

    // the texts are kept encoded for HTML, as p() gives them, the plugin decodes them when it connects
    $data = [
        'name' => p('name'),
        'host' => p('host'),
        'username' => p('username'),
        'password' => p('password'),
        'port' => trim(p('port')),
        'root' => p('root'),
        'link' => p('link'),
        'passive' => ip('passive') ? 1 : 0,
        'active' => ip('active') ? 1 : 0,
        'ssl' => ip('ssl') ? 1 : 0,
    ];

    // the files keep this name in their folder, ftp://{name}:{folder}, so it can't have a colon or a slash
    if (!preg_match('/^[a-z0-9_-]{1,20}$/i', $data['name'])) {
        kleeja_admin_err($olang['KJ_FTP_ACCOUNT_NAME_INVALID'], $back);
    }

    // the address of the server, without the protocol or a path that was pasted with it
    $data['host'] = preg_replace('#^[a-z]+://#i', '', $data['host']);
    $data['host'] = trim(explode('/', $data['host'])[0]);

    if (
        !filter_var($data['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) &&
        !filter_var(trim($data['host'], '[]'), FILTER_VALIDATE_IP)
    ) {
        kleeja_admin_err($olang['KJ_FTP_ACCOUNT_HOST_INVALID'], $back);
    }

    $data['host'] = trim($data['host'], '[]');

    if ($data['port'] === '') {
        $data['port'] = 21;
    } elseif (!ctype_digit($data['port']) || (int) $data['port'] < 1 || (int) $data['port'] > 65535) {
        kleeja_admin_err($olang['KJ_FTP_ACCOUNT_PORT_INVALID'], $back);
    }

    $data['port'] = (int) $data['port'];

    // the public URL is the start of the links of the files, it has to be a web address
    $link = kleeja_html_decode($data['link']);

    if ($link !== '' && (!preg_match('#^https?://#i', $link) || !filter_var($link, FILTER_VALIDATE_URL))) {
        kleeja_admin_err($olang['KJ_FTP_ACCOUNT_LINK_INVALID'], $back);
    }

    // the saved password stays when the field is left empty
    if ($data['password'] === '') {
        unset($data['password']);
    }

    $result = $SQL->build([
        'SELECT' => 'k.id',
        'FROM' => "`{$dbprefix}kj_ftp_info` k",
        'WHERE' => 'k.id <> :id AND k.name = :name',
        'BIND' => ['id' => $id, 'name' => $data['name']],
    ]);

    $conflict = $SQL->fetch_array($result);
    $SQL->freeresult($result);

    if ($conflict) {
        kleeja_admin_err($olang['KJ_FTP_ACCOUNT_NAME_CONFLICT'], $back);
    }

    $update_query = [
        'UPDATE' => "{$dbprefix}kj_ftp_info",
        'SET' => implode(', ', array_map(fn($column) => "`{$column}` = :{$column}", array_keys($data))),
        'WHERE' => 'id = :id',
        'BIND' => $data + ['id' => $id],
    ];

    if (!$SQL->build($update_query)) {
        kleeja_admin_err($lang['ERROR_TRY_AGAIN'], $back);
    }

    // the active accounts, the uploader reads them from the cache
    kj_ftp_clear_cache();

    kleeja_admin_info($olang['KJ_FTP_ACCOUNT_UPDATED'], $back);
}

/**
 * the list of the accounts, the one that was just added or saved is open
 */
$open_account = g('account', 'int');
// the account chosen on the test tab: the one that was tested, or the one of the link
$test_selected = isset($test_account['id']) ? (int) $test_account['id'] : $open_account;
$ftp_accounts = [];
$kj_ftp_any_active = false;

$result = $SQL->build([
    'SELECT' => 'k.*',
    'FROM' => "`{$dbprefix}kj_ftp_info` k",
    'ORDER BY' => 'k.id ASC',
]);

while ($row = $SQL->fetch_array($result)) {
    $kj_ftp_any_active = $kj_ftp_any_active || (int) $row['active'] === 1;

    // the template prints them as they are, the password never leaves the server
    $ftp_accounts[] = [
        'id' => (int) $row['id'],
        'name' => kleeja_html_display($row['name']),
        'host' => kleeja_html_display($row['host']),
        'username' => kleeja_html_display($row['username']),
        'port' => (int) $row['port'] ?: 21,
        'root' => kleeja_html_display($row['root']),
        'link' => kleeja_html_display($row['link']),
        'active' => (int) $row['active'] === 1,
        'passive' => (int) $row['passive'] === 1,
        'ssl' => (int) $row['ssl'] === 1,
        'open' => (int) $row['id'] === $open_account,
        'selected' => (int) $row['id'] === $test_selected,
    ];
}

$SQL->freeresult($result);

$kj_ftp_no_extension = !kj_ftp_available();
$kj_ftp_no_active = !$kj_ftp_no_extension && !$kj_ftp_any_active;
