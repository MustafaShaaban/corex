# CoreX in Arabic: glossary and style

The words `corex-ar.po` uses, so one term is translated one way on every screen (spec 121, US-NFR-02,
DECISIONS #466). A translator adding strings reads this first; the Arabic proof and the reviewer check against it.

## Style

- Modern Standard Arabic, plain and calm. Speak to the reader as أنت. No marketing tone.
- Buttons and menu items are verbal nouns: حفظ, إلغاء, حذف, تصدير, not imperatives.
- Sentences that tell the reader what to do use the imperative: احفظ الإعدادات ثم أعد المحاولة.
- Punctuation: the Arabic comma ، semicolon ؛ and question mark ؟. A full stop stays a full stop.
- Numerals are written in Western digits (0 to 9), as WordPress's Arabic translation writes them.
- Placeholders (`%s`, `%1$s`, `%d`), markup (`<a>`, `<strong>`, `<code>`) and named slots (`{name}`) are kept
  exactly; a translation may move them, never drop, add or rename them. In a plural, a form for one may leave
  the number out.
- Arabic has six plural forms (none, one, two, three to ten, eleven to ninety-nine, a hundred and more), in that order.

## Kept in English

Names the catalogue does not translate, and the Arabic proof does not count as untranslated:

CoreX, WordPress, WooCommerce, WP-CLI, PHP, MySQL, SMTP, DKIM, SPF, DMARC, REST, API, JSON, CSV, XML, PDF, Excel, HTML,
CSS, JavaScript, URL, SVG, WebP, ACF, Polylang, WPML, Gutenberg, reCAPTCHA, hCaptcha, Turnstile, Cloudflare, Google,
Gmail, Outlook, GitHub, Azure, cron, OPcache, UTF-8, SSO, OAuth, IP, Space Grotesk, JetBrains Mono, IBM Plex Sans
Arabic; code identifiers, file names, commands, option and hook names.

## Terms

| English | العربية |
|---|---|
| Add-on | وحدة إضافية (الوحدات الإضافية) |
| Plugin | إضافة (الإضافات) |
| Theme | قالب الموقع |
| Template (page, email) | قالب (القوالب) |
| Pattern | نمط (الأنماط) |
| Block | مكوّن (المكوّنات) |
| Dashboard | لوحة التحكم |
| Overview | نظرة عامة |
| Settings | الإعدادات |
| Form | نموذج (النماذج) |
| Field | حقل (الحقول) |
| Submission | إرسال (الإرسالات) |
| Inbox | صندوق الوارد |
| Flow | مسار (المسارات) |
| Step | خطوة |
| Reply | رد (الردود) |
| Email Studio | استوديو البريد |
| Email | بريد إلكتروني؛ a message: رسالة بريد |
| Capture (mail) | التقاط البريد؛ captured: محتجَزة |
| Data Models | نماذج البيانات |
| Record | سجل (السجلات) |
| Entry (log) | قيد |
| Export / Import | تصدير / استيراد |
| Release | إصدار (الإصدارات) |
| Operations mode | وضع التشغيل |
| Development / Staging / Production | التطوير / الاختبار / الإنتاج |
| Coming soon | قريبًا |
| Maintenance | الصيانة |
| Access | الوصول |
| Ability | صلاحية (الصلاحيات) |
| Role | دور (الأدوار) |
| Capability | قدرة |
| Kit | حزمة (Company kit: حزمة الشركة) |
| Setup wizard | معالج الإعداد |
| Guides | الأدلة |
| Account | الحساب |
| Profile | الملف الشخصي |
| Sign in / Sign out | تسجيل الدخول / تسجيل الخروج |
| Password | كلمة المرور |
| Username | اسم المستخدم |
| Captcha | اختبار التحقق |
| Notifications | الإشعارات |
| Insights | التحليلات |
| Blog Pro | المدونة الاحترافية |
| Trash / Restore / Delete permanently | سلة المهملات / استعادة / حذف نهائي |
| Retention | مدة الاحتفاظ |
| Appearance: Light / Dark / System | المظهر: فاتح / داكن / حسب النظام |
| Security | الأمان |
| Health | سلامة الموقع |
| Cache | ذاكرة التخزين المؤقت |
| Upload | رفع |
| Download | تنزيل |
| Save / Cancel / Close | حفظ / إلغاء / إغلاق |
| Slug | الاسم اللطيف (as WordPress writes it) |
| Route (mail) | توجيه البريد |
| Version (of a template or file) | نسخة؛ CoreX's release version: إصدار |
| Expired | انتهت مدته (never صلاحية, which is Ability) |
| Display name / User name | الاسم الظاهر |
| Transactional (email) | للمعاملات |
| Required | مطلوب |
| Optional | اختياري |
