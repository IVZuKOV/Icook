CREATE TABLE IF NOT EXISTS joint_projects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    project_url VARCHAR(2048) NOT NULL,
    label_image VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_joint_projects_slug (slug),
    KEY idx_joint_projects_active_sort (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO joint_projects (slug, name, project_url, is_active, sort_order)
VALUES
    ('edu-em', 'Еду ем', '/sitoedu.html', 1, 10),
    ('cheap-honest', 'Дёшево! Честно!', '/cheap-honest.html', 1, 20)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    project_url = VALUES(project_url);

CREATE TABLE IF NOT EXISTS edu_em_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    delivery_type ENUM('train', 'bus') NOT NULL,
    customer_name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    route VARCHAR(200) NOT NULL,
    station VARCHAR(160) NOT NULL,
    delivery_local_time VARCHAR(16) NOT NULL,
    carriage_seat VARCHAR(100) DEFAULT NULL,
    order_details TEXT NOT NULL,
    comment TEXT DEFAULT NULL,
    privacy_consent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('new', 'processing', 'completed', 'cancelled') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_edu_em_requests_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
