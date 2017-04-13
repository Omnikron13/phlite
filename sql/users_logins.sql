CREATE TABLE IF NOT EXISTS users_logins(
    id      INTEGER PRIMARY KEY,
    userID  INTEGER NOT NULL,
    success NUMERIC NOT NULL,
    time    REAL    NOT NULL,
    IP      TEXT    NOT NULL,
    FOREIGN KEY (userID) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE VIEW IF NOT EXISTS users_logins_fail_view AS
    SELECT id, userID, time, IP FROM users_logins WHERE success = 0 ORDER BY time DESC;
