'use strict';

const test = require('node:test');
const assert = require('node:assert');
const jwt = require('jsonwebtoken');
const { signAccess, signRefresh, verify } = require('./tokens');

test('access token carries sub and token_type=access', () => {
  const claims = verify(signAccess(42));
  assert.strictEqual(claims.sub, 42);
  assert.strictEqual(claims.token_type, 'access');
});

test('refresh token is marked as refresh', () => {
  assert.strictEqual(verify(signRefresh(42)).token_type, 'refresh');
});

test('a token signed with another secret is rejected', () => {
  const forged = jwt.sign({ sub: 1, token_type: 'access' }, 'attacker-secret', { algorithm: 'HS256' });
  assert.throws(() => verify(forged));
});

test('an unsigned (alg none) token is rejected', () => {
  const unsigned = jwt.sign({ sub: 1, token_type: 'access' }, '', { algorithm: 'none' });
  assert.throws(() => verify(unsigned));
});
