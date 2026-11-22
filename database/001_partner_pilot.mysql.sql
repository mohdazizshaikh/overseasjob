-- Pilot extension; PRIVATE MySQL 8+ staging only. Do not execute without verified hosting,
-- an offsite backup and migration rollback. Intentionally does not touch provider jobs.
-- Time zone for sessions is UTC. Never store applicant CVs/passports or raw bearer tokens.
CREATE TABLE IF NOT EXISTS oj_employer_orgs (
  id VARCHAR(72) PRIMARY KEY,
  company_name VARCHAR(255) NOT NULL,
  status ENUM('unverified','pending_review','approved','suspended','closed') NOT NULL DEFAULT 'unverified',
  email_verified_at DATETIME NULL,
  domain_verified_at DATETIME NULL,
  approved_at DATETIME NULL,
  approved_by VARCHAR(128) NULL,
  evidence_reference VARCHAR(1024) NULL,
  verified_domains_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_employer_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS oj_partner_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id VARCHAR(72) NOT NULL,
  token_prefix CHAR(12) NOT NULL,
  fingerprint CHAR(64) NOT NULL,
  scopes_json JSON NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_partner_fingerprint(fingerprint),
  UNIQUE KEY uq_partner_prefix(token_prefix),
  FOREIGN KEY (org_id) REFERENCES oj_employer_orgs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS oj_partner_jobs (
  id CHAR(28) PRIMARY KEY,
  org_id VARCHAR(72) NOT NULL,
  external_id VARCHAR(160) NOT NULL,
  payload_json JSON NOT NULL,
  status ENUM('draft','pending_review','published','closed','expired','rejected','suspended') NOT NULL DEFAULT 'pending_review',
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  valid_through DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_partner_org_external (org_id, external_id),
  INDEX idx_partner_org_id (org_id,id),
  INDEX idx_partner_expires (status,valid_through),
  FOREIGN KEY(org_id) REFERENCES oj_employer_orgs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS oj_partner_idempotency (
  org_id VARCHAR(72) NOT NULL,
  request_key VARCHAR(128) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  response_json JSON NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY(org_id,request_key),
  FOREIGN KEY(org_id) REFERENCES oj_employer_orgs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS oj_partner_rate (
  token_id BIGINT UNSIGNED NOT NULL,
  minute_utc DATETIME NOT NULL,
  requests INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY(token_id,minute_utc),
  FOREIGN KEY(token_id) REFERENCES oj_partner_tokens(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS oj_partner_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id VARCHAR(72) NOT NULL,
  job_id CHAR(28) NOT NULL,
  action VARCHAR(64) NOT NULL,
  occurred_at DATETIME NOT NULL,
  INDEX idx_partner_audit(job_id,occurred_at),
  FOREIGN KEY(org_id) REFERENCES oj_employer_orgs(id),
  FOREIGN KEY(job_id) REFERENCES oj_partner_jobs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Future verified human review must explicitly mark published in a separate controlled audit;
-- no API operation can self-approve a job. This migration has NO public publish trigger.
