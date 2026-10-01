<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json');

$user = current_user();
$currentUserId = $user['users_id'] ?? '';

$message = trim($_POST['message'] ?? '');
$conversationId = id_param($_POST['conversation_id'] ?? '');

if ($message === '') {
    echo json_encode(['success' => false, 'error' => 'Empty message']);
    exit;
}

if ($conversationId) {
    $stmt = db()->prepare('SELECT ai_conversations_id FROM ai_conversations WHERE ai_conversations_id = ? AND user_id = ?');
    $stmt->execute([$conversationId, $currentUserId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Conversation not found']);
        exit;
    }
}

if ($conversationId === '') {
    $title = mb_substr($message, 0, 80);
    $conversationId = new_id('ai_conversations');
    $stmt = db()->prepare('INSERT INTO ai_conversations (ai_conversations_id, user_id, title) VALUES (?, ?, ?)');
    $stmt->execute([$conversationId, $currentUserId, $title]);
}

$userMessageId = new_id('ai_messages');
$stmt = db()->prepare('INSERT INTO ai_messages (ai_messages_id, conversation_id, sender, message) VALUES (?, ?, "user", ?)');
$stmt->execute([$userMessageId, $conversationId, $message]);

function callGroqAPI(string $userMessage): array {
    $payload = [
        'model' => AI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a helpful IT and technology support assistant. Provide clear, practical solutions.'
            ],
            [
                'role' => 'user',
                'content' => $userMessage
            ]
        ],
        'temperature' => 0.7,
        'max_tokens'  => 2000,
    ];

    $ch = curl_init(AI_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . AI_API_KEY,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('Groq curl error: ' . $curlError);
        return ['error' => 'Connection error: ' . $curlError];
    }

    if ($httpCode !== 200) {
        error_log('Groq HTTP ' . $httpCode . ': ' . $response);
        return ['error' => 'API returned status ' . $httpCode . ': ' . $response];
    }

    $result = json_decode($response, true);

    if (isset($result['choices'][0]['message']['content'])) {
        return ['success' => $result['choices'][0]['message']['content']];
    }

    error_log('Groq unexpected format: ' . $response);
    return ['error' => 'Unexpected response format'];
}

$aiResponse = callGroqAPI($message);
$aiText = $aiResponse['success'] ?? ('Error: ' . ($aiResponse['error'] ?? 'Unknown error'));

$aiMessageId = new_id('ai_messages');
$stmt = db()->prepare('INSERT INTO ai_messages (ai_messages_id, conversation_id, sender, message) VALUES (?, ?, "ai", ?)');
$stmt->execute([$aiMessageId, $conversationId, $aiText]);

$stmt = db()->prepare('UPDATE ai_conversations SET updated_at = CURRENT_TIMESTAMP WHERE ai_conversations_id = ?');
$stmt->execute([$conversationId]);

echo json_encode([
    'success' => true,
    'conversation_id' => $conversationId,
    'user_message' => [
        'id' => $userMessageId,
        'message' => $message,
        'time' => date('h:i A'),
    ],
    'ai_message' => [
        'id' => $aiMessageId,
        'message' => $aiText,
        'time' => date('h:i A'),
    ],
]);