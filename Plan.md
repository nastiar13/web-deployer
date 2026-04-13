# Private VPS Static Website Deployer (PHP Version)

## 1. Project Overview

A private deployment tool that allows you to:

- Create a new project
- Upload HTML/CSS/JS (or ZIP)
- Automatically configure Nginx
- Optionally enable SSL (Let's Encrypt)
- Delete projects cleanly

Environment:

- VPS (Ubuntu 22.04 recommended)
- Nginx
- PHP 8+
- PHP-FPM
- Certbot

This tool is for private use only (single admin).

---

# 2. Final Tech Stack

Backend: PHP 8+
Web Server: Nginx
Process: PHP-FPM
SSL: Certbot (Let's Encrypt)
Database: SQLite (recommended for simplicity)

---

# 3. Server Directory Structure

/var/www/
│
├── deployer/ # PHP deployer app
│ ├── public/
│ ├── src/
│ ├── config.php
│ └── database.sqlite
│
└── sites/ # Generated static sites
├── project1/
├── project2/

Nginx configs:
/etc/nginx/sites-available/
/etc/nginx/sites-enabled/

---

# 4. Core Features

## 4.1 Authentication

- Single admin login
- Password hashed (password_hash)
- Session-based auth
- Protect all routes

---

## 4.2 Create Project

Input:

- Project name
- Domain or subdomain
- Upload type (files or ZIP)

Process:

1. Sanitize project name (a-z, 0-9, dash only)
2. Create folder:
   /var/www/sites/{project-name}
3. Upload or extract files
4. Store project record in SQLite

---

## 4.3 File Upload

Allowed:

- .html
- .css
- .js
- images (png, jpg, svg, webp)
- ZIP (recommended method)

ZIP Flow:

1. Upload ZIP
2. Extract with ZipArchive
3. Delete ZIP after extraction

Never allow:

- .php
- .sh
- executables

---

## 4.4 Nginx Configuration Automation

Generate config file:

Path:
/etc/nginx/sites-available/{project}

Template:

server {
listen 80;
server_name project.yourdomain.com;

    root /var/www/sites/project;
    index index.html;

    location / {
        try_files $uri $uri/ =404;
    }

}

Steps:

1. Write config file
2. Create symlink:
   ln -s /etc/nginx/sites-available/project /etc/nginx/sites-enabled/
3. Reload nginx:
   systemctl reload nginx

---

## 4.5 SSL Automation (Optional)

Run:

certbot --nginx -d project.yourdomain.com --non-interactive --agree-tos -m your@email.com

Automate via:

shell_exec()

After SSL:

- HTTPS enabled automatically
- HTTP redirected

---

## 4.6 Delete Project

Steps:

1. Delete site folder
2. Remove nginx config
3. Remove symlink
4. Reload nginx
5. Optionally revoke SSL
6. Remove DB record

---

# 5. Sudo Configuration (Important)

Do NOT run PHP as root.

Create a limited user:

adduser deployer

Allow only specific commands:

Edit sudoers:

sudo visudo

Add:

deployer ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx
deployer ALL=(ALL) NOPASSWD: /usr/bin/certbot
deployer ALL=(ALL) NOPASSWD: /bin/ln
deployer ALL=(ALL) NOPASSWD: /bin/rm

Your PHP app runs under this user.

---

# 6. Security Rules

- Strict folder name sanitization
- Whitelist file extensions
- File size limit (e.g., 20MB)
- Disable directory listing in Nginx
- CSRF token in forms
- Sessions with secure cookie flags
- No PHP execution inside /var/www/sites/

Optional hardening:

- Set ownership:
  chown -R deployer:www-data /var/www/sites
- Set folder permission:
  755

---

# 7. Database Schema (SQLite)

Table: projects

Fields:

- id INTEGER PRIMARY KEY
- name TEXT
- domain TEXT
- folder_path TEXT
- ssl_enabled INTEGER
- created_at DATETIME

Purpose:

- Dashboard listing
- Management
- Future expansion

---

# 8. Dashboard Features

Display:

- Project name
- Domain
- Status (Live / SSL enabled)
- Created date

Actions:

- Visit
- Delete
- Redeploy (future)

---

# 9. Deployment Flow

Login →
Create Project →
Upload ZIP →
System:

- Creates folder
- Extracts files
- Generates nginx config
- Reloads nginx
- Runs certbot
  Site Live

Time to deploy: ~5–15 seconds

---

# 10. Why This Architecture Is Correct

- Minimal complexity
- No unnecessary Node.js runtime
- Stable long-term
- Easy to maintain
- Perfect for private VPS usage
- No overengineering

---

# 11. Future Upgrades (Optional)

- Multi-user support
- API-based deploy endpoint
- Git auto-deploy
- Docker isolation per project
- Deployment logs
- Backup system

---

# 12. Final Result

You get a private mini Netlify-like tool:

Example:

Create project:
portfolio

Accessible at:
https://portfolio.yourdomain.com

Fully automated.
Private.
Lightweight.
Production-ready.
