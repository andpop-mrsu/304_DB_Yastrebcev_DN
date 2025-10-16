#!/bin/bash
chcp 65001

sqlite3 movies_rating.db < db_init.sql

echo "1. Составить список фильмов, имеющих хотя бы одну оценку. Сортировка фильмов по году выпуска и названиям. В списке оставить первые 10 фильмов."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "SELECT title, year FROM movies WHERE id IN (SELECT movie_id FROM ratings) ORDER BY year, title LIMIT 10;"
echo " "

echo "2. Вывести список всех пользователей, фамилии которых начинаются на букву 'A'. Сортировка по дате регистрации. В списке оставить первых 5 пользователей."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "SELECT name, register_date FROM users WHERE substr(name, instr(name, ' ')+1, 1) = 'A' ORDER BY register_date LIMIT 5;"
echo " "

echo "3. Информация о рейтингах: имя, фамилия эксперта, название фильма, год, оценка, дата (ГГГГ-ММ-ДД). Сортировка данных по имени эксперта, названию фильма, оценке. В списке оставить первые 50 записей."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "SELECT u.name, m.title, m.year, r.rating, datetime(r.timestamp, 'unixepoch') as rating_date FROM ratings r JOIN users u ON r.user_id = u.id JOIN movies m ON r.movie_id = m.id ORDER BY u.name, m.title, r.rating LIMIT 50;"
echo " "

echo "4. Вывести список фильмов с тегами. Сортировка по году выпуска, названию фильма, тегу. В списке оставить первые 40 записей."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "SELECT m.title, m.year, t.tag FROM movies m JOIN tags t ON m.id = t.movie_id ORDER BY m.year, m.title, t.tag LIMIT 40;"
echo " "

echo "5. Вывести список самых свежих фильмов (год выпуска определяется автоматически)."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "SELECT title, year FROM movies WHERE year = (SELECT MAX(year) FROM movies);"
echo " "

echo "6. Драмы после 2005, понравившиеся женщинам (оценка >=4.5). В этом списке вывести название, год и количество таких оценок. Сортировка результата по году выпуска, названию фильма."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "SELECT m.title, m.year, COUNT(r.id) AS cnt FROM movies m JOIN ratings r ON m.id = r.movie_id JOIN users u ON r.user_id = u.id WHERE m.genres LIKE 'Drama' AND m.year > 2005 AND u.gender = 'female' AND r.rating >= 4.5 GROUP BY m.title, m.year ORDER BY m.year, m.title;"
echo " "

echo "7. Вывести количество пользователей, зарегистрированных по годам. Найти годы максимум/минимум регистраций."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "SELECT substr(register_date,1,4) as year, COUNT(*) as cnt FROM users GROUP BY year ORDER BY year;"
sqlite3 movies_rating.db -box -echo "SELECT year, cnt FROM (SELECT substr(register_date,1,4) as year, COUNT(*) as cnt FROM users GROUP BY year) WHERE cnt = (SELECT MAX(cnt) FROM (SELECT COUNT(*) as cnt FROM users GROUP BY substr(register_date,1,4))) OR cnt = (SELECT MIN(cnt) FROM (SELECT COUNT(*) as cnt FROM users GROUP BY substr(register_date,1,4)));"
echo " "
