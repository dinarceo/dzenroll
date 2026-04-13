<?php
/* ═══════════════════════════════════════════════════
   DZENROLL — admin/api.php
   Обработчик B2B-заявок:
   1. Сохраняет в data/leads.json
   2. Отправляет email-уведомление
════════════════════════════════════════════════��══ */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

/* ─── КОНФИГУРАЦИЯ ─────────────────────────────── */
define('LEADS_FILE',    __DIR__ . '/../data/leads.json');
define('NOTIFY_EMAIL',  'your@email.ru');          // ← замените на свой email
define('NOTIFY_FROM',   'noreply@dzenroll.ru');    // ← замените на свой домен
define('MAX_LEADS',     10000);                    // максимум записей в файле

/* ─── ЧТЕНИЕ ТЕЛА ЗАПРОСА ──────────────────────── */
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

/* ─── САНИТИЗАЦИЯ ──────────────────────────────── */
function clean(string $v, int $max = 255): string {
    return mb_substr(strip_tags(trim($v)), 0, $max);
}

$lead = [
    'id'            => uniqid('lead_', true),
    'timestamp'     => date('Y-m-d H:i:s'),
    'name'          => clean($data['name']          ?? ''),
    'phone'         => clean($data['phone']         ?? ''),
    'email'         => clean($data['email']         ?? ''),
    'city'          => clean($data['city']          ?? ''),
    'business_type' => clean($data['business_type'] ?? ''),
    'comment'       => clean($data['comment']       ?? '', 2000),
    'ip'            => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent'    => clean($_SERVER['HTTP_USER_AGENT'] ?? '', 512),
];

/* ─── ВАЛИДАЦИЯ ────────────────────────────────── */
$errors = [];
if (mb_strlen($lead['name'])  < 2) $errors[] = 'Имя слишком короткое';
if (!preg_match('/^[\+\d][\d\s\-\(\)]{6,18}$/', $lead['phone'])) $errors[] = 'Некорректный телефон';
if (!filter_var($lead['email'], FILTER_VALIDATE_EMAIL))           $errors[] = 'Некорректный email';
if (mb_strlen($lead['city'])  < 2) $errors[] = 'Город не указан';
if (empty($lead['business_type']))  $errors[] = 'Тип бизнеса не указан';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode('; ', $errors)]);
    exit;
}

/* ─── СОХРАНЕНИЕ В JSON ────────────────────────── */
$leadsDir = dirname(LEADS_FILE);
if (!is_dir($leadsDir)) {
    mkdir($leadsDir, 0750, true);
}

// Создаём файл если не существует
if (!file_exists(LEADS_FILE)) {
    file_put_contents(LEADS_FILE, json_encode(['leads' => []], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// Блокировка файла на запись
$fp = fopen(LEADS_FILE, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Cannot open leads file']);
    exit;
}

flock($fp, LOCK_EX);
$content = stream_get_contents($fp);
$db = json_decode($content, true) ?: ['leads' => []];

// Ограничение размера
if (count($db['leads']) >= MAX_LEADS) {
    array_shift($db['leads']); // удаляем самую старую
}

$db['leads'][] = $lead;

ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($db, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
flock($fp, LOCK_UN);
fclose($fp);

/* ─── EMAIL УВЕДОМЛЕНИЕ ────────────────────────── */
$businessTypes = [
    'distributor' => 'Дистрибьютор',
    'hookah'      => 'Кальянная',
    'shop'        => 'Табачный магазин / сеть',
    'other'       => 'Другое',
];
$typeLabel = $businessTypes[$lead['business_type']] ?? $lead['business_type'];

$subject = "🔥 Новая заявка DZENROLL от {$lead['name']} ({$lead['city']})";

$body = "
Новая B2B-заявка с сайта DZENROLL
==================================

ID заявки:    {$lead['id']}
Дата/время:   {$lead['timestamp']}

Имя/Компания: {$lead['name']}
Телефон:      {$lead['phone']}
Email:        {$lead['email']}
Город:        {$lead['city']}
Тип бизнеса:  {$typeLabel}

Комментарий:
{$lead['comment']}

--
IP: {$lead['ip']}
";

$headers  = "From: DZENROLL <" . NOTIFY_FROM . ">\r\n";
$headers .= "Reply-To: {$lead['email']}\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

@mail(NOTIFY_EMAIL, $subject, $body, $headers);

/* ─── ОТВЕТ ────────────────────────────────────── */
echo json_encode([
    'success' => true,
    'message' => 'Заявка принята',
    'id'      => $lead['id'],
]);