# 🌐 DomainMaster: Domain Portfolio & Expiry Tracker

**DomainMaster** is a specialized management tool designed to help users track domain registration periods, renewal dates, and status changes across multiple registrars in one central dashboard.

## 📋 Key Features
* **Expiry Countdown:** Real-time tracking of days remaining for domain renewal.
* **WHOIS Integration:** Quick access to domain ownership and registrar details.
* **Status Monitoring:** Track DNS changes and server availability.
* **Responsive UI:** Manage your portfolio from any device.

---

## 🛠️ Installation Guide (cPanel / Plesk)

This project is optimized for shared hosting environments. The database connection is pre-configured to link with your existing system.

### Option 1: cPanel Setup
1. **Upload:** Use **File Manager** to upload the project files to your `public_html` or a subdomain folder.
2. **Extract:** Unzip the files in the directory.
3. **PHP Version:** Ensure your hosting is running **PHP 7.4 or higher**.
4. **Environment:** Update your database credentials in the `config.php` (or equivalent) file.

### Option 2: Plesk Setup
1. **Upload:** Go to **Files** tab and upload your ZIP to the `httpdocs` directory.
2. **Extract:** Extract the files directly on the server.
3. **PHP Settings:** Verify that the PHP version matches the project requirements.
4. **Permissions:** Use the "Check Permissions" tool if you encounter any access issues.

---

## ⚙️ Database Configuration
> [!NOTE]
> This repository does not contain a `.sql` file. It is designed to connect to an existing database. Ensure your remote SQL settings allow the connection from your hosting IP.

---

## 🛡️ Support
If you have any issues with the setup, check your server's error logs or verify your remote database's firewall permissions.
