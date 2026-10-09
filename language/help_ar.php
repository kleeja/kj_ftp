<?php
//
// kj_ftp, its guide on the help page of the control panel
// Arabic, see help_en.php for how the words are named
//
// A word missing here is shown in English.
// Names in <strong> are written as the control panel shows them in Arabic,
// the words of Kleeja are in lang/ar/, the words of the plugin in kj_ftp_translations() of init.php.
// A <code> that begins or ends with a symbol takes dir="ltr", or the symbol shows on the wrong side.
//

return [
    'KJ_FTP_HELP_TITLE' => 'التخزين عبر FTP',
    'KJ_FTP_HELP_INTRO' =>
        'خزّن الملفات المرفوعة على خادم FTP واحد أو أكثر بدلًا من الخادم الذي يشغّل كليجا. يتوزّع الرفع الجديد على الحسابات المفعّلة، ويبقى كل ملف متاحًا عبر صفحة تحميله وروابطه.',

    //
    // what the plugin does
    //
    'KJ_FTP_HELP_FEATURE_1' =>
        'أضف من حسابات FTP ما تحتاج إليه. يذهب كل ملف جديد إلى أحد الحسابات المفعّلة يُختار عشوائيًا، فتتوزّع الملفات وحركة تحميلها على خوادمك.',
    'KJ_FTP_HELP_FEATURE_2' =>
        'يفحص تبويب <strong>اختبار الاتصال</strong> الحساب من أوله إلى آخره: الخادم والمنفذ وFTPS وتسجيل الدخول والمجلد الجذر وإنشاء مجلد ورفع ملف وحذفه ورابطه العام. ويحدد الإعداد الخاطئ وطريقة إصلاحه.',
    'KJ_FTP_HELP_FEATURE_3' =>
        'يمر كل رفع بفحوصات كليجا نفسها: الامتدادات والأحجام المسموح بها، ورمز الأمان، والحماية من الإغراق. وتُنشأ المصغّرات وتُختم الصور قبل إرسال الملف إلى خادم FTP.',
    'KJ_FTP_HELP_FEATURE_4' =>
        'تعمل صفحات التحميل والروابط المباشرة والمصغّرات وروابط الحذف كما تعمل مع ملفات هذا الخادم. ويُحوَّل التحميل إلى <strong>الرابط العام</strong> للحساب.',
    'KJ_FTP_HELP_FEATURE_5' =>
        'حذف الملف من لوحة التحكم، أو من مدير ملفات صاحبه، أو برابط حذفه، أو عبر <strong>حذف الملفات الخاملة</strong>، يحذفه من خادم FTP مع مصغّرته.',
    'KJ_FTP_HELP_FEATURE_6' =>
        'يمكن تشفير الاتصال عبر FTPS (‏TLS الصريح)، ويعمل الوضع الخامل خلف معظم الجدران النارية.',
    'KJ_FTP_HELP_FEATURE_7' => 'تشغّل إضافتا مشغّل الفيديو وعارض PDF الملفات المخزّنة على خوادم FTP وتعرضانها.',

    //
    // how to use it
    //
    'KJ_FTP_HELP_STEP_TITLE' => 'إضافة حساب FTP',
    'KJ_FTP_HELP_STEP_1' =>
        'افتح <strong>حسابات FTP</strong>، واضغط <strong>إضافة حساب FTP</strong> ثم أكّد. يظهر في القائمة حساب جديد غير مفعّل.',
    'KJ_FTP_HELP_STEP_2' =>
        'افتح الحساب وأدخل <strong>الخادم</strong> و<strong>المنفذ</strong> و<strong>اسم المستخدم</strong> و<strong>كلمة المرور</strong> التي أعطتك إياها جهة الاستضافة لـ FTP.',
    'KJ_FTP_HELP_STEP_3' =>
        'في <strong>المجلد الجذر</strong>، أدخل المجلد الذي يعرضه خادم الويب لدى تلك الاستضافة، مثل <code dir="ltr">/public_html/files</code>. وفي <strong>الرابط العام</strong>، أدخل عنوان ذلك المجلد على الويب، مثل <code dir="ltr">https://files.example.com</code>.',
    'KJ_FTP_HELP_STEP_4' =>
        'اضغط <strong>تحديث</strong>، ثم <strong>اختبار الاتصال</strong>. أصلح ما يذكره الاختبار، واحفظ، وأعد الاختبار حتى تنجح جميع الفحوصات.',
    'KJ_FTP_HELP_STEP_5' =>
        'فعّل <strong>مفعّل</strong>، ثم اضغط <strong>تحديث</strong>. تنشئ كليجا مجلد الرفع على خادم FTP عند أول رفع.',

    //
    // common questions
    //
    'KJ_FTP_HELP_FAQ_Q_1' => 'ما زالت الملفات تُرفع إلى هذا الخادم، لماذا؟',
    'KJ_FTP_HELP_FAQ_A_1' =>
        'لا تستخدم كليجا حسابات FTP إلا إذا كان أحدها على الأقل مفعّلًا، وكان امتداد FTP في PHP مفعّلًا. وتعرض لوحة المعلومات ملاحظة إذا نقص أي منهما.',
    'KJ_FTP_HELP_FAQ_Q_2' => 'يرى الزوار رسالة «خادم تخزين الملفات غير متاح حاليًا»، ماذا أراجع؟',
    'KJ_FTP_HELP_FAQ_A_2' =>
        'شغّل <strong>اختبار الاتصال</strong> للحساب أولًا، فهو يحدد الإعداد الخاطئ. لم تتمكن كليجا من الاتصال بالحساب الذي اختير للرفع أو من تسجيل الدخول إليه. راجع <strong>الخادم</strong> و<strong>المنفذ</strong> و<strong>اسم المستخدم</strong> و<strong>كلمة المرور</strong>. وإذا كان الخادم لا يدعم FTPS، فعطّل <strong>اتصال آمن (FTPS)</strong>. وإذا تم الاتصال وفشل الرفع، فجرّب عكس إعداد <strong>الوضع الخامل (Passive)</strong>. وألغِ تفعيل الحساب إلى أن يعود خادمه إلى العمل.',
    'KJ_FTP_HELP_FAQ_Q_3' => 'ينجح الرفع، لكن روابط الملفات تفتح صفحة «<span dir="ltr">404 Not Found</span>»، لماذا؟',
    'KJ_FTP_HELP_FAQ_A_3' =>
        'يفحص <strong>اختبار الاتصال</strong> ذلك نيابةً عنك. لا يقود <strong>الرابط العام</strong> إلى <strong>المجلد الجذر</strong>. يُحفظ الملف داخل المجلد الجذر في مجلد الرفع الخاص بكليجا، مثل <code dir="ltr">/public_html/files/uploads/photo.jpg</code>، ورابطه هو الرابط العام يليه المسار نفسه، مثل <code dir="ltr">https://files.example.com/uploads/photo.jpg</code>.',
    'KJ_FTP_HELP_FAQ_Q_4' => 'كيف أزيل حساب FTP؟',
    'KJ_FTP_HELP_FAQ_A_4' =>
        'لا يمكن حذف الحساب، لأن الملفات المخزّنة عليه تحتاج إلى بياناته. ألغِ <strong>مفعّل</strong> بدلًا من ذلك: لن يستقبل الحساب ملفات جديدة، وتبقى ملفاته متاحة للتحميل والحذف.',
    'KJ_FTP_HELP_FAQ_Q_5' => 'كيف أنقل ملفات حساب إلى خادم آخر؟',
    'KJ_FTP_HELP_FAQ_A_5' =>
        'انسخ الملفات إلى الخادم الجديد بالمجلدات نفسها، ثم غيّر <strong>الخادم</strong> وبيانات الدخول و<strong>المجلد الجذر</strong> و<strong>الرابط العام</strong> في الحساب نفسه. وأبقِ <strong>الاسم الثابت</strong> كما هو، فالملفات تعرف حسابها به.',
    'KJ_FTP_HELP_FAQ_Q_6' => 'لا تُفتح ملفات PDF المخزّنة على FTP في عارض PDF، لماذا؟',
    'KJ_FTP_HELP_FAQ_A_6' =>
        'يقرأ العارض الملف من خادم الويب الخاص بحساب FTP، وهو موقع آخر، فلا بد أن يسمح ذلك الخادم بذلك. أضف إلى ملفات ذلك الخادم الترويسة <code>Access-Control-Allow-Origin</code> مع عنوان موقع كليجا.',
    'KJ_FTP_HELP_FAQ_Q_7' => 'ماذا يحدث للملفات عند إلغاء تثبيت الإضافة؟',
    'KJ_FTP_HELP_FAQ_A_7' =>
        'تبقى الملفات على خوادم FTP، وتحتفظ كليجا بالحسابات، لكن روابطها تتوقف عن العمل، وتعود إذا ثبّت الإضافة من جديد. أما الملفات المرفوعة بعد إلغاء التثبيت فتُحفظ على هذا الخادم.',

    //
    // beside the guide
    //
    'KJ_FTP_HELP_TIP_1' =>
        'استخدم عنوانًا يبدأ بـ <code dir="ltr">https://</code> في <strong>الرابط العام</strong>. فمن دونه تبني كليجا الروابط عبر <code dir="ltr">http://</code>، وتحذّر المتصفحات من الصور المحمّلة بهذه الطريقة في موقع آمن.',
    'KJ_FTP_HELP_TIP_2' => 'أنشئ لكليجا مستخدم FTP مستقلًا يقتصر على المجلد الجذر، بدلًا من الحساب الرئيسي لاستضافتك.',
    'KJ_FTP_HELP_TIP_3' =>
        'إذا اخترت <strong>رابط مباشر</strong> في <strong>شكل روابط الملفات</strong> أو <strong>شكل روابط الصور</strong>، فإن الروابط تشير إلى خادم FTP مباشرة. أما الأشكال الأخرى فتمر بكليجا أولًا، فتُحتسب التحميلات.',

    'KJ_FTP_HELP_WARNING_1' => 'لا تغيّر <strong>الاسم الثابت</strong> لحساب عليه ملفات، وإلا فلن تجدها كليجا.',
    'KJ_FTP_HELP_WARNING_2' => 'يُرسل كل ملف إلى خادم FTP أثناء رفعه، لذا يجعل خادم FTP البطيء الرفع أبطأ على زوارك.',
];
