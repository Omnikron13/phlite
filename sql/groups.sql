CREATE TABLE IF NOT EXISTS groups(
    id          INTEGER PRIMARY KEY,
    name        TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    description TEXT
);
