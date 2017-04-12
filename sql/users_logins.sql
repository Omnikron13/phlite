CREATE TABLE IF NOT EXISTS users_logins(
    id      INTEGER PRIMARY KEY,
    userID  INTEGER NOT NULL,
    success NUMERIC NOT NULL,
    time    REAL    NOT NULL,
    IP      TEXT    NOT NULL,
    FOREIGN KEY (userID) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
)
