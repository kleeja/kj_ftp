<?php
//
// kj_ftp, its guide on the help page of the control panel
// English
//
// The guide is built by the admin_help_guides hook in init.php, the texts here can have HTML.
//  - a list is numbered: KJ_FTP_HELP_TIP_1, KJ_FTP_HELP_TIP_2 ... it ends at the first missing number,
//    so an item can be added or removed without touching the code
//  - KJ_FTP_HELP_{LIST}_TITLE is the title of the list, a list without it takes the title that Kleeja gives its type
//  - questions and answers are KJ_FTP_HELP_FAQ_Q_1 and KJ_FTP_HELP_FAQ_A_1
//  - names in <strong> are written as the control panel shows them: the words of Kleeja are in lang/en/,
//    the words of the plugin in kj_ftp_translations() of init.php
//

return [
    'KJ_FTP_HELP_TITLE' => 'Multi-FTP Storage',
    'KJ_FTP_HELP_INTRO' =>
        'Store uploaded files on one or more FTP servers instead of the server that runs Kleeja. New uploads are spread across the active accounts, and every file stays reachable through its download page and links.',

    //
    // what the plugin does
    //
    'KJ_FTP_HELP_FEATURE_1' =>
        'Add as many FTP accounts as you need. Each new file goes to one of the active accounts, chosen at random, which spreads the files and their traffic across your servers.',
    'KJ_FTP_HELP_FEATURE_2' =>
        'The <strong>Connection test</strong> tab checks an account from end to end: the host, the port, FTPS, the sign-in, the root folder, creating a folder, uploading and deleting a file, and its public link. It names the setting that is wrong and how to fix it.',
    'KJ_FTP_HELP_FEATURE_3' =>
        'Every upload keeps the checks of Kleeja: allowed extensions and sizes, the safety code and the flood protection. Thumbnails and watermarks are made before the file is sent to the FTP server.',
    'KJ_FTP_HELP_FEATURE_4' =>
        'Download pages, direct links, thumbnails and deletion links work as they do for files on this server. A download is redirected to the <strong>Public URL</strong> of the account.',
    'KJ_FTP_HELP_FEATURE_5' =>
        'Deleting a file from the control panel, from the file manager of its owner, with its deletion link, or with <strong>Auto Delete undownloaded files</strong> deletes it from the FTP server, with its thumbnail.',
    'KJ_FTP_HELP_FEATURE_6' =>
        'Connections can be encrypted with FTPS (explicit TLS), and passive mode works behind most firewalls.',
    'KJ_FTP_HELP_FEATURE_7' =>
        'The Video Player and PDF Viewer plugins play and show the files that are stored on FTP servers.',

    //
    // how to use it
    //
    'KJ_FTP_HELP_STEP_TITLE' => 'Add an FTP account',
    'KJ_FTP_HELP_STEP_1' =>
        'Open <strong>FTP Accounts</strong>, click <strong>Add FTP account</strong>, and confirm. A new inactive account appears in the list.',
    'KJ_FTP_HELP_STEP_2' =>
        'Open the account and enter the <strong>Host</strong>, <strong>Port</strong>, <strong>Username</strong> and <strong>Password</strong> that your host gave you for FTP.',
    'KJ_FTP_HELP_STEP_3' =>
        'In <strong>Root folder</strong>, enter the folder that the web server of that host serves, like <code>/public_html/files</code>. In <strong>Public URL</strong>, enter the web address of that folder, like <code>https://files.example.com</code>.',
    'KJ_FTP_HELP_STEP_4' =>
        'Click <strong>Update</strong>, then <strong>Test connection</strong>. Fix what the test reports, save, and test again until every check passes.',
    'KJ_FTP_HELP_STEP_5' =>
        'Turn on <strong>Active</strong>, then click <strong>Update</strong>. Kleeja creates the upload folder on the FTP server at the first upload.',

    //
    // common questions
    //
    'KJ_FTP_HELP_FAQ_Q_1' => 'Files are still uploaded to this server. Why?',
    'KJ_FTP_HELP_FAQ_A_1' =>
        'Kleeja uses the FTP accounts only when at least one account is active and the FTP extension of PHP is enabled. The dashboard shows a note when either is missing.',
    'KJ_FTP_HELP_FAQ_Q_2' => 'Visitors see “The file storage server is not reachable right now”. What should I check?',
    'KJ_FTP_HELP_FAQ_A_2' =>
        'Run the <strong>Connection test</strong> of the account first: it names the setting that is wrong. Kleeja could not connect or sign in to the account that was chosen for the upload. Check the <strong>Host</strong>, the <strong>Port</strong>, the <strong>Username</strong> and the <strong>Password</strong>. If the server does not support FTPS, turn off <strong>Secure connection (FTPS)</strong>. If the connection is made but the upload fails, try the opposite setting of <strong>Passive mode</strong>. Deactivate an account until its server works again.',
    'KJ_FTP_HELP_FAQ_Q_3' => 'Uploads work, but their links open a “404 Not Found” page. Why?',
    'KJ_FTP_HELP_FAQ_A_3' =>
        'The <strong>Connection test</strong> checks it for you. The <strong>Public URL</strong> does not lead to the <strong>Root folder</strong>. A file is saved in the root folder, in the upload folder of Kleeja, like <code>/public_html/files/uploads/photo.jpg</code>, and its link is the public URL followed by the same path, like <code>https://files.example.com/uploads/photo.jpg</code>.',
    'KJ_FTP_HELP_FAQ_Q_4' => 'How do I remove an FTP account?',
    'KJ_FTP_HELP_FAQ_A_4' =>
        'An account can not be deleted, because the files that it stores need its details. Turn off <strong>Active</strong> instead: the account receives no new files, and its files can still be downloaded and deleted.',
    'KJ_FTP_HELP_FAQ_Q_5' => 'How do I move the files of an account to another server?',
    'KJ_FTP_HELP_FAQ_A_5' =>
        'Copy the files to the new server with the same folders, then change the <strong>Host</strong>, the sign-in details, the <strong>Root folder</strong> and the <strong>Public URL</strong> of the same account. Keep its <strong>Unique name</strong>, which the files use to find it.',
    'KJ_FTP_HELP_FAQ_Q_6' => 'PDF files on FTP don’t open in the PDF Viewer. Why?',
    'KJ_FTP_HELP_FAQ_A_6' =>
        'The viewer reads the file from the web server of the FTP account, which is another site, so that server has to allow it. Add the header <code>Access-Control-Allow-Origin</code> with the address of your Kleeja site to the files of that server.',
    'KJ_FTP_HELP_FAQ_Q_7' => 'What happens to the files when I uninstall the plugin?',
    'KJ_FTP_HELP_FAQ_A_7' =>
        'They stay on the FTP servers, and Kleeja keeps the accounts, but their links stop working. Installing the plugin again brings them back. Files uploaded after the uninstall go to this server.',

    //
    // beside the guide
    //
    'KJ_FTP_HELP_TIP_1' =>
        'Use an <code>https://</code> address in <strong>Public URL</strong>. Without it, Kleeja makes the links over <code>http://</code>, and browsers warn about images loaded that way on a secure site.',
    'KJ_FTP_HELP_TIP_2' =>
        'Create a separate FTP user for Kleeja, limited to the root folder, rather than the main account of your hosting.',
    'KJ_FTP_HELP_TIP_3' =>
        'With <strong>Direct</strong> in <strong>Files URLs form</strong> or <strong>Images URLs form</strong>, the links point straight to the FTP server. The other forms go through Kleeja first, which counts the downloads.',

    'KJ_FTP_HELP_WARNING_1' =>
        'Don’t change the <strong>Unique name</strong> of an account that has files: Kleeja would no longer find them.',
    'KJ_FTP_HELP_WARNING_2' =>
        'Each file is sent to the FTP server while it is uploaded, so a slow FTP server makes uploads slower for your visitors.',
];
