# SMARTDEADLINE AI - Project Setup Guide

SMARTDEADLINE AI is an AI-powered assignment deadline risk prediction and notification system for distance learning students. The system uses Symfony/PHP for the web application, MySQL for the database, and Python/scikit-learn for the machine learning component.

## 1. Clone the Repository

```bash
git clone https://github.com/mary20205090/smartdeadline-ai.git
cd smartdeadline-ai
```

## 2. Install Required System Packages

```bash
sudo apt update
sudo apt install -y php php-cli php-common php-mbstring php-xml php-intl php-curl php-zip php-mysql unzip git curl mysql-server
```

Confirm PHP is installed:

```bash
php -v
```

## 3. Install Composer

Composer is used to manage PHP/Symfony dependencies.

```bash
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

## 4. Install Symfony CLI

```bash
curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | sudo -E bash
sudo apt install -y symfony-cli
symfony -v
```

## 5. Set Up MySQL Database

Start and enable MySQL:

```bash
sudo systemctl start mysql
sudo systemctl enable mysql
```

Open MySQL:

```bash
sudo mysql
```

Create the database and user:

```sql
CREATE DATABASE smartdeadline_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'smartdeadline_user'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD';

GRANT ALL PRIVILEGES ON smartdeadline_ai.* TO 'smartdeadline_user'@'localhost';

FLUSH PRIVILEGES;

EXIT;
```

Test the database user:

```bash
mysql -u smartdeadline_user -p smartdeadline_ai
```

Inside MySQL:

```sql
SHOW TABLES;
EXIT;
```

## 6. Configure Symfony Environment

Move into the Symfony application folder:

```bash
cd app
```

Create a local environment file:

```bash
cp .env .env.local
```

Open `.env.local` and update the database connection:

```env
DATABASE_URL="mysql://smartdeadline_user:YOUR_PASSWORD@127.0.0.1:3306/smartdeadline_ai?serverVersion=8.0&charset=utf8mb4"
```

Important: `.env.local` should not be committed to GitHub because it contains local credentials.

## 7. Install Symfony Dependencies

From inside the `app/` directory, run:

```bash
composer install
```

## 8. Run Database Migrations

Create/update the database schema using Doctrine migrations:

```bash
php bin/console doctrine:migrations:migrate
```

When prompted, type:

```bash
yes
```

Validate the schema:

```bash
php bin/console doctrine:schema:validate
```

Expected result:

```text
[OK] The mapping files are correct.
[OK] The database schema is in sync with the mapping files.
```

## 9. Confirm Database Tables

```bash
mysql -u smartdeadline_user -p smartdeadline_ai
```

Then run:

```sql
SHOW TABLES;
EXIT;
```

Expected tables include:

```text
activity_log
assignment
course
doctrine_migration_versions
messenger_messages
notification
prediction
user
```

## 10. Run the Symfony Development Server

From inside the `app/` directory:

```bash
symfony server:start
```

Open the local URL shown in the terminal, usually:

```text
http://127.0.0.1:8000
```

To stop the server:

```bash
Ctrl + C
```

## 11. Project Folder Structure

```text
smartdeadline-ai/
├── app/              # Symfony web application
├── datasets/         # Sample datasets for ML
├── docs/             # Documentation files
├── ml-service/       # Python ML service
├── README.md
└── .gitignore
```

## 12. Git Workflow

Check changes:

```bash
git status
```

Stage changes:

```bash
git add .
```

Commit changes:

```bash
git commit -m "Add setup documentation"
```

Push changes:

```bash
git push
```

## Notes

* Use Doctrine migrations to create and update database tables.
* Use Beekeeper or MySQL CLI only to inspect data, not to manually design tables.
* Keep `.env.local` private and uncommitted.
* Commit migration files because they allow the database schema to be recreated on another machine.
