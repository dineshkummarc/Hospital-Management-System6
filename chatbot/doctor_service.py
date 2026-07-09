"""
doctor_service.py
-------------------
Looks up ACTIVE doctors for a given department (or by name, or across all
departments) from your EXISTING MySQL database (the same `doctors` and
`departments` tables your PHP Hospital Management System already uses).

This module is READ-ONLY: it only ever runs SELECT queries. It does not
insert, update, or delete anything.

Table/column names come from config.py so this file doesn't need to
change if your schema uses different naming — see the "Database Schema
Mapping" section in config.py.
"""

import re

from database import run_query
from config import config

# Matches "Dr. Sharma", "dr sharma", "Doctor Mehta Kumar" etc. and
# captures just the name part, so a doctor-search message can be turned
# into a LIKE query without the user needing any special syntax.
DOCTOR_NAME_PATTERN = re.compile(r"\bdr\.?\s+([a-zA-Z]+(?:\s+[a-zA-Z]+){0,2})", re.IGNORECASE)


def extract_doctor_name_query(message):
    """
    Look for a "Dr. <Name>" style pattern in the message and return just
    the name part (e.g. "Do you have Dr. Sharma available?" -> "Sharma").
    Returns None if no such pattern is found.
    """
    if not message:
        return None
    match = DOCTOR_NAME_PATTERN.search(message)
    if match:
        return match.group(1).strip()
    return None


def get_active_doctors_by_department(db_department_name, limit=5):
    """
    Fetch active doctors belonging to a department.

    Args:
        db_department_name (str): department name as stored in the
            departments table (e.g. "Cardiology"). This should match
            the "db_name" value produced by symptom_engine.

        limit (int): max number of doctors to return.

    Returns:
        list[dict]: each dict has keys: id, name, specialization,
        experience, fee. Empty list if none found OR if the query fails
        (a DB/schema mismatch should never crash the chatbot - see the
        try/except below).
    """
    cfg = config

    sql = f"""
        SELECT
            doc.{cfg.DOCTORS_COL_ID} AS id,
            doc.{cfg.DOCTORS_COL_NAME} AS name,
            doc.{cfg.DOCTORS_COL_SPECIALIZATION} AS specialization,
            doc.{cfg.DOCTORS_COL_EXPERIENCE} AS experience,
            doc.{cfg.DOCTORS_COL_FEE} AS fee
        FROM {cfg.TBL_DOCTORS} doc
        INNER JOIN {cfg.TBL_DEPARTMENTS} dept
            ON doc.{cfg.DOCTORS_COL_DEPARTMENT_ID} = dept.{cfg.DEPARTMENTS_COL_ID}
        WHERE dept.{cfg.DEPARTMENTS_COL_NAME} = %s
          AND doc.{cfg.DOCTORS_COL_STATUS} = %s
        ORDER BY doc.{cfg.DOCTORS_COL_EXPERIENCE} DESC
        LIMIT %s
    """

    try:
        rows = run_query(
            sql,
            (db_department_name, cfg.DOCTORS_STATUS_ACTIVE_VALUE, limit),
        )
        return rows or []
    except Exception as e:
        # A schema mismatch (wrong table/column name) or a DB outage
        # should degrade gracefully - the chatbot still recommends a
        # department, just without a doctor list - rather than crashing
        # the whole /api/chat request.
        print(f"[doctor_service.py] Failed to fetch doctors for "
              f"'{db_department_name}': {e}")
        return []


def search_doctors_by_name(name_query, limit=5):
    """
    Search active doctors by (partial) name match, e.g. "Sharma" matches
    "Dr. Anil Sharma". Includes the department name in results since a
    name search can span multiple departments.

    Returns:
        list[dict]: id, name, specialization, experience, fee, department.
    """
    cfg = config
    like_query = f"%{name_query}%"

    sql = f"""
        SELECT
            doc.{cfg.DOCTORS_COL_ID} AS id,
            doc.{cfg.DOCTORS_COL_NAME} AS name,
            doc.{cfg.DOCTORS_COL_SPECIALIZATION} AS specialization,
            doc.{cfg.DOCTORS_COL_EXPERIENCE} AS experience,
            doc.{cfg.DOCTORS_COL_FEE} AS fee,
            dept.{cfg.DEPARTMENTS_COL_NAME} AS department
        FROM {cfg.TBL_DOCTORS} doc
        INNER JOIN {cfg.TBL_DEPARTMENTS} dept
            ON doc.{cfg.DOCTORS_COL_DEPARTMENT_ID} = dept.{cfg.DEPARTMENTS_COL_ID}
        WHERE doc.{cfg.DOCTORS_COL_NAME} LIKE %s
          AND doc.{cfg.DOCTORS_COL_STATUS} = %s
        ORDER BY doc.{cfg.DOCTORS_COL_EXPERIENCE} DESC
        LIMIT %s
    """

    try:
        rows = run_query(sql, (like_query, cfg.DOCTORS_STATUS_ACTIVE_VALUE, limit))
        return rows or []
    except Exception as e:
        print(f"[doctor_service.py] Failed to search doctors by name '{name_query}': {e}")
        return []


def get_all_active_doctors(limit=10):
    """
    Fetch active doctors across ALL departments, most experienced first.
    Used when someone asks to "find a doctor" without naming a
    department or a doctor.
    """
    cfg = config

    sql = f"""
        SELECT
            doc.{cfg.DOCTORS_COL_ID} AS id,
            doc.{cfg.DOCTORS_COL_NAME} AS name,
            doc.{cfg.DOCTORS_COL_SPECIALIZATION} AS specialization,
            doc.{cfg.DOCTORS_COL_EXPERIENCE} AS experience,
            doc.{cfg.DOCTORS_COL_FEE} AS fee,
            dept.{cfg.DEPARTMENTS_COL_NAME} AS department
        FROM {cfg.TBL_DOCTORS} doc
        INNER JOIN {cfg.TBL_DEPARTMENTS} dept
            ON doc.{cfg.DOCTORS_COL_DEPARTMENT_ID} = dept.{cfg.DEPARTMENTS_COL_ID}
        WHERE doc.{cfg.DOCTORS_COL_STATUS} = %s
        ORDER BY doc.{cfg.DOCTORS_COL_EXPERIENCE} DESC
        LIMIT %s
    """

    try:
        rows = run_query(sql, (cfg.DOCTORS_STATUS_ACTIVE_VALUE, limit))
        return rows or []
    except Exception as e:
        print(f"[doctor_service.py] Failed to fetch all active doctors: {e}")
        return []


def get_all_department_names():
    """
    Fetch the list of department names from the departments table.
    Used to prompt the user with real options when a doctor-search
    message doesn't name a department or a doctor.
    """
    cfg = config

    sql = f"SELECT {cfg.DEPARTMENTS_COL_NAME} AS name FROM {cfg.TBL_DEPARTMENTS} ORDER BY {cfg.DEPARTMENTS_COL_NAME} ASC"

    try:
        rows = run_query(sql)
        return [r["name"] for r in rows] if rows else []
    except Exception as e:
        print(f"[doctor_service.py] Failed to fetch department list: {e}")
        return []


def search_doctors(message, limit=5):
    """
    High-level entry point for the "Find Doctor" intent (Phase 4).

    Tries, in priority order:
        1. A named doctor ("Dr. Sharma", "doctor Rao")
        2. A department/specialist mention ("heart doctor", "cardiology")
        3. Falls back to a general active-doctor list if neither matched

    Args:
        message (str): the raw user message.
        limit (int): max doctors to return.

    Returns:
        dict: {
            "mode": "name" | "department" | "general",
            "query": str | None,             # the name searched for (mode == "name")
            "department_name": str | None,   # display name (mode == "department")
            "icon": str | None,               # department icon (mode == "department")
            "doctors": list[dict],
        }
    """
    # Imported here (not at module top) to avoid a circular import -
    # symptom_engine.py doesn't need to know about doctor_service.py.
    from symptom_engine import match_department, DEPARTMENT_KEYWORDS

    name_query = extract_doctor_name_query(message)
    if name_query:
        doctors = search_doctors_by_name(name_query, limit=limit)
        if doctors:
            return {
                "mode": "name",
                "query": name_query,
                "department_name": None,
                "icon": None,
                "doctors": doctors,
            }
        # Name pattern matched but nobody found - fall through to a
        # general list rather than returning nothing.

    dept_key = match_department(message or "")
    if dept_key:
        meta = DEPARTMENT_KEYWORDS[dept_key]
        doctors = get_active_doctors_by_department(meta["db_name"], limit=limit)
        return {
            "mode": "department",
            "query": None,
            "department_name": meta["display_name"],
            "icon": meta["icon"],
            "doctors": doctors,
        }

    doctors = get_all_active_doctors(limit=limit)
    return {
        "mode": "general",
        "query": None,
        "department_name": None,
        "icon": None,
        "doctors": doctors,
    }


def format_doctors_for_response(doctors):
    """
    Convert raw DB rows into the shape chatbot.js already knows how to
    render as doctor cards: name, specialization, experience, fee (and
    department, when available - e.g. for name search / all-doctors
    results where doctors may span multiple departments).
    Values are cast to strings/formatted so the frontend never has to
    guess about types coming back from MySQL.
    """
    formatted = []
    for doc in doctors:
        experience = doc.get("experience")
        fee = doc.get("fee")

        entry = {
            "name": doc.get("name") or "Unnamed Doctor",
            "specialization": doc.get("specialization") or "",
            "experience": str(experience) if experience is not None else "",
            "fee": _format_fee(fee),
        }
        if doc.get("department"):
            entry["department"] = doc["department"]

        formatted.append(entry)
    return formatted


def _format_fee(fee):
    """Best-effort formatting for a consultation fee value from the DB."""
    if fee is None:
        return ""
    try:
        return f"₹{float(fee):.0f}"
    except (TypeError, ValueError):
        return str(fee)
