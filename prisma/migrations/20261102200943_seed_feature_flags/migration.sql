-- Seed default feature flags. Idempotent: never overwrites existing rows.
INSERT INTO "FeatureFlag" ("key", "name", "description", "enabled", "scope", "sortOrder", "createdAt", "updatedAt")
VALUES
    ('login', 'Đăng nhập', '', true, 'GLOBAL', 10, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('register', 'Đăng ký', '', true, 'GLOBAL', 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('profile', 'Hồ sơ cá nhân', '', true, 'GLOBAL', 30, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('like', 'Thích', '', true, 'GLOBAL_AND_PUBLICATION', 40, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('comment', 'Bình luận', '', true, 'GLOBAL_AND_PUBLICATION', 50, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('favorite', 'Lưu ấn phẩm', '', true, 'GLOBAL_AND_PUBLICATION', 60, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('history', 'Lịch sử đọc', '', true, 'GLOBAL_AND_PUBLICATION', 70, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('share', 'Chia sẻ', '', true, 'GLOBAL', 80, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('download', 'Tải xuống', '', true, 'GLOBAL', 90, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('print', 'In', '', true, 'GLOBAL', 100, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT ("key") DO NOTHING;
