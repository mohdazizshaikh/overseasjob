-- MySQL 8 staging-only migration: DNS control evidence is NOT full company verification.
CREATE TABLE IF NOT EXISTS oj_employer_domain_challenges (
  org_id VARCHAR(72) NOT NULL,
  domain VARCHAR(253) NOT NULL,
  digest CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  verified_at DATETIME NULL,
  PRIMARY KEY(org_id,domain),
  FOREIGN KEY(org_id) REFERENCES oj_employer_orgs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
