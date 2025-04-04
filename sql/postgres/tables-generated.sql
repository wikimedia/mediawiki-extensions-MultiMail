-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: sql/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE user_secondary_email (
  use_id SERIAL NOT NULL,
  use_cuid INT NOT NULL,
  use_email TEXT NOT NULL,
  use_email_authenticated TIMESTAMPTZ DEFAULT NULL,
  use_email_token TEXT DEFAULT NULL,
  use_email_token_expires TIMESTAMPTZ DEFAULT NULL,
  PRIMARY KEY(use_id)
);

CREATE INDEX use_cuid ON user_secondary_email (use_cuid);

CREATE INDEX use_email_token ON user_secondary_email (use_email_token);

CREATE INDEX use_email ON user_secondary_email (use_email);
