'use strict';

const http = require('http');
const { signAccess, signRefresh, verify } = require('./tokens');

// Fake OTP login: any user id with this code gets tokens. A real service checks an OTP.
const DEMO_OTP = '123456';

function readJson(req) {
  return new Promise((resolve, reject) => {
    let body = '';
    req.on('data', (chunk) => (body += chunk));
    req.on('end', () => {
      try {
        resolve(body ? JSON.parse(body) : {});
      } catch (e) {
        reject(e);
      }
    });
  });
}

function send(res, status, payload) {
  res.writeHead(status, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify(payload));
}

const server = http.createServer(async (req, res) => {
  if (req.method !== 'POST') return send(res, 404, { error: 'Not found' });

  let body;
  try {
    body = await readJson(req);
  } catch {
    return send(res, 400, { error: 'Invalid JSON' });
  }

  if (req.url === '/auth/login') {
    if (!body.user_id || body.otp !== DEMO_OTP) return send(res, 401, { error: 'Invalid credentials' });
    return send(res, 200, { access_token: signAccess(body.user_id), refresh_token: signRefresh(body.user_id) });
  }

  if (req.url === '/auth/refresh') {
    try {
      const claims = verify(body.refresh_token || '');
      // An access token must not be usable here, only a refresh token.
      if (claims.token_type !== 'refresh') return send(res, 401, { error: 'Wrong token type' });
      return send(res, 200, { access_token: signAccess(claims.sub) });
    } catch {
      return send(res, 401, { error: 'Invalid refresh token' });
    }
  }

  return send(res, 404, { error: 'Not found' });
});

if (require.main === module) {
  const port = process.env.PORT || 4000;
  server.listen(port, () => console.log(`node-auth listening on ${port}`));
}

module.exports = server;
