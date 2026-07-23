import os
import random
import pandas as pd

random.seed(42)

DATASET_PATH = "../datasets/assignment_risk_dataset.csv"

rows = []

for _ in range(1000):
    days_to_deadline = random.randint(-5, 30)
    priority = random.choice(["low", "medium", "high"])
    status = random.choice(["pending", "in_progress", "completed"])
    login_frequency = random.randint(0, 14)
    previous_late_submissions = random.randint(0, 6)
    pending_assignments = random.randint(0, 10)
    recent_activity_count = random.randint(0, 20)
    inactivity_days = random.randint(0, 21)

    score = 0

    if status == "completed":
        score -= 5
    elif status == "pending":
        score += 2
    elif status == "in_progress":
        score += 0.5

    if days_to_deadline < 0:
        score += 4
    elif days_to_deadline <= 1:
        score += 3
    elif days_to_deadline <= 3:
        score += 2
    elif days_to_deadline <= 7:
        score += 1

    if priority == "high":
        score += 2
    elif priority == "medium":
        score += 1

    if login_frequency <= 1:
        score += 2
    elif login_frequency <= 3:
        score += 1
    elif login_frequency >= 8:
        score -= 1

    if previous_late_submissions >= 4:
        score += 2
    elif previous_late_submissions >= 2:
        score += 1

    if pending_assignments >= 7:
        score += 2
    elif pending_assignments >= 4:
        score += 1

    if recent_activity_count <= 2:
        score += 2
    elif recent_activity_count <= 5:
        score += 1
    elif recent_activity_count >= 12:
        score -= 1

    if inactivity_days >= 10:
        score += 2
    elif inactivity_days >= 5:
        score += 1

    # Small noise to avoid a too-perfect dataset
    score += random.choice([-0.5, 0, 0.5])

    if score >= 6:
        risk_level = "high"
    elif score >= 3:
        risk_level = "medium"
    else:
        risk_level = "low"

    rows.append({
        "days_to_deadline": days_to_deadline,
        "priority": priority,
        "status": status,
        "login_frequency": login_frequency,
        "previous_late_submissions": previous_late_submissions,
        "pending_assignments": pending_assignments,
        "recent_activity_count": recent_activity_count,
        "inactivity_days": inactivity_days,
        "risk_level": risk_level
    })

os.makedirs(os.path.dirname(DATASET_PATH), exist_ok=True)

df = pd.DataFrame(rows)
df.to_csv(DATASET_PATH, index=False)

print(f"Dataset generated successfully: {DATASET_PATH}")
print(df.head())
print()
print("Risk distribution:")
print(df["risk_level"].value_counts())
