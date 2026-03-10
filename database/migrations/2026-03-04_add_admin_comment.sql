USE uccrdc_issn;

ALTER TABLE submissions
  ADD COLUMN admin_comment TEXT NULL AFTER staff_recommendation;
