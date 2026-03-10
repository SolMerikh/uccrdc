-- UCC – RDC ISSN Application Portal (Native PHP)
-- Create DB then import this file.

CREATE DATABASE IF NOT EXISTS uccrdc_issn CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uccrdc_issn;

CREATE TABLE IF NOT EXISTS authors (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  last_name VARCHAR(100) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  contact_number VARCHAR(30) NOT NULL,
  course VARCHAR(190) NULL,
  address VARCHAR(255) NOT NULL,
  postal_code VARCHAR(20) NOT NULL,
  region VARCHAR(120) NOT NULL,
  valid_id_path VARCHAR(255) NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  role ENUM('Author','Staff','Admin') NOT NULL DEFAULT 'Author',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_authors_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS publication_formats (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_publication_formats_name (name)
) ENGINE=InnoDB;

INSERT IGNORE INTO publication_formats (name, is_active) VALUES
 ('Print', 1), ('CD/DVD', 1), ('Online', 1);

CREATE TABLE IF NOT EXISTS submissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  author_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  starting_date DATE NOT NULL,
  frequency VARCHAR(100) NOT NULL,
  language VARCHAR(60) NOT NULL,
  suggested_retail_price DECIMAL(10,2) NULL,
  formerly_published TINYINT(1) NOT NULL DEFAULT 0,
  former_title VARCHAR(255) NULL,
  former_form_of_publication VARCHAR(190) NULL,
  former_starting_date DATE NULL,
  former_ending_date DATE NULL,
  pdf_path VARCHAR(255) NOT NULL,
  status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  staff_recommendation TEXT NULL,
  admin_comment TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_submissions_author (author_id),
  KEY idx_submissions_status (status),
  CONSTRAINT fk_submissions_author FOREIGN KEY (author_id) REFERENCES authors(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submission_formats (
  submission_id INT UNSIGNED NOT NULL,
  format_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (submission_id, format_id),
  CONSTRAINT fk_submission_formats_submission FOREIGN KEY (submission_id) REFERENCES submissions(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_submission_formats_format FOREIGN KEY (format_id) REFERENCES publication_formats(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Optional seed admin (change password after first login)
-- Password: Admin@12345
INSERT IGNORE INTO authors (last_name, first_name, email, contact_number, address, postal_code, region, password_hash, status, role)
VALUES (
  'System', 'Admin', 'admin@ucc.local', '0000000000', 'UCC', '0000', 'NCR',
  '$2y$10$KY5bIaTqviQvTQCWNhWVVuN.qH4lmnE4mBoXZ3MKKKphhDJDnOkI.',
  'Active', 'Admin'
);

CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  author_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  request_ip VARCHAR(45) NULL,
  request_ua VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_resets_token (token_hash),
  KEY idx_password_resets_author (author_id),
  CONSTRAINT fk_password_resets_author FOREIGN KEY (author_id) REFERENCES authors(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS colleges (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_colleges_code (code),
  UNIQUE KEY uq_colleges_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS courses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  college_id INT UNSIGNED NOT NULL,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(160) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_courses_college_code (college_id, code),
  UNIQUE KEY uq_courses_college_name (college_id, name),
  KEY idx_courses_college (college_id),
  CONSTRAINT fk_courses_college FOREIGN KEY (college_id) REFERENCES colleges(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;
