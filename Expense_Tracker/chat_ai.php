<?php
// chat_ai.php
header("Content-Type: application/json");

// Lấy dữ liệu người dùng gửi lên
$input = json_decode(file_get_contents("php://input"), true);
$prompt = $input["prompt"] ?? "Xin chào! Tôi là trợ lý GreenStep.";

// API key của OpenAI - Thay thế bằng API key thật của bạn
$api_key = "sk-...YOUR_API_KEY_HERE..."; 

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $api_key
]);

// Cấu hình request gửi đến OpenAI API
$data = [
    "model" => "gpt-3.5-turbo",
    "messages" => [
        [
            "role" => "system",
            "content" => "Bạn là GreenBot, trợ lý của hệ thống GreenStep. Hãy trả lời thân thiện, ngắn gọn và bằng tiếng Việt."
        ],
        [
            "role" => "user", 
            "content" => $prompt
        ]
    ],
    "temperature" => 0.7,
    "max_tokens" => 200
];

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

try {
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['error'])) {
        throw new Exception($result['error']['message']);
    }
    
    echo json_encode([
        "status" => "success",
        "choices" => [
            [
                "message" => [
                    "content" => $result['choices'][0]['message']['content']
                ]
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}

curl_close($ch);

error_log("Request to OpenAI: " . json_encode($data));
error_log("Response from OpenAI: " . $response);
