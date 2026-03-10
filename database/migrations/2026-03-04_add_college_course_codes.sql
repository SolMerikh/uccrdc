-- Add code columns for colleges and courses

ALTER TABLE colleges
  ADD COLUMN code VARCHAR(20) NOT NULL AFTER id,
  ADD UNIQUE KEY uq_colleges_code (code);

ALTER TABLE courses
  ADD COLUMN code VARCHAR(30) NOT NULL AFTER college_id,
  ADD UNIQUE KEY uq_courses_college_code (college_id, code);
