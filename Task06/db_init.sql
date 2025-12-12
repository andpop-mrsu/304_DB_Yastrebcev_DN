PRAGMA foreign_keys = ON;

BEGIN;

DROP TABLE IF EXISTS procedures_completed;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS service_categories;
DROP TABLE IF EXISTS specializations;

CREATE TABLE specializations (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE employees (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    surname            TEXT NOT NULL,
    name               TEXT NOT NULL,
    patronymic         TEXT,
    specialization_id  INTEGER NOT NULL,
    salary_percentage  REAL NOT NULL CHECK (salary_percentage > 0 AND salary_percentage <= 100),
    hire_date          DATE NOT NULL DEFAULT (date('now')),
    dismissal_date     DATE,
    status             TEXT NOT NULL DEFAULT 'работает' CHECK (status IN ('работает', 'уволен')),

    CHECK (
        (status = 'работает' AND dismissal_date IS NULL) OR
        (status = 'уволен'  AND dismissal_date IS NOT NULL)
    ),
    CHECK (dismissal_date IS NULL OR dismissal_date >= hire_date),

    FOREIGN KEY (specialization_id) REFERENCES specializations(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

CREATE INDEX idx_employees_surname ON employees(surname);
CREATE INDEX idx_employees_status  ON employees(status);

CREATE TABLE service_categories (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE services (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    name             TEXT NOT NULL,
    category_id       INTEGER NOT NULL,
    duration_minutes  INTEGER NOT NULL CHECK (duration_minutes > 0),
    price             REAL NOT NULL CHECK (price > 0),
    UNIQUE (name, category_id),

    FOREIGN KEY (category_id) REFERENCES service_categories(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

CREATE INDEX idx_services_name     ON services(name);
CREATE INDEX idx_services_category ON services(category_id);

CREATE TABLE patients (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    surname            TEXT NOT NULL,
    name               TEXT NOT NULL,
    patronymic         TEXT,
    phone              TEXT NOT NULL,
    email              TEXT,
    birth_date         DATE,
    registration_date  DATE NOT NULL DEFAULT (date('now'))
);

CREATE INDEX idx_patients_surname ON patients(surname);
CREATE INDEX idx_patients_phone   ON patients(phone);

CREATE TABLE appointments (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id            INTEGER NOT NULL,
    employee_id           INTEGER NOT NULL,
    service_id            INTEGER NOT NULL,
    appointment_datetime  DATETIME NOT NULL,
    status               TEXT NOT NULL DEFAULT 'запланирована'
        CHECK (status IN ('запланирована', 'выполнена', 'отменена')),
    notes                TEXT,

    FOREIGN KEY (patient_id)  REFERENCES patients(id)  ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (service_id)  REFERENCES services(id)  ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE INDEX idx_appointments_datetime ON appointments(appointment_datetime);
CREATE INDEX idx_appointments_employee ON appointments(employee_id);
CREATE INDEX idx_appointments_patient  ON appointments(patient_id);
CREATE INDEX idx_appointments_status   ON appointments(status);

CREATE UNIQUE INDEX ux_appointments_employee_datetime_active
ON appointments(employee_id, appointment_datetime)
WHERE status IN ('запланирована', 'выполнена');

CREATE TABLE procedures_completed (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    appointment_id  INTEGER,
    employee_id     INTEGER NOT NULL,
    service_id      INTEGER NOT NULL,
    patient_id      INTEGER NOT NULL,
    completion_date DATETIME NOT NULL DEFAULT (datetime('now')),
    actual_price    REAL NOT NULL CHECK (actual_price >= 0),
    notes           TEXT,

    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (employee_id)    REFERENCES employees(id)    ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (service_id)     REFERENCES services(id)     ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (patient_id)     REFERENCES patients(id)     ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE INDEX idx_procedures_date          ON procedures_completed(completion_date);
CREATE INDEX idx_procedures_employee      ON procedures_completed(employee_id);
CREATE INDEX idx_procedures_employee_date ON procedures_completed(employee_id, completion_date);

CREATE TRIGGER trg_proc_completed_set_appt_done
AFTER INSERT ON procedures_completed
WHEN NEW.appointment_id IS NOT NULL
BEGIN
    UPDATE appointments
       SET status = 'выполнена'
     WHERE id = NEW.appointment_id
       AND status <> 'отменена';
END;

INSERT INTO specializations (name) VALUES
    ('Терапевт'),
    ('Хирург'),
    ('Ортодонт'),
    ('Ортопед'),
    ('Пародонтолог'),
    ('Детский стоматолог');

INSERT INTO service_categories (name) VALUES
    ('Терапевтическая стоматология'),
    ('Хирургическая стоматология'),
    ('Ортодонтия'),
    ('Ортопедия'),
    ('Имплантация'),
    ('Профилактика и гигиена');

INSERT INTO services (name, category_id, duration_minutes, price) VALUES
    ('Консультация', 1, 30, 500.00),
    ('Лечение кариеса', 1, 60, 3500.00),
    ('Лечение пульпита', 1, 90, 5500.00),
    ('Удаление зуба простое', 2, 30, 2000.00),
    ('Удаление зуба сложное', 2, 60, 4500.00),
    ('Установка брекетов', 3, 120, 35000.00),
    ('Коррекция брекетов', 3, 45, 2000.00),
    ('Установка коронки металлокерамика', 4, 60, 8000.00),
    ('Установка импланта', 5, 90, 45000.00),
    ('Профессиональная чистка зубов', 6, 45, 3000.00);

INSERT INTO employees (surname, name, patronymic, specialization_id, salary_percentage, hire_date, status) VALUES
    ('Иванов',  'Иван',   'Иванович',      1, 40, '2020-01-15', 'работает'),
    ('Петрова', 'Мария',  'Сергеевна',     2, 45, '2019-03-20', 'работает'),
    ('Сидоров', 'Петр',   'Алексеевич',    3, 50, '2021-06-01', 'работает'),
    ('Козлова', 'Анна',   'Владимировна',  1, 38, '2018-09-10', 'работает'),
    ('Смирнов', 'Дмитрий','Николаевич',    5, 42, '2017-02-14', 'работает');

UPDATE employees
   SET status = 'уволен',
       dismissal_date = '2024-11-30'
 WHERE id = 5;

INSERT INTO patients (surname, name, patronymic, phone, email, birth_date) VALUES
    ('Андреев',    'Сергей',  'Петрович',       '+79001234567', 'andreev@mail.ru',     '1985-05-12'),
    ('Белова',     'Елена',   'Игоревна',       '+79007654321', 'belova@gmail.com',    '1990-08-23'),
    ('Васильев',   'Алексей', 'Дмитриевич',     '+79009876543', 'vasiliev@yandex.ru',  '1978-11-30'),
    ('Григорьева', 'Ольга',   'Александровна',  '+79005551234', 'grigorieva@mail.ru',  '1995-03-15'),
    ('Денисов',    'Михаил',  'Сергеевич',      '+79003334455', NULL,                  '2000-07-08');

INSERT INTO appointments (patient_id, employee_id, service_id, appointment_datetime, status) VALUES
    (1, 1,  2, '2025-12-15 10:00:00', 'запланирована'),
    (2, 2,  4, '2025-12-15 14:00:00', 'запланирована'),
    (3, 3,  6, '2025-12-16 09:00:00', 'запланирована'),
    (4, 1,  1, '2025-12-10 11:00:00', 'выполнена'),
    (5, 4, 10, '2025-12-12 15:00:00', 'выполнена');

INSERT INTO procedures_completed (appointment_id, employee_id, service_id, patient_id, completion_date, actual_price) VALUES
    (4,    1,  1, 4, '2025-12-10 11:30:00',  500.00),
    (5,    4, 10, 5, '2025-12-12 15:45:00', 3000.00),
    (NULL, 1,  2, 1, '2025-12-05 10:00:00', 3500.00),
    (NULL, 2,  5, 2, '2025-11-28 16:00:00', 4500.00),
    (NULL, 5,  9, 3, '2024-10-15 14:00:00', 45000.00);

COMMIT;