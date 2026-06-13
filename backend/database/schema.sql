SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(191) NOT NULL,
    email         VARCHAR(191) NOT NULL UNIQUE,
    phone         VARCHAR(30),
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','pharmacist','med_rep','lab','medical_services','patient') NOT NULL DEFAULT 'patient',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    is_verified   TINYINT(1) NOT NULL DEFAULT 0,
    last_login    DATETIME,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pharmacies (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    name       VARCHAR(191) NOT NULL,
    wilaya     VARCHAR(100),
    city       VARCHAR(100),
    phone      VARCHAR(30),
    address    TEXT,
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    pharmacy_id     INT,
    product_name    VARCHAR(255) NOT NULL,
    product_name_ar VARCHAR(255),
    category        ENUM('medicine','device','special_needs','parapharmacy') NOT NULL DEFAULT 'medicine',
    quantity        INT NOT NULL DEFAULT 0,
    is_available    TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS labs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    name       VARCHAR(191) NOT NULL,
    wilaya     VARCHAR(100),
    city       VARCHAR(100),
    phone      VARCHAR(30),
    address    TEXT,
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS med_reps (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    name       VARCHAR(191),
    company    VARCHAR(191),
    wilaya     VARCHAR(100),
    phone      VARCHAR(30),
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS medical_services (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT,
    name         VARCHAR(191) NOT NULL,
    service_type VARCHAR(100),
    wilaya       VARCHAR(100),
    city         VARCHAR(100),
    phone        VARCHAR(30),
    address      TEXT,
    is_active    TINYINT(1) DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscriptions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    plan_id    VARCHAR(100),
    role_type  VARCHAR(50),
    tier       VARCHAR(50),
    price      INT,
    start_date DATE,
    end_date   DATE,
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_verifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(191) NOT NULL,
    code       VARCHAR(10)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS donations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    title       VARCHAR(255),
    description TEXT,
    wilaya      VARCHAR(100),
    status      ENUM('open','closed','fulfilled') DEFAULT 'open',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(191),
    email      VARCHAR(191),
    phone      VARCHAR(30),
    subject    VARCHAR(255),
    message    TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS advertisements (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    title      VARCHAR(255),
    content    TEXT,
    image_url  VARCHAR(500),
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;
