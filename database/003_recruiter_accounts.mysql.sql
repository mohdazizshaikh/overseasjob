-- Private, gated pilot: execute on BACKED UP staging after 001 and 002 only.
-- Sending actual email and independent employer verification remain separate external gates.
CREATE TABLE IF NOT EXISTS oj_employer_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id VARCHAR(72) NOT NULL,
  email VARCHAR(254) COLLATE utf8mb4_bin NOT NULL,
  password_hash VARCHAR(255) NULL,
  role ENUM('admin','manager','viewer') NOT NULL DEFAULT 'manager',
  status ENUM('invited','active','revoked') NOT NULL DEFAULT 'invited',
  email_verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_employer_member_email(email),
  INDEX idx_employer_member_org(org_id,status),
  FOREIGN KEY(org_id) REFERENCES oj_employer_orgs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS oj_employer_email_challenges (
  member_id BIGINT UNSIGNED PRIMARY KEY,
  code_digest CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY(member_id) REFERENCES oj_employer_members(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS oj_recruiter_auth_rate (
  identity_hash CHAR(64) NOT NULL,
  minute_bucket DATETIME NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY(identity_hash, minute_bucket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
