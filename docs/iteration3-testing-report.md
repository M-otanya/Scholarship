# Iteration 3 Testing Report

## Project
Pathways to Scholarships — Frontend and Quality Testing

## What We Tested
We uploaded CSV files using `admin/import.php` and checked the results on `admin/validation_report.php`.

## Test Results

| CSV File | Total | Ready | Warning | Duplicate | Error |
|---|---:|---:|---:|---:|---:|
| `01_clean_baseline.csv` | 3 | 3 | 0 | 0 | 0 |
| `02_missing_urls_duplicates.csv` | 5 | 1 | 0 | 1 | 3 |
| `03_cleaning_states_regional.csv` | 4 | 2 | 2 | 0 | 0 |
| `Mohamed_Fadumo_20_scholarship_research_draft.csv` | 20 | 13 | 6 | 0 | 1 |
![alt text](image.png)
![alt text](image-1.png)
![alt text](image-2.png)
![alt text](image-3.png)
## Findings
- All four CSV files uploaded and displayed validation reports.
- The system showed Ready, Warning, Duplicate, and Error results.
- Missing or invalid data and duplicate rows were flagged in the test files.
- The 20-scholarship research draft still has 6 warnings and 1 error to review.

## Next Steps
- Check and correct the flagged real-data records.
- Verify scholarship details using official sources.
- Test importing approved records into the live database when that feature is ready.

**Note:** These tests confirm CSV validation and staging, not final import into the live scholarship table.
