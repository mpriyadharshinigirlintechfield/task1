CREATE DATABASE IF NOT EXISTS expense_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE expense_db;

CREATE TABLE IF NOT EXISTS transactions (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tx_date    DATE          NOT NULL,
  category   VARCHAR(40)   NOT NULL,
  note       VARCHAR(255)  NOT NULL DEFAULT '',
  amount     DECIMAL(12,2) NOT NULL,            -- negative = expense, positive = income
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_date (tx_date),
  INDEX idx_category (category)
) ENGINE=InnoDB;

-- Sample data (January 2026)
INSERT INTO transactions (tx_date, category, note, amount) VALUES
('2026-01-21','Food','lunch',-15.50),
('2026-01-20','Food','Grocery shopping',-50.20),
('2026-01-20','Entertainment','concert ticket',-120.00),
('2026-01-25','Freelance','logo design project',850.00),
('2026-01-05','Transport','petrol',-60.00),
('2026-01-02','Rent','apartment rent',-1200.00),
('2026-01-01','Salary','monthly salary',5000.00);