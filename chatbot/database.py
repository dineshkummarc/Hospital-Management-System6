"""
database.py
-----------
Handles all MySQL connections for the Smart Hospital Assistant.

Design goals for Phase 1:
- Connect to the EXISTING Hospital Management System database.
- READ-ONLY usage in this phase (no INSERT / UPDATE / DELETE anywhere yet).
- One safe, reusable way to get a connection + run a query, so every
  later phase (symptom engine, doctor search, appointments, FAQs) uses
  the exact same pattern instead of reinventing DB code.

Uses PyMySQL (pure Python, no compiler needed, easy to install anywhere).
"""

import pymysql
import pymysql.cursors
from contextlib import contextmanager

from config import config


def get_connection():
    """
    Create and return a new PyMySQL connection to the hospital database.
    Cursor returns rows as dictionaries (e.g. row["doctor_name"])
    so the JSON responses to the chatbot are easy to build.
    """
    return pymysql.connect(
        host=config.MYSQL_HOST,
        port=config.MYSQL_PORT,
        user=config.MYSQL_USER,
        password=config.MYSQL_PASSWORD,
        database=config.MYSQL_DATABASE,
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
    )


@contextmanager
def get_cursor():
    """
    Context manager that yields a ready-to-use cursor and guarantees
    the connection is closed afterwards, even if an error occurs.

    Usage:
        with get_cursor() as cursor:
            cursor.execute("SELECT * FROM departments")
            rows = cursor.fetchall()
    """
    connection = get_connection()
    try:
        with connection.cursor() as cursor:
            yield cursor
    finally:
        connection.close()


def run_query(sql, params=None, fetch_one=False):
    """
    Convenience helper for simple SELECT queries.

    Args:
        sql (str): SQL query with %s placeholders (never use string
                    formatting / f-strings to insert values - this
                    prevents SQL injection).
        params (tuple|list): values to safely bind to the placeholders.
        fetch_one (bool): return a single row (dict) instead of a list.

    Returns:
        list[dict] | dict | None
    """
    with get_cursor() as cursor:
        cursor.execute(sql, params or ())
        if fetch_one:
            return cursor.fetchone()
        return cursor.fetchall()


def test_connection():
    """
    Quick sanity check used by the /api/health endpoint.
    Returns True if the database is reachable, False otherwise.
    """
    try:
        with get_cursor() as cursor:
            cursor.execute("SELECT 1")
            cursor.fetchone()
        return True
    except Exception as e:
        print(f"[database.py] Connection test failed: {e}")
        return False
