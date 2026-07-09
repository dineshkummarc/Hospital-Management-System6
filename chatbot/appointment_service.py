"""
appointment_service.py
------------------------
Looks up a LOGGED-IN patient's appointments, doctor, and prescriptions
from your EXISTING MySQL database. READ-ONLY - only ever runs SELECT
queries, never books, cancels, or modifies anything.

Everything here is keyed off the `user` object the widget already sends
with every /api/chat request (see chatbot-widget.php / chatbot.js from
Phase 2):

    { "isLoggedIn": true, "id": 42, "role": "patient", "name": "..." }

`id` there is the logged-in user's `users.id`. See config.py's
"Phase 5" schema section - in particular USE_SEPARATE_PATIENTS_TABLE -
for how that gets resolved into a `patients.id` for querying.
"""

from datetime import date, time, datetime

from database import run_query
from config import config


def resolve_patient_id(user_id):
    """
    Turn a logged-in user's `users.id` into the `patients.id` used by
    the appointments/prescriptions tables (or just return it unchanged,
    if your schema uses users.id directly - see config.py).

    Returns None if no matching patient record is found.
    """
    cfg = config

    if not cfg.USE_SEPARATE_PATIENTS_TABLE:
        return user_id

    sql = (
        f"SELECT {cfg.PATIENTS_COL_ID} AS id "
        f"FROM {cfg.TBL_PATIENTS} "
        f"WHERE {cfg.PATIENTS_COL_USER_ID} = %s "
        f"LIMIT 1"
    )
    try:
        row = run_query(sql, (user_id,), fetch_one=True)
        return row["id"] if row else None
    except Exception as e:
        print(f"[appointment_service.py] Failed to resolve patient id for user {user_id}: {e}")
        return None


def get_my_appointments(user_id, limit=5, upcoming_only=False):
    """
    Fetch this patient's appointments, earliest first, including doctor
    name and department.

    Args:
        user_id: the logged-in user's id (from the `user` object sent by the widget).
        limit (int): max rows to return.
        upcoming_only (bool): if True, only appointments today or later.

    Returns:
        list[dict]: id, appointment_date, appointment_time, status,
        doctor_name, department. Empty list if none found, the user
        isn't a recognized patient, or the query fails.
    """
    cfg = config
    patient_id = resolve_patient_id(user_id)
    if patient_id is None:
        return []

    date_filter = f"AND appt.{cfg.APPT_COL_DATE} >= CURDATE()" if upcoming_only else ""

    sql = f"""
        SELECT
            appt.{cfg.APPT_COL_ID} AS id,
            appt.{cfg.APPT_COL_DATE} AS appointment_date,
            appt.{cfg.APPT_COL_TIME} AS appointment_time,
            appt.{cfg.APPT_COL_STATUS} AS status,
            doc.{cfg.DOCTORS_COL_NAME} AS doctor_name,
            dept.{cfg.DEPARTMENTS_COL_NAME} AS department
        FROM {cfg.TBL_APPOINTMENTS} appt
        INNER JOIN {cfg.TBL_DOCTORS} doc
            ON appt.{cfg.APPT_COL_DOCTOR_ID} = doc.{cfg.DOCTORS_COL_ID}
        INNER JOIN {cfg.TBL_DEPARTMENTS} dept
            ON doc.{cfg.DOCTORS_COL_DEPARTMENT_ID} = dept.{cfg.DEPARTMENTS_COL_ID}
        WHERE appt.{cfg.APPT_COL_PATIENT_ID} = %s
        {date_filter}
        ORDER BY appt.{cfg.APPT_COL_DATE} ASC, appt.{cfg.APPT_COL_TIME} ASC
        LIMIT %s
    """

    try:
        rows = run_query(sql, (patient_id, limit))
        return rows or []
    except Exception as e:
        print(f"[appointment_service.py] Failed to fetch appointments for user {user_id}: {e}")
        return []


def get_next_appointment(user_id):
    """Returns the single soonest upcoming appointment (dict) or None."""
    rows = get_my_appointments(user_id, limit=1, upcoming_only=True)
    return rows[0] if rows else None


def get_my_doctor(user_id):
    """
    "My doctor" is interpreted as: the doctor from the next upcoming
    appointment, or - if there isn't one - the doctor from the most
    recent past appointment.

    Returns a single appointment-shaped dict (see get_my_appointments)
    or None if the patient has no appointments on record at all.
    """
    cfg = config

    next_appt = get_next_appointment(user_id)
    if next_appt:
        return next_appt

    patient_id = resolve_patient_id(user_id)
    if patient_id is None:
        return None

    sql = f"""
        SELECT
            appt.{cfg.APPT_COL_ID} AS id,
            appt.{cfg.APPT_COL_DATE} AS appointment_date,
            appt.{cfg.APPT_COL_TIME} AS appointment_time,
            appt.{cfg.APPT_COL_STATUS} AS status,
            doc.{cfg.DOCTORS_COL_NAME} AS doctor_name,
            dept.{cfg.DEPARTMENTS_COL_NAME} AS department
        FROM {cfg.TBL_APPOINTMENTS} appt
        INNER JOIN {cfg.TBL_DOCTORS} doc
            ON appt.{cfg.APPT_COL_DOCTOR_ID} = doc.{cfg.DOCTORS_COL_ID}
        INNER JOIN {cfg.TBL_DEPARTMENTS} dept
            ON doc.{cfg.DOCTORS_COL_DEPARTMENT_ID} = dept.{cfg.DEPARTMENTS_COL_ID}
        WHERE appt.{cfg.APPT_COL_PATIENT_ID} = %s
          AND appt.{cfg.APPT_COL_DATE} < CURDATE()
        ORDER BY appt.{cfg.APPT_COL_DATE} DESC, appt.{cfg.APPT_COL_TIME} DESC
        LIMIT 1
    """

    try:
        row = run_query(sql, (patient_id,), fetch_one=True)
        return row
    except Exception as e:
        print(f"[appointment_service.py] Failed to fetch most recent doctor for user {user_id}: {e}")
        return None


def get_my_prescriptions(user_id, limit=5):
    """
    Fetch this patient's prescriptions, most recent first, including the
    prescribing doctor's name.

    Returns:
        list[dict]: id, details, prescribed_on, doctor_name.
    """
    cfg = config
    patient_id = resolve_patient_id(user_id)
    if patient_id is None:
        return []

    sql = f"""
        SELECT
            presc.{cfg.PRESCRIPTION_COL_ID} AS id,
            presc.{cfg.PRESCRIPTION_COL_DETAILS} AS details,
            presc.{cfg.PRESCRIPTION_COL_DATE} AS prescribed_on,
            doc.{cfg.DOCTORS_COL_NAME} AS doctor_name
        FROM {cfg.TBL_PRESCRIPTIONS} presc
        INNER JOIN {cfg.TBL_DOCTORS} doc
            ON presc.{cfg.PRESCRIPTION_COL_DOCTOR_ID} = doc.{cfg.DOCTORS_COL_ID}
        WHERE presc.{cfg.PRESCRIPTION_COL_PATIENT_ID} = %s
        ORDER BY presc.{cfg.PRESCRIPTION_COL_DATE} DESC
        LIMIT %s
    """

    try:
        rows = run_query(sql, (patient_id, limit))
        return rows or []
    except Exception as e:
        print(f"[appointment_service.py] Failed to fetch prescriptions for user {user_id}: {e}")
        return []


# ---------------------------------------------------------------------------
# Formatting helpers - turn raw DB rows into JSON-safe, display-ready dicts
# ---------------------------------------------------------------------------

def format_appointments_for_response(rows):
    """Convert appointment rows into the shape chatbot.js renders as cards."""
    formatted = []
    for row in rows:
        formatted.append({
            "doctor_name": row.get("doctor_name") or "",
            "department": row.get("department") or "",
            "date": _format_date(row.get("appointment_date")),
            "time": _format_time(row.get("appointment_time")),
            "status": (row.get("status") or "").title(),
        })
    return formatted


def format_prescriptions_for_response(rows):
    """Convert prescription rows into the shape chatbot.js renders as cards."""
    formatted = []
    for row in rows:
        formatted.append({
            "doctor_name": row.get("doctor_name") or "",
            "prescribed_on": _format_date(row.get("prescribed_on")),
            "details": row.get("details") or "",
        })
    return formatted


def _format_date(value):
    """Best-effort formatting for a date/datetime value coming back from MySQL."""
    if value is None:
        return ""
    if isinstance(value, (date, datetime)):
        return value.strftime("%d %b %Y")
    return str(value)


def _format_time(value):
    """Best-effort formatting for a time/datetime value coming back from MySQL."""
    if value is None:
        return ""
    if isinstance(value, (time, datetime)):
        return value.strftime("%I:%M %p")
    return str(value)
