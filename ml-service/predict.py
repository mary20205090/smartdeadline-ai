import json
import sys
import joblib
import pandas as pd

MODEL_PATH = "models/assignment_risk_decision_tree.joblib"

FEATURES = [
    "days_to_deadline",
    "priority",
    "status",
    "login_frequency",
    "previous_late_submissions",
    "pending_assignments",
    "recent_activity_count",
    "inactivity_days",
]

def main():
    try:
        input_data = json.loads(sys.stdin.read())

        row = {
            "days_to_deadline": int(input_data.get("days_to_deadline", 0)),
            "priority": input_data.get("priority", "medium"),
            "status": input_data.get("status", "pending"),
            "login_frequency": int(input_data.get("login_frequency", 0)),
            "previous_late_submissions": int(input_data.get("previous_late_submissions", 0)),
            "pending_assignments": int(input_data.get("pending_assignments", 0)),
            "recent_activity_count": int(input_data.get("recent_activity_count", 0)),
            "inactivity_days": int(input_data.get("inactivity_days", 0)),
        }

        model = joblib.load(MODEL_PATH)
        df = pd.DataFrame([row], columns=FEATURES)

        risk_level = model.predict(df)[0]

        probability = None
        if hasattr(model, "predict_proba"):
            probabilities = model.predict_proba(df)[0]
            classes = list(model.classes_)
            class_index = classes.index(risk_level)
            probability = round(float(probabilities[class_index]), 2)

        result = {
            "risk_level": risk_level,
            "probability": probability,
            "model_name": "decision_tree_model_v1",
            "features_used": row
        }

        print(json.dumps(result))

    except Exception as error:
        print(json.dumps({
            "error": str(error)
        }))
        sys.exit(1)

if __name__ == "__main__":
    main()
