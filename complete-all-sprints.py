#!/usr/bin/env python3
"""
Complete all sprints on the Jira board.

Jira requires: future → active → closed
So this script activates each future sprint first, then immediately closes it.

Usage:
    python3 complete-all-sprints.py [--dry-run]
"""

import os
import sys
import time
import argparse
import requests
from requests.auth import HTTPBasicAuth

JIRA_BASE_URL = "https://undrafted.atlassian.net"
JIRA_BOARD_ID = 35


def get_auth():
    email = os.getenv("JIRA_EMAIL")
    token = os.getenv("JIRA_API_TOKEN")
    if not email or not token:
        print("❌ JIRA_EMAIL and JIRA_API_TOKEN must be set.")
        sys.exit(1)
    return HTTPBasicAuth(email, token)


def get_all_sprints(auth):
    """Fetch all sprints from the board."""
    sprints = []
    start_at = 0

    while True:
        r = requests.get(
            f"{JIRA_BASE_URL}/rest/agile/1.0/board/{JIRA_BOARD_ID}/sprint",
            auth=auth,
            headers={"Accept": "application/json"},
            params={"startAt": start_at, "maxResults": 50},
            timeout=30
        )
        if not r.ok:
            print(f"❌ Failed to fetch sprints: {r.status_code} {r.text}")
            sys.exit(1)

        data = r.json()
        batch = data.get("values", [])
        if not batch:
            break
        sprints.extend(batch)
        if data.get("isLast", True):
            break
        start_at += len(batch)

    return sprints


def set_sprint_state(auth, sprint_id, state, dry_run):
    """Set sprint to 'active' or 'closed'."""
    if dry_run:
        print(f"   [DRY RUN] Would set sprint {sprint_id} → {state}")
        return True

    r = requests.post(
        f"{JIRA_BASE_URL}/rest/agile/1.0/sprint/{sprint_id}",
        auth=auth,
        headers={"Content-Type": "application/json", "Accept": "application/json"},
        json={"state": state},
        timeout=30
    )

    if r.status_code == 200:
        return True

    print(f"   ⚠️  Set {state} failed: {r.status_code} — {r.text[:120]}")
    return False


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    auth = get_auth()

    if args.dry_run:
        print("⚠️  DRY RUN MODE — no changes will be made\n")

    print(f"🔍 Fetching all sprints from board {JIRA_BOARD_ID}...\n")
    sprints = get_all_sprints(auth)

    already_closed  = [s for s in sprints if s["state"] == "closed"]
    active_sprints  = [s for s in sprints if s["state"] == "active"]
    future_sprints  = [s for s in sprints if s["state"] == "future"]
    to_process      = active_sprints + future_sprints

    print(f"📊 Sprint breakdown:")
    print(f"   Already closed : {len(already_closed)}")
    print(f"   Active         : {len(active_sprints)}")
    print(f"   Future         : {len(future_sprints)}")
    print(f"   To close       : {len(to_process)}")
    print()

    if not to_process:
        print("✅ All sprints are already closed.")
        return

    print(f"🚀 Closing {len(to_process)} sprint(s)...\n")

    closed = 0
    failed = 0

    for sprint in to_process:
        sprint_id = sprint["id"]
        name      = sprint["name"]
        state     = sprint["state"]

        print(f"Sprint: {name} [{state}]")

        # Future sprints must be activated first before they can be closed
        if state == "future":
            print(f"   → Activating first...")
            if not set_sprint_state(auth, sprint_id, "active", args.dry_run):
                print(f"   ❌ Could not activate — skipping")
                failed += 1
                continue
            if not args.dry_run:
                time.sleep(0.5)

        # Now close it
        print(f"   → Closing...")
        if set_sprint_state(auth, sprint_id, "closed", args.dry_run):
            print(f"   ✅ Closed")
            closed += 1
        else:
            print(f"   ❌ Failed to close")
            failed += 1

        if not args.dry_run:
            time.sleep(0.5)

    print()
    print("=" * 60)
    print("📊 FINAL SUMMARY")
    print("=" * 60)
    print(f"Total sprints found:   {len(sprints)}")
    print(f"Already closed:        {len(already_closed)}")
    print(f"Successfully closed:   {closed}")
    print(f"Failed:                {failed}")
    if args.dry_run:
        print("\n(Dry run — no actual changes made)")
    print("=" * 60)


if __name__ == "__main__":
    main()
