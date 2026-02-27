# web-scraper
This script is designed to **monitor changes on websites**.  
Every change is logged, the HTML is saved, and notifications are sent via **ntfy** when a change occurs.

<img width="1916" height="905" alt="image" src="https://github.com/user-attachments/assets/8da3b7fb-4b00-4f48-b2b0-98cbbde71000" />

---

## Features
- Monitor multiple websites simultaneously
- Save previous versions of website HTML
- Log changes
- Send notifications via ntfy
- Add or remove sites from the dashboard
- Simple UI with Bootstrap and AJAX

---

## Installation
1. PHP 7.4 or higher required.
2. Upload the project folder to your server.
3. Open `config.php` and set your websites and ntfy topic.
4. Make sure the `storage` folder is writable (`chmod 777 storage`).
5. For automatic checks, create a Cron Job to run `watch.php` every few minutes.

---

## Usage
- Use the **Check Now** button on the dashboard to manually check sites.
- Use **Add New Site** to add a website.
- Use the delete button in the table to remove a site.

---

## Requirements
- PHP 7.4+
- Writable `storage` folder
- Internet access to fetch site HTML and send ntfy notifications
- (Recommended) Chromium/Google Chrome for pages that load data with AJAX

### AJAX-aware scraping
If a website loads content after the initial HTML (AJAX), the script tries these methods in order:
1. `render_api_template` (if you configure it)
2. Local headless browser (`chromium`/`google-chrome`) when available
3. `r.jina.ai` fallback
4. Direct fetch from original URL as a final safety fallback

Example:

```php
$wait_seconds = 5; // wait longer for slow AJAX responses
$render_api_template = ''; // optional: https://your-render-service/render?url={url}&wait={wait_ms}
```

### cPanel note
On normal shared cPanel hosting, `shell_exec` is usually disabled and Chrome/Chromium is usually unavailable.
So the script will still run, but truly JS-rendered pages may need an external render API (`$render_api_template`) for best results.

---
