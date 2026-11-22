-- Gated recruiter-only password recovery. Run ONCE after 001–004 on verified, backed-up staging.
-- No candidate identities or applicant documents are stored here.
ALTER TABLE oj_employer_members
  ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS oj_employer_password_resets (
  member_id BIGINT UNSIGNED PRIMARY KEY,
  code_digest CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  FOREIGN KEY(member_id) REFERENCES oj_employer_members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
