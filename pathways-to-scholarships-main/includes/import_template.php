<?php
declare(strict_types=1);

/**
 * Scholarship import template mapping.
 * Converts Data Science template headers into
 * internal PHP field names.
 */

function scholarshipTemplateColumns(): array
{
    return [
        'Scholarship name' => 'scholarship_name',
        'Scholarship provider/organization' => 'provider_name',
        'Provider type' => 'provider_type',
        'Official scholarship URL' => 'official_url',
        'Discovery source' => 'discovery_source',
        'State/region' => 'state',
        'National vs. state/local' => 'scope',
        'Eligible grade levels' => 'eligible_grade_levels',
        'Middle school / high school / both' => 'school_level',
        'Scholarship category' => 'scholarship_category',
        'Field/area of interest' => 'field_of_study',
        'Award amount' => 'award_amount',
        'Number of awards (if known)' => 'number_of_awards',
        'Renewable (Yes/No/Unknown)' => 'renewable',
        'Maximum potential value' => 'max_potential_value',
        'Application open date (if known)' => 'application_open_date',
        'Application deadline' => 'application_deadline',
        'Residency requirement' => 'residency_requirement',
        'Minimum GPA (if any)' => 'minimum_gpa',
        'Financial need required (Yes/No/Unknown)' => 'financial_need_required',
        'Essay required' => 'essay_required',
        'Recommendation required' => 'recommendation_required',
        'Transcript/test score required' => 'transcript_required',
        'Community service requirement' => 'community_service_required',
        'Leadership requirement' => 'leadership_required',
        'Other major eligibility requirements' => 'eligibility_requirements',
        'Application method' => 'application_method',
        'Verification date' => 'verification_date',
        'Notes' => 'notes'
    ];
}

function normalizeTemplateHeader(string $header): string
{
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);

    $header = str_replace(
        ["\xC2\xA0", "\xE2\x80\xAF"],
        ' ',
        $header
    );

    $header = strtolower(trim($header));

    return preg_replace('/\s+/', ' ', $header) ?? $header;
}

function scholarshipHeaderLookup(): array
{
    $lookup = [];

    foreach (scholarshipTemplateColumns() as $heading => $field) {
        $lookup[normalizeTemplateHeader($heading)] = $field;
        $lookup[normalizeTemplateHeader($field)] = $field;
    }

    $lookup['award_min'] = 'award_min';
    $lookup['award_max'] = 'award_max';

    return $lookup;
}

function mapScholarshipRow(array $row): array
{
    $lookup = scholarshipHeaderLookup();
    $mapped = [];

    foreach ($row as $heading => $value) {
        $key = normalizeTemplateHeader((string) $heading);

        if (isset($lookup[$key])) {
            $mapped[$lookup[$key]] = $value;
        }
    }

    return $mapped;
}

function validateTemplateHeaders(array $headers): array
{
    $lookup = scholarshipHeaderLookup();
    $mappedHeaders = [];
    $errors = [];

    foreach ($headers as $header) {
        $key = normalizeTemplateHeader((string) $header);

        if (isset($lookup[$key])) {
            $mappedHeaders[] = $lookup[$key];
        }
    }

    if (count($mappedHeaders) !== count(array_unique($mappedHeaders))) {
        $errors[] = 'Duplicate mapped column headers found.';
    }

    foreach (['scholarship_name', 'official_url'] as $required) {
        if (!in_array($required, $mappedHeaders, true)) {
            $errors[] = "Missing required column: $required";
        }
    }

    return $errors;
}
