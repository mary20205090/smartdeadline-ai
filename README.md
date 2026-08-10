# SMARTDEADLINE AI

SMARTDEADLINE AI is an AI-powered assignment deadline risk prediction and notification system for distance learning students. The system helps students manage courses, track assignments, monitor deadlines, receive alerts, and view machine-learning-based predictions showing whether an assignment has a low, medium, or high risk of being missed.

## Project Overview

Distance learning students often handle many units, assignments, and deadlines at the same time. Since deadline information may be scattered across different notices or platforms, students may forget or miss submissions.

SMARTDEADLINE AI solves this problem by providing a centralized web application where students can:

- Register and log in
- Add courses
- Create and manage assignments
- Track assignment status
- Receive due-soon and overdue notifications
- View AI-based assignment risk predictions
- Get alerts for high-risk assignments

The system uses a Decision Tree machine-learning model to predict assignment deadline risk. Prediction results are saved in the database and displayed on the dashboard, assignment list, and assignment details page.

## Main Features

- User registration and login
- Course management
- Assignment management
- Assignment status workflow:
  - Pending
  - In progress
  - Completed
- Deadline tracking
- Due-soon and overdue notifications
- Activity logging for assignment actions
- Decision Tree machine-learning risk prediction
- Risk levels:
  - Low
  - Medium
  - High
- Prediction confidence percentage
- AI high-risk notification alerts
- Dashboard analytics
- Notification read/unread management
- MySQL database integration
- Beekeeper Studio database viewing support

## Technology Stack

### Backend

- PHP
- Symfony 7
- Doctrine ORM
- MySQL

### Frontend

- Twig templates
- HTML
- CSS

### Machine Learning

- Python
- pandas
- scikit-learn
- joblib
- Decision Tree Classifier

### Tools

- Composer
- Symfony CLI
- Git and GitHub
- Beekeeper Studio

## Project Structure

```text
smartdeadline-ai/
├── app/                  # Symfony web application
├── datasets/             # Generated ML dataset
├── docs/                 # Documentation files
├── ml-service/           # Python ML service and model files
├── README.md
└── .gitignore
```

## Machine Learning Model

The system uses a Decision Tree classifier to predict assignment deadline risk.

### Model Input Features

```text
days_to_deadline
priority
status
login_frequency
previous_late_submissions
pending_assignments
recent_activity_count
inactivity_days
```

### Model Output

```text
risk_level
probability
model_name
```

Example output:

```json
{
  "risk_level": "high",
  "probability": 1.0,
  "model_name": "decision_tree_model_v1"
}
```

The result is saved in the `prediction` database table and displayed in the web application.

## Main Database Tables

```text
user
course
assignment
activity_log
prediction
notification
doctrine_migration_versions
messenger_messages
```

## Quick Setup

For full installation steps, see:

```text
docs/SETUP.md
```

Basic setup summary:

```bash
git clone https://github.com/mary20205090/smartdeadline-ai.git
cd smartdeadline-ai
```

Install Symfony dependencies:

```bash
cd app
composer install
```

Configure database in:

```text
app/.env.local
```

Set a local Symfony app secret in the same file:

```bash
cd app
openssl rand -hex 32
```

If that command prints nothing in your terminal, use:

```bash
php -r 'echo bin2hex(random_bytes(32)).PHP_EOL;'
```

Copy the generated value into:

```env
APP_SECRET=PASTE_GENERATED_SECRET_HERE
```

`APP_SECRET` protects Symfony-signed values such as CSRF tokens and other framework security tokens. Keep the real value only in `.env.local` or server secrets, never in committed files.

Configure local email delivery in `.env.local` if you want to send reminder emails:

```env
MAILER_DSN=smtp://YOUR_GMAIL_ADDRESS:YOUR_GMAIL_APP_PASSWORD@smtp.gmail.com:587
MAILER_FROM="SMARTDEADLINE AI <YOUR_GMAIL_ADDRESS>"
```

Use a Gmail App Password, not your normal Google password. Keep the real value in `.env.local` only.

Run migrations:

```bash
php bin/console doctrine:migrations:migrate
```

Set up ML service:

```bash
cd ../ml-service
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python generate_dataset.py
python train_model.py
```

Run the Symfony app:

```bash
cd ../app
symfony server:start
```

Open:

```text
http://127.0.0.1:8000
```

## Beekeeper Studio Database Connection

Use these values to view the database:

```text
Connection type: MySQL
Host: 127.0.0.1
Port: 3306
User: smartdeadline_user
Password: use the password from app/.env.local
Database: smartdeadline_ai
```

## Useful Demo Queries

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

## Demo Flow

A typical demonstration flow:

```text
1. Register or login as a student
2. Add a course
3. Add an assignment
4. View the ML risk prediction
5. Check the dashboard risk summary
6. Start an assignment
7. Complete an assignment
8. Confirm completed assignment becomes low risk
9. Check notifications
10. Dry-run email reminders
11. Send reminder emails
12. View database records in Beekeeper Studio
```

Email reminder commands:

```bash
php bin/console app:send-deadline-reminders --dry-run
php bin/console app:send-deadline-reminders
```

To test one email account only:

```bash
php bin/console app:send-deadline-reminders --dry-run --recipient=maryposhia16@gmail.com
```

## Important Notes

- `.env.local` should not be committed because it contains local database credentials and `APP_SECRET`.
- `APP_SECRET` must be a strong random value and should be different for each environment.
- Reminder emails are sent only for important deadline alerts: due soon, overdue, and AI high-risk alerts.
- Normal actions such as creating courses or editing assignments do not send emails.
- `ml-service/.venv/` should not be committed.
- Completed assignments are automatically treated as low risk.
- The ML model name saved in the database is `decision_tree_model_v1`.
- The project uses a generated training dataset because real LMS historical data was not available.

## Author

Mary Mutua  
Bachelor of Information Technology  
Mount Kenya University
