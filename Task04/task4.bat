@echo off
chcp 65001

sqlite3 movies_rating.db < db_init.sql

echo "1. Найти все пары пользователей, оценивших один и тот же фильм. Устранить дубликаты, проверить отсутствие пар с самим собой. Для каждой пары должны быть указаны имена пользователей и название фильма, который они ценили. В списке оставить первые 100 записей."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "SELECT u1.name AS user1, u2.name AS user2, m.title FROM ratings r1 JOIN ratings r2 ON r1.movie_id = r2.movie_id AND r1.user_id < r2.user_id JOIN users u1 ON u1.id = r1.user_id JOIN users u2 ON u2.id = r2.user_id JOIN movies m ON m.id = r1.movie_id ORDER BY m.title, user1, user2 LIMIT 100;"
echo " "

echo "2. Найти 10 самых свежих оценок от разных пользователей, вывести названия фильмов, имена пользователей, оценку, дату отзыва в формате ГГГГ-ММ-ДД."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "WITH ranked AS (SELECT r.id, r.user_id, r.movie_id, r.rating, r.timestamp, ROW_NUMBER() OVER (PARTITION BY r.user_id ORDER BY r.timestamp DESC) AS rn FROM ratings r) SELECT m.title, u.name, r.rating, date(r.timestamp,'unixepoch') AS rating_date FROM ranked r JOIN users u ON u.id = r.user_id JOIN movies m ON m.id = r.movie_id WHERE r.rn = 1 ORDER BY r.timestamp DESC LIMIT 10;"
echo " "

echo "3. Вывести в одном списке все фильмы с максимальным средним рейтингом и все фильмы с минимальным средним рейтингом. Общий список отсортировать по году выпуска и названию фильма. В зависимости от рейтинга в колонке 'Рекомендуем' для фильмов должно быть написано 'Да' или 'Нет'."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "WITH avg_scores AS (SELECT m.id, m.title, m.year, AVG(r.rating) AS avg_rating FROM movies m JOIN ratings r ON r.movie_id = m.id GROUP BY m.id, m.title, m.year), bounds AS (SELECT MAX(avg_rating) AS max_avg, MIN(avg_rating) AS min_avg FROM avg_scores) SELECT a.title, a.year, ROUND(a.avg_rating,2) AS avg_rating, CASE WHEN a.avg_rating = b.max_avg THEN 'Да' WHEN a.avg_rating = b.min_avg THEN 'Нет' END AS 'Рекомендуем' FROM avg_scores a, bounds b WHERE a.avg_rating = b.max_avg OR a.avg_rating = b.min_avg ORDER BY a.year, a.title;"
echo " "

echo "4. Вычислить количество оценок и среднюю оценку, которую дали фильмам пользователи-женщины в период с 2010 по 2012 год."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "SELECT COUNT(*) AS ratings_count, ROUND(AVG(r.rating),2) AS avg_rating_female_2010_2012 FROM ratings r JOIN users u ON u.id = r.user_id WHERE u.gender = 'female' AND date(r.timestamp,'unixepoch') BETWEEN '2010-01-01' AND '2012-12-31';"
echo " "

echo "5. Составить список фильмов с указанием их средней оценки и места в рейтинге по средней оценке. Полученный список отсортировать по году выпуска и названиям фильмов. В списке оставить первые 20 записей."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "WITH avg_scores AS (SELECT m.id, m.title, m.year, AVG(r.rating) AS avg_rating FROM movies m JOIN ratings r ON r.movie_id = m.id GROUP BY m.id, m.title, m.year), ranked AS (SELECT id, title, year, avg_rating, DENSE_RANK() OVER (ORDER BY avg_rating DESC) AS rank_by_avg FROM avg_scores) SELECT title, year, ROUND(avg_rating,2) AS avg_rating, rank_by_avg FROM ranked ORDER BY year, title LIMIT 20;"
echo " "

echo "6. Вывести список из 10 последних зарегистрированных пользователей в формате 'Фамилия Имя|Дата регистрации' (сначала фамилия, потом имя)."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "SELECT TRIM(SUBSTR(name, INSTR(name,' ')+1)) || ' ' || TRIM(SUBSTR(name, 1, INSTR(name,' ')-1)) || '|' || register_date AS 'Фамилия Имя|Дата регистрации' FROM users ORDER BY register_date DESC LIMIT 10;"
echo " "

echo "7. С помощью рекурсивного CTE составить таблицу умножения для чисел от 1 до 10."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "WITH RECURSIVE i(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM i WHERE n < 10), j(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM j WHERE n < 10) SELECT i.n || 'x' || j.n || '=' || (i.n*j.n) AS line FROM i, j ORDER BY i.n, j.n;"
echo " "

echo "8. С помощью рекурсивного CTE выделить все жанры фильмов, имеющиеся в таблице movies (каждый жанр в отдельной строке)."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "WITH RECURSIVE split(id, rest, genre) AS (SELECT id, genres || '|' AS rest, '' AS genre FROM movies UNION ALL SELECT id, SUBSTR(rest, INSTR(rest,'|')+1), SUBSTR(rest, 1, INSTR(rest,'|')-1) FROM split WHERE rest <> '') SELECT id, genre FROM split WHERE genre <> '' ORDER BY id, genre;"
echo " "
