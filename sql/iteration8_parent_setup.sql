-- Iteration 8 additive migration. Run in phpMyAdmin after choosing scholarships.
USE scholarships;
CREATE TABLE IF NOT EXISTS parent_children (
 child_id INT AUTO_INCREMENT PRIMARY KEY,
 parent_user_id INT NOT NULL,
 child_name VARCHAR(120) NOT NULL,
 grade_id INT NOT NULL,
 state_id INT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_parent_owner(parent_user_id),
 CONSTRAINT fk_pc_parent FOREIGN KEY(parent_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
 CONSTRAINT fk_pc_grade FOREIGN KEY(grade_id) REFERENCES grade_levels(grade_id),
 CONSTRAINT fk_pc_state FOREIGN KEY(state_id) REFERENCES states(state_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS parent_child_interests (
 child_id INT NOT NULL,
 field_id INT NOT NULL,
 PRIMARY KEY(child_id,field_id),
 CONSTRAINT fk_pci_child FOREIGN KEY(child_id) REFERENCES parent_children(child_id) ON DELETE CASCADE,
 CONSTRAINT fk_pci_field FOREIGN KEY(field_id) REFERENCES fields_of_study(field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS parent_child_saved_scholarships (
 saved_id INT AUTO_INCREMENT PRIMARY KEY,
 child_id INT NOT NULL,
 scholarship_id INT NOT NULL,
 application_status VARCHAR(30) NOT NULL DEFAULT 'Not Started',
 saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_pc_saved(child_id,scholarship_id),
 CONSTRAINT fk_pcs_child FOREIGN KEY(child_id) REFERENCES parent_children(child_id) ON DELETE CASCADE,
 CONSTRAINT fk_pcs_scholarship FOREIGN KEY(scholarship_id) REFERENCES scholarships(scholarship_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Existing student_profiles and saved_scholarships remain unchanged.
