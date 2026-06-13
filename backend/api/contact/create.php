<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendError('Method not allowed', 405);
$body = getBody();
if (empty($body['name']) || empty($body['email']) || empty($body['message'])) sendError('Missing required fields');

$db = getDB();
$stmt = $db->prepare("INSERT INTO contact_messages (name,email,message) VALUES (?,?,?)");
$stmt->bind_param('sss', $body['name'], $body['email'], $body['message']);
$stmt->execute();
$db->close();
sendJSON(['success' => true, 'message' => 'تم إرسال رسالتك بنجاح'], 201);
