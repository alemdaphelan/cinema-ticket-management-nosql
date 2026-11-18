'use strict';

const http = require('http');
const cassandra = require('cassandra-driver');

const port = Number(process.env.PORT || 8080);
const keyspace = process.env.CASSANDRA_KEYSPACE || 'cinema_analytics';
const client = new cassandra.Client({
  contactPoints: [process.env.CASSANDRA_HOST || 'cassandra'],
  localDataCenter: process.env.CASSANDRA_DC || 'datacenter1',
  keyspace,
  credentials: {
    username: process.env.CASSANDRA_USERNAME || 'cinema_app',
    password: process.env.CASSANDRA_PASSWORD || '',
  },
  queryOptions: { consistency: cassandra.types.consistencies.localQuorum },
});

const queries = {
  userInsert: `INSERT INTO ${keyspace}.user_activity_by_month
    (user_id, month, occurred_at, event_id, event_type, movie_id, order_id, metadata)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
  movieInsert: `INSERT INTO ${keyspace}.movie_events_by_day
    (movie_id, event_date, occurred_at, event_id, event_type, user_id, order_id, metadata)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
  auditInsert: `INSERT INTO ${keyspace}.audit_events_by_day
    (actor_type, event_date, occurred_at, event_id, actor_id, event_type, aggregate_type, aggregate_id, metadata)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
  userRead: `SELECT user_id, month, occurred_at, event_id, event_type, movie_id, order_id, metadata
    FROM ${keyspace}.user_activity_by_month WHERE user_id = ? AND month = ? LIMIT ?`,
  movieRead: `SELECT movie_id, event_date, occurred_at, event_id, event_type, user_id, order_id, metadata
    FROM ${keyspace}.movie_events_by_day WHERE movie_id = ? AND event_date = ? LIMIT ?`,
};

function json(response, status, body) {
  response.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8' });
  response.end(JSON.stringify(body));
}

function readBody(request) {
  return new Promise((resolve, reject) => {
    let body = '';
    request.on('data', chunk => {
      body += chunk;
      if (body.length > 1024 * 1024) request.destroy();
    });
    request.on('end', () => {
      try { resolve(body === '' ? {} : JSON.parse(body)); } catch (error) { reject(error); }
    });
    request.on('error', reject);
  });
}

function timeUuidFromSource(source) {
  const normalized = String(source || '').toLowerCase();
  if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(normalized)) {
    throw new Error('event_id must be a UUID');
  }
  const value = normalized.slice(0, 14) + '1' + normalized.slice(15, 19) + '8' + normalized.slice(20);
  return cassandra.types.TimeUuid.fromString(value);
}

function longOrNull(value) {
  return value === null || value === undefined ? null : cassandra.types.Long.fromString(String(value));
}

function serialize(row) {
  const plain = {};
  for (const [key, value] of Object.entries(row)) {
    if (value instanceof Date) plain[key] = value.toISOString();
    else if (value && typeof value.toString === 'function' && typeof value !== 'string') plain[key] = value.toString();
    else plain[key] = value;
  }
  try { plain.metadata = JSON.parse(plain.metadata || '{}'); } catch (_) {}
  return plain;
}

async function recordEvent(body) {
  const occurredAt = new Date(body.occurred_at || Date.now());
  if (Number.isNaN(occurredAt.getTime())) throw new Error('occurred_at is invalid');
  const eventId = timeUuidFromSource(body.event_id);
  const userId = longOrNull(body.user_id);
  const movieId = longOrNull(body.movie_id);
  const orderId = longOrNull(body.order_id);
  const eventType = String(body.event_type || '').toUpperCase();
  if (!eventType) throw new Error('event_type is required');

  const month = occurredAt.toISOString().slice(0, 7);
  const eventDate = occurredAt.toISOString().slice(0, 10);
  const metadata = JSON.stringify({ ...(body.metadata || {}), source_event_id: body.event_id });
  const statements = [];
  if (userId) statements.push({ query: queries.userInsert, params: [userId, month, occurredAt, eventId, eventType, movieId, orderId, metadata] });
  if (movieId) statements.push({ query: queries.movieInsert, params: [movieId, eventDate, occurredAt, eventId, eventType, userId, orderId, metadata] });
  statements.push({
    query: queries.auditInsert,
    params: [String(body.actor_type || (userId ? 'user' : 'system')), eventDate, occurredAt, eventId, userId, eventType,
      String(body.aggregate_type || 'activity'), String(body.aggregate_id || body.movie_id || body.order_id || body.event_id), metadata],
  });

  await client.batch(statements, { prepare: true, logged: false, consistency: cassandra.types.consistencies.localQuorum });
  return { event_id: body.event_id, partitions_written: statements.length };
}

async function handle(request, response) {
  const url = new URL(request.url, `http://${request.headers.host || 'localhost'}`);
  if (request.method === 'GET' && url.pathname === '/health') {
    const result = await client.execute('SELECT release_version, data_center FROM system.local');
    return json(response, 200, { status: 'ok', keyspace, consistency: 'LOCAL_QUORUM', node: serialize(result.first()) });
  }
  if (request.method === 'POST' && url.pathname === '/events') {
    return json(response, 201, { data: await recordEvent(await readBody(request)) });
  }

  let match = url.pathname.match(/^\/users\/(\d+)\/activity$/);
  if (request.method === 'GET' && match) {
    const month = url.searchParams.get('month') || new Date().toISOString().slice(0, 7);
    const eventType = (url.searchParams.get('event_type') || '').toUpperCase();
    const requestedLimit = Math.min(100, Math.max(1, Number(url.searchParams.get('limit') || 50)));
    const result = await client.execute(queries.userRead, [longOrNull(match[1]), month, 200], { prepare: true, consistency: cassandra.types.consistencies.localQuorum });
    const rows = result.rows.map(serialize).filter(row => !eventType || row.event_type === eventType).slice(0, requestedLimit);
    return json(response, 200, { data: rows, partition: { user_id: Number(match[1]), month }, allow_filtering: false });
  }

  match = url.pathname.match(/^\/movies\/(\d+)\/events$/);
  if (request.method === 'GET' && match) {
    const date = url.searchParams.get('date') || new Date().toISOString().slice(0, 10);
    const eventType = (url.searchParams.get('event_type') || '').toUpperCase();
    const requestedLimit = Math.min(100, Math.max(1, Number(url.searchParams.get('limit') || 50)));
    const result = await client.execute(queries.movieRead, [longOrNull(match[1]), date, 200], { prepare: true, consistency: cassandra.types.consistencies.localQuorum });
    const rows = result.rows.map(serialize).filter(row => !eventType || row.event_type === eventType).slice(0, requestedLimit);
    return json(response, 200, { data: rows, partition: { movie_id: Number(match[1]), event_date: date }, allow_filtering: false });
  }

  return json(response, 404, { message: 'Not found' });
}

client.connect().then(() => {
  http.createServer((request, response) => {
    handle(request, response).catch(error => {
      console.error(error);
      json(response, error instanceof SyntaxError ? 400 : 503, { message: error.message });
    });
  }).listen(port, '0.0.0.0', () => console.log(`Cassandra API listening on ${port}`));
}).catch(error => {
  console.error(error);
  process.exit(1);
});

process.on('SIGTERM', async () => { await client.shutdown(); process.exit(0); });
