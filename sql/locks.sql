CREATE TABLE IF NOT EXISTS locks(
    id          INTEGER PRIMARY KEY,
    name        TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    description TEXT
);
