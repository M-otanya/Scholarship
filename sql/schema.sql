CREATE DATABASE IF NOT EXISTS scholarships
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE scholarships;

-- Create roles table
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- Create states table
CREATE TABLE states (
    state_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    abbreviation VARCHAR(10) NOT NULL UNIQUE
);

-- Create grade levels table
CREATE TABLE grade_levels (
    grade_id INT AUTO_INCREMENT PRIMARY KEY,
    grade_number INT NOT NULL UNIQUE
);

-- Create categories table
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
);

-- Create fields of study table
CREATE TABLE fields_of_study (
    field_id INT AUTO_INCREMENT PRIMARY KEY,
    field_name VARCHAR(100) NOT NULL UNIQUE
);

-- Create national themes table
CREATE TABLE national_themes (
    national_theme_id INT AUTO_INCREMENT PRIMARY KEY,
    theme_name VARCHAR(150) NOT NULL UNIQUE
);

-- Create providers table
CREATE TABLE providers (
    provider_id INT AUTO_INCREMENT PRIMARY KEY,
    provider_name VARCHAR(150) NOT NULL,
    provider_type VARCHAR(100)
);

-- Create student profiles table
CREATE TABLE student_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    grade_id INT NOT NULL,
    state_id INT NOT NULL,

    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (grade_id) REFERENCES grade_levels(grade_id),
    FOREIGN KEY (state_id) REFERENCES states(state_id)
);

-- Create import batches table
CREATE TABLE import_batches (
    batch_id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    contributing_student VARCHAR(150),
    uploaded_by INT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_rows INT DEFAULT 0,
    ready_rows INT DEFAULT 0,
    warning_rows INT DEFAULT 0,
    duplicate_rows INT DEFAULT 0,
    error_rows INT DEFAULT 0,
    status VARCHAR(30) DEFAULT 'pending',

    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
);

CREATE TABLE scholarships (
    scholarship_id INT AUTO_INCREMENT PRIMARY KEY,
    scholarship_name VARCHAR(255) NOT NULL,
    provider_id INT,
    state_id INT,
    national_theme_id INT,
    import_batch_id INT NULL,
    official_url VARCHAR(500) NOT NULL,
    normalized_url VARCHAR(500) NOT NULL,
    discovery_source VARCHAR(255),
    scope ENUM('national', 'state/local'),
    description TEXT,
    award_min DECIMAL(12,2),
    award_max DECIMAL(12,2),
    number_of_awards INT,
    renewable ENUM('Yes', 'No', 'Unknown'),
    max_potential_value DECIMAL(12,2),
    application_open_date DATE,
    application_deadline DATE,
    rolling_deadline BOOLEAN DEFAULT FALSE,
    residency_requirement TEXT,
    minimum_gpa DECIMAL(3,2),
    financial_need_required ENUM('Yes', 'No', 'Unknown'),
    essay_required ENUM('Yes', 'No', 'Unknown'),
    recommendation_required ENUM('Yes', 'No', 'Unknown'),
    transcript_required ENUM('Yes', 'No', 'Unknown'),
    community_service_required ENUM('Yes', 'No', 'Unknown'),
    leadership_required ENUM('Yes', 'No', 'Unknown'),
    eligibility_requirements TEXT,
    application_method VARCHAR(100),
    notes TEXT,
    contributing_student VARCHAR(150),
    verification_date DATE,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    updated_by INT NULL,

    FOREIGN KEY (provider_id) REFERENCES providers(provider_id),
    FOREIGN KEY (state_id) REFERENCES states(state_id),
    FOREIGN KEY (national_theme_id)
        REFERENCES national_themes(national_theme_id),
    FOREIGN KEY (import_batch_id) REFERENCES import_batches(batch_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    FOREIGN KEY (updated_by) REFERENCES users(user_id),

    INDEX idx_state (state_id),
    INDEX idx_deadline (application_deadline),
    UNIQUE KEY unique_url (normalized_url)
);

-- Create profile interests table
CREATE TABLE profile_interests (
    profile_id INT NOT NULL,
    field_id INT NOT NULL,

    PRIMARY KEY (profile_id, field_id),

    FOREIGN KEY (profile_id) REFERENCES student_profiles(profile_id),
    FOREIGN KEY (field_id) REFERENCES fields_of_study(field_id)
);

-- Create scholarship grades table
CREATE TABLE scholarship_grades (
    scholarship_id INT NOT NULL,
    grade_id INT NOT NULL,

    PRIMARY KEY (scholarship_id, grade_id),

    FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id),
    FOREIGN KEY (grade_id) REFERENCES grade_levels(grade_id)
);

-- Create scholarship categories table
CREATE TABLE scholarship_categories (
    scholarship_id INT NOT NULL,
    category_id INT NOT NULL,

    PRIMARY KEY (scholarship_id, category_id),

    FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id),
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

-- Create scholarship fields table
CREATE TABLE scholarship_fields (
    scholarship_id INT NOT NULL,
    field_id INT NOT NULL,

    PRIMARY KEY (scholarship_id, field_id),

    FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id),
    FOREIGN KEY (field_id) REFERENCES fields_of_study(field_id)
);

-- Create saved scholarships table
CREATE TABLE saved_scholarships (
    saved_id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL,
    scholarship_id INT NOT NULL,
    application_status VARCHAR(30) DEFAULT 'Interested',
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (profile_id) REFERENCES student_profiles(profile_id),
    FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id),

    UNIQUE (profile_id, scholarship_id)
);

-- Create import staging rows table
CREATE TABLE import_staging_rows (
    staging_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    row_number INT NOT NULL,
    raw_data LONGTEXT NOT NULL,
    row_status VARCHAR(30) DEFAULT 'Ready',
    error_message TEXT,

    FOREIGN KEY (batch_id) REFERENCES import_batches(batch_id)
);

-- Create contact messages table
CREATE TABLE contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create search log table
CREATE TABLE search_log (
    search_id INT AUTO_INCREMENT PRIMARY KEY,
    state_id INT,
    grade_id INT,
    field_id INT,
    searched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (state_id) REFERENCES states(state_id),
    FOREIGN KEY (grade_id) REFERENCES grade_levels(grade_id),
    FOREIGN KEY (field_id) REFERENCES fields_of_study(field_id)
);

-- Create audit log table
CREATE TABLE audit_log (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

