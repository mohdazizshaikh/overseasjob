-- Run only on a backed-up, isolated STAGING MySQL database after 001.
-- Do not invent evidence: these fields remain NULL until independently verified by the operator.
ALTER TABLE oj_employer_orgs
  ADD COLUMN representative_verified_at DATETIME NULL;
CREATE TABLE IF NOT EXISTS oj_partner_review_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id VARCHAR(72) NOT NULL,
  job_id CHAR(28) NOT NULL,
  old_revision INT UNSIGNED NOT NULL,
  new_revision INT UNSIGNED NOT NULL,
  decision ENUM('publish','reject','suspend') NOT NULL,
  reason VARCHAR(500) NOT NULL,
  actor VARCHAR(90) NOT NULL,
  decided_at DATETIME NOT NULL,
  INDEX idx_review_org_job(org_id,job_id,id),
  FOREIGN KEY(org_id) REFERENCES oj_employer_orgs(id),
  FOREIGN KEY(job_id) REFERENCES oj_partner_jobs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Database migrations do NOT approve any employer or publish any posting.
