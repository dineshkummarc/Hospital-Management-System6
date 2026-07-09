"""
config.py
---------
Central configuration for the Smart Hospital Assistant (Flask API).

This file holds settings only. It does NOT connect to anything by itself.
Update the values below (or better, set environment variables) to match
your existing Hospital Management System's MySQL database.

IMPORTANT:
This chatbot service connects to the SAME MySQL database your PHP project
already uses. It does not create new tables in Phase 1 - it only reads
existing ones (users, doctors, departments, patients, appointments,
prescriptions).
"""

import os
from dotenv import load_dotenv
load_dotenv()

class Config:
    # ------------------------------------------------------------------
    # MySQL Database Configuration
    # Use the SAME credentials your PHP project (config.php / db.php) uses.
    # ------------------------------------------------------------------
    MYSQL_HOST = os.environ.get("HMS_DB_HOST", "localhost")
    MYSQL_PORT = int(os.environ.get("HMS_DB_PORT", 3306))
    MYSQL_USER = os.environ.get("HMS_DB_USER", "root")
    MYSQL_PASSWORD = os.environ.get("HMS_DB_PASSWORD", "")
    MYSQL_DATABASE = os.environ.get("HMS_DB_NAME", "hospital_management")

    # ------------------------------------------------------------------
    # Flask App Configuration
    # ------------------------------------------------------------------
    FLASK_HOST = os.environ.get("HMS_FLASK_HOST", "0.0.0.0")
    FLASK_PORT = int(os.environ.get("HMS_FLASK_PORT", 5000))
    DEBUG = os.environ.get("HMS_FLASK_DEBUG", "True") == "True"

    # ------------------------------------------------------------------
    # CORS Configuration
    # Add the exact origin(s) where your PHP site runs.
    # Examples: "http://localhost", "http://localhost/hospital_system",
    #           "https://yourdomain.com"
    # ------------------------------------------------------------------
    ALLOWED_ORIGINS = os.environ.get(
        "HMS_ALLOWED_ORIGINS",
        "http://localhost,http://127.0.0.1"
    ).split(",")

    # ------------------------------------------------------------------
    # Database Schema Mapping
    # ------------------------------------------------------------------
    # These constants exist so Phase 3's doctor lookup query can be
    # pointed at YOUR real table/column names by editing values here,
    # instead of hunting through SQL strings inside doctor_service.py.
    #
    # IMPORTANT: these are best-effort common names for a Hospital
    # Management System. Open your existing PHP project's database
    # (or db.php / your `doctors` and `departments` table definitions)
    # and update anything below that doesn't match your real schema.
    # ------------------------------------------------------------------
    TBL_DOCTORS = "doctors"
    TBL_DEPARTMENTS = "departments"

    DOCTORS_COL_ID = "id"
    DOCTORS_COL_NAME = "name"
    DOCTORS_COL_SPECIALIZATION = "specialization"
    DOCTORS_COL_EXPERIENCE = "experience"          # numeric years
    DOCTORS_COL_FEE = "consultation_fee"
    DOCTORS_COL_DEPARTMENT_ID = "department_id"    # FK into departments table
    DOCTORS_COL_STATUS = "status"                  # column marking active/inactive

    # The value stored in DOCTORS_COL_STATUS that means "this doctor is
    # active / approved / visible". Change to 1, "Active", "approved",
    # etc. to match your actual data.
    DOCTORS_STATUS_ACTIVE_VALUE = "active"

    DEPARTMENTS_COL_ID = "id"
    DEPARTMENTS_COL_NAME = "name"

    # ------------------------------------------------------------------
    # Phase 5: Appointments / Prescriptions / Patients Schema Mapping
    # ------------------------------------------------------------------
    # Same idea as above - best-effort common names. Update these to
    # match your real schema before testing "my appointment" / "my
    # prescription" / "my doctor" for a logged-in user.
    #
    # IMPORTANT ASSUMPTION: many Hospital Management Systems keep a
    # separate `patients` table (with its own id) linked to `users` via
    # a user_id foreign key, rather than using the users.id directly in
    # appointments/prescriptions. This is controlled by
    # USE_SEPARATE_PATIENTS_TABLE below:
    #
    #   True  (default): appointments.patient_id / prescriptions.patient_id
    #          store a `patients.id`, and `patients.user_id` links back
    #          to the logged-in user's `users.id`. appointment_service.py
    #          will look up the patient row first.
    #   False: appointments.patient_id / prescriptions.patient_id store
    #          the logged-in user's `users.id` directly - no lookup needed.
    #
    # Check your actual `patients` and `appointments` table definitions
    # and flip this if needed.
    # ------------------------------------------------------------------
    USE_SEPARATE_PATIENTS_TABLE = True

    TBL_PATIENTS = "patients"
    PATIENTS_COL_ID = "id"
    PATIENTS_COL_USER_ID = "user_id"   # FK back to users.id

    TBL_APPOINTMENTS = "appointments"
    APPT_COL_ID = "id"
    APPT_COL_PATIENT_ID = "patient_id"   # FK to patients.id (or users.id - see above)
    APPT_COL_DOCTOR_ID = "doctor_id"
    APPT_COL_DATE = "appointment_date"
    APPT_COL_TIME = "appointment_time"
    APPT_COL_STATUS = "status"           # e.g. 'pending', 'approved', 'completed', 'cancelled'

    TBL_PRESCRIPTIONS = "prescriptions"
    PRESCRIPTION_COL_ID = "id"
    PRESCRIPTION_COL_PATIENT_ID = "patient_id"
    PRESCRIPTION_COL_DOCTOR_ID = "doctor_id"
    PRESCRIPTION_COL_DETAILS = "details"     # column holding medicine/notes text
    PRESCRIPTION_COL_DATE = "created_at"     # when the prescription was issued

    # ------------------------------------------------------------------
    # Phase 6: Hospital Info (used by faq.py)
    # ------------------------------------------------------------------
    # Plain facts about your hospital, kept here so editing them doesn't
    # mean hunting through faq.py's answer strings. Fill in your real
    # details before deploying.
    # ------------------------------------------------------------------
    HOSPITAL_NAME = os.environ.get("HMS_HOSPITAL_NAME", "Our Hospital")
    HOSPITAL_TIMINGS = os.environ.get("HMS_HOSPITAL_TIMINGS", "Monday to Saturday, 8:00 AM to 8:00 PM")
    HOSPITAL_PHONE = os.environ.get("HMS_HOSPITAL_PHONE", "+91-XXXXXXXXXX")
    HOSPITAL_EMAIL = os.environ.get("HMS_HOSPITAL_EMAIL", "info@yourhospital.com")
    HOSPITAL_ADDRESS = os.environ.get("HMS_HOSPITAL_ADDRESS", "123 Hospital Road, Your City")
    EMERGENCY_PHONE = os.environ.get("HMS_EMERGENCY_PHONE", "+91-XXXXXXXXXX")
    EMERGENCY_AVAILABLE_247 = os.environ.get("HMS_EMERGENCY_24_7", "True") == "True"


config = Config()
