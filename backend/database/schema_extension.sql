SET NAMES utf8mb4;

ALTER TABLE pharmacies
  ADD COLUMN IF NOT EXISTS email         VARCHAR(191) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS website       VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS facebook      VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS instagram     VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS linkedin      VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS opening_hours VARCHAR(255) DEFAULT NULL;

ALTER TABLE labs
  ADD COLUMN IF NOT EXISTS email         VARCHAR(191) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS website       VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS facebook      VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS instagram     VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS linkedin      VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS opening_hours VARCHAR(255) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS equipment_inventory (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id       INT NOT NULL,
    equipment_name    VARCHAR(255) NOT NULL,
    equipment_name_ar VARCHAR(255),
    category          VARCHAR(100) NOT NULL,
    brand             VARCHAR(150),
    description       TEXT,
    description_ar    TEXT,
    quantity          INT NOT NULL DEFAULT 0,
    price             DECIMAL(10,2),
    availability      ENUM('available','limited','unavailable') NOT NULL DEFAULT 'available',
    last_updated      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES med_reps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lab_analyses (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    lab_id             INT NOT NULL,
    name               VARCHAR(255) NOT NULL,
    name_ar            VARCHAR(255),
    category           VARCHAR(100) DEFAULT 'other',
    description        TEXT,
    estimated_duration VARCHAR(100),
    availability       ENUM('available','unavailable') DEFAULT 'available',
    price              DECIMAL(10,2),
    last_updated       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS medicines (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    pharmacy_id  INT NOT NULL,
    name         VARCHAR(255) NOT NULL,
    name_ar      VARCHAR(255),
    brand        VARCHAR(150),
    dosage       VARCHAR(100),
    form_type    ENUM('tablet','syrup','injection','cream','drops','capsule','other') DEFAULT 'other',
    type         VARCHAR(100) DEFAULT 'medicine',
    quantity     INT DEFAULT 0,
    availability ENUM('available','limited','unavailable') DEFAULT 'available',
    price        DECIMAL(10,2),
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
