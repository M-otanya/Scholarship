# Iteration 8 — Fadumo Parent Features (development branch)

This branch adds parent-owned child profiles, separate saved scholarship lists, deadline summaries, and role-based parent navigation. It does **not** alter existing student_profiles or saved_scholarships.

## Local setup (XAMPP)

1. Download this branch ZIP from GitHub (Code → Download ZIP) and extract it into your XAMPP htdocs directory. Preserve your private local config/config.php; it is not committed.
2. Start Apache and MySQL.
3. Back up the `scholarships` database in phpMyAdmin.
4. Select the `scholarships` database, open the SQL tab, and run `sql/iteration8_parent_setup.sql`.
5. Ensure Iteration 7 authentication migration has already been applied: `users.is_active` must exist, and the `roles` table must contain `Parent`. Check with `SHOW COLUMNS FROM users LIKE 'is_active';` and `SELECT role_name FROM roles;`. The committed baseline `sql/schema.sql` does not currently include `is_active`.
6. Sign in with a Parent account, or register one at `register.php`. Click **Parent Dashboard** in the navigation.
7. Create two child profiles with different grade/state/interests, then save different scholarships for each child.

## Pages

- `parent/dashboard.php`: total children, saved scholarships and deadlines due within 30 days.
- `parent/children.php`: list, edit, and delete child profiles.
- `parent/child_form.php`: add or edit child grade/state/interests.
- `parent/child_scholarships.php?child_id=...`: view/save/unsave scholarships and set application status for one child.

## Access control tests

- A non-Parent account must receive HTTP 403 on Parent pages.
- Log in as Parent A; create a child and note their `child_id`.
- Log in as Parent B; try Parent A's `child_id` in the child edit URL and scholarship URL. Both must return HTTP 404.
- Try the same ID in a POST request; it must not modify Parent A's data.
- Verify adding/removing a saved scholarship updates dashboard counts and upcoming deadline summaries.

## Team integration note

This branch uses dedicated `parent_children`, `parent_child_interests`, and `parent_child_saved_scholarships` tables. It intentionally does not modify Khalid's student profiles or Abdirahman's student saved-scholarship AJAX flow. Coordinate a shared parent/student save model during integration to avoid duplicate implementations.

Do not merge before database migration and authorization tests pass.
