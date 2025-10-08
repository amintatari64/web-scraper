# web-scraper
This script is designed to **monitor changes on websites**.  
Every change is logged, the HTML is saved, and notifications are sent via **ntfy** when a change occurs.

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

---
