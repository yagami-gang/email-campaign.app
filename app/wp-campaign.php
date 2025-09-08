<?php
// campaign-mailer-endpoint.php
// Fallback sans REST API. A placer à la racine WordPress.

require_once __DIR__ . '/wp-load.php';

header('Content-Type: application/json; charset=utf-8');

define("CAMPAIGN_MAILER_API_KEY", "124610112358134711235813acdfa0aab");

@set_time_limit(0);

// --- Auth Bearer ---
$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (!$auth && function_exists('apache_request_headers')) {
    $h = apache_request_headers();
    if (!empty($h['Authorization'])) $auth = $h['Authorization'];
}
if (!$auth || stripos($auth, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Missing or invalid Authorization header']); exit;
}
$token = trim(substr($auth, 7));
if (!defined('CAMPAIGN_MAILER_API_KEY') || !hash_equals(CAMPAIGN_MAILER_API_KEY, $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid API key']); exit;
}

// --- Body JSON ---
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or empty JSON body']); exit;
}

$campaign_id = isset($data['campaign_id']) ? $data['campaign_id'] : null;
$from_email  = isset($data['from_email'])  ? sanitize_email($data['from_email']) : '';
$from_name   = isset($data['from_name'])   ? wp_strip_all_tags($data['from_name']) : '';
$messages    = isset($data['messages'])    ? $data['messages'] : [];

if (!$from_email || !is_email($from_email)) {
    http_response_code(400);
    echo json_encode(['error' => 'from_email is missing or invalid']); exit;
}
if (!is_array($messages) || empty($messages)) {
    http_response_code(400);
    echo json_encode(['error' => 'messages array is missing or empty']); exit;
}

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . wp_specialchars_decode($from_name ?: $from_email, ENT_QUOTES) . " <{$from_email}>",
    'Reply-To: ' . $from_email,
];

$results = [];
$sent = 0; $failed = 0;

foreach ($messages as $i => $m) {
    $to = isset($m['to_email']) ? sanitize_email($m['to_email']) : '';
    $subj = isset($m['subject']) ? str_replace(["\r","\n"], '', wp_strip_all_tags($m['subject'])) : '';
    $html = isset($m['content']) ? (string)$m['content'] : '';

    if (!$to || !is_email($to)) {
        $failed++;
        $results[] = ['index' => $i, 'to_email' => $to ?: '(empty)', 'status' => 'failed', 'error' => 'Invalid recipient email'];
        continue;
    }

    $ok = wp_mail($to, $subj, $html, $headers);
    if ($ok) {
        $sent++;
        $results[] = ['index' => $i, 'to_email' => $to, 'status' => 'sent'];
    } else {
        $failed++;
        $results[] = ['index' => $i, 'to_email' => $to, 'status' => 'failed', 'error' => 'wp_mail() returned false'];
    }
}

http_response_code(200);
echo json_encode([
    'success'     => true,
    'campaign_id' => $campaign_id,
    'processed'   => count($messages),
    'sent'        => $sent,
    'failed'      => $failed,
    'results'     => $results,
]);
