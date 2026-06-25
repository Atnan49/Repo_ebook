-- ============================================================
-- REPOSITORI EBOOK - SUPABASE POSTGRESQL SCHEMA
-- PostgreSQL Database
-- ============================================================

-- 1. Create Enum Types
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') THEN
        CREATE TYPE user_role AS ENUM ('member', 'admin');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'ebook_status') THEN
        CREATE TYPE ebook_status AS ENUM ('pending', 'approved', 'rejected');
    END IF;
END $$;

-- 2. TABEL USERS
CREATE TABLE IF NOT EXISTS users (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        user_role NOT NULL DEFAULT 'member',
    avatar      VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- 3. TABEL CATEGORIES
CREATE TABLE IF NOT EXISTS categories (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    slug        VARCHAR(120) NOT NULL UNIQUE,
    icon        VARCHAR(50) DEFAULT NULL,
    created_at  TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_categories_slug ON categories(slug);

-- 4. TABEL EBOOKS
CREATE TABLE IF NOT EXISTS ebooks (
    id              SERIAL PRIMARY KEY,
    title           VARCHAR(255) NOT NULL,
    slug            VARCHAR(280) NOT NULL UNIQUE,
    author          VARCHAR(150) NOT NULL,
    description     TEXT DEFAULT NULL,
    cover_image     VARCHAR(255) DEFAULT NULL,
    pdf_file        VARCHAR(255) NOT NULL,
    file_size       BIGINT DEFAULT 0,
    total_pages     INT DEFAULT 0,
    category_id     INT DEFAULT NULL,
    uploaded_by     INT NOT NULL,
    status          ebook_status NOT NULL DEFAULT 'pending',
    views           INT DEFAULT 0,
    downloads       INT DEFAULT 0,
    rejection_note  TEXT DEFAULT NULL,
    created_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_ebook_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT fk_ebook_uploader
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_ebooks_status ON ebooks(status);
CREATE INDEX IF NOT EXISTS idx_ebooks_category ON ebooks(category_id);
CREATE INDEX IF NOT EXISTS idx_ebooks_uploader ON ebooks(uploaded_by);
CREATE INDEX IF NOT EXISTS idx_ebooks_slug ON ebooks(slug);
CREATE INDEX IF NOT EXISTS idx_ebooks_created ON ebooks(created_at);

-- 5. TABEL BOOKMARKS
CREATE TABLE IF NOT EXISTS bookmarks (
    id          SERIAL PRIMARY KEY,
    user_id     INT NOT NULL,
    ebook_id    INT NOT NULL,
    created_at  TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_bookmark UNIQUE (user_id, ebook_id),

    CONSTRAINT fk_bookmark_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_bookmark_ebook
        FOREIGN KEY (ebook_id) REFERENCES ebooks(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- DATA AWAL (SEED)
-- ============================================================

-- Admin default (password: Password)
INSERT INTO users (name, email, password, role) 
VALUES ('Administrator', 'admin@repoebook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON CONFLICT (email) DO NOTHING;

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
('History',    'history',    'fa-landmark')
ON CONFLICT (name) DO NOTHING;
