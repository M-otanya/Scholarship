# Import Cleaning Rules

## Project
Pathways to Scholarships — Iteration 3

Our CSV validator uses these rules:

| Rule | What It Does |
|---|---|
| Extra spaces | Removes extra and non-breaking spaces |
| Scholarship name | Flags an empty name as Error |
| Official URL | Checks that a URL is provided and valid |
| Duplicate URL | Flags a repeated normalized URL as Duplicate |
| Award amount | Converts values like `$2,500` to `2500` |
| Dates | Checks the `YYYY-MM-DD` format |
| State names | Matches state names and abbreviations to database records |
| Grade levels | Accepts grades 6–12 and ranges like `9-12` |
| Yes/No values | Standardizes Yes, No, and Unknown |
| Regional eligibility | Flags unclear regional rules for review |

## Validation Statuses
- **Ready:** No validation issues found.
- **Warning:** Information needs review.
- **Duplicate:** Official URL matches another scholarship.
- **Error:** Required or invalid data must be fixed.

## Note
The CSV files are checked before import. Records stay in the staging tables until the final import feature is completed.
