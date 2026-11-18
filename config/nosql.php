<?php

return [
    'redis' => [
        'connection' => env('NOSQL_REDIS_CONNECTION', 'default'),
        'cache_store' => env('NOSQL_REDIS_CACHE_STORE', 'redis'),
        'seat_hold_store' => env('NOSQL_REDIS_SEAT_HOLD_STORE', 'redis'),
        'leaderboard_store' => env('NOSQL_REDIS_LEADERBOARD_STORE', 'redis'),
        'movie_ttl_seconds' => (int) env('NOSQL_REDIS_MOVIE_TTL_SECONDS', 600),
        'show_ttl_seconds' => (int) env('NOSQL_REDIS_SHOW_TTL_SECONDS', 300),
        'seat_hold_ttl_seconds' => (int) env('NOSQL_REDIS_SEAT_HOLD_TTL_SECONDS', 600),
        'leaderboard_ttl_seconds' => (int) env('NOSQL_REDIS_LEADERBOARD_TTL_SECONDS', 2678400),
        'leaderboard_weights' => [
            'view' => (float) env('NOSQL_REDIS_LEADERBOARD_VIEW_WEIGHT', 1),
            'review' => (float) env('NOSQL_REDIS_LEADERBOARD_REVIEW_WEIGHT', 3),
            'booking' => (float) env('NOSQL_REDIS_LEADERBOARD_BOOKING_WEIGHT', 5),
        ],
    ],

    'mongodb' => [
        'driver' => env('NOSQL_MONGODB_DRIVER', 'mongodb'),
        'connection' => env('NOSQL_MONGODB_CONNECTION', 'mongodb'),
        'host' => env('MONGO_HOST', '127.0.0.1'),
        'port' => (int) env('MONGO_PORT', 27017),
        'database' => env('MONGO_DATABASE', 'cinema_content'),
        'username' => env('MONGO_USERNAME', 'cinema_app'),
        'password' => env('MONGO_PASSWORD'),
        'authentication_database' => env('MONGO_AUTH_DATABASE', 'cinema_content'),
        'connect_timeout_ms' => (int) env('MONGO_CONNECT_TIMEOUT_MS', 3000),
        'review_collection' => env('MONGO_REVIEW_COLLECTION', 'movie_reviews'),
        'chat_collection' => env('MONGO_CHAT_COLLECTION', 'chat_sessions'),
        'outbox_collection' => env('MONGO_OUTBOX_COLLECTION', 'integration_outbox'),
        'outbox_batch_size' => (int) env('MONGO_OUTBOX_BATCH_SIZE', 100),
        'chat_ttl_hours' => (int) env('MONGO_CHAT_TTL_HOURS', 24),
    ],

    'cassandra' => [
        'driver' => env('NOSQL_CASSANDRA_DRIVER', 'http'),
        'api_url' => env('CASSANDRA_API_URL', 'http://127.0.0.1:9043'),
        'host' => env('CASSANDRA_HOST', '127.0.0.1'),
        'port' => (int) env('CASSANDRA_PORT', 9042),
        'keyspace' => env('CASSANDRA_KEYSPACE', 'cinema_analytics'),
        'username' => env('CASSANDRA_USERNAME', 'cinema_app'),
        'password' => env('CASSANDRA_PASSWORD'),
        'connect_timeout_seconds' => (int) env('CASSANDRA_CONNECT_TIMEOUT_SECONDS', 5),
        'request_timeout_seconds' => (int) env('CASSANDRA_REQUEST_TIMEOUT_SECONDS', 10),
        'consistency' => env('CASSANDRA_CONSISTENCY', 'LOCAL_QUORUM'),
        'activity_ttl_seconds' => (int) env('CASSANDRA_ACTIVITY_TTL_SECONDS', 7776000),
        'audit_ttl_seconds' => (int) env('CASSANDRA_AUDIT_TTL_SECONDS', 15552000),
    ],

    'neo4j' => [
        'driver' => env('NOSQL_NEO4J_DRIVER', 'http'),
        'uri' => env('NEO4J_URI', 'bolt://127.0.0.1:7687'),
        'http_url' => env('NEO4J_HTTP_URL', 'http://127.0.0.1:7474'),
        'username' => env('NEO4J_USERNAME', 'neo4j'),
        'password' => env('NEO4J_PASSWORD'),
        'connect_timeout_seconds' => (int) env('NEO4J_CONNECT_TIMEOUT_SECONDS', 5),
        'request_timeout_seconds' => (int) env('NEO4J_REQUEST_TIMEOUT_SECONDS', 15),
        'cache_ttl_seconds' => (int) env('NEO4J_RECOMMENDATION_CACHE_TTL_SECONDS', 300),
    ],
];
