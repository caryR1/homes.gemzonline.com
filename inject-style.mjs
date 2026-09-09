import { readFileSync } from 'fs';

const credsRaw = readFileSync('.secrets/homes-gemzonline-credentials.txt', 'utf8');
const creds = Object.fromEntries(
  credsRaw.trim().split('\n').map(l => {
    const i = l.indexOf(':');
    return [l.slice(0, i).trim(), l.slice(i + 1).trim()];
  })
);
const site = creds.url.replace('http://', 'https://');
const auth = 'Basic ' + Buffer.from(`${creds.username}:${creds.app_password}`).toString('base64');

const css = readFileSync('content/style.css', 'utf8');
const styleBlock = `<!-- wp:html -->\n<style>${css}</style>\n<!-- /wp:html -->\n`;

const backToTop = readFileSync('content/back-to-top.html', 'utf8');
const backToTopBlock = `<!-- wp:html -->\n${backToTop}<!-- /wp:html -->\n`;

const headerRes = await fetch(`${site}/wp-json/wp/v2/template-parts/hostinger-ai-theme%2F%2Fheader`, {
  headers: { Authorization: auth },
});
const header = await headerRes.json();
const existing = header.content.raw;

// Remove any previously-injected style/back-to-top blocks (idempotent re-runs), then prepend fresh ones
const stripped = existing
  .replace(/<!-- wp:html -->\n<style>[\s\S]*?<\/style>\n<!-- \/wp:html -->\n/, '')
  .replace(/<!-- wp:html -->\n<a href="#" class="thb-back-to-top"[\s\S]*?<\/script>\n<!-- \/wp:html -->\n/, '');
const newContent = styleBlock + backToTopBlock + stripped;

const putRes = await fetch(`${site}/wp-json/wp/v2/template-parts/hostinger-ai-theme%2F%2Fheader`, {
  method: 'POST',
  headers: { Authorization: auth, 'Content-Type': 'application/json' },
  body: JSON.stringify({ content: newContent }),
});
const putJson = await putRes.json();
if (!putRes.ok) {
  console.error('FAIL', putRes.status, putJson.message || JSON.stringify(putJson));
} else {
  console.log('OK header template part updated, id:', putJson.id);
}
