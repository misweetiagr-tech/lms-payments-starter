'use strict';

const jwt = require('jsonwebtoken');

// Must be the same value as NODE_JWT_SECRET in the Laravel .env.
const SECRET = process.env.NODE_JWT_SECRET || 'change-me-locally-use-at-least-32-characters';

/** Short-lived token the other backends accept. `sub` is the user id in the shared database. */
function signAccess(userId) {
  return jwt.sign({ sub: userId, token_type: 'access' }, SECRET, { algorithm: 'HS256', expiresIn: '30m' });
}

/** Long-lived token that can ONLY be exchanged for a new access token. */
function signRefresh(userId) {
  return jwt.sign({ sub: userId, token_type: 'refresh' }, SECRET, { algorithm: 'HS256', expiresIn: '7d' });
}

/** Verify and return claims, or throw. Pins the algorithm so "alg: none" tokens are refused. */
function verify(token) {
  return jwt.verify(token, SECRET, { algorithms: ['HS256'] });
}

module.exports = { signAccess, signRefresh, verify };
