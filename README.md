# Web Scraper & Website Change Monitor

A lightweight PHP-based website monitoring dashboard that tracks changes across multiple websites, stores snapshots of fetched content, writes detailed logs, and sends instant notifications through [ntfy.sh](https://ntfy.sh/) whenever a monitored page changes.

This project is useful when you need a simple self-hosted tool to watch web pages for updates such as product availability, content changes, announcement pages, status pages, price changes, or any other website content that should be checked periodically.

<img width="1916" height="905" alt="Web Scraper Dashboard" src="https://github.com/user-attachments/assets/8da3b7fb-4b00-4f48-b2b0-98cbbde71000" />

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [How It Works](#how-it-works)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Cron Job Setup](#cron-job-setup)
- [Notifications](#notifications)
- [Storage and Logs](#storage-and-logs)
- [Security Notes](#security-notes)
- [Future Improvements](#future-improvements)
- [License](#license)

---

## Overview

**Web Scraper & Website Change Monitor** is a compact PHP application designed to monitor selected websites and detect changes in their rendered HTML content. It provides a browser-based dashboard where users can add websites, remove websites, manually trigger checks, view the latest monitoring status, and inspect saved snapshots.

The monitoring process is handled by `watch.php`. For every configured website, the script fetches the page content, generates a SHA-256 hash from the response, compares it with the previously stored hash, and determines whether the page has changed. On the first run, the application stores the initial snapshot. On later runs, it compares the new content against the saved version and logs the result.

When a change is detected, the application updates the stored snapshot, records the event in the log file, and sends a notification through ntfy.

---

## Key Features

- **Multi-site monitoring**: Track multiple websites from a single configuration file.
- **Web dashboard**: Manage monitored websites through a simple Bootstrap-powered interface.
- **Manual checks**: Run an immediate check directly from the dashboard using AJAX.
- **Automatic checks**: Schedule `watch.php` with a cron job for continuous background monitoring.
- **HTML snapshot storage**: Save the latest fetched content for each monitored website.
- **Hash-based change detection**: Use SHA-256 hashes to compare current and previous versions.
- **Event logging**: Store monitoring activity, errors, first-run events, unchanged pages, and detected changes.
- **ntfy notifications**: Receive real-time alerts when a monitored website changes.
- **Add and delete websites**: Add new targets or remove existing ones directly from the dashboard.
- **Rendered page fetching**: Fetch website content through `r.jina.ai`, which can help retrieve content from pages that rely on client-side rendering.

---

## How It Works

1. Websites are defined in `config.php` or added through the dashboard.
2. `watch.php` loops through every configured website.
3. The page content is fetched using the configured URL.
4. The fetched HTML/text response is hashed with SHA-256.
5. The hash is compared with the previous hash stored in `storage/*.meta.json`.
6. If no previous hash exists, the application saves the first snapshot.
7. If the hash is unchanged, the event is logged as "No change detected".
8. If the hash is different, the application:
   - logs the detected change,
   - sends an ntfy notification,
   - updates the metadata file,
   - saves the latest page content.

---

## Project Structure

```text
web-scraper/
├── index.php              # Main web dashboard
├── watch.php              # Website monitoring and change detection script
├── config.php             # Website list, ntfy topic, and wait-time settings
├── storage/               # Generated snapshots, metadata, and log files
│   ├── *.meta.json        # Stored hash, URL, and last-check time
│   ├── *.data.txt         # Latest saved page content
│   └── watch.log          # Monitoring log file
└── assets/
    ├── bootstrap/         # Bootstrap files used by the dashboard
    └── Jquery/            # jQuery used for AJAX manual checks
```

---

## Requirements

- PHP **7.4 or higher**
- A web server capable of running PHP, such as Apache, Nginx, or PHP's built-in development server
- Write permission for the `storage` directory
- Internet access from the server
- An ntfy topic if you want to receive notifications
- Cron access for automated periodic checks

---

## Installation

1. Clone the repository:

```bash
git clone https://github.com/amintatari64/web-scraper.git
cd web-scraper
```

2. Make sure PHP is installed:

```bash
php -v
```

3. Create the storage directory if it does not already exist:

```bash
mkdir -p storage
```

4. Give the web server permission to write to the storage directory:

```bash
chmod 755 storage
```

If your server user still cannot write to the directory, adjust ownership instead of using overly broad permissions:

```bash
chown -R www-data:www-data storage
```

5. Configure your websites and notification topic in `config.php`.

6. Open the project in your browser through your local or production web server.

---

## Configuration

The main configuration file is `config.php`.

Example configuration:

```php
<?php
$sites = [
    [
        'name' => 'Example Website',
        'url' => 'https://example.com'
    ],
    [
        'name' => 'Product Page',
        'url' => 'https://example.com/product'
    ]
];

// ntfy.sh topic used for notifications
$ntfy_topic = 'your-topic-name';

// Additional wait time after fetching a page
$wait_seconds = 2;
```

### Configuration Options

| Option | Description |
|---|---|
| `$sites` | List of websites that should be monitored. Each item contains a `name` and `url`. |
| `$ntfy_topic` | The ntfy topic used to send change notifications. |
| `$wait_seconds` | Additional delay used during page fetching. |

---

## Usage

### Open the Dashboard

Open `index.php` in your browser. The dashboard allows you to:

- view all monitored websites,
- see the last check time for each website,
- open the latest saved snapshot,
- add a new website,
- delete an existing website,
- manually run a monitoring check.

### Add a Website

1. Click **Add New Site**.
2. Enter a readable site name.
3. Enter the full website URL.
4. Submit the form.

The new website will be added to `config.php` and displayed in the dashboard.

### Run a Manual Check

Click **Check Now (AJAX)** from the dashboard. The application will call `watch.php`, check all configured websites, update the logs, and reload the dashboard when the check is complete.

### Delete a Website

Click the **Delete** button next to a website in the dashboard. The website will be removed from the configuration, and its stored snapshot files will be deleted.

---

## Cron Job Setup

For automatic monitoring, schedule `watch.php` to run periodically.

Example: run the monitor every 5 minutes:

```bash
*/5 * * * * /usr/bin/php /path/to/web-scraper/watch.php >> /path/to/web-scraper/storage/cron.log 2>&1
```

Make sure to replace `/path/to/web-scraper` with the absolute path to your project.

You can find the PHP path with:

```bash
which php
```

---

## Notifications

This project uses [ntfy.sh](https://ntfy.sh/) to send notifications when a monitored website changes.

To use notifications:

1. Choose a unique ntfy topic name.
2. Put that topic in `config.php`:

```php
$ntfy_topic = 'your-topic-name';
```

3. Subscribe to the same topic using the ntfy mobile app, web app, or API.

When a change is detected, the application sends a message like:

```text
Change detected on https://example.com
```

---

## Storage and Logs

The `storage` directory is used to save generated monitoring data.

For each monitored website, the application creates:

- a metadata file containing the URL, latest hash, and last check time,
- a data file containing the latest fetched page content,
- a shared `watch.log` file containing monitoring events.

Example metadata file:

```json
{
    "hash": "generated-sha256-hash",
    "url": "https://example.com",
    "time": "2026-01-01T12:00:00+00:00"
}
```

---

## Security Notes

- Do not expose sensitive ntfy topics in public deployments.
- Make sure the `storage` directory is writable by the server but not unnecessarily open to everyone.
- Avoid using `chmod 777` in production unless you fully understand the risks.
- If the dashboard is deployed publicly, consider adding authentication before using it in production.
- Validate and review monitored URLs before adding them to the system.

---

## Future Improvements

Possible improvements for future versions:

- User authentication for the dashboard
- Per-site notification settings
- Better visual diff between old and new snapshots
- Email, Telegram, or Discord notifications
- Configurable monitoring intervals per website
- Database-backed storage instead of PHP configuration files
- Exportable monitoring reports
- Improved error handling and retry logic

---

## License

No license has been specified yet. If this project is intended to be open source, consider adding a `LICENSE` file such as MIT, Apache-2.0, or GPL-3.0.

---

## Author

Developed by [Amin Tatari](https://github.com/amintatari64).
