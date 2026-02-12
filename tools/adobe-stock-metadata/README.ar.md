# النسخة المبدئية (Deprecated)

> **مهم جداً:** أدوات Node في هذا المجلد أصبحت **Deprecated** للإنتاج.
>
> المصدر الرسمي والوحيد لإنتاج بيانات Adobe Stock الآن هو إضافة WordPress:
> `tools/adobe-stock-metadata/wp-adobe-stock-metadata/`

## لماذا؟
- لضمان عدم وجود تعارض سياسات بين بيئة Node وبيئة WordPress.
- لضمان قاعدة تحقق واحدة صارمة (Fail Closed) خاصة بـ Adobe Stock.
- لضمان أن WordPress هو مصدر الحقيقة الوحيد للإنتاج.

## النتيجة
- لا تستخدم CLI الحالي في الإنتاج.
- استخدم الإضافة داخل WordPress فقط.
