# Import Fields and Data Collection Rules

## Purpose

The importer must preserve every column in the Data Science project's **Recommended Data Fields**. One spreadsheet row represents one scholarship. The import should retain the submitted value, validate or standardize it, and report problems instead of silently discarding data.

## Source columns to import

| # | Template column | Import handling |
|---:|---|---|
| 1 | Scholarship name | Required text; trim leading/trailing whitespace. |
| 2 | Scholarship provider/organization | Text identifying the sponsor; map to a provider record when possible. |
| 3 | Provider type | Preserve and standardize to the supplied provider categories where possible. |
| 4 | Official scholarship URL | Required. Store the provider's official page and a normalized URL for duplicate detection. |
| 5 | Discovery source | Record where the researcher found the listing; this may be an aggregator. |
| 6 | State/region | Store the assigned state/region; standardize state names/codes consistently. |
| 7 | National vs. state/local | Required classification using the rules below. |
| 8 | Eligible grade levels | Preserve one or more eligible grades; standardize grade values. |
| 9 | Middle school / high school / both | Preserve the source value or derive consistently from eligible grades. |
| 10 | Scholarship category | Preserve the category; normalize spelling and spacing consistently. |
| 11 | Field/area of interest | Preserve one or more fields; normalize spelling and spacing consistently. |
| 12 | Award amount | Parse as a numeric amount; handle currency symbols and comma separators. |
| 13 | Number of awards (if known) | Optional integer; allow blank when unknown. |
| 14 | Renewable (Yes/No/Unknown) | Normalize to Yes, No, or Unknown. |
| 15 | Maximum potential value | Optional numeric amount; parse currency formatting. |
| 16 | Application open date (if known) | Optional date; retain blank when not stated. |
| 17 | Application deadline | Parse as a date; preserve rolling deadlines as rolling rather than inventing a date. |
| 18 | Residency requirement | Preserve the stated location/residency criteria as text. |
| 19 | Minimum GPA (if any) | Optional numeric value; preserve blank when no minimum is stated. |
| 20 | Financial need required | Normalize to Yes, No, or Unknown. |
| 21 | Essay required | Normalize to Yes, No, or Unknown. |
| 22 | Recommendation required | Normalize to Yes, No, or Unknown. |
| 23 | Transcript/test score required | Normalize to Yes, No, or Unknown. |
| 24 | Community service requirement | Normalize to Yes, No, or Unknown. |
| 25 | Leadership requirement | Normalize to Yes, No, or Unknown. |
| 26 | Other major eligibility requirements | Preserve relevant eligibility text. |
| 27 | Application method | Preserve/standardize as online form, email, mail, or other when known. |
| 28 | Verification date | Parse as a date; identifies when details were checked against the provider. |
| 29 | Notes | Preserve useful clarifications or caveats. |

### Application-managed metadata

These are system fields from the application requirements, not additional columns in the Data Science spreadsheet. The application may attach them during import: national theme, contributing student, import batch ID, created/updated timestamps and users, and active/archived status. Do not require the Data Science spreadsheet to supply these unless the instructor's template says otherwise.

## Data collection and import rules

1. **Include scholarships only.** A record must provide money, tuition support, a savings award, or another clearly stated financial educational award. Do not import ordinary camps, classes, clubs, competitions, internships, or enrichment programs unless a scholarship or monetary educational award is explicitly attached.
2. **Target middle- and high-school students.** Include awards open to middle school students, high school students, or both. Do not treat an award for current graduate/adult learners as eligible unless a middle/high-school student can use it for a future pathway such as dual enrollment or college.
3. **Classify geography consistently.** State/local means eligibility requires residence in one state or a city, county, or district within it. National means open to residents of at least two states. Multi-state regional awards count as national.
4. **Use official URLs.** Aggregators may be used to discover awards, but record the scholarship provider's official page and verify award amount, deadline, and eligibility there when available. Record the verification date.
5. **Deduplicate by official URL.** Normalize the official URL and compare it with the class claim sheet and existing records. If already claimed, skip it (first-claimed-wins). The URL is the main duplicate key.
6. **Respect assigned ranges and themes.** State assignments and national themes are in the student assignment file. National records must match the student's assigned theme and sponsor-name range. Use the provider name, not scholarship name, for the range. Ignore a leading “The”; names beginning with a number go in A–M; an individual's named fund uses its first word as written.
7. **Clean without changing meaning.** Trim leading/trailing whitespace and remove non-breaking spaces. Standardize values such as state, grade, Yes/No/Unknown, amount, date, and category. Do not guess missing facts; leave them blank/unknown and report validation issues.
8. **Keep source attribution.** Preserve discovery source and contributing student so records can be traced back to the submitted file/researcher. Submitted filenames use `LastName_FirstName` per the project instructions.
9. **Treat expired or incomplete listings transparently.** Do not silently remove records. Flag missing required URLs, invalid amounts/dates, possible duplicates, and expired deadlines for review.

## Source files reviewed

- Data Science Final Project: *Pathways to Scholarships* — Recommended Data Fields, scope, verification guidance, and submission rules.
- *Pathways to Scholarships: Student Assignments* — geographic definitions, sponsor-name ranges, themes, and duplicate-avoidance rules.
