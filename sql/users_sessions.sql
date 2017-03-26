CREATE TABLE IF NOT EXISTS users_sessions(
    id      INTEGER PRIMARY KEY,
    userID  INTEGER NOT NULL,
    key     TEXT    NOT NULL,
    IP      TEXT    NOT NULL,
    active  INTEGER NOT NULL,
    FOREIGN KEY (userID) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
