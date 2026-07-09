"""
intents.py
----------
Lightweight intent detection for routing chat messages.

Phase 3 told apart a handful of coarse intents (greeting / thanks /
symptom-or-other) so the symptom engine doesn't try to "diagnose" a
simple "hello".

Phase 4 added doctor-search detection: explicit requests like "find a
doctor", "heart doctor", or "Dr. Sharma" go to doctor_service.py's
search rather than (or before) the symptom engine.

Phase 5 added logged-in-user intents: "my appointment", "my next
appointment", "my doctor", "my prescription". These are checked BEFORE
doctor-search phrases, since e.g. "my doctor" contains the word
"doctor" but means something different from "find a doctor".

Phase 6 adds general hospital FAQ detection: timings, booking/cancelling
an appointment, departments, emergency contact, general contact info,
and consultation fees (faq.py). Checked after logged-in-user intents (a
personal question always wins over a generic one) and before doctor
search, since FAQ phrasing rarely overlaps with "find a doctor" style
requests.

This is intentionally simple keyword/phrase/regex matching, consistent
with the "no heavy AI frameworks" requirement for the whole project.
"""

import re

from symptom_engine import match_department
from faq import match_faq_topic

GREETING_PHRASES = [
    "hi", "hello", "hey", "hii", "hola",
    "good morning", "good afternoon", "good evening",
]

THANKS_PHRASES = [
    "thanks", "thank you", "thankyou", "thx", "tysm", "appreciate it",
]

MY_NEXT_APPOINTMENT_PHRASES = [
    "next appointment", "upcoming appointment", "when is my appointment",
    "when's my appointment", "my upcoming appointment",
]

MY_APPOINTMENT_PHRASES = [
    "my appointment", "my appointments", "appointment status",
    "my appointment status", "check my appointment", "do i have an appointment",
    "my booking", "my bookings",
]

MY_DOCTOR_PHRASES = [
    "my doctor", "who is my doctor", "who's my doctor", "assigned doctor",
    "which doctor am i seeing",
]

MY_PRESCRIPTION_PHRASES = [
    "my prescription", "my prescriptions", "my medicine", "my medicines",
    "my medication", "my medications",
]

DOCTOR_SEARCH_PHRASES = [
    "find doctor", "find a doctor", "find me a doctor", "search doctor",
    "search for doctor", "looking for doctor", "looking for a doctor",
    "need a doctor", "show doctors", "list doctors", "doctor available",
    "which doctors", "find a specialist", "recommend a doctor",
    "suggest a doctor", "book a doctor",
]

# Matches "dr sharma", "dr. rao", "doctor patel" and captures the name.
DOCTOR_NAME_PATTERN = re.compile(r"\b(?:dr\.?|doctor)\s+([a-zA-Z]+)", re.IGNORECASE)

# Intent constants (used by app.py so route logic doesn't rely on raw strings)
INTENT_GREETING = "greeting"
INTENT_THANKS = "thanks"
INTENT_MY_NEXT_APPOINTMENT = "my_next_appointment"
INTENT_MY_APPOINTMENT = "my_appointment"
INTENT_MY_DOCTOR = "my_doctor"
INTENT_MY_PRESCRIPTION = "my_prescription"
INTENT_FAQ = "faq"
INTENT_DOCTOR_SEARCH = "doctor_search"
INTENT_SYMPTOM_OR_OTHER = "symptom_or_other"


def detect_intent(message):
    """
    Classify a message into a coarse intent bucket.

    Returns one of:
        INTENT_GREETING             - e.g. "hi", "good morning"
        INTENT_THANKS               - e.g. "thanks", "thank you"
        INTENT_FAQ                  - e.g. "hospital timings", "how to book/
                                       cancel an appointment", "what
                                       departments do you have", "emergency
                                       contact", "consultation fees".
                                       Checked BEFORE the personal "my
                                       appointment" intents below, since
                                       e.g. "how do I cancel my appointment"
                                       contains the words "my appointment"
                                       but is a general how-to question,
                                       not a lookup of a specific booking.
        INTENT_MY_NEXT_APPOINTMENT  - e.g. "when's my next appointment"
        INTENT_MY_APPOINTMENT       - e.g. "my appointment status"
        INTENT_MY_DOCTOR            - e.g. "who is my doctor"
        INTENT_MY_PRESCRIPTION      - e.g. "my prescription"
        INTENT_DOCTOR_SEARCH        - e.g. "find a doctor", "heart doctor",
                                       "Dr. Sharma", "cardiology doctors"
        INTENT_SYMPTOM_OR_OTHER     - everything else; app.py hands this
                                       to symptom_engine.analyze_symptoms()
                                       to decide what it actually is.
    """
    normalized = (message or "").strip().lower()

    if not normalized:
        return INTENT_SYMPTOM_OR_OTHER

    # Greeting: message IS a greeting phrase, or starts with one
    # (e.g. "hello there", "hi, i have a question").
    if any(normalized == g or normalized.startswith(g) for g in GREETING_PHRASES):
        return INTENT_GREETING

    if any(t in normalized for t in THANKS_PHRASES):
        return INTENT_THANKS

    # General hospital FAQ, checked BEFORE the personal "my ..." intents.
    # This matters because phrases like "how do I cancel my appointment"
    # contain "my appointment" but are a how-to question, not a request
    # to look up one specific booking.
    if match_faq_topic(normalized):
        return INTENT_FAQ

    # Logged-in-user intents are checked BEFORE doctor-search phrasing,
    # since "my doctor" contains "doctor" but is not a doctor search.
    if any(p in normalized for p in MY_NEXT_APPOINTMENT_PHRASES):
        return INTENT_MY_NEXT_APPOINTMENT

    if any(p in normalized for p in MY_APPOINTMENT_PHRASES):
        return INTENT_MY_APPOINTMENT

    if any(p in normalized for p in MY_DOCTOR_PHRASES):
        return INTENT_MY_DOCTOR

    if any(p in normalized for p in MY_PRESCRIPTION_PHRASES):
        return INTENT_MY_PRESCRIPTION

    # Explicit "find a doctor" style phrasing
    if any(p in normalized for p in DOCTOR_SEARCH_PHRASES):
        return INTENT_DOCTOR_SEARCH

    # "Dr. <name>" / "doctor <name>" - looking up a specific doctor
    if DOCTOR_NAME_PATTERN.search(normalized):
        return INTENT_DOCTOR_SEARCH

    # A department/specialist alias with no symptom described, e.g.
    # "heart doctor", "cardiology", "skin specialist" - route to doctor
    # search rather than the symptom engine, since no symptom was named.
    if match_department(normalized) and (
        "doctor" in normalized or "specialist" in normalized or "doctors" in normalized
    ):
        return INTENT_DOCTOR_SEARCH

    return INTENT_SYMPTOM_OR_OTHER
