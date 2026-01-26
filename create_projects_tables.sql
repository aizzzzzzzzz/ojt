-- Create projects table
CREATE TABLE IF NOT EXISTS projects (
    project_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create project_submissions table
CREATE TABLE IF NOT EXISTS project_submissions (
    submission_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    project_id INT NOT NULL,
    code LONGTEXT NOT NULL,
    language VARCHAR(50) NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    grade INT,
    feedback LONGTEXT,
    graded_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_project (project_id),
    INDEX idx_submitted_at (submitted_at)
);

-- Add some sample projects
INSERT INTO projects (title, description) VALUES 
('Web Form Validation', 'Create an HTML form with CSS styling and PHP/JavaScript validation. Validate email, password strength, and form submission.'),
('Todo Application', 'Build a simple Todo application using HTML, CSS, and your choice of backend language. Features: add, delete, mark complete.'),
('Simple Calculator', 'Create a calculator application with basic operations (add, subtract, multiply, divide). Can use any supported language.'),
('Login System', 'Develop a secure login system with HTML forms, CSS styling, and backend authentication using PHP or Java.');
