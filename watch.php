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

// Function to fetch rendered HTML (supports AJAX content)
function fetch_rendered_html($url, $waitSeconds = 2) {
    $renderUrl = "https://r.jina.ai/" . urlencode($url);
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: php-watch-script/2.0\r\n",
            'timeout' => 30
        ]
    ];
    $context = stream_context_create($opts);
    $html = @file_get_contents($renderUrl, false, $context);
    if ($html === false) throw new Exception("Failed to fetch page.");
    sleep($waitSeconds); // Wait additional seconds after render
    return $html;
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
        $html = fetch_rendered_html($url, $wait_seconds);
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
