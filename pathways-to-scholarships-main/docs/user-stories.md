User Stories
Iteration 1

Visitor
US-VIS-01: As a visitor, I want to search by state, grade, and field of study from the home page so that I can see relevant scholarships without making an account. 
US-VIS-02: As a visitor, I want to open a scholarship and see the deadline, amount, requirements, and a link to the official site so that I can confirm details at the source. 
US-VIS-03: As a visitor, I want to register as a student or parent so that I can save scholarships and get matches.

Student 
US-STU-01: As a student, I want to create and maintain my profile, so that I can receive scholarships relevant to my grade, state, and interests.
US-STU-02: As a student, I want to view personalized scholarship matches, so that I can quickly find scholarships I may qualify for.
US-STU-03: As a student, I want to save and un-save scholarships so that I can build a short list. 
US-STU-04: As a student, I want to track status (Interested, In Progress, Submitted, Awarded, Not Awarded) so that I know where each application stands. 
US-STU-05: As a student, I want to track scholarship deadlines, so that I do not miss important application dates.

Parent 
US-PAR-01: As a parent, I want to add a profile for each child (grade, state, interests) so that the child does not need their own login.
US-PAR-02: As a parent, I want to edit or remove a child profile so that information stays current. 
US-PAR-03: As a parent, I want to switch between children and see matches for each so that I compare options per child.
US-PAR-04:As a parent, I want to view my children's saved scholarships, so that I can help them organize their opportunities
US-PAR-05: As a parent, I want upcoming deadlines per child, with items due soon highlighted, so that I can plan around them.

Administrator 
US-ADM-01: As an administrator, I want to upload several .xlsx/.csv files at once and see a validation report (Ready, Warning, Duplicate, Error) so that I can fix problems before anything goes live.
US-ADM-02: As an administrator, I want to commit a reviewed batch in a single transaction, and roll the whole batch back if needed, so that bad data never half-loads.
US-ADM-03: As an administrator, I want to create, edit, verify, and archive scholarships so that the dataset stays accurate. 
US-ADM-04: As an administrator, I want a data quality dashboard (expired, stale, missing fields, possible duplicates) so that I know what to clean next. 
US-ADM-05: As an administrator, I want to export the live dataset to CSV in the Data Science template format so that the Data Science class can analyze it.

Super-admin 
US-SA-01: As a super-admin, I want to create users of any role so that I can onboard administrators.
US-SA-02: As a super-admin, I want to change roles and deactivate accounts, with the last active super-admin protected, so that I cannot lock everyone out.
US-SA-03: As a super-admin, I want to reset a password to a temporary one so that a locked-out user can get back in safely.
US-SA-04: As a super-admin, I want a filterable audit log of logins, role changes, edits, imports, rollbacks, and exports so that I can trace who did what. 
