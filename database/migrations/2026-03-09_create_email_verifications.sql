-- Create email verification table for OTP-based registration

CREATE TABLE IF NOT EXISTS email_verifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  otp_code VARCHAR(6) NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  verified_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_verifications_email (email),
  KEY idx_email_verifications_expires (expires_at)
) ENGINE=InnoDB;
