-- ============================================================
--  TaxFlow CRM — Add Tasks Table
--  Run in phpMyAdmin › SQL tab AFTER taxflow_schema.sql
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(255)    NOT NULL,
  `notes`       TEXT,
  `client_id`   INT UNSIGNED             DEFAULT NULL,
  `return_id`   INT UNSIGNED             DEFAULT NULL,
  `assignee_id` INT UNSIGNED             DEFAULT NULL,
  `priority`    ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status`      ENUM('open','in_progress','done','cancelled') NOT NULL DEFAULT 'open',
  `due_date`    DATE                     DEFAULT NULL,
  `done_at`     TIMESTAMP                DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assignee` (`assignee_id`),
  KEY `idx_client`   (`client_id`),
  KEY `idx_status`   (`status`),
  KEY `idx_due`      (`due_date`),
  CONSTRAINT `fk_task_client`   FOREIGN KEY (`client_id`)   REFERENCES `clients`(`id`)      ON DELETE SET NULL,
  CONSTRAINT `fk_task_return`   FOREIGN KEY (`return_id`)   REFERENCES `tax_returns`(`id`)  ON DELETE SET NULL,
  CONSTRAINT `fk_task_assignee` FOREIGN KEY (`assignee_id`) REFERENCES `staff`(`id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Demo tasks ──
INSERT INTO `tasks` (`title`, `notes`, `client_id`, `return_id`, `assignee_id`, `priority`, `status`, `due_date`) VALUES
  ('Review Henderson & Walsh 1120-S draft',        'Check officer comp and depreciation schedule before sending to client for approval.', 1, 1, 1, 'urgent',  'open',        '2026-04-29'),
  ('Follow up: Bright Arch Studios missing W-2s',  'Third request sent Apr 28. Call if no response by Apr 30.',                          5, 5, 1, 'urgent',  'open',        '2026-04-30'),
  ('Complete David Reinholt FBAR filing',          'FinCEN 114 due Apr 15 via BSA E-Filing. Foreign bank account details confirmed.',    4, 4, 3, 'high',    'in_progress', '2026-04-29'),
  ('Prepare Coastal Baking Section 179 election',  'Confirm new bakery equipment qualifies. Enter in Lacerte before filing.',            7, 7, 2, 'high',    'open',        '2026-04-30'),
  ('Send Q1 estimated payment reminders',          'Email all clients with Q1 estimates due Apr 15 who have not yet confirmed payment.', NULL, NULL, 1, 'medium', 'done', '2026-04-13'),
  ('Request James Mercer RSU tax documents',       'Need Form 3921 and broker 1099-B for stock sales.',                                  8, 8, 1, 'medium',  'done',        '2026-04-14'),
  ('Update Rivera Family Trust beneficiary statements', 'DNI calculation complete. Generate and mail K-1 equivalents.',                  10, 10, 3, 'medium', 'open',       '2026-05-01'),
  ('Reconcile Sunrise Tech partnership books',     'Member A and B books need to agree before 1065 can be filed.',                      3, 3, 2, 'high',    'in_progress', '2026-04-28'),
  ('Invoice Henderson & Walsh — 2025 return fee',  'INV-1089 sent. Follow up if not paid by May 1.',                                   1, NULL, 1, 'medium', 'open',       '2026-05-01'),
  ('File Maria Fontaine CA estimated payment',     'Q2 CA estimate due Jun 15. Prepare Form 540-ES.',                                   6, NULL, 2, 'low',    'open',       '2026-06-10'),
  ('Partner review: Meridian Partners 1065',       'Final review of 12 K-1s before archiving. Confirm all partners received copies.',   9, 9, 2, 'low',     'done',        '2026-04-05'),
  ('Set up new client onboarding — Coastal Baking','Update engagement letter template for 2026 season. Send organizer.',               7, NULL, 2, 'low',    'open',        '2026-05-15');
