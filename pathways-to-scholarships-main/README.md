# Pathways to Scholarships

A web app that helps students and parents find scholarships for middle and high school students by state, grade level, and field of study. Built for ICS 325 (Fall 2026), paired with the Data Science class's Pathways to Scholarships project.

**Team 6:** Abdirahman Hirsi, Fadumo Mohamed, Khalid Hassan

**Live site:** added in the final iteration

## Folder structure

```
/admin       pages for administrators only
/api         AJAX endpoints
/includes    shared PHP (database, header, nav, footer)
/config      config.sample.php (in Git); config.php (NOT in Git)
/css, /js, /images   front-end files
/sql         database scripts
/docs        user stories, ERD, wireframes, field inventory
```

## Local setup
1. Install XAMPP and start Apache and MySQL.
2. Clone this repo into `htdocs/scholarships`.
3. Copy `config/config.sample.php` to `config/config.php` and enter your database settings.
4. Create a database named `scholarships` (utf8mb4_unicode_ci) in phpMyAdmin.
5. Import the SQL scripts from `sql/`.
6. Open `http://localhost/scholarships/`.

## Branching
- `main` always works. Don't commit to it directly.
- Make a branch per task, such as `feature/fr-pub-02-contact`.
- Merge through a pull request that a teammate reviews.
- Tag each iteration (`iter-01`, `iter-02`, ...).


