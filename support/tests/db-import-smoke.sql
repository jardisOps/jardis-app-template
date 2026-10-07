-- CI smoke input for `bin/console db:import` (SQLite dialect).
CREATE TABLE IF NOT EXISTS smoke_a (id INTEGER PRIMARY KEY, label TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS smoke_b (id INTEGER PRIMARY KEY, a_id INTEGER NOT NULL);
