<?php
// watch.php
// Main script to monitor websites, save HTML, log events, and send notifications

require 'config.php';

$storageDir = __DIR__ . '/storage';
if (!is_dir($storageDir)) mkdir($storageDir, 0777, true);

$logFile = "$storageDir/watch.log";

// Function to send notification via ntfy
function send_ntfy_notification($topic, $message) {
    $url = "https://ntfy.sh/" . urlencode($topic);
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: text/plain\r\n",
            'content' => $message,
            'timeout' => 10
        ]
    ];
    $context = stream_context_create($opts);
    @file_get_contents($url, false, $context);
}

function is_shell_exec_available() {
    if (!function_exists('shell_exec')) {
        return false;
    }

    $disabled = ini_get('disable_functions');
    if (!is_string($disabled) || trim($disabled) === '') {
        return true;
    }

    $disabledFunctions = array_map('trim', explode(',', $disabled));
    return !in_array('shell_exec', $disabledFunctions, true);
}

function fetch_with_file_get_contents($url, $timeout = 30) {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: php-watch-script/2.2\r\n",
            'timeout' => $timeout,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true
        ]
    ];

    $context = stream_context_create($opts);
    $html = @file_get_contents($url, false, $context);
    if (is_string($html) && trim($html) !== '') {
        return $html;
    }

    return null;
}

function fetch_with_curl($url, $timeout = 30) {
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => 'php-watch-script/2.2'
    ]);

    $html = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error === '' && is_string($html) && trim($html) !== '') {
        return $html;
    }

    return null;
}

function is_blocked_or_invalid_snapshot($html) {
    if (!is_string($html)) {
        return true;
    }

    $normalized = strtolower($html);
    $blockedMarkers = [
        'unavailable for legal reasons',
        'error 451',
        'http 451',
        'access denied',
        'request blocked'
    ];

    foreach ($blockedMarkers as $marker) {
        if (strpos($normalized, $marker) !== false) {
            return true;
        }
    }

    return trim($html) === '';
}

function fetch_remote_html($url, &$methodUsed = null) {
    // Try both URL styles because some hosts/proxies are picky.
    $renderCandidates = [
        "https://r.jina.ai/http://" . preg_replace('#^https?://#', '', $url),
        "https://r.jina.ai/" . urlencode($url)
    ];

    foreach ($renderCandidates as $renderUrl) {
        $html = fetch_with_file_get_contents($renderUrl, 30);
        if ($html !== null && !is_blocked_or_invalid_snapshot($html)) {
            $methodUsed = "r.jina.ai:file_get_contents";
            return $html;
        }

        $html = fetch_with_curl($renderUrl, 30);
        if ($html !== null && !is_blocked_or_invalid_snapshot($html)) {
            $methodUsed = "r.jina.ai:curl";
            return $html;
        }
    }

    // Last resort: fetch original URL directly so check process does not stop on cPanel.
    $html = fetch_with_file_get_contents($url, 30);
    if ($html !== null && !is_blocked_or_invalid_snapshot($html)) {
        $methodUsed = "direct:file_get_contents";
        return $html;
    }

    $html = fetch_with_curl($url, 30);
    if ($html !== null && !is_blocked_or_invalid_snapshot($html)) {
        $methodUsed = "direct:curl";
        return $html;
    }

    throw new Exception("Failed to fetch page from all fallback methods.");
}

function fetch_via_render_api($url, $waitMs, $renderApiTemplate, &$methodUsed = null) {
    $renderUrl = str_replace(
        ['{url}', '{wait_ms}'],
        [urlencode($url), (string)$waitMs],
        $renderApiTemplate
    );

    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: php-watch-script/2.1\r\n",
            'timeout' => 45
        ]
    ];

    $context = stream_context_create($opts);
    $html = @file_get_contents($renderUrl, false, $context);
    if (is_string($html) && trim($html) !== '') {
        $methodUsed = "render_api";
        return $html;
    }

    return null;
}

// Function to fetch rendered HTML (supports AJAX content)
function fetch_rendered_html($url, $waitSeconds = 2, $renderApiTemplate = '') {
    $waitMs = max(0, (int)$waitSeconds * 1000);
    $renderApiTemplate = is_string($renderApiTemplate) ? trim($renderApiTemplate) : '';
    $methodUsed = null;

    // cPanel-friendly option: external render service URL template from config.php
    // Example: https://your-render-service/render?url={url}&wait={wait_ms}
    if ($renderApiTemplate !== '') {
        $html = fetch_via_render_api($url, $waitMs, $renderApiTemplate, $methodUsed);
        if ($html !== null) {
            return ['html' => $html, 'method' => $methodUsed];
        }
    }

    // Local browser path (works only if host allows shell_exec + has Chromium/Chrome installed)
    if (is_shell_exec_available()) {
        $browsers = ['chromium-browser', 'chromium', 'google-chrome', 'google-chrome-stable'];

        foreach ($browsers as $browser) {
            $browserPath = trim((string)@shell_exec("command -v $browser"));
            if ($browserPath === '') {
                continue;
            }

            $cmd = sprintf(
                '%s --headless=new --disable-gpu --no-sandbox --virtual-time-budget=%d --dump-dom %s 2>/dev/null',
                escapeshellarg($browserPath),
                $waitMs,
                escapeshellarg($url)
            );

            $html = @shell_exec($cmd);
            if (is_string($html) && trim($html) !== '') {
                return ['html' => $html, 'method' => "headless_browser:$browser"];
            }
        }
    }

    // Final fallback: remote renderer
    $html = fetch_remote_html($url, $methodUsed);
    return ['html' => $html, 'method' => $methodUsed ?? 'unknown'];
}

// Function to log events
function log_event($file, $message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($file, "[$timestamp] $message\n", FILE_APPEND);
}

// Loop through each site and check for changes
foreach ($sites as $site) {
    $url = $site['url'];
    $name = $site['name'];
    $key = hash('sha256', $url);
    $metaFile = "$storageDir/$key.meta.json";
    $dataFile = "$storageDir/$key.data.txt";

    try {
        echo "Fetching rendered page: $name\n";
        $result = fetch_rendered_html($url, $wait_seconds, $render_api_template ?? '');
        $html = $result['html'];
        $method = $result['method'];
        echo "Method used: $method\n";
        log_event($logFile, "[$name] Fetch method: $method");
    } catch (Exception $e) {
        $msg = "Fetch error for $name: " . $e->getMessage();
        echo $msg . "\n";
        log_event($logFile, $msg);
        continue;
    }

    $hash = hash('sha256', $html);
    $now = date('c');

    $prev = file_exists($metaFile) ? json_decode(file_get_contents($metaFile), true) : null;
    $prevHash = $prev['hash'] ?? null;

    if ($prevHash === null) {
        echo "First run for $name — snapshot saved.\n";
        log_event($logFile, "[$name] First run — snapshot saved.");
        file_put_contents($metaFile, json_encode(['hash' => $hash, 'url' => $url, 'time' => $now], JSON_PRETTY_PRINT));
        file_put_contents($dataFile, $html);
    } elseif ($prevHash === $hash) {
        echo "No change detected for $name.\n";
        log_event($logFile, "[$name] No change detected.");
    } else {
        echo "⚠️ Change detected for $name!\n";
        log_event($logFile, "[$name] Change detected!");
        send_ntfy_notification($ntfy_topic, "Change detected on $url");
        file_put_contents($metaFile, json_encode(['hash' => $hash, 'url' => $url, 'time' => $now], JSON_PRETTY_PRINT));
        file_put_contents($dataFile, $html);
    }
}
echo "Done.\n";
