<?php
// Kleeja Plugin
// kj_ftp
// Version: 2.0
// Developer: Kleeja team

// Prevent illegal run
if (!defined('IN_PLUGINS_SYSTEM')) {
    exit();
}

// Plugin Basic Information
$kleeja_plugin['kj_ftp']['information'] = [
    // The casual name of this plugin, anything can a human being understands
    'plugin_title' => [
        'en' => 'Multi-FTP Storage',
        'ar' => 'التخزين عبر FTP',
    ],
    // Who wrote this plugin?
    'plugin_developer' => 'Kleeja.net',
    // This plugin version
    'plugin_version' => '2.0',
    // Explain what is this plugin, why should I use it?
    'plugin_description' => [
        'en' =>
            'Store uploaded files on one or more FTP servers instead of your web server, and spread new uploads across them.',
        'ar' => 'تخزّن الملفات المرفوعة على خادم FTP واحد أو أكثر بدلًا من خادم موقعك، وتوزّع الرفع الجديد بينها.',
    ],
    //settings page, if there is one (what after ? like cp=j_plugins)
    'settings_page' => 'cp=kj_ftp_options',
    // Min version of Kleeja that's requiered to run this plugin
    'plugin_kleeja_version_min' => '4.0.0',
    // Max version of Kleeja that support this plugin, use 0 for unlimited
    'plugin_kleeja_version_max' => '4.9',
    // Should this plugin run before others?, 0 is normal, and higher number has high priority
    'plugin_priority' => 0,
];

//after installation message, you can remove it, it's not requiered
$kleeja_plugin['kj_ftp']['first_run']['ar'] = "
أضافت هذه الإضافة صفحة «حسابات FTP» إلى لوحة التحكم. أضف حسابًا وأدخل بيانات خادمه، واختبره، ثم فعّله،
فتُحفظ الملفات المرفوعة بعدها على خوادم FTP المفعّلة بدلًا من هذا الخادم.
<br>
<a href='./index.php?cp=kj_ftp_options'>حسابات FTP</a> -
<a href='./index.php?cp=s_help#help-kj_ftp'>دليل الإضافة</a> -
<a href='https://github.com/kleeja/kj_ftp/issues' target='_blank' rel='noopener'>الإبلاغ عن مشكلة</a>
";

$kleeja_plugin['kj_ftp']['first_run']['en'] = "
This plugin added the FTP Accounts page to the control panel. Add an account, enter the details of its server,
test it and activate it, and new uploads are stored on the active FTP servers instead of this server.
<br>
<a href='./index.php?cp=kj_ftp_options'>FTP Accounts</a> -
<a href='./index.php?cp=s_help#help-kj_ftp'>Plugin guide</a> -
<a href='https://github.com/kleeja/kj_ftp/issues' target='_blank' rel='noopener'>Report a problem</a>
";

// Plugin Installation function
$kleeja_plugin['kj_ftp']['install'] = function ($plg_id) {
    global $SQL, $dbprefix, $config;

    // Kleeja hides the SQL errors while it installs a plugin, and it has listed the plugin before this runs.
    // Without its table the plugin can't work, so it is taken out of the list again, and the admin sees why
    if (!kj_ftp_create_table()) {
        // read before the next query, which clears it
        $error = (string) $SQL->get_error()[1];

        $SQL->build([
            'DELETE' => "{$dbprefix}plugins",
            'WHERE' => 'plg_id = :plg_id',
            'BIND' => ['plg_id' => (int) $plg_id],
        ]);

        kleeja_log('[kj_ftp] not installed, its table could not be created: ' . $error);

        $words = kj_ftp_translations();
        $words = $words[$config['language'] ?? 'en'] ?? $words['en'];
        $message = sprintf($words['KJ_FTP_INSTALL_ERROR'], htmlspecialchars($error, ENT_QUOTES));

        // the control panel, the store installs from there too. No redirect, the error is there to be read
        if (function_exists('kleeja_admin_err')) {
            kleeja_admin_err($message);
        }

        throw new RuntimeException(strip_tags($message));
    }

    foreach (kj_ftp_translations() as $lang_id => $words) {
        add_olang($words, $lang_id, $plg_id);
    }
};

// the words are written again, so a new version brings its own (2.0 rewrote all of them),
// with queries of its own: add_olang(), delete_olang() and delete_cache() run hooks, and the plugins are still loading.
// $kleeja_plugin isn't a global, Kleeja includes this file in a method, so the closure takes the information above with it
$kleeja_plugin['kj_ftp']['update'] = function ($old_version, $new_version) use ($kleeja_plugin) {
    global $SQL, $dbprefix;

    // the accounts of the old versions are kept as they are. This runs on any page, even a visitor's,
    // so a failure isn't shown here: the page of the plugin tries again and shows the error of the database
    kj_ftp_create_table();

    $result = $SQL->build([
        'SELECT' => 'plg_id',
        'FROM' => "{$dbprefix}plugins",
        'WHERE' => 'plg_name = :name',
        'BIND' => ['name' => 'kj_ftp'],
    ]);

    $row = $SQL->fetch_array($result);
    $plg_id = (int) ($row['plg_id'] ?? 0);
    $SQL->freeresult($result);

    //a plg_id of 0 would delete the words of the core and of every plugin
    if ($plg_id > 0) {
        $SQL->build([
            'DELETE' => "{$dbprefix}lang",
            'WHERE' => 'plg_id = :plg_id',
            'BIND' => ['plg_id' => $plg_id],
        ]);

        foreach (kj_ftp_translations() as $lang_id => $words) {
            foreach ($words as $word => $trans) {
                $SQL->build([
                    'INSERT' => 'word, trans, lang_id, plg_id',
                    'INTO' => "{$dbprefix}lang",
                    'VALUES' => ':word, :trans, :lang_id, :plg_id',
                    'BIND' => ['word' => $word, 'trans' => $trans, 'lang_id' => $lang_id, 'plg_id' => $plg_id],
                ]);
            }
        }
    }

    // the developer and the description that the old versions were installed with,
    // the list of plugins shows them while the plugin is disabled
    if (version_compare($old_version, '2.0', '<')) {
        $SQL->build([
            'UPDATE' => "{$dbprefix}plugins",
            'SET' => 'plg_author = :author, plg_dsc = :description',
            'WHERE' => 'plg_name = :name',
            'BIND' => [
                'author' => 'Kleeja.net',
                'description' => kleeja_html_encode(
                    $kleeja_plugin['kj_ftp']['information']['plugin_description']['en'],
                ),
                'name' => 'kj_ftp',
            ],
        ]);
    }

    // the cached words and accounts, and the compiled page of the control panel, which is a Bootstrap 5 one since 2.0.
    // They are PHP files, and OPcache would keep running the old ones for a while, or until PHP restarts
    // when it doesn't check the files. Before a file is deleted, OPcache finds a relative path only when the file exists
    $cached = array_merge(glob(PATH . 'cache/data_lang*.php') ?: [], glob(PATH . 'cache/tpl_admin_kjftp*.php') ?: [], [
        PATH . 'cache/klj_ftp__ftp_names.php',
    ]);

    foreach ($cached as $file) {
        if (file_exists($file)) {
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true);
            }

            @unlink($file);
        }
    }
};

// Plugin Uninstallation, function to be called at unistalling
// the accounts stay in their table: the files on the FTP servers keep their account in their folder,
// so installing the plugin again brings their links back
$kleeja_plugin['kj_ftp']['uninstall'] = function ($plg_id) {
    //a plg_id of 0 would delete the words of the core and of every plugin
    if ((int) $plg_id > 0) {
        foreach (array_keys(kj_ftp_translations()) as $lang_id) {
            delete_olang(null, $lang_id, (int) $plg_id);
        }
    }

    kj_ftp_clear_cache();
};

// Plugin functions
$kleeja_plugin['kj_ftp']['functions'] = [
    //add to admin menu
    'begin_admin_page' => function ($args) {
        $adm_extensions = $args['adm_extensions'];
        $ext_icons = $args['ext_icons'];

        $adm_extensions[] = 'kj_ftp_options';
        $ext_icons['kj_ftp_options'] = 'cloud-arrow-up';

        //the words that the database misses, in the language of the admin, or in English for a language without them
        $olang = (array) ($args['olang'] ?? []) + kj_ftp_words();

        return compact('adm_extensions', 'ext_icons', 'olang');
    },

    //add as admin page to reach when click on admin menu item we added.
    'not_exists_kj_ftp_options' => function () {
        $include_alternative = __DIR__ . '/kj_ftp_options.php';

        return compact('include_alternative');
    },

    // new uploads go to the FTP accounts while one of them is active. A site can stop that from config.php
    // with define('DISABLE_KLJ_FTP', true), the files that are on the FTP servers already keep working
    'begin_index_page' => function () {
        if (defined('DISABLE_KLJ_FTP') || !kj_ftp_available() || !kj_ftp_active_names()) {
            return;
        }

        $uploadingMethodClass = __DIR__ . '/ftpUploader.php';

        return compact('uploadingMethodClass');
    },

    // the uploader of Kleeja, which uploads while no FTP account is active, skips the files that PHP didn't receive,
    // like one bigger than upload_max_filesize, then asks to choose a file. Their errors are shown here instead,
    // once, if Kleeja shows them itself one day. The uploader of the plugin shows them itself
    'end_index_page' => function ($args) {
        global $lang;

        $uploader = $args['uploader'] ?? null;

        if (!ip('submitr') || !($uploader instanceof defaultUploader)) {
            return;
        }

        $info = $args['info'] ?? [];
        $shown = array_column($info, 'message_content');
        $errors = [];

        for ($i = 0; $i <= $uploader->getUploadFieldsLimit(); $i++) {
            $error = kj_ftp_upload_error($i);

            if ($error !== '' && !in_array($error, $shown, true) && !in_array($error, $errors, true)) {
                $errors[] = $error;
            }
        }

        if (!$errors) {
            return;
        }

        // the message that asks to choose a file, it was given only because nothing was uploaded
        $info = array_values(array_filter($info, fn($message) => $message['message_content'] !== $lang['CHOSE_F']));

        foreach ($errors as $error) {
            // as index.php fills it, t and i are for old styles
            $info[] = ['t' => 'index_err', 'i' => $error, 'message_content' => $error, 'message_type' => 'error'];
        }

        return compact('info');
    },

    // the direct links of files on an FTP server, Kleeja would make them on this site: {siteurl}ftp://{name}:{folder}/{file}
    'kleeja_get_link_func2' => function ($args) {
        global $config;

        $pid = $args['pid'];

        if (
            !(($pid == 'image' || $pid == 'thumb') && $config['id_form_img'] == 'direct') &&
            !($pid == 'file' && $config['id_form'] == 'direct')
        ) {
            return;
        }

        $path = kj_ftp_split_path(
            str_replace(array_keys($args['extra']), array_values($args['extra']), $args['links'][$pid]),
        );

        if ($path === null) {
            return;
        }

        $link = getKleejaFtpLink($path[0], $path[1]);

        if ($link === '') {
            return;
        }

        $return_link = htmlspecialchars($link, ENT_QUOTES);

        return compact('return_link');
    },

    // a download, an image or a thumbnail of do.php that is on an FTP server: the visitor is sent to its public link
    'down_go_page' => function ($args) {
        $path = kj_ftp_split_path((string) ($args['f'] ?? ''));

        if ($path === null) {
            return;
        }

        $folder = ig('thmb') || ig('thmbf') ? $path[1] . '/thumbs' : $path[1];
        $link = getKleejaFtpLink($path[0], $folder, (string) $args['n']);

        // an account that was deleted, Kleeja says that the file is not found
        if ($link === '') {
            return;
        }

        if (!defined('MAKE_DOPHP_301_HEADER')) {
            define('MAKE_DOPHP_301_HEADER', true);
        }

        $path_file = $link;

        return compact('path_file');
    },

    // a file on an FTP server is deleted there, with its thumbnail, as Kleeja checks for a thumbnail
    // only on this server. When the server can't be reached, the file stays there, and Kleeja deletes its record
    'kleeja_unlink_func' => function ($args) {
        $path = kj_ftp_split_path((string) $args['filePath']);

        if ($path === null) {
            return;
        }

        $return = true;
        $account = getKleejaFtpAccount($path[0]);
        $ftp = $account && kj_ftp_available() ? getKleejaFtpAccountInstance($account) : null;

        if ($ftp === null) {
            kleeja_log('[kj_ftp] could not delete ' . $args['filePath'] . ', its FTP account is not reachable');

            return compact('return');
        }

        $file = kleeja_html_decode($path[1]);
        $ftp->delete($file);

        if (!preg_match('#(^|/)thumbs/[^/]+$#', $file)) {
            $ftp->delete(dirname($file) . '/thumbs/' . basename($file));
        }

        return compact('return');
    },

    // the files that Kleeja deletes when nobody downloaded them for a while, Kleeja deletes only the ones on this server
    'while_klj_clean_old_files_func' => function ($args) {
        if ($args['continue'] && kj_ftp_split_path((string) $args['row']['folder']) !== null) {
            kleeja_unlink($args['row']['folder'] . '/' . $args['row']['name']);
        }
    },

    // the notes of the plugin on the dashboard
    'require_admin_page_end_start' => function ($args) {
        global $lang, $olang;

        $ADM_NOTIFICATIONS = $args['ADM_NOTIFICATIONS'] ?? [];
        $title = kj_ftp_word('R_KJ_FTP_OPTIONS');

        if (!kj_ftp_available()) {
            $ADM_NOTIFICATIONS['kljFtpNoExtension'] = [
                'id' => 'kljFtpNoExtension',
                'msg_type' => 'error',
                'title' => $lang['WARN'] . ' (' . $title . ')',
                'msg' => kj_ftp_word('KJ_FTP_NO_EXTENSION'),
            ];
        } elseif (defined('DISABLE_KLJ_FTP') || !kj_ftp_active_names()) {
            $ADM_NOTIFICATIONS['kljFtpNoActive'] = [
                'id' => 'kljFtpNoActive',
                'msg_type' => 'info',
                'title' => $lang['NOTE'] . ' (' . $title . ')',
                'msg' => kj_ftp_word('KJ_FTP_NO_ACTIVE_ACCOUNTS_NOTE'),
            ];
        } else {
            // the uploads go to the FTP servers, the upload folder of this server isn't used for them
            unset($ADM_NOTIFICATIONS['htaccess_u'], $ADM_NOTIFICATIONS['htaccess_t'], $ADM_NOTIFICATIONS['no_thumbs']);
        }

        return compact('ADM_NOTIFICATIONS');
    },

    //support video plugin
    'plugin:video_player:do_display' => function ($args) {
        $path = kj_ftp_split_path((string) ($args['file_info']['folder'] ?? ''));
        $link = $path === null ? '' : getKleejaFtpLink($path[0], $path[1], (string) $args['file_info']['name']);

        if ($link === '') {
            return;
        }

        $video_path = $link;

        return compact('video_path');
    },

    //support pdf plugin
    'plugin:pdf_viewer:do_display' => function ($args) {
        $path = kj_ftp_split_path((string) ($args['file_info']['folder'] ?? ''));
        $link = $path === null ? '' : getKleejaFtpLink($path[0], $path[1], (string) $args['file_info']['name']);

        if ($link === '') {
            return;
        }

        $pdf_path = $link;

        return compact('pdf_path');
    },

    // the guide of the plugin on the help page of the control panel, its words are in language/help_{code}.php
    'admin_help_guides' => function ($args) {
        $help_guides = $args['help_guides'];
        $words = kj_ftp_help_words();

        // the name of the plugin is the key, so Kleeja knows that the plugin has its guide, and shows its icon,
        // and the help button of the page of the plugin opens it
        $help_guides['kj_ftp'] = [
            'group' => 'plugins',
            'title' => $words['KJ_FTP_HELP_TITLE'],
            'intro' => $words['KJ_FTP_HELP_INTRO'],
            'page' => 'kj_ftp_options',
            'link' => './?cp=kj_ftp_options',
            // tips and warnings are shown beside the others on wide screens
            'sections' => [
                kj_ftp_help_section($words, 'features', 'FEATURE'),
                kj_ftp_help_section($words, 'steps', 'STEP'),
                kj_ftp_help_section($words, 'faq', 'FAQ'),
                kj_ftp_help_section($words, 'tips', 'TIP'),
                kj_ftp_help_section($words, 'warnings', 'WARNING'),
            ],
        ];

        return compact('help_guides');
    },
];

/**
 * special functions
 */

if (!function_exists('kj_ftp_create_table')) {
    /**
     * the table of the accounts, a file keeps the unique name of its account in its folder: ftp://{name}:{folder}.
     * The name is unique on its own column, SQLite doesn't read a UNIQUE KEY line, and `ssl` and `group` are quoted
     * because MySQL keeps them as words of its own.
     * The error page of Kleeja is turned off for it, the caller tells why it failed with $SQL->get_error()
     * @return bool false when the table could not be created
     */
    function kj_ftp_create_table()
    {
        global $SQL, $dbprefix;

        $show_errors = $SQL->show_errors;
        $SQL->show_errors = false;

        $created =
            $SQL->query(
                "CREATE TABLE IF NOT EXISTS `{$dbprefix}kj_ftp_info` (
                `id` int(11) unsigned NOT NULL auto_increment PRIMARY KEY,
                `name` varchar(20) collate utf8_bin DEFAULT NULL UNIQUE,
                `host` varchar(199) collate utf8_bin DEFAULT NULL,
                `username` varchar(199) collate utf8_bin DEFAULT NULL,
                `password` varchar(199) collate utf8_bin DEFAULT NULL,
                `port` int(5) DEFAULT '21',
                `root` varchar(199) collate utf8_bin DEFAULT '',
                `passive` tinyint(1) DEFAULT '1',
                `ssl` tinyint(1) DEFAULT '1',
                `timeout` int(4) DEFAULT '60',
                `link` varchar(199) collate utf8_bin DEFAULT NULL,
                `active` tinyint(1) NOT NULL DEFAULT '0',
                `group` int(4) DEFAULT '0'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;",
            ) !== false;

        $SQL->show_errors = $show_errors;

        return $created;
    }

    /**
     * can PHP connect to FTP servers, the ftp extension of PHP isn't always there
     * @return bool
     */
    function kj_ftp_available()
    {
        return function_exists('ftp_connect');
    }

    /**
     * all the accounts by their unique names, read once in a request
     * @return array
     */
    function kj_ftp_accounts()
    {
        global $SQL, $dbprefix;

        static $accounts = null;

        if ($accounts === null) {
            $accounts = [];
            $result = $SQL->build([
                'SELECT' => 'k.*',
                'FROM' => "`{$dbprefix}kj_ftp_info` k",
                'ORDER BY' => 'k.id ASC',
            ]);

            while ($result && ($row = $SQL->fetch_array($result))) {
                $accounts[$row['name']] = $row;
            }

            $SQL->freeresult($result);
        }

        return $accounts;
    }

    /**
     * the unique names of the active accounts, the ones that take new uploads.
     * They are cached, the page of the accounts deletes the cache when an account is saved
     * @return array
     */
    function kj_ftp_active_names()
    {
        global $cache;

        static $names = null;

        if ($names !== null) {
            return $names;
        }

        $names = $cache->get('klj_ftp::ftp_names');

        if (!is_array($names)) {
            $names = [];

            foreach (kj_ftp_accounts() as $name => $account) {
                if ((int) $account['active'] === 1) {
                    $names[] = $name;
                }
            }

            $cache->save('klj_ftp::ftp_names', $names);
        }

        return $names;
    }

    /**
     * delete the cached names of the active accounts, OPcache keeps a PHP file until it is told
     * @return void
     */
    function kj_ftp_clear_cache()
    {
        global $cache;

        $file = PATH . 'cache/klj_ftp__ftp_names.php';

        if (file_exists($file) && function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }

        $cache->clean('klj_ftp::ftp_names');
    }

    /**
     * the account and the path of a file on an FTP server, from its folder or a path that has it
     * like ftp://{name}:uploads/file.png, or ../ftp://{name}:uploads/file.png from the control panel
     * @param  string     $path
     * @return array|null [unique name, path on the server], null for a file of this server
     */
    function kj_ftp_split_path($path)
    {
        $start = strpos($path, 'ftp://');

        if ($start === false) {
            return null;
        }

        $parts = explode(':', substr($path, $start + 6), 2);

        if (count($parts) != 2 || $parts[0] === '') {
            return null;
        }

        return [$parts[0], trim($parts[1], '/')];
    }

    /**
     * why PHP didn't receive the file of an upload field, in the words of the uploader of Kleeja,
     * empty when the file was received, or the field was left empty
     * @param  int    $i the number of the field, file_{i}_ or file[i]
     * @return string
     */
    function kj_ftp_upload_error($i)
    {
        global $lang;

        $file = $_FILES['file_' . $i . '_'] ?? ($_FILES['file'][$i] ?? null);

        // a form with one field named file, the uploaders read it as file[0] once PHP received it
        if ($file === null && $i === 0 && isset($_FILES['file']['error']) && !is_array($_FILES['file']['error'])) {
            $file = $_FILES['file'];
        }

        if (!is_array($file) || !empty($file['tmp_name'])) {
            return '';
        }

        $filename = htmlspecialchars((string) ($file['name'] ?? '....'));

        switch ($file['error'] ?? -1) {
            case UPLOAD_ERR_OK:
            case UPLOAD_ERR_PARTIAL:
            case UPLOAD_ERR_NO_FILE:
                return '';

            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return sprintf(
                    $lang['SIZE_F_BIG'],
                    $filename,
                    'php.ini/upload_max_filesize: ' . ini_get('upload_max_filesize'),
                );

            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';

            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';

            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';

            default:
                return sprintf($lang['CANT_UPLAOD'], $filename);
        }
    }

    /**
     * a word of the plugin, from the database, or from kj_ftp_words() when the database misses it
     * @param  string $name
     * @return string
     */
    function kj_ftp_word($name)
    {
        global $olang;

        return $olang[$name] ?? (kj_ftp_words()[$name] ?? $name);
    }

    /**
     * the words of the plugin in the language of the page, and in English for a language that has no translation of them.
     * The database has the words of the version that was installed, these cover the ones that it misses
     * @return array
     */
    function kj_ftp_words()
    {
        global $config;
        static $words = [];

        $language = (string) ($config['language'] ?? 'en');

        if (!isset($words[$language])) {
            $translations = kj_ftp_translations();
            $words[$language] = ($translations[$language] ?? []) + $translations['en'];
        }

        return $words[$language];
    }

    /**
     * the words of the guide on the help page, in the language of the admin,
     * a word that the translation misses is shown in English
     * @return array
     */
    function kj_ftp_help_words()
    {
        global $config;

        $words = (array) require __DIR__ . '/language/help_en.php';
        $language = preg_replace('/[^a-z0-9_-]/i', '', (string) ($config['language'] ?? ''));
        $translation = __DIR__ . "/language/help_{$language}.php";

        if ($language !== '' && $language !== 'en' && file_exists($translation)) {
            // in its own line, Prettier drops the brackets of (require $translation) + $words
            $translated = require $translation;
            $words = (array) $translated + $words;
        }

        return $words;
    }

    /**
     * a section of the guide from its numbered words, like KJ_FTP_HELP_TIP_1, KJ_FTP_HELP_TIP_2 ..
     * its title, when it has its own, is KJ_FTP_HELP_TIP_TITLE
     * @param  array  $words
     * @param  string $type  how Kleeja shows it: features, steps, tips, warnings or faq
     * @param  string $name  the name of its words, KJ_FTP_HELP_{name}_1
     * @return array
     */
    function kj_ftp_help_section($words, $type, $name)
    {
        $prefix = 'KJ_FTP_HELP_' . $name;
        $section = ['type' => $type, 'title' => $words[$prefix . '_TITLE'] ?? '', 'items' => []];

        for ($n = 1; isset($words[$prefix . ($type == 'faq' ? '_Q_' : '_') . $n]); $n++) {
            $section['items'][] =
                $type == 'faq'
                    ? ['q' => $words[$prefix . '_Q_' . $n], 'a' => $words[$prefix . '_A_' . $n] ?? '']
                    : $words[$prefix . '_' . $n];
        }

        return $section;
    }

    /**
     * the words of the plugin, they are added to the language table when it is installed or updated,
     * each one fits the 255 letters of that table on MySQL
     * @return array
     */
    function kj_ftp_translations()
    {
        return [
            'en' => [
                'R_KJ_FTP_OPTIONS' => 'FTP Accounts',
                'KJ_FTP_ADD_NEW_ACCOUNT' => 'Add FTP account',
                'KJ_FTP_ADD_NEW_ACCOUNT_EXP' =>
                    'A new inactive account will be added to the list. Open it to enter the details of its server, then turn on <strong>Active</strong> to send uploads to it.',
                'KJ_FTP_NO_ACCOUNTS' => 'No FTP accounts yet',
                'KJ_FTP_NO_ACCOUNTS_EXP' =>
                    'Add an account for each FTP server that should store uploaded files. Until one is active, files are uploaded to this server.',
                'KJ_FTP_STATUS_ACTIVE' => 'Active',
                'KJ_FTP_STATUS_INACTIVE' => 'Inactive',
                'KJ_FTP_OPT_NAME' => 'Unique name',
                'KJ_FTP_OPT_NAME_HELP' =>
                    'Each uploaded file keeps this name to find its account. Change it only to give this account the name of a deleted one and bring its files back. Letters, digits, - and _, up to 20.',
                'KJ_FTP_OPT_HOST' => 'Host',
                'KJ_FTP_OPT_HOST_HELP' =>
                    'The address of the FTP server without ftp://, like ftp.example.com or 203.0.113.10.',
                'KJ_FTP_OPT_PORT' => 'Port',
                'KJ_FTP_OPT_USERNAME' => 'Username',
                'KJ_FTP_OPT_PASSWORD' => 'Password',
                'KJ_FTP_OPT_PASSWORD_HELP' => 'The saved password is never shown. Leave it empty to keep it.',
                'KJ_FTP_OPT_ROOT' => 'Root folder',
                'KJ_FTP_OPT_ROOT_HELP' =>
                    'The folder of the FTP server where Kleeja creates its upload folder, like /public_html/files. Leave it empty for the folder that the account opens in.',
                'KJ_FTP_OPT_LINK' => 'Public URL',
                'KJ_FTP_OPT_LINK_HELP' =>
                    'The web address of the root folder, like https://files.example.com. If it is empty, Kleeja builds it from the host and the root folder, over http.',
                'KJ_FTP_OPT_ACTIVE' => 'Active',
                'KJ_FTP_OPT_ACTIVE_HELP' =>
                    'New uploads go to the active accounts. An inactive account receives no new files, but its files can still be downloaded and deleted.',
                'KJ_FTP_OPT_PASSIVE' => 'Passive mode',
                'KJ_FTP_OPT_PASSIVE_HELP' =>
                    'The server opens the data connection, which works behind most firewalls and routers. Keep it on unless your host asks otherwise.',
                'KJ_FTP_OPT_SSL' => 'Secure connection (FTPS)',
                'KJ_FTP_OPT_SSL_HELP' =>
                    'Encrypts the password and the files with explicit TLS. Turn it off only if the server does not support FTPS.',
                'KJ_FTP_ACCOUNT_ADDED' =>
                    'The FTP account was added. Enter the details of its server, then activate it.',
                'KJ_FTP_ACCOUNT_UPDATED' => 'The FTP account was saved.',
                'KJ_FTP_ACCOUNT_NOT_FOUND' => 'This FTP account does not exist.',
                'KJ_FTP_ACCOUNT_NAME_CONFLICT' =>
                    'Another account already uses this unique name. Choose a different one.',
                'KJ_FTP_ACCOUNT_NAME_INVALID' =>
                    'The unique name can have only letters, digits, hyphens and underscores, up to 20 characters.',
                'KJ_FTP_ACCOUNT_HOST_INVALID' => 'Enter the host name or the IP address of the FTP server.',
                'KJ_FTP_ACCOUNT_PORT_INVALID' => 'The port must be a number from 1 to 65535.',
                'KJ_FTP_ACCOUNT_LINK_INVALID' =>
                    'The public URL must be a full web address that starts with https:// or http://.',
                'KJ_FTP_NO_ACTIVE_ACCOUNTS' =>
                    'No FTP account is active, so new files are uploaded to this server. Open an account, turn on <strong>Active</strong> and save it.',
                'KJ_FTP_NO_ACTIVE_ACCOUNTS_NOTE' =>
                    'No FTP account is active, so new files are uploaded to this server. To store them on an FTP server, <a href="./?cp=kj_ftp_options">activate an FTP account</a>.',
                'KJ_FTP_NO_EXTENSION' =>
                    'The FTP extension of PHP is not enabled on this server, so Kleeja can not reach the FTP accounts, and new files are uploaded to this server. Ask your host to enable it.',
                'KJ_FTP_CONNECT_ERROR' => 'The file storage server is not reachable right now. Please try again later.',
                'KJ_FTP_INSTALL_ERROR' =>
                    'The plugin was not installed, because Kleeja could not create the table of the FTP accounts. The database answered: %s',
                'KJ_FTP_TABLE_ERROR' =>
                    'Kleeja could not create the table of the FTP accounts. The database answered: %s',
                // the connection test
                'KJ_FTP_TAB_ACCOUNTS' => 'Accounts',
                'KJ_FTP_TAB_TEST' => 'Connection test',
                'KJ_FTP_TEST_INTRO' =>
                    'Checks the saved settings of an account: Kleeja connects and signs in, creates a folder, uploads its FTP logo, opens it at its public link, then deletes them. Save your changes before you test.',
                'KJ_FTP_TEST_ACCOUNT' => 'Account',
                'KJ_FTP_TEST_RUN' => 'Run the test',
                'KJ_FTP_TEST_BUTTON' => 'Test connection',
                'KJ_FTP_TEST_NEEDS_ACCOUNT' => 'Add an FTP account first, then test it here.',
                'KJ_FTP_TEST_RESULTS' => 'Results for %s',
                'KJ_FTP_TEST_RESULT_OK' => 'All checks passed. This account is ready to store files.',
                'KJ_FTP_TEST_RESULT_WARNINGS' => 'The account works, with %d warning(s) to review below.',
                'KJ_FTP_TEST_RESULT_ERRORS' =>
                    '%d problem(s) found. Start with the first one: the checks after it depend on it.',
                'KJ_FTP_TEST_SKIPPED' => 'Skipped, because an earlier check failed.',
                'KJ_FTP_TEST_GROUP_SETTINGS' => 'Settings',
                'KJ_FTP_TEST_GROUP_CONNECTION' => 'Connection',
                'KJ_FTP_TEST_GROUP_FILES' => 'Folders and files',
                'KJ_FTP_TEST_EXTENSION' => 'FTP extension of PHP',
                'KJ_FTP_TEST_PHP_TLS' => 'FTPS support of PHP',
                'KJ_FTP_TEST_ENABLED' => 'Enabled.',
                'KJ_FTP_TEST_PHP_TLS_ERROR' =>
                    'PHP on this server was built without OpenSSL, so it can not open FTPS connections.',
                'KJ_FTP_TEST_PHP_TLS_HINT' =>
                    'Ask your host to enable OpenSSL in PHP, or turn off <strong>Secure connection (FTPS)</strong> if the FTP server accepts plain FTP.',
                'KJ_FTP_TEST_LINK_EMPTY' => 'Not set, so the links are built from the host and the root folder: %s',
                'KJ_FTP_TEST_LINK_EMPTY_HINT' =>
                    'That address works only if the root folder is also the web folder of that host. Enter the web address of the root folder in <strong>Public URL</strong>.',
                'KJ_FTP_TEST_LINK_HTTP' => 'Your site uses https, but the public URL uses http.',
                'KJ_FTP_TEST_LINK_HTTP_HINT' =>
                    'Browsers block or warn about files loaded over http in a secure page. Use an https address.',
                'KJ_FTP_TEST_ACTIVE_OK' => 'New uploads can go to this account.',
                'KJ_FTP_TEST_INACTIVE' => 'Inactive, so it receives no uploads. Turn it on when the test passes.',
                'KJ_FTP_TEST_HOST_EXAMPLE' =>
                    'The account still has the example host. Enter the address of your FTP server.',
                'KJ_FTP_TEST_DNS_OK' => '%1$s points to %2$s.',
                'KJ_FTP_TEST_DNS_IP' => '%s is an IP address.',
                'KJ_FTP_TEST_DNS_ERROR' => 'The host %s does not exist.',
                'KJ_FTP_TEST_DNS_HINT' =>
                    'Check its spelling. Use the FTP address that your host gave you, often ftp.your-domain.com, or the IP address of the server.',
                'KJ_FTP_TEST_PORT_OK' => 'An FTP server answers on port %1$d: %2$s',
                'KJ_FTP_TEST_PORT_REFUSED' => 'Nothing answers on port %d of this host.',
                'KJ_FTP_TEST_PORT_REFUSED_HINT' =>
                    'The port is wrong, or the FTP server is stopped. FTP uses port 21, unless your host gave you another one.',
                'KJ_FTP_TEST_PORT_TIMEOUT' => 'No answer from port %1$d within %2$d seconds.',
                'KJ_FTP_TEST_PORT_TIMEOUT_HINT' =>
                    'A firewall blocks the connection, or the host or the port is wrong. Ask your host to allow FTP connections from the IP address of this server.',
                'KJ_FTP_TEST_PORT_ERROR' => 'Could not connect to port %1$d: %2$s',
                'KJ_FTP_TEST_PORT_SSH' => 'Port %d belongs to an SSH (SFTP) server, not an FTP server.',
                'KJ_FTP_TEST_PORT_SSH_HINT' =>
                    'Kleeja connects with FTP or FTPS, not SFTP. Use the FTP port of the server, usually 21.',
                'KJ_FTP_TEST_PORT_SILENT' => 'Port %d accepted the connection, but no FTP server answered on it.',
                'KJ_FTP_TEST_PORT_SILENT_HINT' =>
                    'Another service, like a website, uses this port. Use the FTP port of the server, usually 21.',
                'KJ_FTP_TEST_PORT_IMPLICIT_HINT' =>
                    'Port 990 is for implicit FTPS, which PHP does not support. Use port 21 with <strong>Secure connection (FTPS)</strong> on.',
                'KJ_FTP_TEST_PORT_BUSY' => 'The FTP server on port %1$d refused the connection: %2$s',
                'KJ_FTP_TEST_PORT_BUSY_HINT' =>
                    'It may have too many connections, or block this server. Try again later, or ask your host.',
                'KJ_FTP_TEST_TLS_OK' => 'The connection is encrypted with %s.',
                'KJ_FTP_TEST_TLS_UNSUPPORTED' => 'The server does not support FTPS. It answered: %s',
                'KJ_FTP_TEST_TLS_UNSUPPORTED_HINT' =>
                    'Turn off <strong>Secure connection (FTPS)</strong>, or ask your host to enable TLS on the FTP server.',
                'KJ_FTP_TEST_TLS_FAILED' => 'The server accepts FTPS, but the encryption failed: %s',
                'KJ_FTP_TEST_TLS_FAILED_HINT' =>
                    'The TLS setup of the FTP server is broken, like a missing certificate or an outdated protocol. Ask your host to fix it, or turn off <strong>Secure connection (FTPS)</strong> for now.',
                'KJ_FTP_TEST_TLS_AVAILABLE' =>
                    'Off, but the server supports FTPS: the password and the files travel unencrypted.',
                'KJ_FTP_TEST_TLS_AVAILABLE_HINT' => 'Turn on <strong>Secure connection (FTPS)</strong>.',
                'KJ_FTP_TEST_TLS_OFF' =>
                    'Off, and the server does not support FTPS: the password and the files travel unencrypted.',
                'KJ_FTP_TEST_LOGIN' => 'Sign in',
                'KJ_FTP_TEST_LOGIN_OK' => 'Signed in as %1$s. The account opens in %2$s.',
                'KJ_FTP_TEST_CONNECT_FAILED' => 'PHP could not open an FTP connection: %s',
                'KJ_FTP_TEST_LOGIN_TLS' => 'The server accepts only encrypted sign-ins. It answered: %s',
                'KJ_FTP_TEST_LOGIN_FAILED' => 'The server refused the username or the password. It answered: %s',
                'KJ_FTP_TEST_LOGIN_FAILED_HINT' =>
                    'Type the password again, its field stays empty after saving. Check the username too: some hosts use its full form, like user@your-domain.com.',
                'KJ_FTP_TEST_DATA' => 'Data connection',
                'KJ_FTP_TEST_DATA_PASSIVE_OK' => 'Files can be transferred in passive mode.',
                'KJ_FTP_TEST_DATA_ACTIVE_OK' => 'Files can be transferred in active mode.',
                'KJ_FTP_TEST_DATA_PASSIVE_FAILED' =>
                    'The sign-in worked, but files can not be transferred in passive mode: %s',
                'KJ_FTP_TEST_DATA_PASSIVE_HINT' =>
                    'The firewall of the FTP server blocks its passive ports. Ask your host to open them, or turn off <strong>Passive mode</strong> to try the active mode.',
                'KJ_FTP_TEST_DATA_NAT_HINT' =>
                    'The FTP server gives the private address %s for the passive mode, usually because it is behind a router. Ask your host to set its public address for the passive mode.',
                'KJ_FTP_TEST_DATA_ACTIVE_FAILED' =>
                    'The sign-in worked, but files can not be transferred in active mode: %s',
                'KJ_FTP_TEST_DATA_ACTIVE_HINT' =>
                    'In active mode, the FTP server connects back to this server, which firewalls and routers usually block. Turn on <strong>Passive mode</strong>.',
                'KJ_FTP_TEST_ROOT_EMPTY' => 'Not set, Kleeja works in the folder that the account opens in: %s',
                'KJ_FTP_TEST_ROOT_OK' => '%s exists.',
                'KJ_FTP_TEST_ROOT_CREATED' =>
                    '%s does not exist yet. Kleeja creates it at its first upload, as this test did.',
                'KJ_FTP_TEST_ROOT_CREATED_HINT' =>
                    'If you meant an existing folder, check the path: names are case sensitive. The <strong>Public URL</strong> has to show this folder.',
                'KJ_FTP_TEST_ROOT_MISSING' => '%s does not exist, and Kleeja can not create it.',
                'KJ_FTP_TEST_ROOT_MISSING_LIST' =>
                    'Check the path, names are case sensitive. The account opens in %1$s, which has: %2$s',
                'KJ_FTP_TEST_ROOT_MISSING_HINT' =>
                    'Check the path, names are case sensitive. Kleeja creates only the last folder of the path, the folders before it have to exist.',
                'KJ_FTP_TEST_MKDIR' => 'Create a folder',
                'KJ_FTP_TEST_MKDIR_OK' => 'Created %s.',
                'KJ_FTP_TEST_MKDIR_FAILED' => 'Could not create %1$s. The server answered: %2$s',
                'KJ_FTP_TEST_MKDIR_HINT' =>
                    'The account can not write in the root folder. Give the FTP user write permission on it, or choose a folder that the user owns.',
                'KJ_FTP_TEST_UPLOAD' => 'Upload a file',
                'KJ_FTP_TEST_UPLOAD_OK' => 'Uploaded the FTP logo of Kleeja (%1$s) to %2$s.',
                'KJ_FTP_TEST_UPLOAD_FAILED' => 'Could not upload the file. The server answered: %s',
                'KJ_FTP_TEST_UPLOAD_HINT' => 'The account can not write files there, or its disk space is full.',
                'KJ_FTP_TEST_UPLOAD_DAMAGED' => 'The file arrived damaged: %1$d of %2$d bytes.',
                'KJ_FTP_TEST_UPLOAD_DAMAGED_HINT' =>
                    'The disk of the server may be full, or the server changes files when they arrive.',
                'KJ_FTP_TEST_LINK' => 'Public link',
                'KJ_FTP_TEST_LINK_OK' => 'The file opens at its public link: %s',
                'KJ_FTP_TEST_LINK_STATUS' => 'Its public link %1$s answered %2$s.',
                'KJ_FTP_TEST_LINK_WRONG_FILE' =>
                    'Its public link %s opened, but showed something else: the public URL leads to another folder or site.',
                'KJ_FTP_TEST_LINK_HINT' =>
                    'The file was at %s on the FTP server. Set the <strong>Public URL</strong> to the web address of the root folder, or the <strong>Root folder</strong> to the folder that the public URL shows.',
                'KJ_FTP_TEST_LINK_FORBIDDEN_HINT' =>
                    'The web server refuses to show the file. Let it read the files of the root folder, and check its protection rules, like hotlink protection or a .htaccess file.',
                'KJ_FTP_TEST_LINK_CERT' => 'The https certificate of %1$s is not valid: %2$s',
                'KJ_FTP_TEST_LINK_CERT_HINT' =>
                    'Visitors would see a security warning instead of the file. Renew the certificate, or use the address that it was made for.',
                'KJ_FTP_TEST_LINK_UNREACHABLE' => 'This server could not open %1$s: %2$s',
                'KJ_FTP_TEST_LINK_UNREACHABLE_HINT' =>
                    'Check the address in <strong>Public URL</strong>. If it opens in your browser, only this server can not reach it, and you can ignore this warning.',
                'KJ_FTP_TEST_LINK_NO_HTTP' =>
                    'This server can not open web addresses (no curl, and allow_url_fopen is off), so the link was not checked.',
                'KJ_FTP_TEST_DELETE' => 'Delete a file',
                'KJ_FTP_TEST_DELETE_OK' => 'Deleted the test file.',
                'KJ_FTP_TEST_DELETE_FAILED' => 'Could not delete the test file. The server answered: %s',
                'KJ_FTP_TEST_DELETE_HINT' =>
                    'Files that are deleted in Kleeja would stay on the server. Give the FTP user permission to delete, then delete %s by hand.',
                'KJ_FTP_TEST_RMDIR' => 'Delete a folder',
                'KJ_FTP_TEST_RMDIR_OK' => 'Deleted the test folder.',
                'KJ_FTP_TEST_RMDIR_FAILED' => 'Could not delete the test folder. The server answered: %s',
                'KJ_FTP_TEST_RMDIR_HINT' => 'Give the FTP user permission to delete folders, then delete %s by hand.',
                'KJ_FTP_TEST_FOLDER' => 'Upload folder',
                'KJ_FTP_TEST_FOLDER_OK' => '%s exists, with the files that protect it.',
                'KJ_FTP_TEST_FOLDER_NEW' => '%s does not exist yet. Kleeja creates it at the first upload.',
                'KJ_FTP_TEST_FOLDER_UNPROTECTED' => '%1$s exists, but it has no %2$s.',
                'KJ_FTP_TEST_FOLDER_UNPROTECTED_HINT' =>
                    'index.html hides the list of files, and .htaccess stops scripts on Apache. Copy them from the upload folder of this site: Kleeja adds them only to the folders that it creates.',
            ],
            'ar' => [
                'R_KJ_FTP_OPTIONS' => 'حسابات FTP',
                'KJ_FTP_ADD_NEW_ACCOUNT' => 'إضافة حساب FTP',
                'KJ_FTP_ADD_NEW_ACCOUNT_EXP' =>
                    'سيُضاف حساب جديد غير مفعّل إلى القائمة. افتحه لإدخال بيانات خادمه، ثم فعّل <strong>مفعّل</strong> ليبدأ استقبال الملفات.',
                'KJ_FTP_NO_ACCOUNTS' => 'لا توجد حسابات FTP بعد',
                'KJ_FTP_NO_ACCOUNTS_EXP' =>
                    'أضف حسابًا لكل خادم FTP تريد تخزين الملفات المرفوعة عليه. وإلى أن يُفعَّل أحدها، تُرفع الملفات إلى هذا الخادم.',
                'KJ_FTP_STATUS_ACTIVE' => 'مفعّل',
                'KJ_FTP_STATUS_INACTIVE' => 'غير مفعّل',
                'KJ_FTP_OPT_NAME' => 'الاسم الثابت',
                'KJ_FTP_OPT_NAME_HELP' =>
                    'يحتفظ كل ملف مرفوع بهذا الاسم ليُعرف حسابه. لا تغيّره إلا لتعطي هذا الحساب اسم حساب محذوف فتستعيد ملفاته. حروف لاتينية وأرقام و - و _، حتى 20 حرفًا.',
                'KJ_FTP_OPT_HOST' => 'الخادم',
                'KJ_FTP_OPT_HOST_HELP' =>
                    'عنوان خادم FTP دون <span dir="ltr">ftp://</span>، مثل <span dir="ltr">ftp.example.com</span> أو <span dir="ltr">203.0.113.10</span>.',
                'KJ_FTP_OPT_PORT' => 'المنفذ',
                'KJ_FTP_OPT_USERNAME' => 'اسم المستخدم',
                'KJ_FTP_OPT_PASSWORD' => 'كلمة المرور',
                'KJ_FTP_OPT_PASSWORD_HELP' => 'لا تُعرض كلمة المرور المحفوظة أبدًا. اترك الحقل فارغًا للإبقاء عليها.',
                'KJ_FTP_OPT_ROOT' => 'المجلد الجذر',
                'KJ_FTP_OPT_ROOT_HELP' =>
                    'المجلد على خادم FTP الذي تنشئ كليجا فيه مجلد الرفع، مثل <span dir="ltr">/public_html/files</span>. اتركه فارغًا لاستخدام المجلد الذي يفتح عليه الحساب.',
                'KJ_FTP_OPT_LINK' => 'الرابط العام',
                'KJ_FTP_OPT_LINK_HELP' =>
                    'عنوان المجلد الجذر على الويب، مثل <span dir="ltr">https://files.example.com</span>. إذا تُرك فارغًا، تبنيه كليجا من الخادم والمجلد الجذر عبر http.',
                'KJ_FTP_OPT_ACTIVE' => 'مفعّل',
                'KJ_FTP_OPT_ACTIVE_HELP' =>
                    'تُرفع الملفات الجديدة إلى الحسابات المفعّلة. لا يستقبل الحساب غير المفعّل ملفات جديدة، لكن تبقى ملفاته متاحة للتحميل والحذف.',
                'KJ_FTP_OPT_PASSIVE' => 'الوضع الخامل (Passive)',
                'KJ_FTP_OPT_PASSIVE_HELP' =>
                    'يفتح الخادم اتصال نقل البيانات، فيعمل خلف معظم الجدران النارية وأجهزة التوجيه. أبقِه مفعّلًا ما لم تطلب جهة الاستضافة غير ذلك.',
                'KJ_FTP_OPT_SSL' => 'اتصال آمن (FTPS)',
                'KJ_FTP_OPT_SSL_HELP' =>
                    'يشفّر كلمة المرور والملفات عبر TLS الصريح. لا تعطّله إلا إذا كان الخادم لا يدعم FTPS.',
                'KJ_FTP_ACCOUNT_ADDED' => 'أُضيف حساب FTP. أدخل بيانات خادمه، ثم فعّله.',
                'KJ_FTP_ACCOUNT_UPDATED' => 'حُفظ حساب FTP.',
                'KJ_FTP_ACCOUNT_NOT_FOUND' => 'حساب FTP هذا غير موجود.',
                'KJ_FTP_ACCOUNT_NAME_CONFLICT' => 'يستخدم حساب آخر هذا الاسم الثابت. اختر اسمًا مختلفًا.',
                'KJ_FTP_ACCOUNT_NAME_INVALID' =>
                    'لا يقبل الاسم الثابت إلا الحروف اللاتينية والأرقام والشرطة (-) والشرطة السفلية (_)، حتى 20 حرفًا.',
                'KJ_FTP_ACCOUNT_HOST_INVALID' => 'أدخل اسم خادم FTP أو عنوان IP الخاص به.',
                'KJ_FTP_ACCOUNT_PORT_INVALID' => 'يجب أن يكون المنفذ رقمًا من 1 إلى 65535.',
                'KJ_FTP_ACCOUNT_LINK_INVALID' =>
                    'يجب أن يكون الرابط العام عنوان ويب كاملًا يبدأ بـ <span dir="ltr">https://</span> أو <span dir="ltr">http://</span>.',
                'KJ_FTP_NO_ACTIVE_ACCOUNTS' =>
                    'لا يوجد حساب FTP مفعّل، لذا تُرفع الملفات الجديدة إلى هذا الخادم. افتح حسابًا وفعّل <strong>مفعّل</strong> ثم احفظه.',
                'KJ_FTP_NO_ACTIVE_ACCOUNTS_NOTE' =>
                    'لا يوجد حساب FTP مفعّل، لذا تُرفع الملفات الجديدة إلى هذا الخادم. لتخزينها على خادم FTP، <a href="./?cp=kj_ftp_options">فعّل حساب FTP</a>.',
                'KJ_FTP_NO_EXTENSION' =>
                    'امتداد FTP في PHP غير مفعّل على هذا الخادم، لذا لا تستطيع كليجا الوصول إلى حسابات FTP، وتُرفع الملفات الجديدة إلى هذا الخادم. اطلب من جهة الاستضافة تفعيله.',
                'KJ_FTP_CONNECT_ERROR' => 'خادم تخزين الملفات غير متاح حاليًا. يُرجى المحاولة لاحقًا.',
                'KJ_FTP_INSTALL_ERROR' =>
                    'لم تُثبَّت الإضافة، لأن كليجا لم تتمكن من إنشاء جدول حسابات FTP. ردّ قاعدة البيانات: %s',
                'KJ_FTP_TABLE_ERROR' => 'لم تتمكن كليجا من إنشاء جدول حسابات FTP. ردّ قاعدة البيانات: %s',
                // the connection test
                'KJ_FTP_TAB_ACCOUNTS' => 'الحسابات',
                'KJ_FTP_TAB_TEST' => 'اختبار الاتصال',
                'KJ_FTP_TEST_INTRO' =>
                    'يفحص الإعدادات المحفوظة لحساب: تتصل كليجا وتسجّل الدخول، وتنشئ مجلدًا، وترفع شعار FTP الخاص بها، وتفتحه عبر رابطه العام، ثم تحذفهما. احفظ تغييراتك قبل الاختبار.',
                'KJ_FTP_TEST_ACCOUNT' => 'الحساب',
                'KJ_FTP_TEST_RUN' => 'تشغيل الاختبار',
                'KJ_FTP_TEST_BUTTON' => 'اختبار الاتصال',
                'KJ_FTP_TEST_NEEDS_ACCOUNT' => 'أضف حساب FTP أولًا، ثم اختبره هنا.',
                'KJ_FTP_TEST_RESULTS' => 'نتائج %s',
                'KJ_FTP_TEST_RESULT_OK' => 'نجحت جميع الفحوصات. هذا الحساب جاهز لتخزين الملفات.',
                'KJ_FTP_TEST_RESULT_WARNINGS' => 'الحساب يعمل، مع تحذيرات (%d) تستحق المراجعة أدناه.',
                'KJ_FTP_TEST_RESULT_ERRORS' => 'وُجدت مشكلات (%d). ابدأ بالأولى، فالفحوصات التي تليها تعتمد عليها.',
                'KJ_FTP_TEST_SKIPPED' => 'لم يُجرَ، لأن فحصًا سابقًا فشل.',
                'KJ_FTP_TEST_GROUP_SETTINGS' => 'الإعدادات',
                'KJ_FTP_TEST_GROUP_CONNECTION' => 'الاتصال',
                'KJ_FTP_TEST_GROUP_FILES' => 'المجلدات والملفات',
                'KJ_FTP_TEST_EXTENSION' => 'امتداد FTP في PHP',
                'KJ_FTP_TEST_PHP_TLS' => 'دعم FTPS في PHP',
                'KJ_FTP_TEST_ENABLED' => 'مفعّل.',
                'KJ_FTP_TEST_PHP_TLS_ERROR' => 'بُني PHP على هذا الخادم دون OpenSSL، لذا لا يستطيع فتح اتصالات FTPS.',
                'KJ_FTP_TEST_PHP_TLS_HINT' =>
                    'اطلب من جهة الاستضافة تفعيل OpenSSL في PHP، أو عطّل <strong>اتصال آمن (FTPS)</strong> إن كان خادم FTP يقبل FTP العادي.',
                'KJ_FTP_TEST_LINK_EMPTY' => 'غير محدد، لذا تُبنى الروابط من الخادم والمجلد الجذر: %s',
                'KJ_FTP_TEST_LINK_EMPTY_HINT' =>
                    'لا يصح هذا العنوان إلا إذا كان المجلد الجذر هو مجلد الويب لذلك الخادم. أدخل عنوان المجلد الجذر على الويب في <strong>الرابط العام</strong>.',
                'KJ_FTP_TEST_LINK_HTTP' => 'يستخدم موقعك https، لكن الرابط العام يستخدم http.',
                'KJ_FTP_TEST_LINK_HTTP_HINT' =>
                    'تحجب المتصفحات الملفات المحمّلة عبر http في صفحة آمنة أو تحذّر منها. استخدم عنوانًا يبدأ بـ https.',
                'KJ_FTP_TEST_ACTIVE_OK' => 'يمكن أن تُرفع الملفات الجديدة إلى هذا الحساب.',
                'KJ_FTP_TEST_INACTIVE' => 'غير مفعّل، لذا لا يستقبل أي ملفات مرفوعة. فعّله بعد نجاح الاختبار.',
                'KJ_FTP_TEST_HOST_EXAMPLE' => 'ما زال الحساب يحمل الخادم المثال. أدخل عنوان خادم FTP الخاص بك.',
                'KJ_FTP_TEST_DNS_OK' => 'يشير %1$s إلى %2$s.',
                'KJ_FTP_TEST_DNS_IP' => '%s عنوان IP.',
                'KJ_FTP_TEST_DNS_ERROR' => 'الخادم %s غير موجود.',
                'KJ_FTP_TEST_DNS_HINT' =>
                    'تحقّق من كتابته. استخدم عنوان FTP الذي أعطتك إياه جهة الاستضافة، وغالبًا ما يكون <span dir="ltr">ftp.your-domain.com</span>، أو عنوان IP الخاص بالخادم.',
                'KJ_FTP_TEST_PORT_OK' => 'يردّ خادم FTP على المنفذ %1$d: %2$s',
                'KJ_FTP_TEST_PORT_REFUSED' => 'لا شيء يردّ على المنفذ %d في هذا الخادم.',
                'KJ_FTP_TEST_PORT_REFUSED_HINT' =>
                    'المنفذ خاطئ، أو خادم FTP متوقف. يستخدم FTP المنفذ 21 ما لم تعطك جهة الاستضافة منفذًا آخر.',
                'KJ_FTP_TEST_PORT_TIMEOUT' => 'لم يصل ردّ من المنفذ %1$d خلال %2$d ثانية.',
                'KJ_FTP_TEST_PORT_TIMEOUT_HINT' =>
                    'جدار ناري يحجب الاتصال، أو أن الخادم أو المنفذ خاطئ. اطلب من جهة الاستضافة السماح باتصالات FTP من عنوان IP لهذا الخادم.',
                'KJ_FTP_TEST_PORT_ERROR' => 'تعذّر الاتصال بالمنفذ %1$d: %2$s',
                'KJ_FTP_TEST_PORT_SSH' => 'المنفذ %d لخادم SSH (أي SFTP)، وليس لخادم FTP.',
                'KJ_FTP_TEST_PORT_SSH_HINT' =>
                    'تتصل كليجا عبر FTP أو FTPS، لا عبر SFTP. استخدم منفذ FTP للخادم، وهو غالبًا 21.',
                'KJ_FTP_TEST_PORT_SILENT' => 'قَبِل المنفذ %d الاتصال، لكن لم يردّ عليه أي خادم FTP.',
                'KJ_FTP_TEST_PORT_SILENT_HINT' =>
                    'تستخدم هذا المنفذَ خدمةٌ أخرى، كموقع ويب. استخدم منفذ FTP للخادم، وهو غالبًا 21.',
                'KJ_FTP_TEST_PORT_IMPLICIT_HINT' =>
                    'المنفذ 990 لـ FTPS الضمني الذي لا يدعمه PHP. استخدم المنفذ 21 مع تفعيل <strong>اتصال آمن (FTPS)</strong>.',
                'KJ_FTP_TEST_PORT_BUSY' => 'رفض خادم FTP على المنفذ %1$d الاتصال: %2$s',
                'KJ_FTP_TEST_PORT_BUSY_HINT' =>
                    'قد تكون اتصالاته كثيرة، أو أنه يحجب هذا الخادم. أعد المحاولة لاحقًا، أو راجع جهة الاستضافة.',
                'KJ_FTP_TEST_TLS_OK' => 'الاتصال مشفّر عبر %s.',
                'KJ_FTP_TEST_TLS_UNSUPPORTED' => 'لا يدعم الخادم FTPS. وكان ردّه: %s',
                'KJ_FTP_TEST_TLS_UNSUPPORTED_HINT' =>
                    'عطّل <strong>اتصال آمن (FTPS)</strong>، أو اطلب من جهة الاستضافة تفعيل TLS على خادم FTP.',
                'KJ_FTP_TEST_TLS_FAILED' => 'يقبل الخادم FTPS، لكن التشفير فشل: %s',
                'KJ_FTP_TEST_TLS_FAILED_HINT' =>
                    'إعداد TLS في خادم FTP معطوب، كشهادة مفقودة أو بروتوكول قديم. اطلب من جهة الاستضافة إصلاحه، أو عطّل <strong>اتصال آمن (FTPS)</strong> مؤقتًا.',
                'KJ_FTP_TEST_TLS_AVAILABLE' => 'معطّل، مع أن الخادم يدعم FTPS: تنتقل كلمة المرور والملفات دون تشفير.',
                'KJ_FTP_TEST_TLS_AVAILABLE_HINT' => 'فعّل <strong>اتصال آمن (FTPS)</strong>.',
                'KJ_FTP_TEST_TLS_OFF' => 'معطّل، والخادم لا يدعم FTPS: تنتقل كلمة المرور والملفات دون تشفير.',
                'KJ_FTP_TEST_LOGIN' => 'تسجيل الدخول',
                'KJ_FTP_TEST_LOGIN_OK' => 'سُجّل الدخول باسم %1$s. يفتح الحساب على %2$s.',
                'KJ_FTP_TEST_CONNECT_FAILED' => 'تعذّر على PHP فتح اتصال FTP: %s',
                'KJ_FTP_TEST_LOGIN_TLS' => 'لا يقبل الخادم إلا تسجيل الدخول المشفّر. وكان ردّه: %s',
                'KJ_FTP_TEST_LOGIN_FAILED' => 'رفض الخادم اسم المستخدم أو كلمة المرور. وكان ردّه: %s',
                'KJ_FTP_TEST_LOGIN_FAILED_HINT' =>
                    'اكتب كلمة المرور من جديد، فحقلها يبقى فارغًا بعد الحفظ. وتحقّق من اسم المستخدم أيضًا، فبعض الاستضافات تستخدم صيغته الكاملة، مثل <span dir="ltr">user@your-domain.com</span>.',
                'KJ_FTP_TEST_DATA' => 'اتصال البيانات',
                'KJ_FTP_TEST_DATA_PASSIVE_OK' => 'يمكن نقل الملفات في الوضع الخامل.',
                'KJ_FTP_TEST_DATA_ACTIVE_OK' => 'يمكن نقل الملفات في الوضع النشط.',
                'KJ_FTP_TEST_DATA_PASSIVE_FAILED' => 'نجح تسجيل الدخول، لكن تعذّر نقل الملفات في الوضع الخامل: %s',
                'KJ_FTP_TEST_DATA_PASSIVE_HINT' =>
                    'يحجب الجدار الناري لخادم FTP منافذه الخاملة. اطلب من جهة الاستضافة فتحها، أو عطّل <strong>الوضع الخامل (Passive)</strong> لتجربة الوضع النشط.',
                'KJ_FTP_TEST_DATA_NAT_HINT' =>
                    'يعطي خادم FTP العنوان الخاص %s للوضع الخامل، وغالبًا لأنه خلف جهاز توجيه. اطلب من جهة الاستضافة ضبط عنوانه العام للوضع الخامل.',
                'KJ_FTP_TEST_DATA_ACTIVE_FAILED' => 'نجح تسجيل الدخول، لكن تعذّر نقل الملفات في الوضع النشط: %s',
                'KJ_FTP_TEST_DATA_ACTIVE_HINT' =>
                    'في الوضع النشط يتصل خادم FTP عائدًا بهذا الخادم، وهو ما تحجبه الجدران النارية وأجهزة التوجيه عادةً. فعّل <strong>الوضع الخامل (Passive)</strong>.',
                'KJ_FTP_TEST_ROOT_EMPTY' => 'غير محدد، تعمل كليجا في المجلد الذي يفتح عليه الحساب: %s',
                'KJ_FTP_TEST_ROOT_OK' => '%s موجود.',
                'KJ_FTP_TEST_ROOT_CREATED' => '%s غير موجود بعد. تنشئه كليجا عند أول رفع، كما فعل هذا الاختبار.',
                'KJ_FTP_TEST_ROOT_CREATED_HINT' =>
                    'إن كنت تقصد مجلدًا موجودًا، فتحقّق من المسار، فالأسماء تفرّق بين الحروف الكبيرة والصغيرة. ويجب أن يعرض <strong>الرابط العام</strong> هذا المجلد.',
                'KJ_FTP_TEST_ROOT_MISSING' => '%s غير موجود، ولا تستطيع كليجا إنشاءه.',
                'KJ_FTP_TEST_ROOT_MISSING_LIST' =>
                    'تحقّق من المسار، فالأسماء تفرّق بين الحروف الكبيرة والصغيرة. يفتح الحساب على %1$s، وفيه: %2$s',
                'KJ_FTP_TEST_ROOT_MISSING_HINT' =>
                    'تحقّق من المسار، فالأسماء تفرّق بين الحروف الكبيرة والصغيرة. لا تنشئ كليجا إلا آخر مجلد في المسار، ويجب أن تكون المجلدات التي قبله موجودة.',
                'KJ_FTP_TEST_MKDIR' => 'إنشاء مجلد',
                'KJ_FTP_TEST_MKDIR_OK' => 'أُنشئ %s.',
                'KJ_FTP_TEST_MKDIR_FAILED' => 'تعذّر إنشاء %1$s. وكان ردّ الخادم: %2$s',
                'KJ_FTP_TEST_MKDIR_HINT' =>
                    'لا يستطيع الحساب الكتابة في المجلد الجذر. امنح مستخدم FTP صلاحية الكتابة فيه، أو اختر مجلدًا يملكه.',
                'KJ_FTP_TEST_UPLOAD' => 'رفع ملف',
                'KJ_FTP_TEST_UPLOAD_OK' => 'رُفع شعار FTP الخاص بكليجا (%1$s) إلى %2$s.',
                'KJ_FTP_TEST_UPLOAD_FAILED' => 'تعذّر رفع الملف. وكان ردّ الخادم: %s',
                'KJ_FTP_TEST_UPLOAD_HINT' => 'لا يستطيع الحساب كتابة الملفات هناك، أو امتلأت المساحة المخصّصة له.',
                'KJ_FTP_TEST_UPLOAD_DAMAGED' => 'وصل الملف تالفًا: %1$d من %2$d بايت.',
                'KJ_FTP_TEST_UPLOAD_DAMAGED_HINT' =>
                    'قد يكون قرص الخادم ممتلئًا، أو أن الخادم يغيّر الملفات عند وصولها.',
                'KJ_FTP_TEST_LINK' => 'رابط الملف العام',
                'KJ_FTP_TEST_LINK_OK' => 'يُفتح الملف عبر رابطه العام: %s',
                'KJ_FTP_TEST_LINK_STATUS' => 'ردّ رابطه العام %1$s بـ %2$s.',
                'KJ_FTP_TEST_LINK_WRONG_FILE' =>
                    'فُتح رابطه العام %s لكنه عرض شيئًا آخر: الرابط العام يقود إلى مجلد أو موقع آخر.',
                'KJ_FTP_TEST_LINK_HINT' =>
                    'كان الملف في %s على خادم FTP. اجعل <strong>الرابط العام</strong> عنوان المجلد الجذر على الويب، أو اجعل <strong>المجلد الجذر</strong> المجلد الذي يعرضه الرابط العام.',
                'KJ_FTP_TEST_LINK_FORBIDDEN_HINT' =>
                    'يرفض خادم الويب عرض الملف. اسمح له بقراءة ملفات المجلد الجذر، وراجع قواعد الحماية فيه، مثل منع الربط الخارجي أو ملف <span dir="ltr">.htaccess</span>.',
                'KJ_FTP_TEST_LINK_CERT' => 'شهادة https للعنوان %1$s غير صالحة: %2$s',
                'KJ_FTP_TEST_LINK_CERT_HINT' =>
                    'سيرى الزوار تحذيرًا أمنيًا بدلًا من الملف. جدّد الشهادة، أو استخدم العنوان الذي صدرت له.',
                'KJ_FTP_TEST_LINK_UNREACHABLE' => 'تعذّر على هذا الخادم فتح %1$s: %2$s',
                'KJ_FTP_TEST_LINK_UNREACHABLE_HINT' =>
                    'تحقّق من العنوان في <strong>الرابط العام</strong>. فإن فُتح في متصفحك، فهذا الخادم وحده لا يصل إليه، ويمكنك تجاهل هذا التحذير.',
                'KJ_FTP_TEST_LINK_NO_HTTP' =>
                    'لا يستطيع هذا الخادم فتح عناوين الويب (لا يوجد curl، والخيار allow_url_fopen معطّل)، لذا لم يُفحص الرابط.',
                'KJ_FTP_TEST_DELETE' => 'حذف ملف',
                'KJ_FTP_TEST_DELETE_OK' => 'حُذف ملف الاختبار.',
                'KJ_FTP_TEST_DELETE_FAILED' => 'تعذّر حذف ملف الاختبار. وكان ردّ الخادم: %s',
                'KJ_FTP_TEST_DELETE_HINT' =>
                    'ستبقى الملفات التي تُحذف في كليجا على الخادم. امنح مستخدم FTP صلاحية الحذف، ثم احذف %s يدويًا.',
                'KJ_FTP_TEST_RMDIR' => 'حذف مجلد',
                'KJ_FTP_TEST_RMDIR_OK' => 'حُذف مجلد الاختبار.',
                'KJ_FTP_TEST_RMDIR_FAILED' => 'تعذّر حذف مجلد الاختبار. وكان ردّ الخادم: %s',
                'KJ_FTP_TEST_RMDIR_HINT' => 'امنح مستخدم FTP صلاحية حذف المجلدات، ثم احذف %s يدويًا.',
                'KJ_FTP_TEST_FOLDER' => 'مجلد الرفع',
                'KJ_FTP_TEST_FOLDER_OK' => '%s موجود، ومعه الملفات التي تحميه.',
                'KJ_FTP_TEST_FOLDER_NEW' => '%s غير موجود بعد. تنشئه كليجا عند أول رفع.',
                'KJ_FTP_TEST_FOLDER_UNPROTECTED' => '%1$s موجود، لكن ينقصه %2$s.',
                'KJ_FTP_TEST_FOLDER_UNPROTECTED_HINT' =>
                    'يخفي index.html قائمة الملفات، ويمنع <span dir="ltr">.htaccess</span> تشغيل السكربتات على Apache. انسخهما من مجلد الرفع في هذا الموقع، فكليجا لا تضيفهما إلا إلى المجلدات التي تنشئها.',
            ],
        ];
    }
}

if (!function_exists('getKleejaFtpInstance')) {
    /**
     * the connection that new uploads go to, to one of the active accounts chosen at random,
     * it is made once in a request
     * @return kleeja_ftp|null null when no account is active, or its server can't be reached
     */
    function getKleejaFtpInstance()
    {
        static $kljFtp = null;
        static $tried = false;

        if ($tried) {
            return $kljFtp;
        }

        $tried = true;
        $names = kj_ftp_active_names();

        if (!$names) {
            return null;
        }

        $account = getKleejaFtpAccount($names[array_rand($names)]);

        if ($account) {
            $kljFtp = getKleejaFtpAccountInstance($account);
        }

        return $kljFtp;
    }
}

if (!function_exists('getKleejaFtpAccountInstance')) {
    /**
     * the connection to the server of an account, it is made once in a request, and closed at its end
     * @param  array           $ftp_account a row of the accounts table
     * @return kleeja_ftp|null null when the server can't be reached
     */
    function getKleejaFtpAccountInstance($ftp_account)
    {
        static $connections = [];

        if (!class_exists('kleeja_ftp')) {
            require_once __DIR__ . '/ftp.php';
        }

        $name = $ftp_account['name'];

        if (!array_key_exists($name, $connections)) {
            $kljFtp = new kleeja_ftp();

            // the texts are kept encoded for HTML in the table
            $connected = $kljFtp->open(
                kleeja_html_decode($ftp_account['host']),
                kleeja_html_decode($ftp_account['username']),
                kleeja_html_decode($ftp_account['password']),
                (int) $ftp_account['port'],
                kleeja_html_decode($ftp_account['root']),
                (int) $ftp_account['passive'] == 1,
                (int) $ftp_account['ssl'] == 1,
                (int) $ftp_account['timeout'],
            );

            if ($connected) {
                $kljFtp->setUniqueName($name);

                register_shutdown_function(function () use ($kljFtp) {
                    $kljFtp->close();
                });
            } else {
                kleeja_log('[kj_ftp] can not connect or sign in to the FTP account ' . $name);
            }

            // an account that failed isn't tried again in the same request
            $connections[$name] = $connected ? $kljFtp : null;
        }

        return $connections[$name];
    }
}

if (!function_exists('getKleejaFtpAccount')) {
    /**
     * an account by its unique name
     * @param  string      $uniqueName
     * @return array|false a row of the accounts table
     */
    function getKleejaFtpAccount($uniqueName)
    {
        return kj_ftp_accounts()[$uniqueName] ?? false;
    }
}

if (!function_exists('getKleejaFtpLink')) {
    /**
     * the public link of a file of an account, from its public URL, or from its host and root folder
     * @param  string $uniqueName the unique name of the account
     * @param  string $folder     the folder of the file, or its path
     * @param  string $filename
     * @return string the link, not encoded for HTML, empty when the account doesn't exist
     */
    function getKleejaFtpLink($uniqueName, $folder, $filename = '')
    {
        $ftp_account = getKleejaFtpAccount($uniqueName);

        if ($ftp_account == false) {
            return '';
        }

        $link = trim(kleeja_html_decode($ftp_account['link'] ?? ''));

        if ($link == '') {
            $root = trim(kleeja_html_decode($ftp_account['root'] ?? ''), '/ ');
            $link = 'http://' . trim(kleeja_html_decode($ftp_account['host'])) . ($root == '' ? '' : '/' . $root);
        }

        // the names are kept encoded for HTML, each part of the path is encoded for the link
        $path = trim(kleeja_html_decode($folder), '/') . ($filename !== '' ? '/' . kleeja_html_decode($filename) : '');
        $path = implode('/', array_map('rawurlencode', explode('/', $path)));

        return rtrim($link, '/') . '/' . $path;
    }
}
