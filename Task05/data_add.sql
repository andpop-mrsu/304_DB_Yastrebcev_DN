PRAGMA foreign_keys = ON;
BEGIN TRANSACTION;

INSERT INTO users (surname, name, email, gender_id, occupation_id)
VALUES
('Тумайкина', 'Дарья', 'tumaikina.darya@student.mrsu.ru',
 (SELECT id FROM genders WHERE name='female'),
 (SELECT id FROM occupations WHERE name='student')),
('Шагилов', 'Кирилл', 'shagilov.kirill@student.mrsu.ru',
 (SELECT id FROM genders WHERE name='male'),
 (SELECT id FROM occupations WHERE name='student')),
('Шеволаев', 'Илья', 'shevolaev.ilya@student.mrsu.ru',
 (SELECT id FROM genders WHERE name='male'),
 (SELECT id FROM occupations WHERE name='student')),
('Ямбаев', 'Константин', 'yambaev.konstantin@student.mrsu.ru',
 (SELECT id FROM genders WHERE name='male'),
 (SELECT id FROM occupations WHERE name='student')),
('Ястребцев', 'Денис', 'yastrebtsev.denis@student.mrsu.ru',
 (SELECT id FROM genders WHERE name='male'),
 (SELECT id FROM occupations WHERE name='student'));

INSERT INTO movies (title, year) VALUES ('The Shawshank Redemption (1994)', 1994);
WITH m AS (SELECT last_insert_rowid() AS mid)
INSERT INTO movie_genres (movie_id, genre_id)
SELECT m.mid, g.id FROM m JOIN genres g ON g.name='Drama';

INSERT INTO movies (title, year) VALUES ('Mad Max: Fury Road (2015)', 2015);
WITH m AS (SELECT last_insert_rowid() AS mid)
INSERT INTO movie_genres (movie_id, genre_id)
SELECT m.mid, g.id FROM m JOIN genres g ON g.name IN ('Action','Adventure');

INSERT INTO movies (title, year) VALUES ('The Grand Budapest Hotel (2014)', 2014);
WITH m AS (SELECT last_insert_rowid() AS mid)
INSERT INTO movie_genres (movie_id, genre_id)
SELECT m.mid, g.id FROM m JOIN genres g ON g.name IN ('Comedy','Drama');

INSERT INTO ratings (user_id, movie_id, rating, review_text)
SELECT
  (SELECT id FROM users WHERE email='yastrebtsev.denis@student.mrsu.ru'),
  (SELECT id FROM movies WHERE title='The Shawshank Redemption (1994)' AND year=1994),
  5.0,
  'Абсолютный шедевр кинематографа! Невероятная история о надежде, дружбе и свободе. Один из лучших фильмов всех времён.';

INSERT INTO ratings (user_id, movie_id, rating, review_text)
SELECT
  (SELECT id FROM users WHERE email='yastrebtsev.denis@student.mrsu.ru'),
  (SELECT id FROM movies WHERE title='Mad Max: Fury Road (2015)' AND year=2015),
  4.5,
  'Потрясающие спецэффекты и невероятная динамика! Два часа непрерывного экшена. Визуально ошеломляющий фильм.';

INSERT INTO ratings (user_id, movie_id, rating, review_text)
SELECT
  (SELECT id FROM users WHERE email='yastrebtsev.denis@student.mrsu.ru'),
  (SELECT id FROM movies WHERE title='The Grand Budapest Hotel (2014)' AND year=2014),
  4.0,
  'Визуально красивый фильм с уникальным стилем Уэса Андерсона. Отличный юмор и захватывающая история.';

COMMIT;