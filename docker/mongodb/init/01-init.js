const databaseName = process.env.MONGO_INITDB_DATABASE || "cinema_content";
const appUsername = process.env.MONGO_APP_USERNAME || "cinema_app";
const appPassword = process.env.MONGO_APP_PASSWORD;

if (!appPassword) {
  throw new Error("MONGO_APP_PASSWORD is required");
}

const cinemaDb = db.getSiblingDB(databaseName);

if (!cinemaDb.getUser(appUsername)) {
  cinemaDb.createUser({
    user: appUsername,
    pwd: appPassword,
    roles: [{ role: "readWrite", db: databaseName }],
  });
}

function ensureCollection(name, validator) {
  if (!cinemaDb.getCollectionNames().includes(name)) {
    cinemaDb.createCollection(name, {
      validator,
      validationLevel: "strict",
      validationAction: "error",
    });
    return;
  }

  cinemaDb.runCommand({
    collMod: name,
    validator,
    validationLevel: "strict",
    validationAction: "error",
  });
}

ensureCollection("movie_reviews", {
  $jsonSchema: {
    bsonType: "object",
    required: ["movie_id", "user_id", "rating", "status", "source", "created_at", "updated_at"],
    properties: {
      movie_id: { bsonType: ["int", "long"] },
      user_id: { bsonType: ["int", "long"] },
      order_id: { bsonType: ["int", "long", "null"] },
      legacy_review_id: { bsonType: ["int", "long", "null"] },
      rating: { bsonType: "int", minimum: 1, maximum: 10 },
      content: { bsonType: ["string", "null"], maxLength: 1000 },
      tags: { bsonType: "array", items: { bsonType: "string" } },
      status: { enum: ["published", "hidden"] },
      source: { bsonType: "string" },
      watched_at: { bsonType: ["date", "null"] },
      created_at: { bsonType: "date" },
      updated_at: { bsonType: "date" },
    },
  },
});

ensureCollection("chat_sessions", {
  $jsonSchema: {
    bsonType: "object",
    required: ["session_id", "user_id", "messages", "context_version", "created_at", "updated_at", "expires_at"],
    properties: {
      session_id: { bsonType: "string" },
      user_id: { bsonType: ["int", "long", "null"] },
      messages: {
        bsonType: "array",
        items: {
          bsonType: "object",
          required: ["role", "content", "at"],
          properties: {
            role: { enum: ["user", "assistant", "system"] },
            content: { bsonType: "string", maxLength: 8000 },
            at: { bsonType: "date" },
          },
        },
      },
      context_version: { bsonType: "int", minimum: 1 },
      created_at: { bsonType: "date" },
      updated_at: { bsonType: "date" },
      expires_at: { bsonType: "date" },
    },
  },
});

ensureCollection("integration_outbox", {
  $jsonSchema: {
    bsonType: "object",
    required: ["event_id", "event_type", "aggregate_id", "payload", "targets", "occurred_at", "published_at", "attempts"],
    properties: {
      event_id: { bsonType: "string" },
      event_type: { bsonType: "string" },
      aggregate_id: { bsonType: "string" },
      payload: { bsonType: "object" },
      targets: { bsonType: "array", items: { enum: ["cassandra", "neo4j"] } },
      occurred_at: { bsonType: "date" },
      published_at: { bsonType: ["date", "null"] },
      attempts: { bsonType: "int", minimum: 0 },
    },
  },
});

cinemaDb.movie_reviews.createIndex(
  { movie_id: 1, user_id: 1 },
  { unique: true, name: "uniq_movie_user" },
);
cinemaDb.movie_reviews.createIndex(
  { movie_id: 1, status: 1, rating: -1, created_at: -1 },
  { name: "movie_status_rating_created" },
);
cinemaDb.movie_reviews.createIndex(
  { source: 1, legacy_review_id: 1 },
  { name: "source_legacy_review" },
);
cinemaDb.chat_sessions.createIndex(
  { session_id: 1 },
  { unique: true, name: "uniq_session" },
);
cinemaDb.chat_sessions.createIndex(
  { expires_at: 1 },
  { expireAfterSeconds: 0, name: "ttl_chat_sessions" },
);
cinemaDb.integration_outbox.createIndex(
  { event_id: 1 },
  { unique: true, name: "uniq_event" },
);
cinemaDb.integration_outbox.createIndex(
  { published_at: 1, occurred_at: 1 },
  { name: "pending_events" },
);

const now = new Date();
cinemaDb.movie_reviews.updateOne(
  { movie_id: NumberLong(1), user_id: NumberLong(1) },
  {
    $setOnInsert: {
      order_id: null,
      rating: NumberInt(9),
      content: "Du lieu mau cho ha tang MongoDB",
      tags: ["seed", "phase-2"],
      status: "published",
      source: "phase-2-seed",
      watched_at: null,
      created_at: now,
      updated_at: now,
    },
  },
  { upsert: true },
);

cinemaDb.chat_sessions.updateOne(
  { session_id: "seed-session-phase-2" },
  {
    $setOnInsert: {
      user_id: null,
      messages: [{ role: "system", content: "NoSQL infrastructure ready", at: now }],
      context_version: NumberInt(1),
      created_at: now,
      updated_at: now,
      expires_at: new Date(now.getTime() + 24 * 60 * 60 * 1000),
    },
  },
  { upsert: true },
);
