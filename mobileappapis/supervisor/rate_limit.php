<?php
// Simple in-memory rate limiting (for FPM, prefer Redis/memcached)
function rl_check($key, $limit = 30, $windowSeconds = 60) {
    $now = time();
    $bucket = sys_get_temp_dir() . '/rl_' . md5($key);
    $data = ['start' => $now, 'count' => 0];
    if (file_exists($bucket)) {
        $data = json_decode(@file_get_contents($bucket), true) ?: $data;
    }
    if ($now - $data['start'] > $windowSeconds) {
        $data = ['start' => $now, 'count' => 0];
    }
    if ($data['count'] >= $limit) {
        http_response_code(429);
        header('Retry-After: ' . max(1, $windowSeconds - ($now - $data['start'])));
        echo json_encode(['success' => false, 'message' => 'Too many requests']);
        exit;
    }
    $data['count']++;
    @file_put_contents($bucket, json_encode($data));
}







