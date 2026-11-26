# FlowProject

A collaborative project manager built with **object-oriented PHP** and **MySQL / MariaDB**: projects, Kanban task board, subtasks, images, links, live chat and a site administration area.

> The site name, logo and icon can be changed from the admin area (*Appearance* tab).

## Features

- **Projects** with members and project roles (`manager`, `membre`); access depends on project membership.
- **Kanban board** (To do / In progress / Done) with drag and drop on the whole card (mouse and touch).
- **Tasks**: assignment, "Take it" / "Release", subtasks with progress, attached images.
- **Important links** per project.
- **Project chat** with history, profile pictures, and deletion of your own messages (managers can delete any message).
- **Live updates** of the project page (tasks, members, links, chat) without reloading.
- **Profile**: picture, avatar colour, password change, account deletion.
- **Themes**: light, dark, or automatic based on the system setting.
- **Administration**: dashboard, users, projects, site appearance (name, logo, icon).

## Requirements

| Item | Version / detail |
|---|---|
| PHP | 8.0 or later |
| PHP extensions | `pdo_mysql`, `mbstring`, `gd` (with WebP support); `exif` recommended |
| Database | MySQL 8 or MariaDB 10.4+ |
| Web server | Apache with `mod_rewrite` (web root: the `public/` folder) |

## Automatic installation (Debian / Ubuntu)

```bash
sudo apt-get update && sudo apt-get install -y git
git clone https://github.com/zelriano36/flowproject.git
cd flowproject
sudo ./install.sh                 # or: sudo ./install.sh your-domain.com
```

The script:

1. installs Apache, PHP (with GD), MariaDB and rsync;
2. copies the project to `/var/www/flowproject`;
3. creates the `flowproject` database and a dedicated SQL user (generated password);
4. imports `sql/schema.sql` (tables and default roles);
5. writes `config/config.php`;
6. **asks for the administrator account details** (name, email, password) and adds it to the database;
7. creates the `storage/` folders (avatars, task images, branding) with the right permissions;
8. sets PHP upload limits (16 MB per image, 48 MB per request) and configures Apache.

Non-interactive mode:

```bash
sudo ADMIN_NAME="Admin" ADMIN_EMAIL="admin@example.com" ADMIN_PASS="a-strong-password" ./install.sh
```

If `ADMIN_PASS` is not set, a random password is generated and shown once.

### Updating

```bash
cd flowproject
git pull
sudo ./install.sh
```

The database, `config/config.php` and the `storage/` folder are left untouched.

## Manual installation (WAMP, XAMPP, existing server)

1. Copy the project into your web folder (e.g. `C:\wamp64\www\flowproject`).
2. Create the database:
   ```sql
   CREATE DATABASE flowproject CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import `sql/schema.sql` (phpMyAdmin > Import, or `mysql -u root -p flowproject < sql/schema.sql`).
4. Copy `config/config.example.php` to `config/config.php` and fill in your database settings:
   ```php
   return [
       'db' => [
           'host' => 'localhost',
           'name' => 'flowproject',
           'user' => 'root',
           'pass' => '',
       ],
   ];
   ```
5. Create the folders `storage/avatars`, `storage/task_images` and `storage/branding` (writable by the web server).
6. Open `http://localhost/flowproject/public/`.
7. Create an account on the sign-up page, then promote it to administrator:
   ```sql
   UPDATE `user` SET role_id = (SELECT id FROM `role` WHERE name = 'admin') WHERE email = 'you@example.com';
   ```

### Image upload limits

In the `php.ini` used by Apache (check with `phpinfo()`), then restart the services:

```ini
upload_max_filesize = 16M
post_max_size = 48M
memory_limit = 256M
```

Alternatively, these values can be set in `public/.htaccess` with `php_value` (PHP running as an Apache module).

## Project structure

```
flowproject/
├── config/          Configuration (config.php is not versioned)
├── includes/        Bootstrap, header/footer, helper functions
├── public/          Web root: pages, chat API, assets (css, js)
├── sql/schema.sql   Database schema and default data
├── src/             Classes (User, Project, Task, Subtask, TaskImage, ProjectLink, ProjectMessage, SiteSetting...)
├── storage/         Uploaded files (outside the public folder, not versioned)
└── install.sh       Automatic installation for Debian / Ubuntu
```

## Security

- Passwords hashed with `password_hash`, PDO prepared statements, CSRF tokens on forms.
- Uploaded files are checked (real type, size, dimensions) and re-encoded; they are stored outside `public/` and served by scripts that check permissions.
- Never commit `config/config.php` or database exports containing user accounts.
- For public use, put the site behind HTTPS (for example with Certbot).

## License

To be defined.
