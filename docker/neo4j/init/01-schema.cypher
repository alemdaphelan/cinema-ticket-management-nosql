CREATE CONSTRAINT user_id_unique IF NOT EXISTS
FOR (user:User) REQUIRE user.user_id IS UNIQUE;

CREATE CONSTRAINT movie_id_unique IF NOT EXISTS
FOR (movie:Movie) REQUIRE movie.movie_id IS UNIQUE;

CREATE CONSTRAINT genre_name_unique IF NOT EXISTS
FOR (genre:Genre) REQUIRE genre.name IS UNIQUE;
