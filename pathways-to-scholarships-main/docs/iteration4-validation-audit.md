# Iteration 4 — Validation and audit integration

Validation and audit logging are implemented on the `validation-audit` branch.

## Database setup

The existing `sql/schema.sql` already defines `audit_log` with `audit_id`, `user_id`, `action`, `table_name`, `record_id`, `details`, and `created_at`. Do not recreate that table on an existing database.

Before using the pages, make sure `config/config.php` contains `dev_user_id` pointing to a real administrator row in `users`. Run `sql/iteration4_admin_setup.sql` once if your local database has no placeholder administrator. Use the returned `user_id` in local configuration. Never commit `config/config.php`.

If this user ID is missing or not present in the `users` table, audited operations fail and roll back rather than silently skipping their audit entries. The development role stub in `includes/auth.php` is not production authentication.

## Functions

- `validate_scholarship($data)` validates required name and URL, numeric fields, dates, choice values, and related IDs.
- `validate_provider($data)` validates provider name and type.
- `validate_scholarship_ids($ids)` validates a list of up to 500 positive IDs.
- `log_admin_action($pdo, $action, $entityType, $entityId, $userId)` writes an audit row.

The three validation helpers return an array of messages or an empty array. The audit helper throws on failure and should be called inside the same database transaction as the related change.

Supported actions: `create`, `edit`, `archive`, `verify`, `import`, `rollback`, `provider_merge`. Entity types: `scholarships`, `providers`, `import_batches`.

## Integration

- Scholarship form: common PHP rules, jQuery inline error messages, and create/edit audit entries.
- Scholarship list: shared ID validation and archive/verify audit entries.
- Import upload: records import-to-staging events.
- Import commit: records successful import publication.
- Rollback: records completed rollback operations.

**Provider merge:** Khalid should call `validate_provider($data)` before provider changes and `log_admin_action($pdo, 'provider_merge', 'providers', $survivingProviderId, current_user_id())` before committing a successful merge.

## Pending

Runtime validation and database integration testing are intentionally deferred. Review this branch with the team before merging. All changes remain in the feature branch.
