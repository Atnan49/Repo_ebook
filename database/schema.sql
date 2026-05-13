-- ============================================================
-- REPOSITORI EBOOK - DATABASE SCHEMA
-- MySQL Database
-- ============================================================

CREATE DATABASE IF NOT EXISTS repo_ebook
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE repo_ebook;

-- ============================================================
-- 1. TABEL USERS
-- Menyimpan data pengguna (member & admin)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,           -- password_hash()
    role        ENUM('member', 'admin') NOT NULL DEFAULT 'member',
    avatar      VARCHAR(255) DEFAULT NULL,       -- path ke foto profil (opsional)
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- ============================================================
-- 2. TABEL CATEGORIES
-- Menyimpan kategori/genre ebook
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    slug        VARCHAR(120) NOT NULL UNIQUE,    -- URL-friendly name
    icon        VARCHAR(50) DEFAULT NULL,         -- nama icon (opsional)
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- ============================================================
-- 3. TABEL EBOOKS
-- Menyimpan data ebook yang diunggah
-- ============================================================
CREATE TABLE IF NOT EXISTS ebooks (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255) NOT NULL,
    slug            VARCHAR(280) NOT NULL UNIQUE,
    author          VARCHAR(150) NOT NULL,
    description     TEXT DEFAULT NULL,                -- sinopsis
    cover_image     VARCHAR(255) DEFAULT NULL,        -- path ke gambar cover
    pdf_file        VARCHAR(255) NOT NULL,            -- path relatif di /storage/pdfs/
    file_size       BIGINT DEFAULT 0,                 -- ukuran file dalam bytes
    total_pages     INT DEFAULT 0,
    category_id     INT DEFAULT NULL,
    uploaded_by     INT NOT NULL,                     -- FK ke users.id
    status          ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    views           INT DEFAULT 0,                    -- jumlah kali dilihat
    downloads       INT DEFAULT 0,                    -- jumlah kali dibaca/unduh
    rejection_note  TEXT DEFAULT NULL,                 -- alasan jika ditolak admin
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_category (category_id),
    INDEX idx_uploader (uploaded_by),
    INDEX idx_slug (slug),
    INDEX idx_created (created_at),
    FULLTEXT idx_search (title, author, description),

    CONSTRAINT fk_ebook_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT fk_ebook_uploader
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 4. TABEL BOOKMARKS (Opsional - fitur Saved/Favorit)
-- Menyimpan ebook favorit user
-- ============================================================
CREATE TABLE IF NOT EXISTS bookmarks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    ebook_id    INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_bookmark (user_id, ebook_id),

    CONSTRAINT fk_bookmark_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_bookmark_ebook
        FOREIGN KEY (ebook_id) REFERENCES ebooks(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 5. DATA AWAL (SEED)
-- ============================================================

-- Admin default (password: Password)
INSERT INTO users (name, email, password, role) VALUES
('Administrator', 'admin@repoebook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Kategori awal
INSERT INTO categories (name, slug, icon) VALUES
('Fantasy',    'fantasy',    'fa-dragon'),
('Drama',      'drama',      'fa-masks-theater'),
('Detective',  'detective',  'fa-magnifying-glass'),
('Education',  'education',  'fa-graduation-cap'),
('Psychology', 'psychology', 'fa-brain'),
('Business',   'business',   'fa-briefcase'),
('Science',    'science',    'fa-flask'),
('Technology', 'technology', 'fa-microchip'),
('Romance',    'romance',    'fa-heart'),
('History',    'history',    'fa-landmark');
