import { readFileSync, writeFileSync } from 'fs';

const credsRaw = readFileSync('.secrets/homes-gemzonline-credentials.txt', 'utf8');
const creds = Object.fromEntries(
  credsRaw.trim().split('\n').map(l => {
    const i = l.indexOf(':');
    return [l.slice(0, i).trim(), l.slice(i + 1).trim()];
  })
);
const site = creds.url.replace('http://', 'https://');
const auth = 'Basic ' + Buffer.from(`${creds.username}:${creds.app_password}`).toString('base64');

const items = [
  { type: 'pages', id: 22, title: 'Home', file: 'content/pages/home.html' },
  { type: 'pages', id: 34, title: 'Cost Guide', file: 'content/pages/cost-guide.html' },
  { type: 'pages', id: 35, title: 'Craftsman Tiny Homes', file: 'content/pages/craftsman-tiny-homes.html' },
  { type: 'pages', id: 36, title: 'Connecticut Tiny Homes', file: 'content/pages/connecticut-tiny-homes.html' },
  { type: 'pages', id: 37, title: 'Smarter Tiny Homes', file: 'content/pages/smarter-tiny-homes.html' },
  { type: 'pages', id: 38, title: 'Tiny Home Builders', file: 'content/pages/tiny-home-builders.html' },
  { type: 'posts', id: 39, title: 'How to Finance a Tiny Home in 2026', file: 'content/posts/financing-a-tiny-home.html' },
  { type: 'posts', id: 40, title: 'Tiny Home Zoning Laws: What to Check Before You Buy', file: 'content/posts/tiny-home-zoning-laws.html' },
  { type: 'posts', id: 41, title: 'Tiny Home vs ADU vs Modular Family Home: Which Is Right for You', file: 'content/posts/tiny-home-vs-adu.html' },
  { type: 'pages', id: 54, title: 'About', file: 'content/pages/about.html' },
  { type: 'pages', id: 55, title: 'FAQ', file: 'content/pages/faq.html' },
  { type: 'pages', id: 104, title: 'Become an Affiliate', file: 'content/pages/become-an-affiliate.html' },
  { type: 'pages', id: 26, title: 'My Account', file: 'content/pages/my-account.html' },
];

const results = [];
for (const item of items) {
  const content = readFileSync(item.file, 'utf8');
  const body = { title: item.title, content, status: 'publish' };
  if (item.slug) body.slug = item.slug;
  if (item.type === 'pages' && item.id !== 22) body.template = 'no-title';
  const url = item.id
    ? `${site}/wp-json/wp/v2/${item.type}/${item.id}`
    : `${site}/wp-json/wp/v2/${item.type}`;
  const res = await fetch(url, {
    method: 'POST',
    headers: { Authorization: auth, 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const json = await res.json();
  if (!res.ok) {
    console.error(`FAIL ${item.title}: ${res.status} ${json.message || JSON.stringify(json)}`);
    results.push({ title: item.title, status: 'FAILED', error: json.message });
  } else {
    console.log(`OK ${item.title} -> ${json.link}`);
    results.push({ title: item.title, status: 'published', link: json.link, id: json.id });
  }
}

writeFileSync('publish-results.json', JSON.stringify(results, null, 2));
