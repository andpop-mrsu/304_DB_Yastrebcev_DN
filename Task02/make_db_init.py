#!/usr/bin/env python3

"""
make_db_init.py — генерирует db_init.sql для SQLite из файлов в dataset/.
Запуск: python3 make_db_init.py --dataset dataset --output db_init.sql
"""

import argparse, csv, os, re, sys
from pathlib import Path

HERE = Path(__file__).resolve().parent

# Утилиты
def sniff_dialect(path, sample_bytes=65536):
    with open(path, 'r', encoding='utf-8-sig', newline='') as f:
        sample = f.read(sample_bytes)
        try:
            dialect = csv.Sniffer().sniff(sample)
        except Exception:
            dialect = csv.getDialect('excel')
    return dialect

def read_rows(path):
    dialect = sniff_dialect(path)
    with open(path, 'r', encoding='utf-8-sig', newline='') as f:
        reader = csv.DictReader(f, dialect=dialect)
        headers = [h.strip() for h in reader.fieldnames]
        rows = []
        for r in reader:
            rows.append({k.strip(): (v.strip() if isinstance(v, str) else v) for k, v in r.items()})
    return headers, rows

_int_re = re.compile(r'^-?\d+$')
_float_re = re.compile(r'^-?(?:\d+\.\d*|\d*\.\d+)$')
def looks_int(s): return s is not None and s != '' and _int_re.match(s)
def looks_float(s): return s is not None and s != '' and (_float_re.match(s) or looks_int(s))

def sql_escape(val):
    if val is None or val == '':
        return "NULL"
    if isinstance(val, (int, float)):
        return str(val)
    return "'" + str(val).replace("'", "''") + "'"

def coerce_number(s):
    if looks_int(s): return int(s)
    if looks_float(s): return float(s)
    return s

def extract_year_from_title(title):
    if not title:
        return None
    m = re.search(r'\((\d{4})\)\s*$', title)
    return int(m.group(1)) if m else None

# Маппинг таблиц
def load_movies(dataset_dir):
    candidates = ['movies.csv', 'movies.txt']
    path = None
    for c in candidates:
        p = Path(dataset_dir) / c
        if p.exists():
            path = p
            break
    if not path:
        return [], []

    headers, rows = read_rows(path)

    norm = []
    for r in rows:
        rid = r.get('id') or r.get('movieId') or r.get('movie_id')
        title = r.get('title')
        genres = r.get('genres')
        year = r.get('year') or extract_year_from_title(title)
        rid = int(rid) if looks_int(rid) else None
        year = int(year) if looks_int(str(year)) else (int(year) if isinstance(year, str) and year and year.isdigit() else year)
        norm.append({
            'id': rid,
            'title': title,
            'year': year,
            'genres': genres
        })
    return ['id','title','year','genres'], norm

def load_ratings(dataset_dir):
    for name in ['ratings.csv','ratings.txt']:
        p = Path(dataset_dir) / name
        if p.exists():
            headers, rows = read_rows(p)
            norm = []
            auto_id = 1
            has_id = any(h.lower()=='id' for h in headers)
            for r in rows:
                rid = r.get('id') if has_id else auto_id
                if not has_id: auto_id += 1
                user_id = r.get('userId') or r.get('user_id') or r.get('userid')
                movie_id = r.get('movieId') or r.get('movie_id') or r.get('movieid')
                rating = r.get('rating')
                ts = r.get('timestamp') or r.get('time') or r.get('ts')
                norm.append({
                    'id': int(rid),
                    'user_id': int(user_id) if looks_int(user_id) else None,
                    'movie_id': int(movie_id) if looks_int(movie_id) else None,
                    'rating': float(rating) if looks_float(rating) else None,
                    'timestamp': int(ts) if looks_int(ts) else None
                })
            return ['id','user_id','movie_id','rating','timestamp'], norm
    return [], []

def load_tags(dataset_dir):
    for name in ['tags.csv','tags.txt']:
        p = Path(dataset_dir) / name
        if p.exists():
            headers, rows = read_rows(p)
            norm = []
            auto_id = 1
            has_id = any(h.lower()=='id' for h in headers)
            for r in rows:
                rid = r.get('id') if has_id else auto_id
                if not has_id: auto_id += 1
                user_id = r.get('userId') or r.get('user_id') or r.get('userid')
                movie_id = r.get('movieId') or r.get('movie_id') or r.get('movieid')
                tag = r.get('tag')
                ts = r.get('timestamp') or r.get('time') or r.get('ts')
                norm.append({
                    'id': int(rid),
                    'user_id': int(user_id) if looks_int(user_id) else None,
                    'movie_id': int(movie_id) if looks_int(movie_id) else None,
                    'tag': tag,
                    'timestamp': int(ts) if looks_int(ts) else None
                })
            return ['id','user_id','movie_id','tag','timestamp'], norm
    return [], []

def load_users(dataset_dir):
    import pandas as pd
    for name in ['users.csv', 'users.txt']:
        p = Path(dataset_dir) / name
        if p.exists():
            df = pd.read_csv(p, sep='|')
            norm = []
            for _, r in df.iterrows():
                norm.append({
                    'id': int(r['id']),
                    'name': r['name'],
                    'email': r['email'],
                    'gender': r['gender'],
                    'register_date': r['register_date'],
                    'occupation': r['occupation']
                })
            return ['id','name','email','gender','register_date','occupation'], norm
    return [], []

def create_table_ddl():
    ddls = {
        'movies': """CREATE TABLE movies(
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            year INTEGER,
            genres TEXT
        );""",
        'ratings': """CREATE TABLE ratings(
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            movie_id INTEGER,
            rating REAL,
            timestamp INTEGER
        );""",
        'tags': """CREATE TABLE tags(
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            movie_id INTEGER,
            tag TEXT,
            timestamp INTEGER
        );""",
        'users': """CREATE TABLE users(
            id INTEGER PRIMARY KEY,
            name TEXT,
            email TEXT,
            gender TEXT,
            register_date TEXT,
            occupation TEXT
        );"""
    }
    return ddls

def emit_insert(table, columns, rows):
    lines = []
    cols_sql = ", ".join(columns)
    for r in rows:
        values = []
        for c in columns:
            values.append(sql_escape(r.get(c)))
        line = f"INSERT INTO {table} ({cols_sql}) VALUES ({', '.join(values)});"
        lines.append(line)
    return lines

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--dataset', default='dataset', help='Папка с исходными файлами')
    ap.add_argument('--output', default='db_init.sql', help='Куда писать SQL-скрипт')
    args = ap.parse_args()

    dataset_dir = Path(args.dataset)
    if not dataset_dir.exists():
        print(f"ERROR: dataset dir not found: {dataset_dir}", file=sys.stderr)
        sys.exit(2)

    ddls = create_table_ddl()

    movies_cols, movies_rows = load_movies(dataset_dir)
    ratings_cols, ratings_rows = load_ratings(dataset_dir)
    tags_cols, tags_rows = load_tags(dataset_dir)
    users_cols, users_rows = load_users(dataset_dir)

    parts = []
    parts.append("-- AUTOGENERATED BY make_db_init.py")
    parts.append("PRAGMA foreign_keys = OFF;")
    parts.append("BEGIN TRANSACTION;")
    for t in ['ratings','tags','movies','users']:
        parts.append(f"DROP TABLE IF EXISTS {t};")

    for t in ['movies','ratings','tags','users']:
        parts.append(ddls[t])

    if movies_cols:
        parts += emit_insert('movies', movies_cols, movies_rows)
    if ratings_cols:
        parts += emit_insert('ratings', ratings_cols, ratings_rows)
    if tags_cols:
        parts += emit_insert('tags', tags_cols, tags_rows)
    if users_cols:
        parts += emit_insert('users', users_cols, users_rows)

    parts.append("COMMIT;")
    parts.append("-- конец скрипта")

    out_path = Path(args.output)
    out_path.write_text("\n".join(parts), encoding='utf-8')
    print(f"Wrote SQL to {out_path}")

if __name__ == '__main__':
    main()
