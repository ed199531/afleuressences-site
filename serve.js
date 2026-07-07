/* Serveur local Afleuressences : aperçu statique de la refonte, URLs propres sans .html.
   Usage : node serve.js [port] -- par défaut http://localhost:8766 */

const http = require("http");
const fs   = require("fs");
const path = require("path");

const ROOT = __dirname;
const PORT = Number(process.env.PORT) || Number(process.argv[2]) || 8766;

const MIME = {
  ".html": "text/html; charset=utf-8",
  ".css":  "text/css; charset=utf-8",
  ".js":   "text/javascript; charset=utf-8",
  ".png":  "image/png",
  ".jpg":  "image/jpeg",
  ".jpeg": "image/jpeg",
  ".svg":  "image/svg+xml",
  ".ttf":  "font/ttf",
  ".ico":  "image/x-icon",
  ".xml":  "application/xml; charset=utf-8",
  ".txt":  "text/plain; charset=utf-8"
};

function applySecurityHeaders(res) {
  res.setHeader("X-Content-Type-Options", "nosniff");
  res.setHeader("X-Frame-Options", "DENY");
  res.setHeader("Referrer-Policy", "strict-origin-when-cross-origin");
  res.setHeader("Permissions-Policy", "camera=(), microphone=(), geolocation=()");
}

function send(res, file) {
  const ext = path.extname(file);
  fs.readFile(file, (err, data) => {
    if (err) return sendNotFound(res);
    res.writeHead(200, { "Content-Type": MIME[ext] || "application/octet-stream" });
    res.end(data);
  });
}

function sendNotFound(res) {
  const page404 = path.join(ROOT, "404.html");
  fs.readFile(page404, (e, d) => {
    res.writeHead(404, { "Content-Type": "text/html; charset=utf-8" });
    res.end(d || "<h1>404 Not Found</h1>");
  });
}

http.createServer((req, res) => {
  applySecurityHeaders(res);
  const urlPath = decodeURIComponent(req.url.split("?")[0]);
  const clean = path.normalize(urlPath === "/" ? "/index.html" : urlPath);
  const direct = path.join(ROOT, clean);

  if (!direct.startsWith(ROOT)) { res.writeHead(403); return res.end("Forbidden"); }

  fs.stat(direct, (err, stat) => {
    if (!err && stat.isFile()) return send(res, direct);

    /* URL propre sans extension : /prestations/mariages -> prestations/mariages.html */
    if (!path.extname(direct)) {
      const withHtml = direct + ".html";
      if (withHtml.startsWith(ROOT)) {
        return fs.stat(withHtml, (e2, s2) => {
          if (!e2 && s2.isFile()) return send(res, withHtml);
          const indexFile = path.join(direct, "index.html");
          fs.stat(indexFile, (e3, s3) => {
            if (!e3 && s3.isFile()) return send(res, indexFile);
            sendNotFound(res);
          });
        });
      }
    }
    sendNotFound(res);
  });
}).listen(PORT, () => console.log("Afleuressences (aperçu) → http://localhost:" + PORT));
