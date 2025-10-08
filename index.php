<?php
require 'config.php';

$storageDir = __DIR__ . '/storage';
$logFile = "$storageDir/watch.log";

// Function to get status of each site
function get_status($site) {
    $key = hash('sha256', $site['url']);
    $metaFile = "$GLOBALS[storageDir]/$key.meta.json";
    $dataFile = "$GLOBALS[storageDir]/$key.data.txt";

    if (!file_exists($metaFile)) return ['status'=>'Never checked','time'=>'-','file'=>'-'];
    $meta = json_decode(file_get_contents($metaFile), true);
    $fileUrl = file_exists($dataFile) ? "storage/" . basename($dataFile) : '-';
    return [
        'status' => 'Checked',
        'time' => $meta['time'],
        'file' => $fileUrl
    ];
}

// Read logs
$logs = file_exists($logFile) ? array_reverse(explode("\n", file_get_contents($logFile))) : [];
$logs = array_filter($logs);

// Handle adding new site via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new site
    if (isset($_POST['new_site_url'], $_POST['new_site_name'])) {
        $newSite = [
            'url' => trim($_POST['new_site_url']),
            'name' => trim($_POST['new_site_name'])
        ];
        $sites[] = $newSite;
        file_put_contents('config.php', "<?php\n\$sites = " . var_export($sites, true) . ";\n\$ntfy_topic = 'timetunnel';\n\$wait_seconds = 2;\n");
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }

    // Delete site
if (isset($_POST['delete_site_index'])) {
    $index = (int)$_POST['delete_site_index'];
    if (isset($sites[$index])) {
        $site = $sites[$index];
        $url = $site['url'];
        // Remove files
        $key = hash('sha256', $url);
        $metaFile = "$storageDir/$key.meta.json";
        $dataFile = "$storageDir/$key.data.txt";
        @unlink($metaFile);
        @unlink($dataFile);
        // Remove related logs
        if (file_exists($logFile)) {
            $logLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $logLines = array_filter($logLines, function($line) use ($url) {
                return strpos($line, $url) === false;
            });
            file_put_contents($logFile, implode("\n", $logLines) . "\n");
        }
        // Remove from array and save config
        array_splice($sites, $index, 1);
        file_put_contents('config.php', "<?php\n\$sites = " . var_export($sites, true) . ";\n\$ntfy_topic = 'timetunnel';\n\$wait_seconds = 2;\n");
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Monitoring Dashboard</title>
<link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet">
<style>
.log { max-height: 300px; overflow-y: scroll; padding: 10px; background-color: #f8f9fa; border: 1px solid #dee2e6; }
</style>
</head>
<body class="bg-light">

<div class="container my-4">
    <h1 class="mb-4">Website Monitoring Dashboard</h1>

    <!-- Buttons -->
    <div class="mb-3">
        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#guideModal">Guide</button>
        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addSiteModal">Add New Site</button>
        <button id="manualCheckBtn" class="btn btn-warning">Check Now (AJAX)</button>
    </div>

    <!-- Sites Status -->
    <h2 class="h4">Sites Status</h2>
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>URL</th>
                <th>Last Checked</th>
                <th>HTML File</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($sites as $index => $site):
            $status = get_status($site);
        ?>
            <tr>
                <td><?php echo htmlspecialchars($site['name']); ?></td>
                <td><a href="<?php echo $site['url']; ?>" target="_blank"><?php echo $site['url']; ?></a></td>
                <td><?php echo $status['time']; ?></td>
                <td>
                    <?php echo $status['file'] !== '-' ? "<a href='{$status['file']}' target='_blank'>View</a>" : '-'; ?>
                </td>
                <td>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this site?');">
                        <input type="hidden" name="delete_site_index" value="<?php echo $index; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Logs -->
    <h2 class="h4 mt-5">Logs</h2>
    <div class="log" id="logContainer">
        <?php foreach($logs as $line): ?>
            <div><?php echo htmlspecialchars($line); ?></div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Guide Modal -->
<div class="modal fade" id="guideModal" tabindex="-1" aria-labelledby="guideModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="guideModalLabel">Guide</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        To automate monitoring, set up a cron job that runs <code>watch.php</code> every few minutes. You can also manually check sites using the "Check Now" button.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Site Modal -->
<div class="modal fade" id="addSiteModal" tabindex="-1" aria-labelledby="addSiteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="addSiteModalLabel">Add New Site</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Site Name</label>
            <input type="text" class="form-control" name="new_site_name" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Site URL</label>
            <input type="url" class="form-control" name="new_site_url" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Add Site</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
<script src="assets/Jquery/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    $('#manualCheckBtn').click(function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Checking...');
        
        $.ajax({
            url: 'watch.php',
            method: 'GET',
            success: function(data) {
                console.log(data);
                location.reload(); // Reload page to show updated logs and status
            },
            error: function(err) {
                console.error(err);
                alert('Error occurred while checking.');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Check Now (AJAX)');
            }
        });
    });
});
</script>

</body>
</html>
