<?php
function sendJSON(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sendSuccess(array $data = [], int $code = 200): void {
    sendJSON(array_merge(['success' => true], $data), $code);
}

function sendError(string $message, int $code = 400): void {
    sendJSON(['success' => false, 'error' => $message, 'message' => $message], $code);
}

function getBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json)) return $json;
    }
    return $_POST ?: [];
}
