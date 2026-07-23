import os
import joblib
import pandas as pd

from sklearn.compose import ColumnTransformer
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder
from sklearn.tree import DecisionTreeClassifier

DATASET_PATH = "../datasets/assignment_risk_dataset.csv"
MODEL_PATH = "models/assignment_risk_decision_tree.joblib"

df = pd.read_csv(DATASET_PATH)

features = [
    "days_to_deadline",
    "priority",
    "status",
    "login_frequency",
    "previous_late_submissions",
    "pending_assignments",
    "recent_activity_count",
    "inactivity_days",
]

target = "risk_level"

X = df[features]
y = df[target]

categorical_features = ["priority", "status"]
numeric_features = [
    "days_to_deadline",
    "login_frequency",
    "previous_late_submissions",
    "pending_assignments",
    "recent_activity_count",
    "inactivity_days",
]

preprocessor = ColumnTransformer(
    transformers=[
        ("categorical", OneHotEncoder(handle_unknown="ignore"), categorical_features),
        ("numeric", "passthrough", numeric_features),
    ]
)

model = DecisionTreeClassifier(
    criterion="gini",
    max_depth=6,
    random_state=42
)

pipeline = Pipeline(
    steps=[
        ("preprocessor", preprocessor),
        ("classifier", model),
    ]
)

X_train, X_test, y_train, y_test = train_test_split(
    X,
    y,
    test_size=0.2,
    random_state=42,
    stratify=y
)

pipeline.fit(X_train, y_train)

y_pred = pipeline.predict(X_test)

accuracy = accuracy_score(y_test, y_pred)

print("Decision Tree model trained successfully.")
print(f"Accuracy: {accuracy:.2f}")
print()
print("Classification Report:")
print(classification_report(y_test, y_pred))
print("Confusion Matrix:")
print(confusion_matrix(y_test, y_pred))

os.makedirs(os.path.dirname(MODEL_PATH), exist_ok=True)
joblib.dump(pipeline, MODEL_PATH)

print()
print(f"Model saved successfully: {MODEL_PATH}")
