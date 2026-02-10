<?php
// Projects logic module for student dashboard

function handle_project_submission($pdo, $student_id, $project_id, $submission_type, $code_content, $uploaded_file, $remarks) {
    $uploadDir = __DIR__ . '/../storage/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    try {
        if ($submission_type === 'code') {
            if (empty($code_content)) {
                return "Code cannot be empty.";
            }
            $fileName = $student_id . '_project_' . $project_id . '_' . time() . '.txt';
            $filePath = $uploadDir . $fileName;

            if (!is_writable($uploadDir)) {
                return "Upload directory is not writable.";
            }
            if (file_put_contents($filePath, $code_content) === false) {
                return "Error saving code file. Please try again.";
            }

            submit_project($pdo, $project_id, $student_id, $fileName, $remarks);
            return "Code submitted successfully!";
        } else {
            if (empty($uploaded_file['tmp_name']) || $uploaded_file['error'] !== UPLOAD_ERR_OK) {
                return "Please select a valid file to upload.";
            }

            $originalName = basename($uploaded_file['name']);
            $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'php', 'html', 'css', 'java', 'js', 'py', 'cpp', 'c', 'sql'];

            if (!in_array($fileExt, $allowedExts)) {
                return "File type not allowed. Allowed: " . implode(', ', $allowedExts);
            }
            if ($uploaded_file['size'] > 10 * 1024 * 1024) {
                return "File too large (maximum 10MB).";
            }

            $uniqueFileName = $student_id . '_project_' . $project_id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $filePath = $uploadDir . $uniqueFileName;

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $uploaded_file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = [
                'text/plain', 'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip', 'application/x-rar-compressed',
                'text/html', 'text/css', 'text/javascript', 'application/javascript',
                'text/x-php', 'text/x-java-source', 'text/x-python',
                'text/x-c', 'text/x-c++'
            ];

            if (!in_array($mimeType, $allowedMimes)) {
                return "File type verification failed. Please upload a valid file.";
            }
            if (!move_uploaded_file($uploaded_file['tmp_name'], $filePath)) {
                return "Error uploading file. Please try again.";
            }

            submit_project($pdo, $project_id, $student_id, $uniqueFileName, $remarks);
            return "File submitted successfully!";
        }
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        if ($e->getCode() == 'HY093') {
            return "Database error: Parameter mismatch. Please contact administrator.";
        } else {
            return "Database error occurred. Please try again.";
        }
    } catch (Exception $e) {
        error_log("General Error: " . $e->getMessage());
        return "An error occurred. Please try again.";
    }
}
?>
