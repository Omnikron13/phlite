CREATE TABLE IF NOT EXISTS users(
    id           INTEGER PRIMARY KEY,
    username     TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    email        TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    password     TEXT    NOT NULL,
    registerTime INTEGER NOT NULL,
    requestToken TEXT
);
