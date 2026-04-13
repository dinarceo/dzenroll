# DZENROLL Landing Page

Одностраничный сайт для продукта DZENROLL (кальянный табак в форме ролла).

**Стек:** HTML5 + CSS (custom) + Vanilla JS · Decap CMS · PHP-обработчик форм · Хостинг Jino

---

## 📁 Структура проекта

```
/dzenroll-landing
├── index.html              # Лендинг
├── admin/
│   ├── config.yml          # Конфигурация Decap CMS
│   ├── index.html          # Интерфейс CMS
│   └── api.php             # Обработчик B2B-заявок
├── assets/
│   ├── css/style.css       # (опционально, доп. стили)
│   ├── js/
│   │   ├── main.js         # Интерактивность
│   │   ├── cms-loader.js   # Загрузка контента из CMS
│   │   └── form-handler.js # Валидация и отправка формы
│   └── img/                # Статические изображения
├── content/
│   ├── texts.json          # Все тексты сайта
│   ├── settings.json       # Цвета, шрифты, контакты
│   ├── media.json          # Медиа-элементы
│   └── flavors.json        # Список вкусов
├── data/
│   ├── .htaccess           # Запрет доступа к папке
│   └── leads.json          # База заявок (создаётся автоматически)
├── public/uploads/         # Загружаемые медиафайлы (CMS)
├── .htaccess               # Настройки Apache
└── README.md
```

---

## 🚀 Деплой на Jino

### 1. Загрузка файлов

1. Войдите в панель управления Jino → **Файловый менеджер** или используйте FTP.
2. Загрузите все файлы проекта в корневую папку сайта (обычно `public_html/` или `www/`).
3. Убедитесь, что папка `data/` существует и доступна для записи PHP:
   ```bash
   chmod 755 data/
   ```

### 2. Настройка PHP-обработчика

Откройте `admin/api.php` и замените:
```php
define('NOTIFY_EMAIL', 'your@email.ru');   // ← ваш email для уведомлений
define('NOTIFY_FROM',  'noreply@dzenroll.ru'); // ← ваш домен
```

Проверьте работу формы: заполните и отправьте B2B-форму на сайте.
Заявка должна появиться в `data/leads.json` и прийти на email.

### 3. Настройка Decap CMS

Decap CMS требует Git-репозиторий для хранения контента.

#### Вариант A: GitHub + Netlify Identity (рекомендуется)

1. Создайте репозиторий на GitHub и загрузите туда проект.
2. Подключите репозиторий к [Netlify](https://app.netlify.com).
3. В Netlify: **Site Settings → Identity → Enable Identity**.
4. В Netlify: **Identity → Services → Enable Git Gateway**.
5. Добавьте пользователя: **Identity → Invite users**.
6. Откройте `https://ваш-сайт.netlify.app/admin/` — войдите через Netlify Identity.

#### Вариант B: GitHub OAuth (для Jino без Netlify)

1. Создайте GitHub OAuth App: **GitHub Settings → Developer Settings → OAuth Apps**.
   - Homepage URL: `https://ваш-домен.ru`
   - Callback URL: `https://api.netlify.com/auth/done`
2. В `admin/config.yml` замените backend:
   ```yaml
   ## backend:
     name: github
     repo: your-username/dzenroll-landing
     branch: main
   ```
3. Откройте `https://ваш-домен.ru/admin/` и авторизуйтесь через GitHub.

---

## ✏️ Управление контентом через CMS

После входа в `/admin/` вы увидите:

| Раздел | Что редактируется |
|--------|------------------|
| 📝 Тексты сайта | Все заголовки, описания, кнопки |
| ⚙️ Настройки | Цвета акцентов, шрифты, контакты |
| 🖼️ Медиа | Изображения, видео (YouTube/Vimeo/MP4), 3D-модели |
| 🍋 Вкусы | Карточки вкусов в карусели |

После сохранения изменений CMS создаёт коммит в Git-репозиторий.
Файлы `content/*.json` обновляются автоматически.

---

## 📬 Работа с заявками

### Просмотр заявок

Все заявки сохраняются в `data/leads.json` в формате:
```json
{
  "leads": [
    {
      "id": "lead_abc123",
      "timestamp": "2025-01-15 14:30:00",
      "name": "ООО Кальян Плюс",
      "phone": "+7 999 123-45-67",
      "email": "info@company.ru",
      "city": "Москва",
      "business_type": "hookah",
      "comment": "Интересует оптовая поставка",
      "ip": "1.2.3.4"
    }
  ]
}
```

### Экспорт в Excel/CSV

Создайте файл `admin/export.php` на сервере:

```php
<?php
// Простой экспорт leads.json в CSV
// Защитите этот файл паролем через .htaccess!
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="leads_' . date('Y-m-d') . '.csv"');

$db = json_decode(file_get_contents('../data/leads.json'), true);
$fp = fopen('php://output', 'w');
fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM для Excel

fputcsv($fp, ['ID','Дата','Имя/Компания','Телефон','Email','Город','Тип','Комментарий'], ';');
foreach ($db['leads'] as $lead) {
    fputcsv($fp, [
        $lead['id'], $lead['timestamp'], $lead['name'],
        $lead['phone'], $lead['email'], $lead['city'],
        $lead['business_type'], $lead['comment']
    ], ';');
}
fclose($fp);
```

Защитите файл в `.htaccess`:
```apache
<Files "export.php">
  AuthType Basic
  AuthName "Admin"
  AuthUserFile /path/to/.htpasswd
  Require valid-user
</Files>
```

---

## 🔗 Интеграция с CRM

### Webhook в amoCRM

1. В `admin/api.php` после сохранения заявки добавьте:

```php
// amoCRM Webhook
$amoWebhook = 'https://your-domain.amocrm.ru/api/v4/leads';
$amoToken   = 'YOUR_AMOCRM_ACCESS_TOKEN';

$amoData = [
    'name'           => "Заявка с сайта: {$lead['name']}",
    'custom_fields_values' => [
        ['field_code' => 'PHONE', 'values' => [['value' => $lead['phone']]]],
        ['field_code' => 'EMAIL', 'values' => [['value' => $lead['email']]]],
    ],
    '_embedded' => [
        'contacts' => [['name' => $lead['name']]]
    ]
];

$ch = curl_init($amoWebhook);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([$amoData]),
    CURLOPT_RETURNTRANSFER => true,
    # CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "Authorization: Bearer {$amoToken}",
    ],
]);
curl_exec($ch);
curl_close($ch);
```

### Уведомление в Telegram

```php
// Telegram Bot уведомление
$tgToken  = 'YOUR_BOT_TOKEN';
$tgChatId = 'YOUR_CHAT_ID';
$tgMsg    = urlencode("🔥 Новая заявка DZENROLL!\n\n👤 {$lead['name']}\n📞 {$lead['phone']}\n📧 {$lead['email']}\n🏙️ {$lead['city']}\n💼 {$typeLabel}");

file_get_contents("https://api.telegram.org/bot{$tgToken}/sendMessage?chat_id={$tgChatId}&text={$tgMsg}&parse_mode=HTML");
```

### Bitrix24

```php
// Bitrix24 REST API
$b24Webhook = 'https://your-domain.bitrix24.ru/rest/1/YOUR_TOKEN/crm.lead.add.json';

$b24Data = http_build_query([
    'fields[TITLE]'       => "Заявка: {$lead['name']}",
    'fields[NAME]'        => $lead['name'],
    'fields[PHONE][0][VALUE]' => $lead['phone'],
    'fields[EMAIL][0][VALUE]' => $lead['email'],
    'fields[CITY]'        => $lead['city'],
    'fields[COMMENTS]'    => $lead['comment'],
]);

file_get_contents("{$b24Webhook}?{$b24Data}");
```

---

## 🎨 Кастомизация дизайна

### Изменение цветов через CMS

1. Войдите в `/admin/`
2. Перейдите в **⚙️ Настройки → Глобальные настройки**
3. Измените цвета в разделе **Цветовая схема**
4. Сохраните — изменения применятся автоматически через `cms-loader.js`

### Изменение шрифтов

В настройках CMS укажите название любого шрифта с [Google Fonts](https://fonts.google.com).
Скрипт `cms-loader.js` автоматически загрузит шрифт и применит его.

---

## 🔒 Безопасность

- Папка `data/` защищена от прямого доступа через `.htaccess`
- PHP-обработчик валидирует и санитизирует все входящие данные
- Используется блокировка файла (`flock`) для безопасной записи
- Рекомендуется настроить HTTPS через панель Jino (Let's Encrypt)

---

## 📞 Поддержка

По вопросам настройки и доработки: **info@dzenroll.ru**

© 2025 DZENROLL. Все права защищены.