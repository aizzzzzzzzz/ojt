# Projects & Code Editor Feature

## Setup Instructions

1. **Run the SQL Migration**
   - Execute the `create_projects_tables.sql` file in your database to create the `projects` and `project_submissions` tables.
   - This will also add 4 sample projects to get you started.

2. **Database Tables Created**

   **projects table:**
   - `project_id` - Auto-increment primary key
   - `title` - Project title
   - `description` - Project description
   - `created_at` - Creation timestamp
   - `updated_at` - Last update timestamp

   **project_submissions table:**
   - `submission_id` - Auto-increment primary key
   - `student_id` - Reference to student
   - `project_id` - Reference to project
   - `code` - Submitted code (LONGTEXT)
   - `language` - Programming language used (php, html, css, java)
   - `submitted_at` - Submission timestamp
   - `grade` - Grade given by instructor
   - `feedback` - Feedback from instructor
   - `graded_at` - When it was graded

## Features

### For Students
- **Project Selection**: Browse available OJT projects in a grid layout
- **Code Editor**: Full-featured code editor powered by CodeMirror
- **Multi-Language Support**: 
  - HTML (with syntax highlighting)
  - CSS (with syntax highlighting)
  - PHP (with syntax highlighting)
  - Java (with syntax highlighting)
- **Code Submission**: Submit completed code for each project
- **Submission History**: View all your previous submissions with language and timestamp

### Code Editor Features
- **Syntax Highlighting**: Professional syntax highlighting for all supported languages
- **Line Numbers**: Line numbering for easy reference
- **Line Wrapping**: Code automatically wraps for easier reading
- **Theme**: Dark "Monokai" theme for comfortable coding
- **Auto-indentation**: Automatic indentation when coding

## Usage

1. **Select a Project**
   - Click on any project card in the "OJT Projects" section
   - The code editor will appear below with the project name displayed

2. **Choose Language**
   - Select the programming language from the dropdown
   - The syntax highlighting will update automatically

3. **Write Code**
   - Start writing your code in the editor
   - The editor provides line numbers and syntax highlighting
   - Code is formatted with proper indentation

4. **Submit Code**
   - Click the "Submit Code" button to save your submission
   - You'll see a success message and the submission will appear in your history

## Technical Stack

- **Frontend**: 
  - Bootstrap 5 for responsive UI
  - CodeMirror 5 for code editing
  - Vanilla JavaScript for interactivity

- **Backend**:
  - PHP with PDO for database operations
  - MySQL for data persistence

## Adding More Projects

To add more projects, simply insert them into the `projects` table:

```sql
INSERT INTO projects (title, description) VALUES 
('Project Title', 'Project Description');
```

## Grading (For Instructors)

Instructors can grade submissions by updating the `grade` and `feedback` fields in the `project_submissions` table.

Example:
```sql
UPDATE project_submissions 
SET grade = 90, feedback = 'Great work!', graded_at = NOW() 
WHERE submission_id = 1;
```

## Notes

- Each submission is stored with the language used, allowing instructors to review code in context
- Submissions are timestamped for tracking purposes
- Students can submit multiple times for the same project (latest overrides previous)
- The editor uses localStorage to prevent data loss if needed in future updates
