-- =============================================================
-- SCHOOL DIGITAL NOTICE BOARD — Database Schema v2
-- Changes from v1:
--   • users: added grade, phoneNo, createdBy columns
--   • notice_scopes: added 12 Student Grade rows (Grade 1–12)
--   • notices: added targetGrade column for Grade scope
-- =============================================================

CREATE DATABASE IF NOT EXISTS school_noticeboard
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE school_noticeboard;

-- -------------------------------------------------------------
-- Table: users
-- -------------------------------------------------------------
CREATE TABLE users (
    userID                  INT           AUTO_INCREMENT PRIMARY KEY,
    fullName                VARCHAR(100)  NOT NULL,
    email                   VARCHAR(100)  NOT NULL UNIQUE,
    password                VARCHAR(255)  NOT NULL,
    salt                    VARCHAR(100)  NOT NULL,
    role                    ENUM('Admin','Teacher','Student') NOT NULL DEFAULT 'Student',
    grade                   VARCHAR(10)   DEFAULT NULL         COMMENT 'Grade 1-12, Students only',
    phoneNo                 VARCHAR(15)   DEFAULT NULL,
    lastLoginAt             DATETIME      DEFAULT NULL,
    lastPasswordChangedAt   DATETIME      DEFAULT NULL,
    loginAttempts           INT           NOT NULL DEFAULT 0,
    active                  TINYINT(1)    NOT NULL DEFAULT 1,
    createdBy               INT           DEFAULT NULL          COMMENT 'FK → users.userID',
    createdAt               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifiedAt              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_user_createdby FOREIGN KEY (createdBy) REFERENCES users(userID) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Table: notice_categories
-- -------------------------------------------------------------
CREATE TABLE notice_categories (
    categoryID   INT          AUTO_INCREMENT PRIMARY KEY,
    categoryName VARCHAR(100) NOT NULL,
    subCategory  VARCHAR(100) NOT NULL,
    description  VARCHAR(255) DEFAULT NULL,
    isActive     TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Table: notice_scopes
-- -------------------------------------------------------------
CREATE TABLE notice_scopes (
    scopeID     INT          AUTO_INCREMENT PRIMARY KEY,
    scopeName   VARCHAR(100) NOT NULL,
    description VARCHAR(100) DEFAULT NULL,
    isActive    TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Table: notices
-- -------------------------------------------------------------
CREATE TABLE notices (
    noticeID       INT          AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(150) NOT NULL,
    description    TEXT         NOT NULL,
    attachment     LONGBLOB     DEFAULT NULL,
    attachmentName VARCHAR(255) DEFAULT NULL,
    attachmentType VARCHAR(100) DEFAULT NULL,
    categoryID     INT          NOT NULL,
    scopeID        INT          NOT NULL,
    targetRole     ENUM('Admin','Teacher','Student') DEFAULT NULL,
    targetUserID   INT          DEFAULT NULL,
    targetGrade    VARCHAR(10)  DEFAULT NULL  COMMENT 'Populated when scope = Student Grade',
    publishDate    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expiryDate     DATETIME     DEFAULT NULL,
    viewCount      INT          NOT NULL DEFAULT 0,
    deletedAt      DATETIME     DEFAULT NULL,
    deletedBy      INT          DEFAULT NULL,
    active         TINYINT(1)   NOT NULL DEFAULT 1,
    createdBy      INT          NOT NULL,
    modifiedBy     INT          DEFAULT NULL,
    createdAt      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifiedAt     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_notice_category  FOREIGN KEY (categoryID)   REFERENCES notice_categories(categoryID),
    CONSTRAINT fk_notice_scope     FOREIGN KEY (scopeID)      REFERENCES notice_scopes(scopeID),
    CONSTRAINT fk_notice_target    FOREIGN KEY (targetUserID) REFERENCES users(userID),
    CONSTRAINT fk_notice_creator   FOREIGN KEY (createdBy)    REFERENCES users(userID),
    CONSTRAINT fk_notice_modifier  FOREIGN KEY (modifiedBy)   REFERENCES users(userID),
    CONSTRAINT fk_notice_deleter   FOREIGN KEY (deletedBy)    REFERENCES users(userID)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Table: otp_tokens  (Forgot Password)
-- -------------------------------------------------------------
CREATE TABLE otp_tokens (
    tokenID   INT         AUTO_INCREMENT PRIMARY KEY,
    userID    INT         NOT NULL,
    otp       VARCHAR(10) NOT NULL,
    expiresAt DATETIME    NOT NULL,
    used      TINYINT(1)  NOT NULL DEFAULT 0,
    createdAt DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_otp_user FOREIGN KEY (userID) REFERENCES users(userID)
) ENGINE=InnoDB;


-- =============================================================
-- SEED DATA
-- =============================================================

-- ── Notice Scopes ─────────────────────────────────────────────
INSERT INTO notice_scopes (scopeName, description) VALUES
('General',         'Visible to all logged-in users'),
('Role Based',      'Visible to all users of a specific role'),
('Individual',      'Visible to one specific user only'),
('Student Grade 1', 'Visible to all Grade 1 students'),
('Student Grade 2', 'Visible to all Grade 2 students'),
('Student Grade 3', 'Visible to all Grade 3 students'),
('Student Grade 4', 'Visible to all Grade 4 students'),
('Student Grade 5', 'Visible to all Grade 5 students'),
('Student Grade 6', 'Visible to all Grade 6 students'),
('Student Grade 7', 'Visible to all Grade 7 students'),
('Student Grade 8', 'Visible to all Grade 8 students'),
('Student Grade 9', 'Visible to all Grade 9 students'),
('Student Grade 10','Visible to all Grade 10 students'),
('Student Grade 11','Visible to all Grade 11 students'),
('Student Grade 12','Visible to all Grade 12 students');

-- ── Notice Categories (8 parents, 25 sub-categories) ──────────
INSERT INTO notice_categories (categoryName, subCategory, description) VALUES
('Academic',           'Exams',               'Exam-related notices'),
('Academic',           'Assignments',         'Assignment notices'),
('Academic',           'Results',             'Result announcements'),
('Academic',           'Syllabus Updates',    'Syllabus change notices'),
('Administrative',     'Fees',                'Fee-related notices'),
('Administrative',     'Timetables',          'Timetable updates'),
('Administrative',     'Policies',            'Policy announcements'),
('Administrative',     'Circulars',           'Official circulars'),
('Examination',        'Exam Schedules',      'Upcoming exam schedules'),
('Examination',        'Seating Arrangements','Seating plan notices'),
('Examination',        'Hall Ticket Notices', 'Hall ticket info'),
('Events',             'Annual Function',     'Annual day events'),
('Events',             'Sports Day',          'Sports events'),
('Events',             'Cultural Programs',   'Cultural activities'),
('Holidays',           'Public Holidays',     'Public holiday notices'),
('Holidays',           'Vacation Announcements','Vacation info'),
('Urgent / Emergency', 'School Closure',      'Closure announcements'),
('Urgent / Emergency', 'Weather Alerts',      'Weather-related alerts'),
('Urgent / Emergency', 'Safety Announcements','Safety info'),
('Co-Curricular',      'Clubs',               'Club activities'),
('Co-Curricular',      'Competitions',        'Competition info'),
('Co-Curricular',      'Workshops',           'Workshop notices'),
('Discipline',         'Code of Conduct',     'Conduct rules'),
('Discipline',         'Warnings',            'Warning notices'),
('Discipline',         'Attendance Issues',   'Attendance alerts');

-- ── Seed Users ────────────────────────────────────────────────
-- Passwords are: Admin@123 / Teacher@123 / Student@123
-- Hash = SHA256( SHA256(password) + salt )
INSERT INTO users (fullName, email, password, salt, role, grade, phoneNo) VALUES
(
  'System Administrator',
  'admin@school.edu',
  SHA2(CONCAT(SHA2('Admin@123',256),'admsalt2024'),256),
  'admsalt2024',
  'Admin', NULL, '9000000001'
),
(
  'Prof. Ramesh Kumar',
  'teacher@school.edu',
  SHA2(CONCAT(SHA2('Teacher@123',256),'tchsalt2024'),256),
  'tchsalt2024',
  'Teacher', NULL, '9000000002'
),
(
  'Arjun Mehta',
  'student@school.edu',
  SHA2(CONCAT(SHA2('Student@123',256),'stdsalt2024'),256),
  'stdsalt2024',
  'Student', '10', '9000000003'
);

-- ── Sample Notices ─────────────────────────────────────────────
INSERT INTO notices
    (title, description, categoryID, scopeID, targetGrade, publishDate, expiryDate, createdBy)
VALUES
(
  'Mid-Term Exam Schedule Released',
  'The mid-term examination schedule for all classes has been released. Students are advised to check the detailed timetable and prepare accordingly. All exams will be held in the main examination hall.',
  1, 1, NULL, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1
),
(
  'Annual Sports Day – Registration Open',
  'Registration for the Annual Sports Day is now open. Students interested in track events, field events, and team sports can register with their Physical Education teacher by Friday.',
  13, 1, NULL, NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY), 1
),
(
  'Fee Payment Reminder – Last Date',
  'This is a reminder that the last date for fee payment for the current term is approaching. Parents/guardians are requested to clear all dues to avoid late penalties.',
  5, 1, NULL, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 1
),
(
  'Staff Meeting – Curriculum Review',
  'All teaching staff are required to attend a mandatory curriculum review meeting covering the updated syllabus and new assessment rubrics.',
  7, 2, NULL, NOW(), NULL, 2
),
(
  'Grade 10 – Pre-Board Examination Notice',
  'All Grade 10 students are informed that the Pre-Board examinations will commence from next Monday. Hall tickets have been dispatched. Students must carry their hall ticket to every exam.',
  9, 4, '10', NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1
);
