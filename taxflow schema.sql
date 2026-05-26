-- ============================================================
--  TaxFlow CRM — Complete Database Schema + Demo Data
--  Database : opbvihjf5mioj6gs_taxflow
--  Run in   : phpMyAdmin › SQL tab
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
--  TABLE: staff   (CPA / preparers)
-- ============================================================
CREATE TABLE IF NOT EXISTS `staff` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)    NOT NULL,
  `initials`   VARCHAR(4)      NOT NULL,
  `role`       VARCHAR(80)     NOT NULL DEFAULT '',
  `email`      VARCHAR(150)    NOT NULL DEFAULT '',
  `color`      VARCHAR(10)     NOT NULL DEFAULT '#c8922a',
  `active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `staff` (`name`, `initials`, `role`, `email`, `color`) VALUES
  ('Margaret Ross',  'MR', 'Senior CPA',       'margaret@taxflow.com',  '#3d5a47'),
  ('James Park',     'JP', 'CPA',               'james@taxflow.com',     '#2a6496'),
  ('Sofia Chen',     'SC', 'Staff Accountant',  'sofia@taxflow.com',     '#7c4d8a');

-- ============================================================
--  TABLE: clients
-- ============================================================
CREATE TABLE IF NOT EXISTS `clients` (
  `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `display_name`     VARCHAR(150)    NOT NULL,
  `initials`         VARCHAR(4)      NOT NULL DEFAULT '',
  `avatar_color`     VARCHAR(10)     NOT NULL DEFAULT '#3d5a47',
  `entity_type`      ENUM('individual','s_corp','c_corp','llc','partnership','trust','nonprofit')
                                     NOT NULL DEFAULT 'individual',
  `industry`         VARCHAR(100)    NOT NULL DEFAULT '',
  -- Tax IDs
  `ein`              VARCHAR(20)     NOT NULL DEFAULT '',
  `ssn_last4`        VARCHAR(4)      NOT NULL DEFAULT '',
  -- Contact
  `contact_name`     VARCHAR(150)    NOT NULL DEFAULT '',
  `email`            VARCHAR(150)    NOT NULL DEFAULT '',
  `phone`            VARCHAR(30)     NOT NULL DEFAULT '',
  `address_line1`    VARCHAR(150)    NOT NULL DEFAULT '',
  `address_line2`    VARCHAR(150)    NOT NULL DEFAULT '',
  `city`             VARCHAR(80)     NOT NULL DEFAULT '',
  `state`            VARCHAR(50)     NOT NULL DEFAULT '',
  `zip`              VARCHAR(20)     NOT NULL DEFAULT '',
  -- Business details
  `fiscal_year_end`  VARCHAR(20)     NOT NULL DEFAULT 'December 31',
  `filing_states`    VARCHAR(100)    NOT NULL DEFAULT '',
  `incorporated_year`SMALLINT        NOT NULL DEFAULT 0,
  `employees`        VARCHAR(80)     NOT NULL DEFAULT '',
  `revenue_est`      DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `payroll_provider` VARCHAR(80)     NOT NULL DEFAULT '',
  `accounting_sw`    VARCHAR(80)     NOT NULL DEFAULT '',
  -- CRM
  `assignee_id`      INT UNSIGNED    NOT NULL DEFAULT 1,
  `client_since`     SMALLINT        NOT NULL DEFAULT 2020,
  `status`           ENUM('active','inactive','prospect','archived')
                                     NOT NULL DEFAULT 'active',
  `notes`            TEXT,
  `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assignee` (`assignee_id`),
  KEY `idx_entity`   (`entity_type`),
  KEY `idx_status`   (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  TABLE: tax_returns
-- ============================================================
CREATE TABLE IF NOT EXISTS `tax_returns` (
  `id`                 INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `client_id`          INT UNSIGNED    NOT NULL,
  `tax_year`           SMALLINT        NOT NULL,
  `form_type`          VARCHAR(30)     NOT NULL,           -- '1040','1120-S','1065','1120','1041'
  `jurisdiction`       VARCHAR(100)    NOT NULL DEFAULT 'Federal',
  `status`             ENUM('not_started','awaiting_docs','in_progress','in_review','filed','extension','archived')
                                       NOT NULL DEFAULT 'not_started',
  `due_date`           DATE            NOT NULL,
  `filed_date`         DATE                     DEFAULT NULL,
  `extension_date`     DATE                     DEFAULT NULL,
  `completion_pct`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  -- Financial figures
  `gross_revenue`      DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `total_expenses`     DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `net_income`         DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `tax_liability`      DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `estimated_paid`     DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `refund_amount`      DECIMAL(14,2)   NOT NULL DEFAULT 0,  -- positive=refund, negative=owed
  `officer_comp`       DECIMAL(14,2)   NOT NULL DEFAULT 0,
  -- People
  `assignee_id`        INT UNSIGNED    NOT NULL DEFAULT 1,
  `software`           VARCHAR(80)     NOT NULL DEFAULT 'Lacerte',
  `return_ref`         VARCHAR(30)     NOT NULL DEFAULT '',
  `fee`                DECIMAL(10,2)   NOT NULL DEFAULT 0,
  `notes`              TEXT,
  `created_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client`  (`client_id`),
  KEY `idx_status`  (`status`),
  KEY `idx_year`    (`tax_year`),
  KEY `idx_due`     (`due_date`),
  CONSTRAINT `fk_return_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  TABLE: return_checklist_items
-- ============================================================
CREATE TABLE IF NOT EXISTS `return_checklist` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `return_id`   INT UNSIGNED    NOT NULL,
  `sort_order`  TINYINT         NOT NULL DEFAULT 0,
  `label`       VARCHAR(200)    NOT NULL,
  `status`      ENUM('pending','done','skipped','blocked') NOT NULL DEFAULT 'pending',
  `assignee_id` INT UNSIGNED             DEFAULT NULL,
  `done_at`     DATE                     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_return` (`return_id`),
  CONSTRAINT `fk_check_return` FOREIGN KEY (`return_id`) REFERENCES `tax_returns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  TABLE: documents
-- ============================================================
CREATE TABLE IF NOT EXISTS `documents` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `client_id`   INT UNSIGNED    NOT NULL,
  `return_id`   INT UNSIGNED             DEFAULT NULL,
  `filename`    VARCHAR(255)    NOT NULL,
  `file_type`   ENUM('pdf','xlsx','csv','img','misc') NOT NULL DEFAULT 'misc',
  `file_size_kb`INT             NOT NULL DEFAULT 0,
  `status`      ENUM('received','missing','requested','superseded') NOT NULL DEFAULT 'received',
  `uploaded_by` ENUM('client','staff','system') NOT NULL DEFAULT 'staff',
  `uploaded_at` DATE                     DEFAULT NULL,
  `notes`       VARCHAR(255)    NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_return` (`return_id`),
  CONSTRAINT `fk_doc_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_doc_return` FOREIGN KEY (`return_id`) REFERENCES `tax_returns`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  TABLE: estimated_payments
-- ============================================================
CREATE TABLE IF NOT EXISTS `estimated_payments` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `return_id`   INT UNSIGNED    NOT NULL,
  `quarter`     TINYINT         NOT NULL,  -- 1,2,3,4
  `due_date`    DATE            NOT NULL,
  `amount`      DECIMAL(12,2)   NOT NULL DEFAULT 0,
  `paid`        TINYINT(1)      NOT NULL DEFAULT 0,
  `paid_date`   DATE                     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_return` (`return_id`),
  CONSTRAINT `fk_est_return` FOREIGN KEY (`return_id`) REFERENCES `tax_returns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  TABLE: activity_log
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `client_id`   INT UNSIGNED    NOT NULL,
  `return_id`   INT UNSIGNED             DEFAULT NULL,
  `type`        ENUM('note','call','email','document','status_change','filed','payment','system')
                                NOT NULL DEFAULT 'note',
  `icon`        VARCHAR(10)     NOT NULL DEFAULT '',  -- emoji
  `body`        TEXT            NOT NULL,
  `staff_id`    INT UNSIGNED             DEFAULT NULL,
  `source`      VARCHAR(80)     NOT NULL DEFAULT '',
  `logged_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_return` (`return_id`),
  KEY `idx_logged` (`logged_at`),
  CONSTRAINT `fk_log_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  TABLE: invoices
-- ============================================================
CREATE TABLE IF NOT EXISTS `invoices` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `client_id`   INT UNSIGNED    NOT NULL,
  `return_id`   INT UNSIGNED             DEFAULT NULL,
  `invoice_ref` VARCHAR(20)     NOT NULL,
  `amount`      DECIMAL(10,2)   NOT NULL DEFAULT 0,
  `status`      ENUM('unpaid','paid','overdue','void') NOT NULL DEFAULT 'unpaid',
  `issued_date` DATE            NOT NULL,
  `paid_date`   DATE                     DEFAULT NULL,
  `notes`       VARCHAR(255)    NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  CONSTRAINT `fk_inv_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  TABLE: shareholders (for K-1 / partnership distributions)
-- ============================================================
CREATE TABLE IF NOT EXISTS `shareholders` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `return_id`   INT UNSIGNED    NOT NULL,
  `name`        VARCHAR(150)    NOT NULL,
  `ownership_pct` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `distribution` DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_return` (`return_id`),
  CONSTRAINT `fk_sh_return` FOREIGN KEY (`return_id`) REFERENCES `tax_returns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  DEMO DATA — 10 CLIENTS
-- ============================================================

INSERT INTO `clients`
  (`display_name`,`initials`,`avatar_color`,`entity_type`,`industry`,
   `ein`,`ssn_last4`,`contact_name`,`email`,`phone`,
   `address_line1`,`city`,`state`,`zip`,
   `fiscal_year_end`,`filing_states`,`incorporated_year`,`employees`,
   `revenue_est`,`payroll_provider`,`accounting_sw`,
   `assignee_id`,`client_since`,`status`)
VALUES
  -- 1 Henderson & Walsh
  ('Henderson & Walsh LLC','HW','#3d5a47','s_corp','Architecture & Design',
   '47-2831094','','Andrea Henderson & James Walsh','james@hendersonwalsh.com','(415) 555-0192',
   '1240 Market St, Ste 400','San Francisco','CA','94102',
   'December 31','CA, NV',2014,'12 FTE + 3 contractors',
   2380000.00,'Gusto','QuickBooks Online',
   1,2018,'active'),

  -- 2 Patricia Okonkwo
  ('Patricia Okonkwo','PO','#7c4d8a','individual','Freelance Consulting',
   '','4821','Patricia Okonkwo','p.okonkwo@email.com','(510) 555-0341',
   '88 Lakeview Ave, Apt 3B','Oakland','CA','94610',
   'December 31','CA',0,'Self-employed',
   142000.00,'','FreshBooks',
   1,2020,'active'),

  -- 3 Sunrise Tech LLC
  ('Sunrise Tech LLC','ST','#c8922a','llc','Technology',
   '83-1047522','','Kevin Zhao','kevin@sunrisetech.io','(650) 555-0874',
   '3200 El Camino Real','Palo Alto','CA','94306',
   'December 31','CA',2019,'8 FTE + 5 contractors',
   980000.00,'Rippling','Xero',
   2,2021,'active'),

  -- 4 David Reinholt
  ('David Reinholt','DR','#2a6496','individual','Finance',
   '','6630','David Reinholt','d.reinholt@email.com','(212) 555-0229',
   '440 Park Ave, Apt 18C','New York','NY','10022',
   'December 31','NY, CA, Foreign',0,'',
   310000.00,'','',
   3,2022,'active'),

  -- 5 Bright Arch Studios
  ('Bright Arch Studios','BA','#b84c2e','c_corp','Creative & Media',
   '26-4918302','','Rachel Bright','rachel@brightarch.com','(323) 555-0617',
   '9001 Sunset Blvd, Ste 200','Los Angeles','CA','90069',
   'December 31','CA',2017,'24 FTE',
   5100000.00,'ADP','NetSuite',
   1,2019,'active'),

  -- 6 Maria Fontaine
  ('Maria Fontaine','MF','#4a7c6e','individual','Real Estate',
   '','1192','Maria Fontaine','maria.fontaine@email.com','(415) 555-0053',
   '720 Green St','San Francisco','CA','94133',
   'December 31','CA',0,'',
   94000.00,'','Stessa',
   2,2021,'active'),

  -- 7 Coastal Baking Co.
  ('Coastal Baking Co.','CB','#4a7c6e','s_corp','Food & Beverage',
   '58-0293741','','Tim Brower','tim@coastalbaking.com','(858) 555-0412',
   '1800 Harbor Blvd','San Diego','CA','92101',
   'December 31','CA',2011,'31 FTE',
   1650000.00,'Paychex','QuickBooks Desktop',
   2,2017,'active'),

  -- 8 James Mercer
  ('James Mercer','JM','#7c4d8a','individual','Healthcare',
   '','7744','James Mercer','j.mercer@email.com','(415) 555-0788',
   '55 Divisadero St, Apt 4','San Francisco','CA','94117',
   'December 31','CA',0,'',
   185000.00,'','',
   1,2023,'active'),

  -- 9 Meridian Partners LP
  ('Meridian Partners LP','MP','#2a6496','partnership','Real Estate Investment',
   '31-7482019','','Grace Whitmore','grace@meridianpartners.com','(312) 555-0300',
   '200 S Michigan Ave, Ste 1800','Chicago','IL','60604',
   'December 31','IL, CA, TX',2008,'4 FTE',
   8200000.00,'','Yardi',
   2,2015,'active'),

  -- 10 Rivera Family Trust
  ('Rivera Family Trust','RV','#5d4037','trust','Trust & Estate',
   '','','Carlos Rivera (Trustee)','carlos.rivera@email.com','(619) 555-0944',
   '340 Coronado Ave','Coronado','CA','92118',
   'December 31','CA',0,'',
   420000.00,'','',
   3,2019,'active');

-- ============================================================
--  TAX RETURNS  (2025 tax year, filed in 2026)
-- ============================================================

INSERT INTO `tax_returns`
  (`client_id`,`tax_year`,`form_type`,`jurisdiction`,`status`,`due_date`,`filed_date`,
   `completion_pct`,`gross_revenue`,`total_expenses`,`net_income`,
   `tax_liability`,`estimated_paid`,`refund_amount`,`officer_comp`,
   `assignee_id`,`software`,`return_ref`,`fee`,`notes`)
VALUES
  -- 1 Henderson & Walsh — In Review
  (1,2025,'1120-S','Federal + CA','in_review','2026-04-15',NULL,
   75,2380000,1940000,440000,84400,92820,8420,180000,
   1,'Lacerte','RTN-2025-0041',3200,
   'Awaiting 1099-NEC contractor list and vehicle mileage log before finalizing Schedule K.'),

  -- 2 Patricia Okonkwo — Filed
  (2,2025,'1040','Federal + CA','filed','2026-04-15','2026-04-22',
   100,142000,38400,103600,18200,21500,3300,0,
   1,'ProConnect','RTN-2025-0042',850,
   'Schedule C freelance income. Mileage deduction applied. State refund processed.'),

  -- 3 Sunrise Tech — Awaiting Docs
  (3,2025,'1065','Federal + CA','awaiting_docs','2026-04-15',NULL,
   35,980000,812000,168000,0,0,-2100,0,
   2,'Lacerte','RTN-2025-0043',3200,
   'Multi-member LLC. Missing K-1 workpapers from two members. Partnership books not reconciled.'),

  -- 4 David Reinholt — In Progress
  (4,2025,'1040','Federal + NY + CA + FBAR','in_progress','2026-04-15',NULL,
   55,310000,42000,268000,61200,60660,540,0,
   3,'ProConnect','RTN-2025-0044',950,
   'Foreign bank account (FBAR due Apr 15 via FinCEN). Foreign tax credit to be calculated.'),

  -- 5 Bright Arch Studios — Awaiting Docs
  (5,2025,'1120','Federal + CA','awaiting_docs','2026-04-15',NULL,
   20,5100000,4620000,480000,100800,86000,-14800,0,
   1,'Lacerte','RTN-2025-0045',4800,
   'C-Corp. Missing signed financial statements and fixed asset additions list.'),

  -- 6 Maria Fontaine — Filed
  (6,2025,'1040','Federal + CA','filed','2026-04-15','2026-04-23',
   100,94000,28600,65400,10800,12690,1890,0,
   2,'ProConnect','RTN-2025-0046',650,
   'Schedule E rental income — 2 properties. Depreciation schedules updated.'),

  -- 7 Coastal Baking — In Progress
  (7,2025,'1120-S','Federal + CA','in_progress','2026-04-15',NULL,
   48,1650000,1428000,222000,46200,42550,-3650,120000,
   2,'Lacerte','RTN-2025-0047',2400,
   'Officer compensation reviewed. 179 elections on new bakery equipment to be confirmed.'),

  -- 8 James Mercer — In Review
  (8,2025,'1040','Federal + CA','in_review','2026-04-15',NULL,
   85,185000,14200,170800,38400,36220,2180,0,
   1,'ProConnect','RTN-2025-0048',650,
   'W-2 + RSU income. AMT exposure reviewed — no AMT triggered. Student loan interest deduction applied.'),

  -- 9 Meridian Partners — Filed
  (9,2025,'1065','Federal + IL + CA + TX','filed','2026-09-15','2026-03-28',
   100,8200000,7380000,820000,0,0,4200,0,
   2,'Lacerte','RTN-2025-0049',5600,
   'Extension filed Mar 15. 12 partners, K-1s distributed. State apportionment: IL 60%, CA 25%, TX 15%.'),

  -- 10 Rivera Family Trust — In Review
  (10,2025,'1041','Federal + CA','in_review','2026-04-15',NULL,
   90,420000,318000,102000,28400,27720,680,0,
   3,'ProConnect','RTN-2025-0050',1800,
   'Simple trust — distributes all income to two beneficiaries. DNI calculation complete.');

-- ============================================================
--  CHECKLIST ITEMS  (return_id 1 = Henderson & Walsh 1120-S)
-- ============================================================

INSERT INTO `return_checklist` (`return_id`,`sort_order`,`label`,`status`,`assignee_id`,`done_at`) VALUES
  (1,1,'Obtain signed engagement letter','done',1,'2026-04-18'),
  (1,2,'Review prior year return & carryovers','done',1,'2026-04-14'),
  (1,3,'Collect income statements (P&L, Balance Sheet)','done',1,'2026-04-27'),
  (1,4,'Collect payroll records & Form W-3','done',1,'2026-04-27'),
  (1,5,'Verify officer compensation & salary election','done',1,'2026-04-25'),
  (1,6,'Input financials into tax software (Lacerte)','done',1,'2026-04-26'),
  (1,7,'Receive & enter 1099-NEC contractor payments','blocked',NULL,NULL),
  (1,8,'Enter vehicle mileage & listed property','blocked',NULL,NULL),
  (1,9,'Partner review, client approval & e-file','pending',1,NULL);

INSERT INTO `return_checklist` (`return_id`,`sort_order`,`label`,`status`,`assignee_id`,`done_at`) VALUES
  (2,1,'Engagement letter signed','done',1,'2026-03-10'),
  (2,2,'Collect W-2, 1099-NEC, and bank statements','done',1,'2026-03-15'),
  (2,3,'Enter Schedule C business income & expenses','done',1,'2026-04-01'),
  (2,4,'Mileage log reviewed & deduction applied','done',1,'2026-04-05'),
  (2,5,'State return prepared (CA)','done',1,'2026-04-10'),
  (2,6,'Client review & approval received','done',1,'2026-04-20'),
  (2,7,'Federal & CA e-filed','done',1,'2026-04-22'),
  (2,8,'Acknowledgements received from IRS & FTB','done',1,'2026-04-23');

-- ============================================================
--  ESTIMATED PAYMENTS  (return_id 1 = Henderson & Walsh)
-- ============================================================

INSERT INTO `estimated_payments` (`return_id`,`quarter`,`due_date`,`amount`,`paid`,`paid_date`) VALUES
  (1,1,'2025-04-15',22500,1,'2025-04-12'),
  (1,2,'2025-06-16',22500,1,'2025-06-14'),
  (1,3,'2025-09-15',22500,1,'2025-09-13'),
  (1,4,'2026-01-15',25320,1,'2026-01-14'),
  (4,1,'2025-04-15',15000,1,'2025-04-15'),
  (4,2,'2025-06-16',15000,1,'2025-06-15'),
  (4,3,'2025-09-15',15330,1,'2025-09-14'),
  (4,4,'2026-01-15',15330,1,'2026-01-15'),
  (8,1,'2025-04-15',9200,1,'2025-04-14'),
  (8,2,'2025-06-16',9000,1,'2025-06-16'),
  (8,3,'2025-09-15',9010,1,'2025-09-12'),
  (8,4,'2026-01-15',9010,1,'2026-01-13');

-- ============================================================
--  DOCUMENTS
-- ============================================================

INSERT INTO `documents` (`client_id`,`return_id`,`filename`,`file_type`,`file_size_kb`,`status`,`uploaded_by`,`uploaded_at`,`notes`) VALUES
  (1,1,'2025 P&L Statement.pdf','pdf',284,'received','client','2026-04-27',''),
  (1,1,'Balance Sheet Dec 2025.xlsx','xlsx',142,'received','client','2026-04-27',''),
  (1,1,'Form W-3 2025.pdf','pdf',58,'received','client','2026-04-27',''),
  (1,1,'Engagement Letter Signed.pdf','pdf',210,'received','client','2026-04-18','DocuSign'),
  (1,1,'1120-S Draft v1.pdf','pdf',1200,'received','staff','2026-04-26','Lacerte export'),
  (1,1,'CA Form 100S Draft.pdf','pdf',680,'received','staff','2026-04-26','Lacerte export'),
  (1,1,'Depreciation Schedule.xlsx','xlsx',94,'received','staff','2026-04-26',''),
  (1,1,'1099-NEC Contractor List','misc',0,'missing','client',NULL,'Requested Apr 28'),
  (1,1,'Vehicle Mileage Log 2025','misc',0,'missing','client',NULL,'Requested Apr 28'),
  (2,2,'Form W-2 2025.pdf','pdf',120,'received','client','2026-03-15',''),
  (2,2,'1099-NEC Consulting.pdf','pdf',88,'received','client','2026-03-15',''),
  (2,2,'Schedule C Workpaper.pdf','pdf',210,'received','staff','2026-04-01',''),
  (2,2,'1040 Filed Copy.pdf','pdf',950,'received','staff','2026-04-22','E-file confirmation attached'),
  (3,3,'Partnership Agreement.pdf','pdf',440,'received','client','2026-03-01',''),
  (3,3,'K-1 Workpapers — Member A','misc',0,'missing','client',NULL,'Not yet received'),
  (3,3,'K-1 Workpapers — Member B','misc',0,'missing','client',NULL,'Not yet received'),
  (5,5,'Engagement Letter Signed.pdf','pdf',195,'received','client','2026-03-20',''),
  (5,5,'Signed Financial Statements 2025','misc',0,'missing','client',NULL,'Requested Mar 25'),
  (6,6,'1040 Filed Copy.pdf','pdf',880,'received','staff','2026-04-23','E-filed & acknowledged'),
  (9,9,'1065 Filed Copy.pdf','pdf',2100,'received','staff','2026-03-28','Extension e-filed Mar 15');

-- ============================================================
--  INVOICES
-- ============================================================

INSERT INTO `invoices` (`client_id`,`return_id`,`invoice_ref`,`amount`,`status`,`issued_date`,`paid_date`) VALUES
  (1,1,'INV-1089',3200,'unpaid','2026-04-01',NULL),
  (1,NULL,'INV-1044',600,'paid','2026-01-15','2026-01-20'),
  (2,2,'INV-1042',850,'paid','2026-04-22','2026-04-23'),
  (3,3,'INV-1078',3200,'unpaid','2026-04-10',NULL),
  (4,4,'INV-1081',950,'unpaid','2026-04-10',NULL),
  (5,5,'INV-1085',4800,'unpaid','2026-03-25',NULL),
  (6,6,'INV-1075',650,'paid','2026-04-23','2026-04-24'),
  (7,7,'INV-1082',2400,'unpaid','2026-04-12',NULL),
  (8,8,'INV-1086',650,'paid','2026-04-25','2026-04-26'),
  (9,9,'INV-1060',5600,'paid','2026-03-28','2026-04-02'),
  (10,10,'INV-1087',1800,'unpaid','2026-04-20',NULL);

-- ============================================================
--  SHAREHOLDERS / K-1
-- ============================================================

INSERT INTO `shareholders` (`return_id`,`name`,`ownership_pct`,`distribution`) VALUES
  (1,  'Andrea Henderson', 50.00, 220000),
  (1,  'James Walsh',      50.00, 220000),
  (9,  'Whitmore Capital', 40.00, 328000),
  (9,  'Rivera Holdings',  35.00, 287000),
  (9,  'Chen Family LP',   25.00, 205000),
  (10, 'Carlos Rivera',    60.00,  61200),
  (10, 'Elena Rivera',     40.00,  40800);

-- ============================================================
--  ACTIVITY LOG
-- ============================================================

INSERT INTO `activity_log` (`client_id`,`return_id`,`type`,`icon`,`body`,`staff_id`,`source`,`logged_at`) VALUES
  (1,1,'email','📧','Follow-up sent re: missing 1099-NEC contractor list and vehicle mileage log. Client acknowledged — expects to send by Apr 29.',1,'','2026-04-29 10:14:00'),
  (1,1,'document','📎','Client uploaded 3 documents: P&L Statement, Balance Sheet, Form W-3.',NULL,'client_portal','2026-04-28 15:41:00'),
  (1,1,'call','📞','Call with James Walsh (15 min). Discussed officer compensation, S-corp salary election, and Q1 2026 estimated payments.',1,'','2026-04-25 14:00:00'),
  (1,1,'status_change','✅','Engagement letter signed electronically by A. Henderson and J. Walsh.',NULL,'DocuSign','2026-04-18 11:00:00'),
  (1,1,'note','📋','2025 tax organizer sent. Prior year return reviewed — no carryforward losses, prior depreciation schedules noted.',1,'','2026-04-14 09:00:00'),
  (2,2,'filed','📤','Form 1040 e-filed and accepted by IRS. CA return also accepted. Refund of $3,300 expected within 21 days.',1,'','2026-04-22 16:00:00'),
  (2,2,'status_change','✅','Client approved return via portal. E-file authorization (8879) signed.',NULL,'client_portal','2026-04-20 10:30:00'),
  (3,3,'email','⚠️','Second follow-up sent to Kevin Zhao re: K-1 workpapers for members A and B. Return cannot proceed without these.',2,'','2026-04-27 09:00:00'),
  (4,4,'call','📞','Discussed FBAR filing requirements. Client has one foreign account — FBAR (FinCEN 114) to be filed separately via BSA E-Filing System.',3,'','2026-04-26 11:30:00'),
  (5,5,'email','⚠️','Reminder sent to Rachel Bright re: signed financial statements and fixed asset list. Third request.',1,'','2026-04-28 08:00:00'),
  (6,6,'payment','💳','Invoice INV-1075 paid — $650 received via ACH.',NULL,'','2026-04-24 00:00:00'),
  (6,6,'filed','📤','Form 1040 e-filed and acknowledged. Schedule E — 2 rental properties. Depreciation updated.',2,'','2026-04-23 14:00:00'),
  (8,8,'note','📝','Draft complete. AMT exposure reviewed — no AMT triggered. Student loan interest deduction $2,500 applied.',1,'','2026-04-26 16:00:00'),
  (9,9,'filed','📤','Form 1065 e-filed. 12 Schedule K-1s generated and distributed to all partners via portal.',2,'','2026-03-28 12:00:00'),
  (10,10,'note','📝','DNI calculation complete. Trust distributes all income to two beneficiaries. Beneficiary statements prepared.',3,'','2026-04-25 10:00:00');

-- ============================================================
--  PRIOR YEAR RETURNS (2024, for history view)
-- ============================================================

INSERT INTO `tax_returns`
  (`client_id`,`tax_year`,`form_type`,`jurisdiction`,`status`,`due_date`,`filed_date`,
   `completion_pct`,`gross_revenue`,`total_expenses`,`net_income`,
   `tax_liability`,`estimated_paid`,`refund_amount`,`officer_comp`,
   `assignee_id`,`software`,`return_ref`,`fee`)
VALUES
  (1,2024,'1120-S','Federal + CA','filed','2025-04-15','2025-03-12',100,2100000,1780000,320000,67200,72340,5140,160000,1,'Lacerte','RTN-2024-0041',3000),
  (2,2024,'1040','Federal + CA','filed','2025-04-15','2025-03-20',100,128000,32000,96000,16800,17800,1000,0,1,'ProConnect','RTN-2024-0042',800),
  (3,2024,'1065','Federal + CA','filed','2025-04-15','2025-03-18',100,820000,698000,122000,0,0,0,0,2,'Lacerte','RTN-2024-0043',2800),
  (4,2024,'1040','Federal + NY + CA','filed','2025-04-15','2025-04-10',100,290000,38000,252000,58100,58100,0,0,3,'ProConnect','RTN-2024-0044',900),
  (6,2024,'1040','Federal + CA','filed','2025-04-15','2025-03-28',100,88000,25000,63000,9800,10900,1100,0,2,'ProConnect','RTN-2024-0046',600),
  (7,2024,'1120-S','Federal + CA','filed','2025-04-15','2025-03-10',100,1480000,1298000,182000,38200,34200,-4000,110000,2,'Lacerte','RTN-2024-0047',2200),
  (9,2024,'1065','Federal + IL + CA + TX','filed','2025-09-15','2025-03-25',100,7800000,7020000,780000,0,0,3800,0,2,'Lacerte','RTN-2024-0049',5200);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  DONE — all tables, demo data loaded.
--  Rows inserted:
--    staff:                3
--    clients:             10
--    tax_returns:         17  (10 current + 7 prior year)
--    return_checklist:    17
--    estimated_payments:  12
--    documents:           20
--    invoices:            11
--    shareholders:         7
--    activity_log:        15
-- ============================================================
