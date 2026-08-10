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
sudo apt install -y php php-cli php-common php-mbstring php-xml php-intl php-curl php-zip php-mysql unzip git curl mysql-server python3 python3-venv python3-pip
```

Confirm PHP and Python are installed:

```bash
php -v
python3 --version
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

Generate and set a local Symfony app secret:

```bash
openssl rand -hex 32
```

If the command prints nothing in your terminal, use PHP instead:

```bash
php -r 'echo bin2hex(random_bytes(32)).PHP_EOL;'
```

Copy the generated value into `.env.local`:

```env
APP_SECRET=PASTE_GENERATED_SECRET_HERE
```

`APP_SECRET` is required because Symfony uses it to sign security-sensitive values such as CSRF tokens and other framework tokens. Keep it private, use a different value for each environment, and do not leave it empty.

Important: `.env.local` should not be committed to GitHub because it contains local secrets such as database credentials and `APP_SECRET`.

Optional: configure email reminders in `.env.local`:

```env
MAILER_DSN=smtp://YOUR_GMAIL_ADDRESS:YOUR_GMAIL_APP_PASSWORD@smtp.gmail.com:587
MAILER_FROM="SMARTDEADLINE AI <YOUR_GMAIL_ADDRESS>"
```

Use a Gmail App Password for `MAILER_DSN`. Do not use your normal Google password, and do not commit `.env.local`.

## 7. Install Symfony Dependencies

From inside the `app/` directory, run:

```bash
composer install
```

The project also uses Symfony Process to call the Python ML prediction script:

```bash
composer require symfony/process
```

If dependencies are already listed in `composer.json`, `composer install` is enough.

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

## 10. Set Up the Python ML Service

Move into the ML service folder:

```bash
cd ../ml-service
```

Create and activate a virtual environment:

```bash
python3 -m venv .venv
source .venv/bin/activate
```

Install Python dependencies:

```bash
pip install --upgrade pip
pip install -r requirements.txt
```

Confirm packages:

```bash
python --version
pip list | grep -E "pandas|scikit-learn|joblib|numpy"
```

Expected packages include:

```text
pandas
scikit-learn
joblib
numpy
```

## 11. Generate Dataset and Train the Decision Tree Model

Generate the simulated training dataset:

```bash
python generate_dataset.py
```

Train the model:

```bash
python train_model.py
```

Expected output includes:

```text
Decision Tree model trained successfully.
Accuracy: ...
Model saved successfully: models/assignment_risk_decision_tree.joblib
```

The trained model is saved in:

```text
ml-service/models/assignment_risk_decision_tree.joblib
```

## 12. Test the ML Prediction Script

From inside `ml-service/`, run:

```bash
echo '{
  "days_to_deadline": 1,
  "priority": "high",
  "status": "pending",
  "login_frequency": 1,
  "previous_late_submissions": 4,
  "pending_assignments": 7,
  "recent_activity_count": 1,
  "inactivity_days": 10
}' | python predict.py
```

Expected output should include:

```json
{
  "risk_level": "high",
  "model_name": "decision_tree_model_v1"
}
```

## 13. Run the Symfony Development Server

Move back into the Symfony application folder:

```bash
cd ../app
```

Start the server:

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

## 14. Beekeeper Studio Database Connection

Beekeeper Studio can be used to view the database during testing or demonstration.

Use these values:

```text
Connection type: MySQL
Host: 127.0.0.1
Port: 3306
User: smartdeadline_user
Password: YOUR_PASSWORD
Database: smartdeadline_ai
```

Key tables to show during demo:

```text
assignment
activity_log
prediction
notification
course
user
```

## 15. Useful Test Queries

### View assignments

```sql
SELECT id, title, status, priority, deadline, course_id
FROM assignment
ORDER BY id DESC;
```

### View predictions

```sql
SELECT p.id, a.title, a.status, p.risk_level, p.probability, p.model_name, p.created_at
FROM prediction p
JOIN assignment a ON p.assignment_id = a.id
ORDER BY p.created_at DESC;
```

### View notifications

```sql
SELECT id, title, status, assignment_id, created_at
FROM notification
ORDER BY id DESC;
```

### View activity logs

```sql
SELECT id, event_type, assignment_id, created_at
FROM activity_log
ORDER BY id DESC;
```

## 16. Demo Flow

A typical lecturer/demo flow:

```text
1. Register or login as a student
2. Add a course
3. Add an assignment
4. View the assignment risk prediction
5. Check dashboard risk summary
6. Start an assignment
7. Complete an assignment
8. Confirm completed assignment becomes low risk
9. Check notifications
10. Dry-run email reminders
11. Send reminder emails
12. View database records in Beekeeper Studio
```

### Email reminder commands

Run a safe dry run first:

```bash
php bin/console app:send-deadline-reminders --dry-run
```

Send pending reminder emails:

```bash
php bin/console app:send-deadline-reminders
```

Test one recipient only:

```bash
php bin/console app:send-deadline-reminders --dry-run --recipient=maryposhia16@gmail.com
```

The command sends email only for due-soon, overdue, and AI high-risk alerts. It does not email for normal course creation, assignment creation, or page views.

## 17. Project Folder Structure

```text
smartdeadline-ai/
├── app/              # Symfony web application
├── datasets/         # Generated ML dataset
├── docs/             # Documentation files
├── ml-service/       # Python ML service
├── README.md
└── .gitignore
```

## 18. Git Workflow

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
git commit -m "Update project documentation"
```

Push changes:

```bash
git push
```

## Notes

- Use Doctrine migrations to create and update database tables.
- Use Beekeeper or MySQL CLI only to inspect data, not to manually design tables.
- Keep `.env.local` private and uncommitted because it contains database credentials and `APP_SECRET`.
- Keep `APP_SECRET` set to a strong random value in every local or production environment. Do not commit the real value to Git.
- Keep `ml-service/.venv/` private and uncommitted.
- Commit migration files because they allow the database schema to be recreated on another machine.
- Completed assignments are automatically treated as low risk.
- The ML model saved in predictions is identified as `decision_tree_model_v1`.
- The project uses a generated training dataset because real LMS historical data was not available.
