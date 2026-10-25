# Field Inventory (Iteration 1)

This document lists the scholarship fields we will use for our project. It includes the spreadsheet columns, database column names, and basic rules for the data.

Y/N/U means Yes, No, or Unknown.

## Scholarship Fields

| # | Template Column | Database Column | Required | Cleaning Rule |
|---|---|---|---|---|
| 1 | Scholarship name | name | Yes | Remove extra spaces |
| 2 | Scholarship provider/organization | provider_id | Yes | Remove extra spaces |
| 3 | Provider type | provider_type | No | Use consistent names |
| 4 | Official scholarship URL | url | Yes | Check that the URL is valid |
| 5 | Discovery source | discovery_source | No | Remove extra spaces |
| 6 | State/region | state_id | Yes | Use 2-letter state codes |
| 7 | National vs. state/local | scope | Yes | Identify if national or local |
| 8 | Eligible grade levels | scholarship_grades | Yes | List the eligible grades |
| 9 | Middle / high / both | school_level | No | Determine from grade levels |
| 10 | Scholarship category | scholarship_categories | No | Use consistent category names |
| 11 | Field/area of interest | scholarship_fields | No | Use consistent names |
| 12 | Award amount | amount_min, amount_max | No | Remove dollar signs and commas |
| 13 | Number of awards | num_awards | No | Use numbers |
| 14 | Renewable | renewable | No | Use Y/N/U |
| 15 | Maximum potential value | max_potential_value | No | Use numbers |
| 16 | Application open date | open_date | No | Use a consistent date format |
| 17 | Application deadline | deadline | No | Use a consistent date format |
| 18 | Residency requirement | residency_requirement | No | Keep as text |
| 19 | Minimum GPA | min_gpa | No | Use a number |
| 20 | Financial need required | need_required | No | Use Y/N/U |
| 21 | Essay required | essay_required | No | Use Y/N/U |
| 22 | Recommendation required | recommendation_required | No | Use Y/N/U |
| 23 | Transcript/test score required | transcript_required | No | Use Y/N/U |
| 24 | Community service requirement | service_required | No | Use Y/N/U |
| 25 | Leadership requirement | leadership_required | No | Use Y/N/U |
| 26 | Other eligibility requirements | other_requirements | No | Keep as text |
| 27 | Application method | application_method | No | Online, email, mail, or other |
| 28 | Verification date | verified_date | No | Use a consistent date format |
| 29 | Notes | notes | No | Keep as text |


## Import Statuses

When scholarship data is uploaded, each row will have a status.

- **Ready:** The information looks correct.
- **Warning:** Some information is missing or may need to be checked.
- **Duplicate:** The scholarship is already in the database or uploaded file.
- **Error:** Important information is missing or incorrect.
