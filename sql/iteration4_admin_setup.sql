USE scholarships;

INSERT INTO users (role_id, email, password_hash)
SELECT role_id, 'dev-admin@localhost.invalid', '!disabled-development-account!'
FROM roles
WHERE LOWER(role_name) = 'administrator'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'dev-admin@localhost.invalid');

SELECT user_id, email FROM users WHERE email = 'dev-admin@localhost.invalid';
