"""
app.py
------
Entry point for the Smart Hospital Assistant Flask API.

Phase 1: Flask setup, folder structure, database connection.
Phase 2: Chat widget UI, AJAX, PHP integration (added the /api/chat route
         as a stub).
Phase 3: /api/chat uses real intent detection (intents.py) and symptom
         analysis (symptom_engine.py) to recommend a department, then
         looks up active doctors for that department (doctor_service.py).
Phase 4: /api/chat also recognizes explicit doctor search requests - by
         name ("Dr. Sharma"), by department/alias ("heart doctor"), or
         generic ("find a doctor") - routed to doctor_service.search_doctors().
Phase 5: /api/chat answers "my appointment", "my next appointment", "my
         doctor", and "my prescription" for logged-in users, using
         appointment_service.py and the `user` object the widget sends
         with every request. Anonymous users get a "please log in"
         message instead of a database error.
Phase 6 (this update): /api/chat also answers general hospital FAQs -
         timings, how to book/cancel an appointment, departments,
         emergency contact, general contact info, and consultation fees
         (faq.py). These don't require login, unlike Phase 5's intents.

Run with:
    python app.py
"""

from flask import Flask, jsonify, request
from flask_cors import CORS

from config import config
from database import test_connection
from intents import (
    detect_intent,
    INTENT_GREETING,
    INTENT_THANKS,
    INTENT_DOCTOR_SEARCH,
    INTENT_MY_NEXT_APPOINTMENT,
    INTENT_MY_APPOINTMENT,
    INTENT_MY_DOCTOR,
    INTENT_MY_PRESCRIPTION,
    INTENT_FAQ,
)
from symptom_engine import analyze_symptoms, MEDICAL_DISCLAIMER, EMERGENCY_WARNING
from doctor_service import (
    get_active_doctors_by_department,
    format_doctors_for_response,
    search_doctors,
)
from appointment_service import (
    get_my_appointments,
    get_next_appointment,
    get_my_doctor,
    get_my_prescriptions,
    format_appointments_for_response,
    format_prescriptions_for_response,
)
from faq import match_faq_topic, get_faq_answer

app = Flask(__name__)

# ----------------------------------------------------------------------
# CORS
# Only allow the origins where your PHP hospital site actually runs.
# This keeps the API from being callable by random websites.
# ----------------------------------------------------------------------
CORS(
    app,
    resources={r"/api/*": {"origins": config.ALLOWED_ORIGINS}},
    supports_credentials=True,
)


@app.route("/api/health", methods=["GET"])
def health_check():
    """
    Simple endpoint to verify:
    1. The Flask service is running.
    2. The Flask service can reach the MySQL database used by the
       existing PHP Hospital Management System.

    Call this first after starting the server, and again after you
    point config.py at your real database credentials.
    """
    db_ok = test_connection()

    return jsonify({
        "status": "ok" if db_ok else "degraded",
        "service": "Smart Hospital Assistant API",
        "database_connected": db_ok,
    }), 200 if db_ok else 500


@app.route("/api/ping", methods=["GET"])
def ping():
    """Lightweight endpoint with no DB call - useful for a quick 'is Flask up' check."""
    return jsonify({"message": "pong"}), 200


@app.route("/api/chat", methods=["POST"])
def chat():
    """
    Main chatbot endpoint.

    Flow:
        1. Detect intent (greeting / thanks / logged-in-user lookups /
           FAQ / doctor search / symptom-or-other).
        2. Greeting/thanks get a short canned reply.
        3. Logged-in-user intents (my appointment/doctor/prescription)
           require an authenticated `user` object and query the database
           scoped to that patient only.
        4. FAQ intents (timings, booking/cancelling, departments,
           emergency, contact, fees) don't require login.
        5. Doctor search (by name, department, or generic) is a
           directory lookup, not a symptom recommendation.
        6. Everything else goes through the symptom engine. If keywords
           matched, recommend a department, fetch active doctors for it,
           and always include the medical disclaimer. If the message
           contained emergency keywords, add a warning too. If nothing
           matched at all, return a friendly fallback.
    """
    data = request.get_json(silent=True) or {}
    message = (data.get("message") or "").strip()
    user = data.get("user") or {}

    if not message:
        return jsonify({
            "type": "text",
            "reply": "Please type a message so I can help.",
        }), 400

    intent = detect_intent(message)

    if intent == INTENT_GREETING:
        return jsonify({
            "type": "text",
            "reply": "Hello! Tell me your symptoms, or ask about a department, "
                     "and I'll point you in the right direction.",
        }), 200

    if intent == INTENT_THANKS:
        return jsonify({
            "type": "text",
            "reply": "You're welcome! Let me know if there's anything else I can help with.",
        }), 200

    if intent == INTENT_DOCTOR_SEARCH:
        return jsonify(_build_doctor_search_response(message)), 200

    if intent == INTENT_MY_NEXT_APPOINTMENT:
        return jsonify(_build_next_appointment_response(user)), 200

    if intent == INTENT_MY_APPOINTMENT:
        return jsonify(_build_appointment_list_response(user)), 200

    if intent == INTENT_MY_DOCTOR:
        return jsonify(_build_my_doctor_response(user)), 200

    if intent == INTENT_MY_PRESCRIPTION:
        return jsonify(_build_prescription_response(user)), 200

    if intent == INTENT_FAQ:
        return jsonify(_build_faq_response(message)), 200

    # intent == symptom-or-other: run the symptom engine
    result = analyze_symptoms(message)

    if not result["is_symptom"]:
        # No department keywords matched, and no other intent (FAQ,
        # doctor search, logged-in lookup, greeting/thanks) matched either.
        return jsonify({
            "type": "text",
            "reply": "I couldn't match that to a specific symptom yet. Try describing "
                     "how you're feeling (e.g. \"I have a headache\" or \"chest pain\"), "
                     "or use one of the quick options below.",
        }), 200

    doctors_raw = get_active_doctors_by_department(result["db_name"])
    doctors = format_doctors_for_response(doctors_raw)

    response = {
        "type": "symptom_result",
        "reply": f"Based on what you described, I'd recommend {result['department_name']}.",
        "department": {
            "name": result["department_name"],
            "icon": result["icon"],
            "reason": result["reason"],
        },
        "doctors": doctors,
        "disclaimer": MEDICAL_DISCLAIMER,
    }

    if result["is_emergency"]:
        response["warning"] = EMERGENCY_WARNING

    if not doctors:
        # Department matched, but no active doctors found - still useful
        # to the user, just say so rather than showing an empty list.
        response["reply"] += (
            " I couldn't find an active doctor listed for this department right now - "
            "please check the Departments page or contact the front desk."
        )

    return jsonify(response), 200


def _build_doctor_search_response(message):
    """
    Runs doctor_service.search_doctors() and formats the result into the
    same JSON contract chatbot.js already knows how to render (reply +
    optional department + doctors list). No medical disclaimer here -
    this is a directory lookup, not a symptom-based recommendation.
    """
    result = search_doctors(message)
    doctors = format_doctors_for_response(result["doctors"])

    if result["mode"] == "name":
        if doctors:
            reply = f"Here's what I found for \"{result['query']}\":"
        else:
            reply = (
                f"I couldn't find an active doctor matching \"{result['query']}\". "
                "Try a department instead, e.g. \"cardiology doctors\"."
            )
        response = {"type": "doctor_search_result", "reply": reply, "doctors": doctors}

    elif result["mode"] == "department":
        if doctors:
            reply = f"Here are active doctors in {result['department_name']}:"
        else:
            reply = (
                f"I couldn't find an active doctor listed for {result['department_name']} "
                "right now - please check the Departments page or contact the front desk."
            )
        response = {
            "type": "doctor_search_result",
            "reply": reply,
            "department": {"name": result["department_name"], "icon": result["icon"]},
            "doctors": doctors,
        }

    else:  # "general"
        if doctors:
            reply = "Here are some of our active doctors. Tell me a department or a doctor's name to narrow this down."
        else:
            reply = "I couldn't find any active doctors right now - please contact the front desk."
        response = {"type": "doctor_search_result", "reply": reply, "doctors": doctors}

    return response


def _require_login(user):
    """
    Shared guard for every logged-in-user intent. Returns a "please log
    in" response dict if the user isn't authenticated, or None if it's
    safe to proceed.

    `user` is whatever the widget sent - see chatbot-widget.php /
    chatbot.js from Phase 2: { isLoggedIn, id, role, name }.
    """
    if not user.get("isLoggedIn") or not user.get("id"):
        return {
            "type": "text",
            "reply": "Please log in to your patient account to view this information.",
        }
    return None


def _build_appointment_list_response(user):
    guard = _require_login(user)
    if guard:
        return guard

    rows = get_my_appointments(user["id"], limit=5)
    if not rows:
        return {
            "type": "text",
            "reply": "You don't have any appointments on record.",
        }

    return {
        "type": "appointment_list",
        "reply": "Here are your appointments:",
        "appointments": format_appointments_for_response(rows),
    }


def _build_next_appointment_response(user):
    guard = _require_login(user)
    if guard:
        return guard

    row = get_next_appointment(user["id"])
    if not row:
        return {
            "type": "text",
            "reply": "You don't have any upcoming appointments scheduled.",
        }

    return {
        "type": "appointment_list",
        "reply": "Here's your next appointment:",
        "appointments": format_appointments_for_response([row]),
    }


def _build_my_doctor_response(user):
    guard = _require_login(user)
    if guard:
        return guard

    row = get_my_doctor(user["id"])
    if not row:
        return {
            "type": "text",
            "reply": "I couldn't find a doctor on record for you yet - "
                     "this shows up once you have a booked appointment.",
        }

    return {
        "type": "appointment_list",
        "reply": "Here's the doctor associated with your most relevant appointment:",
        "appointments": format_appointments_for_response([row]),
    }


def _build_prescription_response(user):
    guard = _require_login(user)
    if guard:
        return guard

    rows = get_my_prescriptions(user["id"], limit=5)
    if not rows:
        return {
            "type": "text",
            "reply": "You don't have any prescriptions on record.",
        }

    return {
        "type": "prescription_list",
        "reply": "Here are your prescriptions:",
        "prescriptions": format_prescriptions_for_response(rows),
    }


def _build_faq_response(message):
    """
    Resolves a general hospital FAQ question (timings, booking/cancelling,
    departments, emergency, contact, fees) into a plain-text answer.
    No login required, no medical disclaimer - this is directory-style
    information, not a symptom recommendation.
    """
    topic = match_faq_topic(message)
    if topic:
        answer = get_faq_answer(topic)
    else:
        # Defensive fallback - shouldn't normally happen, since
        # detect_intent() only returns INTENT_FAQ when a phrase from
        # faq.py's own topic list matched.
        answer = (
            "I can help with hospital timings, booking or cancelling an appointment, "
            "our departments, emergency contact info, general contact details, and "
            "consultation fees. What would you like to know?"
        )
    return {"type": "text", "reply": answer}


if __name__ == "__main__":
    app.run(
        host=config.FLASK_HOST,
        port=config.FLASK_PORT,
        debug=config.DEBUG,
    )
