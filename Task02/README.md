# Task02 — ETL в SQLite (db_init.sql -> movies_rating.db)

## Требования к окружению
- Python 3.8+ (проверено на 3.13)
- SQLite CLI (`sqlite3.exe` для Windows или `sqlite3` для Linux/macOS, должен быть установлен и добавлен в PATH)
  Документация CLI: https://sqlite.org/cli.html

## Структура
- `dataset/` — исходные тексты (CSV/TXT)
- `make_db_init.py` — утилита генерации SQL-скрипта (`db_init.sql`)
- `db_init.bat` — сценарий запуска
- `db_init.sql` — сгенерированный SQL-скрипт для создания/наполнения БД
- `movies_rating.db` — итоговая база данных