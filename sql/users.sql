CREATE TABLE IF NOT EXISTS users(
    id           INTEGER PRIMARY KEY,
    username     TEXT    NOT NULL COLLATE NOCASE,
    email        TEXT    NOT NULL COLLATE NOCASE,
    password     TEXT    NOT NULL,
    registerTime INTEGER NOT NULL,
    failureCount INTEGER,
    failureTime  REAL,
    requestToken TEXT
);
