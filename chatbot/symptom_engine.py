"""
symptom_engine.py
------------------
Keyword-group based symptom analysis, plus department-name matching used
by the doctor search feature (Phase 4).

This does NOT use exact string matching against a single symptom name.
Instead, each department maps to a list of keywords/phrases. A user's
free-text message is scored against every department by counting how
many of that department's keywords appear in the message. The
department with the most matches wins.

Deliberately NOT a machine-learning model — per the project's "keep it
lightweight" requirement, this is plain substring matching over curated
keyword lists, which is transparent, fast, and trivial to extend.

HOW TO EXTEND
-------------
To add a new symptom to an existing department, add a string to that
department's "keywords" list below. To add a whole new department, copy
one of the existing dict entries and change its values. Nothing else in
this file needs to change.

To make a keyword "emergency-worthy" (trigger the safety warning
regardless of which department is recommended), add it to
EMERGENCY_KEYWORDS near the bottom.

"synonyms" (added in Phase 4) are department-NAME style phrases (e.g.
"heart doctor", "skin specialist") used when someone is searching for a
doctor directly, as opposed to describing a symptom. Keeping these
separate from "keywords" avoids odd phrasing like "Based on the symptom
you mentioned: heart doctor" when the symptom engine is being used for
the wrong kind of message.
"""

MEDICAL_DISCLAIMER = (
    "This recommendation is based only on the symptoms you entered and is not a "
    "medical diagnosis. Please consult a qualified doctor."
)

EMERGENCY_WARNING = (
    "Your symptoms may be serious. If symptoms become severe, or you experience "
    "chest pain, severe breathing difficulty, or loss of consciousness, please seek "
    "emergency medical care immediately."
)

# ---------------------------------------------------------------------------
# Department keyword groups
# ---------------------------------------------------------------------------
# "db_name" must match the value stored in your `departments` table's name
# column (see config.py DEPARTMENTS_COL_NAME) exactly, so the doctor lookup
# in doctor_service.py can find the right department. Adjust if your table
# uses different department naming/casing.
# ---------------------------------------------------------------------------

DEPARTMENT_KEYWORDS = {
    "cardiology": {
        "display_name": "Cardiology",
        "db_name": "Cardiology",
        "icon": "❤️",
        "keywords": [
            "chest pain", "heart pain", "heart ache", "palpitation", "palpitations",
            "high bp", "high blood pressure", "breathlessness", "breathing difficulty",
            "difficulty breathing", "irregular heartbeat", "chest tightness",
            "heart attack", "racing heart", "shortness of breath",
        ],
        "synonyms": [
            "heart doctor", "heart specialist", "cardiologist", "cardiology",
        ],
    },
    "neurology": {
        "display_name": "Neurology",
        "db_name": "Neurology",
        "icon": "🧠",
        "keywords": [
            "headache", "migraine", "dizziness", "dizzy", "memory loss",
            "seizure", "numbness", "tingling", "fainting", "blurred vision",
            "loss of balance", "tremor",
        ],
        "synonyms": [
            "brain doctor", "neurologist", "nerve specialist", "neurology",
        ],
    },
    "dermatology": {
        "display_name": "Dermatology",
        "db_name": "Dermatology",
        "icon": "🩹",
        "keywords": [
            "itching", "rash", "skin allergy", "acne", "skin infection",
            "eczema", "hives", "skin irritation", "pimples", "dry skin",
        ],
        "synonyms": [
            "skin doctor", "skin specialist", "dermatologist", "dermatology",
        ],
    },
    "orthopedics": {
        "display_name": "Orthopedics",
        "db_name": "Orthopedics",
        "icon": "🦴",
        "keywords": [
            "joint pain", "fracture", "back pain", "leg pain", "knee pain",
            "bone pain", "shoulder pain", "sprain", "arthritis", "muscle pain",
            "spine pain", "neck pain",
        ],
        "synonyms": [
            "bone doctor", "orthopedic doctor", "orthopedist", "joint specialist",
            "orthopedics",
        ],
    },
    "ent": {
        "display_name": "ENT",
        "db_name": "ENT",
        "icon": "👂",
        "keywords": [
            "ear pain", "nose blockage", "throat pain", "sore throat",
            "hearing loss", "sinus", "ear infection", "blocked nose",
            "difficulty swallowing", "runny nose",
        ],
        "synonyms": [
            "ent doctor", "ear nose throat", "ent specialist", "ent",
        ],
    },
    "gastroenterology": {
        "display_name": "Gastroenterology",
        "db_name": "Gastroenterology",
        "icon": "🍽️",
        "keywords": [
            "stomach pain", "vomiting", "gas", "acidity", "diarrhea",
            "indigestion", "bloating", "nausea", "abdominal pain", "constipation",
        ],
        "synonyms": [
            "stomach doctor", "gastro doctor", "gastroenterologist", "gastroenterology",
        ],
    },
    "pediatrics": {
        "display_name": "Pediatrics",
        "db_name": "Pediatrics",
        "icon": "🧒",
        "keywords": [
            "child fever", "vaccination", "child cough", "baby not eating",
            "child rash", "infant", "toddler fever", "child not feeding",
        ],
        "synonyms": [
            "child doctor", "children doctor", "pediatrician", "child specialist",
            "pediatrics",
        ],
    },
    "gynecology": {
        "display_name": "Gynecology",
        "db_name": "Gynecology",
        "icon": "🌸",
        "keywords": [
            "pregnancy", "period pain", "menstrual", "women health",
            "missed period", "pcos", "irregular periods", "pelvic pain",
        ],
        "synonyms": [
            "women doctor", "women's doctor", "gynecologist", "lady doctor",
            "gynecology",
        ],
    },
    "general_medicine": {
        "display_name": "General Medicine",
        "db_name": "General Medicine",
        "icon": "🩺",
        "keywords": [
            "fever", "cold", "weakness", "infection", "body ache", "flu",
            "cough", "tiredness", "fatigue", "chills",
        ],
        "synonyms": [
            "general physician", "general doctor", "family doctor", "general medicine",
        ],
    },
}

# ---------------------------------------------------------------------------
# Emergency keywords
# ---------------------------------------------------------------------------
# If ANY of these appear in the message, the response is flagged as an
# emergency regardless of which department was recommended. Keep this list
# focused on genuinely urgent presentations.
# ---------------------------------------------------------------------------

EMERGENCY_KEYWORDS = [
    "chest pain", "breathing difficulty", "difficulty breathing",
    "not breathing", "severe bleeding", "unconscious", "unconsciousness",
    "seizure", "stroke", "blue lips", "severe chest pain", "heart attack",
    "can't breathe", "cannot breathe", "collapsed", "severe head injury",
]


def analyze_symptoms(message):
    """
    Analyze free-text symptom input and recommend a department.

    Args:
        message (str): the user's raw chat message.

    Returns:
        dict with keys:
            is_symptom (bool)      - whether any department keywords matched
            department_key (str)   - internal key, e.g. "cardiology"
            department_name (str)  - display name, e.g. "Cardiology"
            db_name (str)          - value to match against the departments table
            icon (str)             - emoji for display
            matched_keywords (list[str])
            reason (str)           - human-readable explanation
            is_emergency (bool)
            disclaimer (str)       - medical disclaimer, always populated
    """
    normalized = (message or "").lower()

    scores = {}
    matches_by_dept = {}

    for dept_key, meta in DEPARTMENT_KEYWORDS.items():
        matched = [kw for kw in meta["keywords"] if kw in normalized]
        if matched:
            scores[dept_key] = len(matched)
            matches_by_dept[dept_key] = matched

    is_emergency = any(kw in normalized for kw in EMERGENCY_KEYWORDS)

    if not scores:
        return {
            "is_symptom": False,
            "department_key": None,
            "department_name": None,
            "db_name": None,
            "icon": None,
            "matched_keywords": [],
            "reason": None,
            "is_emergency": is_emergency,
            "disclaimer": MEDICAL_DISCLAIMER,
        }

    # Pick the department with the highest keyword-match count.
    # Ties are broken by the order departments are defined above.
    best_dept_key = max(scores, key=lambda k: (scores[k], -list(DEPARTMENT_KEYWORDS.keys()).index(k)))
    meta = DEPARTMENT_KEYWORDS[best_dept_key]
    matched_keywords = matches_by_dept[best_dept_key]

    reason = _build_reason(matched_keywords)

    return {
        "is_symptom": True,
        "department_key": best_dept_key,
        "department_name": meta["display_name"],
        "db_name": meta["db_name"],
        "icon": meta["icon"],
        "matched_keywords": matched_keywords,
        "reason": reason,
        "is_emergency": is_emergency,
        "disclaimer": MEDICAL_DISCLAIMER,
    }


def _build_reason(matched_keywords):
    """Turn a list of matched keywords into a short, readable explanation."""
    if len(matched_keywords) == 1:
        return f"Based on the symptom you mentioned: {matched_keywords[0]}."
    shown = matched_keywords[:3]
    joined = ", ".join(shown)
    return f"Based on the symptoms you mentioned: {joined}."


def match_department(message):
    """
    Phase 4: match a message against department NAMES/synonyms rather than
    symptom keywords. Used for doctor-search flows like "find a heart
    doctor" or "I need a skin specialist", where the user is naming a
    department directly instead of describing how they feel. Also used
    by intents.py to route bare department mentions ("cardiology",
    "skin specialist") to doctor search instead of the symptom engine.

    Returns the department_key (e.g. "cardiology") or None if nothing
    matched. Look up DEPARTMENT_KEYWORDS[key] for the full metadata
    (display_name, db_name, icon).
    """
    normalized = (message or "").lower()

    for dept_key, meta in DEPARTMENT_KEYWORDS.items():
        # Check synonyms first (e.g. "heart doctor"), then fall back to
        # the plain department name itself (e.g. "cardiology").
        candidates = meta.get("synonyms", []) + [meta["display_name"].lower(), meta["db_name"].lower()]
        if any(candidate in normalized for candidate in candidates):
            return dept_key

    return None
