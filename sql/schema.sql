-- DB schema will live here
-- Splitcries MVP schema (MariaDB / MySQL)

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `groups` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(12) NOT NULL UNIQUE,
  owner_user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_groups_owner
    FOREIGN KEY (owner_user_id) REFERENCES users(id)
    ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE group_members (
  group_id INT NOT NULL,
  user_id INT NOT NULL,
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (group_id, user_id),
  CONSTRAINT fk_members_group
    FOREIGN KEY (group_id) REFERENCES `groups`(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_members_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  paid_by_user_id INT NOT NULL,
  amount_cents INT NOT NULL,
  description VARCHAR(255) NOT NULL,
  expense_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expenses_group
    FOREIGN KEY (group_id) REFERENCES `groups`(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_expenses_paid_by
    FOREIGN KEY (paid_by_user_id) REFERENCES users(id)
    ON DELETE RESTRICT,
  INDEX idx_expenses_group (group_id),
  INDEX idx_expenses_paid_by (paid_by_user_id)
) ENGINE=InnoDB;