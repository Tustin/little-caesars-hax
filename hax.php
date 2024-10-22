<?php

require_once __DIR__ . '/vendor/autoload.php';

use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Client;

$client = new Client([
    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0',
        'Accept-Language' => 'en-US',
    ],
    'proxy' => 'http://192.168.0.121:8888',
    'verify' => false,
]);

$fingerprint = Uuid::uuid4()->toString();
$startTime = Carbon::now()->toIso8601ZuluString();
$endTime = Carbon::now()->addHours(24)->toIso8601ZuluString();

// Generate process key
$response = $client->post('https://front-end-api.prod.fooji.com/v1/campaign/anonymous/start', [
    'json' => [
        'workflow_key' => 'call-of-duty-2024',
        'fingerprint' => $fingerprint,
        'client' => 'microsite',
    ]
]);

if ($response->getStatusCode() !== 200) {
    throw new Exception('Campaign start request failed with status code ' . $response->getStatusCode());
}

$data = json_decode($response->getBody()->getContents(), true);

$processKey = $data['process_key'];

echo 'Process key: ' . $processKey . PHP_EOL;

// Generate JWT
$response = $client->post('https://auth-api.prod.fooji.com/auth/anonymous', [
    'json' => [
        'client' => 'microsite',
        'fingerprint' => $fingerprint,
        'fingerprint_description' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0',
        'fooji_data' => [
            'campaign_user' => [
                'campaign_key' => 'call-of-duty-2024',
                'channel' => 'anonymous',
                'identifier' => $fingerprint,
                'user_key' => 'call-of-duty-2024-anonymous-' . $fingerprint
            ],
            'organization_id' => 327,
            'period' =>  $startTime . '/' . $endTime,
            'process_key' => $processKey,
            'workflow_key' => 'call-of-duty-2024'
        ],
        'language' => 'en',
    ]
]);

if ($response->getStatusCode() !== 200) {
    throw new Exception('Anonymous auth request failed with status code ' . $response->getStatusCode());
}

$data = json_decode($response->getBody()->getContents(), true);

if ($data['success'] !== true) {
    throw new Exception('Anonymous auth request failed with bad response');
}

$jwt = $data['jwt'];

echo 'JWT: ' . $jwt . PHP_EOL;

$response = $client->put('https://auth-api.prod.fooji.com/me', [
    'headers' => [
        'Authorization' => 'Bearer ' . $jwt,
    ],
    'json' => [
        'first_name' => 'Spaghetti',
        'last_name' => 'Man',
        'email' => 'tustin@tustin.dev',
        'console_platform' => 'battle.net',
        'language' => 'en',
    ],
]);

// Set activison ID for this user
$atvi = 10752186;

$response = $client->put('https://auth-api.prod.fooji.com/me', [
    'headers' => [
        'Authorization' => 'Bearer ' . $jwt,
    ],
    'json' => [
        'activision_user_id' => $atvi,
    ],
]);

// Modify image slightly to prevent hash collision
$image = imagecreatefromjpeg(__DIR__ . '/receipt.jpg');

$width = imagesx($image);
$height = imagesy($image);

$color = imagecolorallocate($image, 255, 255, 255);
imagesetpixel($image, rand(0, $width - 1), rand(0, $height - 1), $color);

imagejpeg($image, __DIR__ . '/receipt_1.jpg');

// Upload the receipt image using multipart form
$response = $client->post('https://functions.prod.fooji.com/upload', [
    'headers' => [
        'Authorization' => 'Bearer ' . $jwt,
    ],
    'multipart' => [
        [
            'name' => 'file',
            'contents' => fopen(__DIR__ . '/receipt_1.jpg', 'r'),
            'filename' => 'receipt_1.jpg',
            'headers' => [
                'Content-Type' => 'image/jpeg',
            ],
        ],
    ],
]);

if ($response->getStatusCode() !== 200) {
    throw new Exception('Receipt upload request failed with status code ' . $response->getStatusCode());
}

$response = $client->post('https://front-end-api.prod.fooji.com/v1/user/activate-workflow', [
    'headers' => [
        'Authorization' => 'Bearer ' . $jwt,
    ],
    'body' => '{}',
]);

if ($response->getStatusCode() !== 200) {
    throw new Exception('Activate workflow request failed with status code ' . $response->getStatusCode());
}

// Ping the campaign user until its finished
while (true) {
    $response = $client->get('https://front-end-api.prod.fooji.com/v1/campaign-user', [
        'headers' => [
            'Authorization' => 'Bearer ' . $jwt,
        ]
    ]);

    if ($response->getStatusCode() !== 200) {
        throw new Exception('Campaign continue request failed with status code ' . $response->getStatusCode());
    }

    $data = json_decode($response->getBody()->getContents(), true);

    if ($data['campaign_user']['current_status'] === 'finished') {
        break;
    }

    echo 'Still waiting for campaign' . PHP_EOL;

    sleep(5);
}

$response = $client->post('https://front-end-api.prod.fooji.com/v1/campaign/continue', [
    'headers' => [
        'Authorization' => 'Bearer ' . $jwt,
    ],
    'json' => [
        'process_key' => $processKey,
    ]
]);

if ($response->getStatusCode() !== 200) {
    throw new Exception('Campaign continue request failed with status code ' . $response->getStatusCode());
}

$response = $client->get('https://front-end-api.prod.fooji.com/v1/campaign-user', [
    'headers' => [
        'Authorization' => 'Bearer ' . $jwt,
    ]
]);

if ($response->getStatusCode() !== 200) {
    throw new Exception('Campaign user request failed with status code ' . $response->getStatusCode());
}

$data = json_decode($response->getBody()->getContents(), true);

dd($data);
