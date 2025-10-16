#!/bin/bash
python3 make_db_init.py --dataset dataset --output db_init.sql
sqlite3 movies_rating.db < db_init.sql