"""
faq.py
------
General hospital FAQ answers - the questions anyone can ask, logged in
or not: timings, how to book/cancel an appointment, departments,
emergency contact, general contact info, and consultation fees.

This deliberately does NOT duplicate what other modules already do
well:
    - "who is my doctor" / "my appointment" / "my prescription" for a
      logged-in patient -> handled by appointment_service.py (Phase 5)
    - "find a doctor" / doctor availability -> handled by
      doctor_service.py's search_doctors() (Phase 4)

Same pattern as symptom_engine.py's keyword groups: each FAQ topic maps
to a list of trigger phrases, so intents.py can detect "this message is
an FAQ" and app.py can pick the right canned answer. To add a new FAQ
topic, add a new entry to FAQ_TOPICS below - nothing else needs to change.
"""

from config import config

# ---------------------------------------------------------------------------
# FAQ_TOPICS
# ---------------------------------------------------------------------------
# Each topic has:
#   "phrases": trigger phrases checked via substring match (like
#              symptom_engine.py's DEPARTMENT_KEYWORDS)
#   "answer":  a plain string, OR a zero-arg function that builds the
#              answer dynamically (e.g. "departments" queries the DB)
# ---------------------------------------------------------------------------


def _timings_answer():
    base = f"{config.HOSPITAL_NAME} is open {config.HOSPITAL_TIMINGS}."
    if config.EMERGENCY_AVAILABLE_247:
        base += " Emergency services are available 24/7."
    return base


def _departments_answer():
    # Imported here (not at module top) to avoid a circular import -
    # doctor_service.py doesn't need to know about faq.py.
    from doctor_service import get_all_department_names

    names = get_all_department_names()
    if names:
        return f"We have the following departments: {', '.join(names)}. Ask me about any of them, or describe your symptoms and I'll recommend one."
    return (
        "We offer departments including Cardiology, Neurology, Dermatology, Orthopedics, "
        "ENT, Gastroenterology, Pediatrics, Gynecology, and General Medicine. Ask me about "
        "any of them, or describe your symptoms and I'll recommend one."
    )


def _emergency_answer():
    return (
        f"For a medical emergency, please call {config.EMERGENCY_PHONE} immediately or come "
        f"directly to our Emergency department{' - open 24/7' if config.EMERGENCY_AVAILABLE_247 else ''}. "
        "If you or someone nearby is in immediate danger, contact local emergency services right away."
    )


def _contact_answer():
    return (
        f"You can reach {config.HOSPITAL_NAME} at {config.HOSPITAL_PHONE} or "
        f"{config.HOSPITAL_EMAIL}. We're located at {config.HOSPITAL_ADDRESS}."
    )


FAQ_TOPICS = {
    "timings": {
        "phrases": [
            "hospital timings", "opening hours", "working hours", "visiting hours",
            "when are you open", "what time do you open", "what time do you close",
            "are you open", "hospital hours",
        ],
        "answer": _timings_answer,
    },
    "book_appointment": {
        "phrases": [
            "how to book", "book an appointment", "book appointment", "schedule an appointment",
            "make an appointment", "booking process", "how do i book",
        ],
        "answer": (
            "To book an appointment: log in to your account, go to the Appointments section, "
            "choose a department and doctor, then pick an available time slot and confirm."
        ),
    },
    "cancel_appointment": {
        "phrases": [
            "how to cancel", "cancel an appointment", "cancel appointment",
            "cancel my appointment", "cancellation policy", "how do i cancel",
        ],
        "answer": (
            "To cancel an appointment: go to \"My Appointments\" in your patient dashboard and "
            "select Cancel next to the appointment you'd like to cancel."
        ),
    },
    "departments": {
        "phrases": [
            "what departments", "list of departments", "which departments",
            "departments do you have", "show departments", "available departments",
        ],
        "answer": _departments_answer,
    },
    "emergency": {
        "phrases": [
            "emergency contact", "emergency number", "urgent care",
            "emergency room", "in an emergency",
        ],
        # Deliberately NOT matching on the bare word "emergency" alone -
        # a message like "I have chest pain, is this an emergency?" is a
        # symptom description that should still go to the symptom engine
        # (which has its own, more relevant emergency warning). This
        # topic is only for someone directly asking who/how to contact
        # emergency services.
        "matcher": lambda n: "emergency" in n and any(
            w in n for w in ("contact", "number", "call", "phone", "reach")
        ),
        "answer": _emergency_answer,
    },
    "contact": {
        "phrases": [
            "contact number", "phone number", "how can i contact", "contact the hospital",
            "contact info", "contact information", "your address", "email address",
            "how do i reach you", "reach the hospital",
        ],
        "answer": _contact_answer,
    },
    "fees": {
        "phrases": [
            "consultation fee", "consultation fees", "how much does it cost",
            "how much is a consultation", "hospital fees", "cost of consultation",
            "charges for", "price of consultation", "how much does a consultation cost",
        ],
        # Fee questions are phrased too many different ways for a fixed
        # phrase list alone ("how much does a consultation cost", "what
        # are the charges", "consultation price" ...). A looser rule -
        # "consultation" plus any cost-related word - catches most of
        # these without needing an exhaustive list.
        "matcher": lambda n: "consultation" in n and any(
            w in n for w in ("fee", "fees", "cost", "charge", "charges", "price")
        ),
        "answer": (
            "Consultation fees vary by doctor and department. Tell me a specific department "
            "(e.g. \"cardiology\") or a doctor's name and I'll show you their fee."
        ),
    },
}


def match_faq_topic(message):
    """
    Match a message against FAQ trigger phrases (or a topic's custom
    matcher function, if it has one - see "fees" above).

    Returns the topic key (e.g. "timings") or None if nothing matched.
    """
    normalized = (message or "").lower()
    for topic_key, meta in FAQ_TOPICS.items():
        matcher = meta.get("matcher")
        if matcher and matcher(normalized):
            return topic_key
        if any(phrase in normalized for phrase in meta["phrases"]):
            return topic_key
    return None


def get_faq_answer(topic_key):
    """
    Resolve a topic key into its answer text. Handles both plain string
    answers and zero-arg functions (used for answers that need to query
    the database or read config, like "departments" or "timings").

    Returns None if topic_key isn't recognized.
    """
    meta = FAQ_TOPICS.get(topic_key)
    if not meta:
        return None

    answer = meta["answer"]
    return answer() if callable(answer) else answer


def get_all_faq_phrases():
    """
    Flatten every topic's trigger phrases into one list. Used by
    intents.py to detect "this message is an FAQ" without duplicating
    the phrase lists in two files.
    """
    phrases = []
    for meta in FAQ_TOPICS.values():
        phrases.extend(meta["phrases"])
    return phrases
