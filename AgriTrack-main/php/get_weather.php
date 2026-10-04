<?php
require_once 'require_user_session.php';
require_once 'db.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET and POST requests are allowed.']);
    exit;
}

date_default_timezone_set('Asia/Manila');

function fetchWeatherSnapshot(string $apiKey): array
{
    $url = 'http://api.weatherstack.com/current?access_key=' . rawurlencode($apiKey)
        . '&query=' . rawurlencode('Orani,Bataan,Philippines')
        . '&units=m';

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Could not initialize the weather request.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
    ]);
    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($body === false) {
        throw new RuntimeException('Could not reach Weatherstack: ' . $curlError);
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Weatherstack returned an invalid response.');
    }
    if ($statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException('Weatherstack request failed with HTTP ' . $statusCode . '.');
    }
    if (($data['success'] ?? true) === false) {
        $providerError = $data['error']['info'] ?? 'The provider rejected the request.';
        throw new RuntimeException('Weatherstack error: ' . $providerError);
    }
    if (!isset($data['current'], $data['location'])) {
        throw new RuntimeException('Weatherstack did not return current conditions.');
    }

    $current = $data['current'];
    $location = $data['location'];
    return [
        'location' => implode(', ', array_filter([
            $location['name'] ?? 'Orani',
            $location['region'] ?? 'Bataan',
        ])),
        'description' => $current['weather_descriptions'][0] ?? 'Conditions unavailable',
        'temperature' => $current['temperature'] ?? '--',
        'feelsLike' => $current['feelslike'] ?? '--',
        'humidity' => $current['humidity'] ?? '--',
        'windSpeed' => $current['wind_speed'] ?? '--',
        'rainfall' => $current['precip'] ?? '--',
        'uvIndex' => $current['uv_index'] ?? null,
        'observationTime' => $current['observation_time'] ?? null,
        'localTime' => $location['localtime'] ?? null,
    ];
}

function sendWeatherResponse(int $statusCode, array $response): void
{
    http_response_code($statusCode);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$conn = getDbConnection();
$responseCode = 200;
$response = [];
$lockAcquired = false;

try {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS weather_daily_cache (
            cache_key VARCHAR(64) NOT NULL PRIMARY KEY,
            fetched_on DATE NOT NULL,
            payload LONGTEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $lock = $conn->prepare('SELECT GET_LOCK(?, 20) AS acquired');
    $lockName = 'agritrack_weather_daily_lock';
    $lock->bind_param('s', $lockName);
    $lock->execute();
    $lockAcquired = (int) $lock->get_result()->fetch_assoc()['acquired'] === 1;
    $lock->close();

    if (!$lockAcquired) {
        $responseCode = 503;
        $response = ['success' => false, 'message' => 'The shared weather update is busy. Please try again shortly.'];
    } else {
        $today = date('Y-m-d');
        $cacheKey = 'orani-current';
        $cache = $conn->prepare('SELECT fetched_on, payload FROM weather_daily_cache WHERE cache_key = ?');
        $cache->bind_param('s', $cacheKey);
        $cache->execute();
        $cachedRow = $cache->get_result()->fetch_assoc();
        $cache->close();

        if ($cachedRow && $cachedRow['fetched_on'] === $today) {
            $response = json_decode($cachedRow['payload'], true);
            if (!is_array($response)) {
                throw new RuntimeException('The saved weather data is invalid.');
            }
            $response['success'] = true;
            $response['cached'] = true;
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $response = [
                'success' => true,
                'needsFetch' => true,
                'cached' => false,
            ];
        } else {
            $apiKey = trim((string) env('WEATHERSTACK_ACCESS_KEY', ''));
            if ($apiKey === '') {
                $responseCode = 503;
                $response = [
                    'success' => false,
                    'message' => 'Weather is not configured. Set WEATHERSTACK_ACCESS_KEY in the project root .env file.',
                ];
            } else {
                $fetchedAt = date(DATE_ATOM);
                $response = [
                    'success' => true,
                    'weather' => null,
                    'error' => 'The daily weather update did not complete. It will not retry until tomorrow.',
                    'fetchedAt' => $fetchedAt,
                    'cached' => false,
                ];
                $payload = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($payload === false) {
                    throw new RuntimeException('Could not encode the weather response.');
                }

                $reserve = $conn->prepare(
                    'INSERT INTO weather_daily_cache (cache_key, fetched_on, payload)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE fetched_on = VALUES(fetched_on), payload = VALUES(payload)'
                );
                $reserve->bind_param('sss', $cacheKey, $today, $payload);
                $reserve->execute();
                $reserve->close();

                $snapshot = null;
                $errorMessage = null;
                try {
                    $snapshot = fetchWeatherSnapshot($apiKey);
                } catch (Throwable $error) {
                    $errorMessage = $error->getMessage();
                    error_log('WeatherStack update failed: ' . $errorMessage);
                }

                $response = [
                    'success' => true,
                    'weather' => $snapshot,
                    'error' => $errorMessage,
                    'fetchedAt' => date(DATE_ATOM),
                    'cached' => false,
                ];
                $payload = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($payload === false) {
                    throw new RuntimeException('Could not encode the weather response.');
                }

                $save = $conn->prepare('UPDATE weather_daily_cache SET payload = ? WHERE cache_key = ?');
                $save->bind_param('ss', $payload, $cacheKey);
                $save->execute();
                $save->close();
            }
        }
    }
} catch (Throwable $error) {
    error_log('Shared weather endpoint failed: ' . $error->getMessage());
    $responseCode = 500;
    $response = ['success' => false, 'message' => 'Could not load the shared weather update. Check the server logs.'];
} finally {
    if ($lockAcquired) {
        $unlock = $conn->prepare('SELECT RELEASE_LOCK(?)');
        $unlock->bind_param('s', $lockName);
        $unlock->execute();
        $unlock->close();
    }
    $conn->close();
}

sendWeatherResponse($responseCode, $response);
