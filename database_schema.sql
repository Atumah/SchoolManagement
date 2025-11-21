-- School Management System - "The Morningstar"
-- Database Schema for MySQL/MariaDB
-- Generated from SCHEMA.dbml

-- Drop tables if they exist (in reverse order of dependencies)
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS notes;
DROP TABLE IF EXISTS grades;
DROP TABLE IF EXISTS progress;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS course_students;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS users;

-- Users table - Central authentication table for all users
CREATE TABLE users (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    first_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NULL,
    role ENUM('Teacher', 'Admin', 'Principal', 'Web Designer', 'Student') NOT NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    twofa_secret VARCHAR(32) NULL,
    twofa_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    twofa_last_used_timestep BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Courses table - Courses taught by teachers
CREATE TABLE courses (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    teacher_id VARCHAR(30) NOT NULL,
    schedule VARCHAR(255) NULL,
    max_students INT NOT NULL DEFAULT 30,
    year INT NOT NULL DEFAULT 1,
    credits INT NOT NULL DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_teacher_id (teacher_id),
    INDEX idx_year (year),
    CONSTRAINT chk_year CHECK (year BETWEEN 1 AND 4),
    CONSTRAINT chk_credits CHECK (credits > 0 AND credits <= 60)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Course Students table - Bridge table connecting courses and students
CREATE TABLE course_students (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    course_id VARCHAR(30) NOT NULL,
    student_id VARCHAR(30) NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (course_id, student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance table - Daily attendance records per student per course
CREATE TABLE attendance (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    student_id VARCHAR(30) NOT NULL,
    course_id VARCHAR(30) NOT NULL,
    teacher_id VARCHAR(30) NOT NULL,
    date DATE NOT NULL,
    status ENUM('Present', 'Absent') NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (student_id, course_id, date),
    INDEX idx_student_id (student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_teacher_id (teacher_id),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Progress table - Student progress notes and status tracking
CREATE TABLE progress (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    student_id VARCHAR(30) NOT NULL,
    course_id VARCHAR(30) NOT NULL,
    teacher_id VARCHAR(30) NOT NULL,
    notes TEXT NOT NULL,
    date DATE NOT NULL,
    status ENUM('Stable', 'Improving', 'Needs Attention', 'Excellent') NOT NULL DEFAULT 'Stable',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_teacher_id (teacher_id),
    INDEX idx_date (date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grades table - Student grades and assessments
CREATE TABLE grades (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    student_id VARCHAR(30) NOT NULL,
    course_id VARCHAR(30) NOT NULL,
    teacher_id VARCHAR(30) NOT NULL,
    original_grade DECIMAL(3,1) NULL,
    final_grade TINYINT NULL,
    date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_teacher_id (teacher_id),
    INDEX idx_date (date),
    INDEX idx_final_grade (final_grade),
    CONSTRAINT chk_original_grade CHECK (original_grade IS NULL OR (original_grade >= 1.0 AND original_grade <= 10.0)),
    CONSTRAINT chk_final_grade CHECK (final_grade IS NULL OR (final_grade >= 1 AND final_grade <= 10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes table - General notes (can be student-specific, course-specific, or general)
CREATE TABLE notes (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    teacher_id VARCHAR(30) NOT NULL,
    student_id VARCHAR(30) NULL,
    course_id VARCHAR(30) NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    tags VARCHAR(255) NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    INDEX idx_teacher_id (teacher_id),
    INDEX idx_student_id (student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Announcements table - Website announcements created by Admin, Principal, or Web Designer
CREATE TABLE announcements (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author_id VARCHAR(30) NOT NULL,
    is_published BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_author_id (author_id),
    INDEX idx_is_published (is_published),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Events table - School events calendar
CREATE TABLE events (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    event_date DATE NOT NULL,
    event_time TIME NULL,
    location VARCHAR(255) NULL,
    author_id VARCHAR(30) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_author_id (author_id),
    INDEX idx_event_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appointments table - Appointments/meetings that can be created by any user
CREATE TABLE appointments (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    created_by_id VARCHAR(30) NOT NULL,
    appointee_id VARCHAR(30) NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('Pending', 'Accepted', 'Declined', 'Cancelled') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (appointee_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created_by_id (created_by_id),
    INDEX idx_appointee_id (appointee_id),
    INDEX idx_appointment_date (appointment_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

