/* PasswordToolkit — documentation site
   ──────────────────────────────────────────────────────────────
   One document, two halves: a landing page at #/home and a guide
   at #/intro … #/changelog. The router swaps between them; the
   chrome — highlighting, copy buttons, the palette, the theme —
   is defined once here and serves both.

   The two halves were written separately and each had its own
   copies of $, esc, the tokeniser and the theme switch. The
   guide's copies won and are declared once at the top of this
   file; the landing page's behaviour runs inside a closure at the
   bottom, so its own DATA, D, pick, SHELF and tally shadow the
   guide's rather than fighting them for the top-level name. */

const $ = s => document.querySelector(s), $$ = s => [...document.querySelectorAll(s)];
const esc = t => t.replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));

/* ── SYNTAX HIGHLIGHTING ────────────────────────────────────── */
/* Small on purpose: this page shows PHP and three shell commands, so a
   hand-rolled tokeniser beats shipping a library for it. Tokenising in one
   pass with alternation avoids the classic bug where a later rule rewrites
   the inside of an earlier match — a keyword found inside a string. */
const KEYWORDS = new Set(['new', 'return', 'true', 'false', 'null', 'use', 'function', 'fn', 'public', 'private', 'class', 'echo']);

const PHP_RULES = new RegExp([
  String.raw`(?<cm>\/\/[^\n]*)`,
  String.raw`(?<st>'(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*")`,
  String.raw`(?<v>\$[A-Za-z_]\w*)`,
  String.raw`(?<cls>\b[A-Z]\w*(?=::|\s*\())`,
  String.raw`(?<fn>\b[a-z_]\w*(?=\s*\())`,
  String.raw`(?<nu>\b\d+\b)`,
  String.raw`(?<word>\b[A-Za-z_]\w*\b)`,
  String.raw`(?<op>::|->|=>|[=;.,()\[\]{}+\-])`,
].join('|'), 'g');


function highlightPhp(src) {
  let out = '', last = 0;
  for (const m of src.matchAll(PHP_RULES)) {
    out += esc(src.slice(last, m.index));
    const g = m.groups;
    const cls = g.cm ? 'cm' : g.st ? 'st' : g.v ? 'v' : g.cls ? 'cls' : g.fn ? 'fn'
      : g.nu ? 'nu' : g.op ? 'op' : (g.word && KEYWORDS.has(g.word)) ? 'k' : null;
    out += cls ? `<span class="${cls}">${esc(m[0])}</span>` : esc(m[0]);
    last = m.index + m[0].length;
  }
  return out + esc(src.slice(last));
}

function highlightSh(src) {
  return src.split('\n').map(line => {
    const m = line.match(/^(\S+)(.*)$/);
    if (!m) return esc(line);
    // An option begins a word: the hyphen in password-toolkit is not one.
    const rest = m[2].replace(/(^|\s)(--?[A-Za-z][\w-]*(?:=\S*)?)/g,
      (_, lead, opt) => `${lead}<span class="sh-opt">${esc(opt)}</span>`);
    const safe = rest.split(/(<span class="sh-opt">.*?<\/span>)/g)
      .map(part => part.startsWith('<span') ? part : esc(part)).join('');
    return `<span class="sh-cmd">${esc(m[1])}</span>${safe}`;
  }).join('\n');
}

/* The guide's router reaches for this and never declared it; the landing
   page did. One declaration now serves both. */
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ── CODE BLOCKS ────────────────────────────────────────────── */
/* JSON is close enough to the PHP rules for strings and numbers, and the
   difference that matters — a key is a string too — needs no extra pass.
   The landing page's blocks are highlighted but not wrapped: its code sits
   inside laid-out figures and a hovering Copy button is a guide affordance. */
$$('pre').forEach(pre => {
  const src = pre.textContent;
  pre.innerHTML = pre.dataset.lang === 'sh' ? highlightSh(src) : highlightPhp(src);
  if (pre.closest('#home')) return;

  const wrap = document.createElement('div');
  wrap.className = 'codewrap';
  pre.parentNode.insertBefore(wrap, pre);
  wrap.append(pre);
  const btn = document.createElement('button');
  btn.textContent = 'Copy';
  btn.onclick = async () => {
    await navigator.clipboard.writeText(src);
    btn.textContent = 'Copied';
    setTimeout(() => { btn.textContent = 'Copy'; }, 1400);
  };
  wrap.append(btn);
});

/* ── PROGRESS ───────────────────────────────────────────────── */
const bar = $('#progress');
addEventListener('scroll', () => {
  const max = document.body.scrollHeight - innerHeight;
  bar.style.width = (max > 0 ? Math.min(1, scrollY / max) * 100 : 0) + '%';
}, { passive: true });

/* ── PAGE ROUTING ───────────────────────────────────────────── */
/* Routes are #/page and #/page/heading. The rail, the pager and the
   right-hand contents all read from the same ordered list of sections, so
   they cannot disagree about what comes next. #/home is not one of them:
   it is the landing page, which has no rail, no pager and no neighbours. */
const PAGES = $$('.doc main > section[id]');
const homeWrap = $('#home'), docWrap = $('.doc'), skyCanvas = $('#sky');
const railLinks = new Map($$('.toc a:not(.out)').map(a => [a.getAttribute('href').replace(/^#\//, ''), a]));
const navLinks = $$('.menu a[data-nav]');
const subs = $('#subs'), onthis = $('.onthis');
const prevCard = $('#pg-prev'), nextCard = $('#pg-next'), pager = $('.pager');
const titleOf = sec => sec.dataset.title
  || sec.querySelector('h2.page-title, h2')?.dataset.label
  || sec.querySelector('h2.page-title, h2')?.textContent.trim()
  || sec.id;

/* Ids and permalinks are assigned once, up front — the rail and the search
   index both read headings, and doing it lazily meant whichever ran first
   decided whether an id existed. */
PAGES.forEach(sec => {
  sec.querySelectorAll('h2, h3').forEach(h => {
    if (h.closest('.card')) return;
    const isTitle = h.classList.contains('page-title');
    // Captured before the glyph is appended, or every label would end in "#".
    h.dataset.label = h.textContent.trim();
    if (!h.id) h.id = isTitle ? sec.id : slug(h);
    const a = document.createElement('a');
    a.className = 'anchor';
    a.href = isTitle ? `#/${sec.id}` : `#/${sec.id}/${h.id}`;
    a.textContent = '#';
    a.setAttribute('aria-label', `Copy link to ${h.dataset.label}`);
    a.onclick = e => {
      // The router listens on the document; this one is done deciding.
      e.preventDefault(); e.stopPropagation();
      navigator.clipboard?.writeText(new URL(a.getAttribute('href'), location.href).href)
        .catch(() => {});
      history.replaceState(null, '', a.getAttribute('href'));
      a.classList.add('done');
      setTimeout(() => a.classList.remove('done'), 1400);
    };
    h.append(a);
  });
});

function show(id, push) {
  const page = PAGES.find(s => s.id === id);
  // An unknown route is the front page rather than a blank screen.
  const isHome = !page;

  homeWrap.classList.toggle('hidden', !isHome);
  docWrap.classList.toggle('hidden', isHome);
  skyCanvas.classList.toggle('hidden', !isHome);
  PAGES.forEach(s => s.classList.toggle('on', s === page));

  railLinks.forEach(a => a.removeAttribute('aria-current'));
  navLinks.forEach(a => a.classList.remove('here'));
  if (isHome) {
    navLinks.find(a => a.dataset.nav === 'home')?.classList.add('here');
    document.title = 'PasswordToolkit — the password you send is not the password you store';
    if (push && !location.hash.startsWith('#/home')) history.pushState(null, '', '#/home');
    return;
  }

  railLinks.get(page.id)?.setAttribute('aria-current', 'true');
  /* The nav names five destinations and the guide has nineteen pages, so any
     page without a link of its own marks Guide — the door it came through. */
  (navLinks.find(a => a.dataset.nav === page.id)
    || navLinks.find(a => a.dataset.nav === 'intro'))?.classList.add('here');
  document.title = titleOf(page) + ' · PasswordToolkit';

  // Right-hand contents: the headings of this page, or nothing at all rather
  // than an empty box with a heading over it. Section headings only: a page's
  // own title is not an entry in its own contents.
  const heads = [...page.querySelectorAll('h2, h3')]
    .filter(h => !h.classList.contains('page-title') && !h.closest('.card'));
  subs.innerHTML = heads.map(h => `<a href="#/${page.id}/${h.id}">${esc(h.dataset.label)}</a>`).join('');
  onthis.classList.toggle('empty', heads.length === 0);

  const i = PAGES.indexOf(page);
  const prev = PAGES[i - 1], next = PAGES[i + 1];
  prevCard.hidden = !prev; nextCard.hidden = !next;
  pager.classList.toggle('only-next', !prev && !!next);
  if (prev) { prevCard.href = '#/' + prev.id; prevCard.querySelector('b').textContent = titleOf(prev); }
  if (next) { nextCard.href = '#/' + next.id; nextCard.querySelector('b').textContent = titleOf(next); }

  if (push && location.hash !== '#/' + page.id) history.pushState(null, '', '#/' + page.id);
  spySubs();
  fitRail();
}

// h3s were not all given ids by hand; one is derived once, from the text.
function slug(h) {
  return 'h-' + h.textContent.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

/* The right rail tracks the heading you are reading within the page. */
function spySubs() {
  const links = [...subs.querySelectorAll('a')];
  if (!links.length) return;
  const y = scrollY + 130;
  let best = 0;
  links.forEach((a, i) => {
    const h = document.getElementById(a.getAttribute('href').split('/')[2]);
    if (h && h.getBoundingClientRect().top + scrollY <= y) best = i;
  });
  links.forEach((a, i) => i === best ? a.setAttribute('aria-current', 'true') : a.removeAttribute('aria-current'));
}
addEventListener('scroll', spySubs, { passive: true });

/* Two things, both measured rather than guessed. The rail is only sticky
   while it fits in the window; and the grid is held at least as tall as the
   rail, so a short page cannot let the footer ride up underneath it. */
const railBox = $('.doc > aside'), docBox = $('.doc');
function fitRail() {
  const h = railBox.scrollHeight;
  railBox.classList.toggle('is-tall', h > innerHeight - 100);
  docBox.style.minHeight = (h + 72) + 'px';
}
addEventListener('resize', fitRail);

/* One handler for every in-site link: rail, nav, pager, right rail and the
   body text, so a cross-reference inside a paragraph switches pages too. */
addEventListener('click', e => {
  const a = e.target.closest('a[href^="#/"]');
  if (!a || a.classList.contains('out')) return;
  const [id, sub] = a.getAttribute('href').slice(2).split('/');
  if (!id) return;
  e.preventDefault();
  show(id, true);
  if (sub) document.getElementById(sub)?.scrollIntoView({ block: 'start' });
  else scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
});

const routeOf = () => location.hash.replace(/^#\/?/, '').split('/');
addEventListener('popstate', () => show(routeOf()[0], false));

const DATA = {"meta":{"dictionaries":200,"names":4396,"sample":32,"groups":{"arts":"Arts","culture":"Culture","drink":"Drink","food":"Food","history":"History","myth":"Myth","nature":"Nature","places":"Places","science":"Science","screen":"Screen","sport":"Sport","vehicles":"Vehicles"}},"dicts":{"star_wars":{"label":"Star Wars","icon":"🚀","group":"screen","tags":["film","science-fiction"],"reach":"global","locale":"en","type":"people","names":[["Luke Skywalker","m"],["Leia Organa","f"],["Han Solo","m"],["Darth Vader","m"],["Yoda","m"],["Chewbacca","m"],["Obi Wan Kenobi","m"],["Anakin Skywalker","m"],["Palpatine","m"],["Lando Calrissian","m"],["Boba Fett","m"],["Darth Maul","m"],["Count Dooku","m"],["Rey","f"],["Kylo Ren","m"]],"en":["Affectionate","Aggressive","Altruistic","Clearest","Committed","Corrosive","Diplomatic","Dominant","Elusive","Empathetic","Enthusiastic","Erudite","Fascinating","Furious","Gritty","Hardened","Hungry","Selfish","Sly","Undaunted"],"it":[["Affamata","f"],["Affamato","m"],["Affascinante","n"],["Affettuosa","f"],["Affettuoso","m"],["Aggressiva","f"],["Aggressivo","m"],["Agguerrita","f"],["Agguerrito","m"],["Altruista","n"],["Chiarissima","f"],["Chiarissimo","m"],["Corrosiva","f"],["Corrosivo","m"],["Diplomatica","f"],["Diplomatico","m"],["Dominante","n"],["Egoista","n"],["Elusiva","f"],["Elusivo","m"],["Empatica","f"],["Empatico","m"],["Entusiasta","n"],["Erudita","f"],["Erudito","m"],["Furba","f"],["Furbo","m"],["Furiosa","f"],["Furioso","m"],["Grintosa","f"],["Grintoso","m"],["Impavida","f"],["Impavido","m"],["Impegnata","f"],["Impegnato","m"]],"tr":{"Count Dooku":"Conte Dooku"}},"dune":{"label":"Dune","icon":"🪱","group":"screen","tags":["film","sciencefiction","twentytwenties"],"reach":"global","locale":"en","type":"people","names":[["Paul Atreides","m"],["Lady Jessica","f"],["Leto Atreides","m"],["Duncan Idaho","m"],["Gurney Halleck","m"],["Thufir Hawat","m"],["Chani","f"],["Stilgar","m"],["Vladimir Harkonnen","m"],["Glossu Rabban","m"],["Piter De Vries","m"],["Feyd Rautha","m"],["Princess Irulan","f"],["Liet Kynes","f"],["Shaddam","m"]],"en":["Ancient","Arid","Ascetic","Austere","Fearsome","Feudal","Hereditary","Imperial","Inexorable","Infinite","Mystical","Noble","Oneiric","Primordial","Prophetic","Relentless","Sandy","Torrid","Tribal","Visionary"],"it":[["Antica","f"],["Antico","m"],["Arida","f"],["Arido","m"],["Ascetica","f"],["Ascetico","m"],["Austera","f"],["Austero","m"],["Ereditaria","f"],["Ereditario","m"],["Feudale","n"],["Imperiale","n"],["Implacabile","n"],["Inesorabile","n"],["Infinita","f"],["Infinito","m"],["Mistica","f"],["Mistico","m"],["Nobile","n"],["Onirica","f"],["Onirico","m"],["Primordiale","n"],["Profetica","f"],["Profetico","m"],["Sabbiosa","f"],["Sabbioso","m"],["Temibile","n"],["Torrida","f"],["Torrido","m"],["Tribale","n"],["Visionaria","f"],["Visionario","m"]],"tr":{}},"harry_potter":{"label":"Harry Potter","icon":"🧙","group":"screen","tags":["books","fantasy"],"reach":"global","locale":"en","type":"people","names":[["Harry Potter","m"],["Hermione Granger","f"],["Ron Weasley","m"],["Albus Dumbledore","m"],["Minerva McGonagall","f"],["Severus Snape","m"],["Draco Malfoy","m"],["Rubeus Hagrid","m"],["Ginny Weasley","f"],["Fred Weasley","m"],["George Weasley","m"],["Neville Longbottom","m"],["Luna Lovegood","f"],["Cedric Diggory","m"],["Cho Chang","f"],["Bill Weasley","m"],["Charlie Weasley","m"],["Arthur Weasley","m"],["Molly Weasley","f"],["Percy Weasley","m"],["Alastor Moody","m"],["Sirius Black","m"],["Remus Lupin","m"],["James Potter","m"],["Lily Potter","f"],["Tom Riddle","m"],["Lord Voldemort","m"],["Bellatrix Lestrange","f"],["Lucius Malfoy","m"],["Narcissa Malfoy","f"],["Dolores Umbridge","f"],["Gilderoy Lockhart","m"],["Filius Flitwick","m"],["Pomona Sprout","f"],["Horace Slughorn","m"],["Madam Malkin","f"],["Sybill Trelawney","f"],["Ariana Dumbledore","f"],["Peter Pettigrew","m"],["Dobby","m"],["Winky","f"],["Kreacher","m"],["Romilda Vane","f"],["Firenze","m"],["Gregory Goyle","m"],["Vincent Crabbe","m"],["Pansy Parkinson","f"],["Lavender Brown","f"],["Parvati Patil","f"],["Padma Patil","f"],["Lee Jordan","m"],["Angelina Johnson","f"],["Katie Bell","f"],["Oliver Wood","m"],["Colin Creevey","m"],["Dennis Creevey","m"],["Adrian Pucey","m"],["Blaise Zabini","m"],["Millicent Bulstrode","f"],["Nymphadora Tonks","f"],["Rita Skeeter","f"],["Viktor Krum","m"],["Fleur Delacour","f"],["Dean Thomas","m"],["Seamus Finnigan","m"],["Albus Potter","m"],["James Sirius Potter","m"],["Rose Weasley","f"],["Hugo Weasley","m"],["Scorpius Malfoy","m"],["Lily Luna Potter","f"]],"en":["Adorable","Affectionate","Altruistic","Cautious","Comic","Complicated","Desperate","Empathetic","Enthusiastic","Furious","Gloomy","Hungry","Icy","Impetuous","Impulsive","Jealous","Sweet","Unsettling","Weary","Wicked"],"it":[["Adorabile","n"],["Affamata","f"],["Affamato","m"],["Affaticata","f"],["Affaticato","m"],["Affettuosa","f"],["Affettuoso","m"],["Altruista","n"],["Cattiva","f"],["Cattivo","m"],["Cauta","f"],["Cauto","m"],["Comica","f"],["Comico","m"],["Complicata","f"],["Complicato","m"],["Cupa","f"],["Cupo","m"],["Disperata","f"],["Disperato","m"],["Dolce","n"],["Empatica","f"],["Empatico","m"],["Entusiasta","n"],["Furiosa","f"],["Furioso","m"],["Gelida","f"],["Gelido","m"],["Gelosa","f"],["Geloso","m"],["Impetuosa","f"],["Impetuoso","m"],["Impulsiva","f"],["Impulsivo","m"],["Inquietante","n"]],"tr":{"Albus Dumbledore":"Albus Silente","Minerva McGonagall":"Minerva McGranitt","Severus Snape":"Severus Piton","Neville Longbottom":"Neville Paciock","Gilderoy Lockhart":"Gilderoy Allock","Filius Flitwick":"Filius Vitious","Horace Slughorn":"Horace Lumacorno","Madam Malkin":"Madama Malkin","Sybill Trelawney":"Sibilla Cooman","Ariana Dumbledore":"Arianna Silente","Peter Pettigrew":"Peter Minus","Lavender Brown":"Lavanda Brown"}},"top_gun":{"label":"Top Gun","icon":"✈️","group":"screen","tags":["aviation","eighties","film"],"reach":"global","locale":"en","type":"people","names":[["Maverick","m"],["Goose","m"],["Iceman","m"],["Viper","m"],["Jester","m"],["Slider","m"],["Charlie","f"],["Rooster","m"],["Hangman","m"],["Phoenix","f"],["Bob","m"],["Payback","m"],["Fanboy","m"],["Coyote","m"],["Cyclone","m"]],"en":["Acrobatic","Audacious","Bold","Brazen","Competitive","Dizzying","Flying","Glacial","Gritty","Invincible","Legendary","Lightning","Military","Reckless","Roaring","Snappy","Supersonic","Swift","Tactical","Undaunted"],"it":[["Acrobatica","f"],["Acrobatico","m"],["Audace","n"],["Competitiva","f"],["Competitivo","m"],["Fulminea","f"],["Fulmineo","m"],["Glaciale","n"],["Grintosa","f"],["Grintoso","m"],["Impavida","f"],["Impavido","m"],["Invincibile","n"],["Leggendaria","f"],["Leggendario","m"],["Militare","n"],["Ruggente","n"],["Scattante","n"],["Sfrontata","f"],["Sfrontato","m"],["Spericolata","f"],["Spericolato","m"],["Supersonica","f"],["Supersonico","m"],["Tattica","f"],["Tattico","m"],["Temeraria","f"],["Temerario","m"],["Veloce","n"],["Vertiginosa","f"],["Vertiginoso","m"],["Volante","n"]],"tr":{}},"the_matrix":{"label":"The Matrix","icon":"💊","group":"screen","tags":["film","nineties","sciencefiction"],"reach":"global","locale":"en","type":"people","names":[["Neo","m"],["Morpheus","m"],["Trinity","f"],["Agent Smith","m"],["The Oracle","f"],["Cypher","m"],["Tank","m"],["Dozer","m"],["Apoc","m"],["Mouse","m"],["Switch","f"],["Agent Brown","m"],["Agent Jones","m"],["Thomas Anderson","m"]],"en":["Artificial","Binary","Cloned","Cybernetic","Digital","Elusive","Encoded","Hypnotic","Illusory","Lucid","Occult","Oneiric","Prophetic","Rebellious","Simulated","Underground","Unfathomable","Unpredictable","Unstoppable","Virtual"],"it":[["Artificiale","n"],["Binaria","f"],["Binario","m"],["Cibernetica","f"],["Cibernetico","m"],["Clonata","f"],["Clonato","m"],["Codificata","f"],["Codificato","m"],["Digitale","n"],["Elusiva","f"],["Elusivo","m"],["Illusoria","f"],["Illusorio","m"],["Imprevedibile","n"],["Inarrestabile","n"],["Insondabile","n"],["Ipnotica","f"],["Ipnotico","m"],["Lucida","f"],["Lucido","m"],["Occulta","f"],["Occulto","m"],["Onirica","f"],["Onirico","m"],["Profetica","f"],["Profetico","m"],["Ribelle","n"],["Simulata","f"],["Simulato","m"],["Sotterranea","f"],["Sotterraneo","m"],["Virtuale","n"]],"tr":{"Agent Brown":"Agente Brown","Agent Jones":"Agente Jones","Agent Smith":"Agente Smith","The Oracle":"Oracolo"}},"italian_pasta_shapes":{"label":"Italian Pasta Shapes","icon":"🍝","group":"food","tags":["cuisine","italian"],"reach":"global","locale":"it","type":"things","names":[["Fusillo","m"],["Orecchietta","f"],["Rigatone","m"],["Farfalla","f"],["Penna","f"],["Spaghetto","m"],["Linguina","f"],["Tagliatella","f"],["Lasagna","f"],["Gnocco","m"],["Raviolo","m"],["Tortellino","m"],["Cannellone","m"],["Conchiglia","f"],["Bucatino","m"],["Pappardella","f"],["Trofia","f"],["Picio","m"],["Maccherone","m"],["Zito","m"]],"en":["Authentic","Creamy","Delicious","Flavorful","Fragrant","Genuine","Greedy","Irresistible","Rustic","Steaming","Succulent","Tasty","Traditional"],"it":[["gustoso","m"],["gustosa","f"],["saporito","m"],["saporita","f"],["cremoso","m"],["cremosa","f"],["fumante","n"],["delizioso","m"],["deliziosa","f"],["goloso","m"],["golosa","f"],["fragrante","n"],["succulento","m"],["succulenta","f"],["tradizionale","n"],["autentico","m"],["autentica","f"],["genuino","m"],["genuina","f"],["irresistibile","n"],["rustico","m"],["rustica","f"]],"tr":{}},"italian_cheeses":{"label":"Italian Cheeses","icon":"🧀","group":"food","tags":["cuisine","italian"],"reach":"global","locale":"it","type":"things","names":[["Parmigiano Reggiano","m"],["Mozzarella","f"],["Gorgonzola","m"],["Pecorino","m"],["Ricotta","f"],["Provolone","m"],["Grana Padano","m"],["Caciocavallo","m"],["Fontina","f"],["Taleggio","m"],["Montasio","m"],["Asiago","m"],["Burrata","f"],["Stracchino","m"],["Ricotta Salata","f"],["Ragusano","m"],["Castelmagno","m"],["Pecorino Sardo","m"],["Pecorino Romano","m"],["Fiore Sardo","m"],["Scamorza","f"],["Caciotta","f"],["Tuma","f"],["Canestrato","m"]],"en":["Aromatic","Creamy","Delicious","Flavorful","Fragrant","Fresh","Intense","Intensive","Nourishing","Refined","Rich","Savory","Soft","Spicy","Tantalizing"],"it":[["delizioso","m"],["deliziosa","f"],["cremoso","m"],["cremosa","f"],["saporito","m"],["saporita","f"],["morbido","m"],["morbida","f"],["piccante","n"],["intenso","m"],["intensa","f"],["fragrante","n"],["aromatico","m"],["aromatica","f"],["salato","m"],["salata","f"],["stuzzicante","n"],["raffinato","m"],["raffinata","f"],["fresco","m"],["fresca","f"],["grasso","m"],["grassa","f"],["nutriente","n"],["intensivo","m"],["intensiva","f"]],"tr":{}},"cocktails":{"label":"Cocktails","icon":"🍸","group":"drink","tags":["bar","mixology"],"reach":"global","locale":"en","type":"things","names":[["Negroni","m"],["Bellini","m"],["Margarita","m"],["Mojito","m"],["Daiquiri","m"],["Manhattan","m"],["Martini","m"],["Spritz","m"],["Cosmopolitan","m"],["Sidecar","m"],["Americano","m"],["Mimosa","m"],["Caipirinha","f"],["Sazerac","m"],["Bloody Mary","m"]],"en":["Bitter","Boozy","Citrusy","Colorful","Effervescent","Frozen","Mixed","Quenching","Refreshing","Shaken","Sparkling","Spiced","Strong","Summery","Sweet","Tropical"],"it":[["ghiacciato","m"],["ghiacciata","f"],["agrumato","m"],["agrumata","f"],["shakerato","m"],["shakerata","f"],["amaro","m"],["amara","f"],["dolce","n"],["frizzante","n"],["alcolico","m"],["alcolica","f"],["rinfrescante","n"],["tropicale","n"],["speziato","m"],["speziata","f"],["miscelato","m"],["miscelata","f"],["colorato","m"],["colorata","f"],["estivo","m"],["estiva","f"],["effervescente","n"],["forte","n"],["dissetante","n"]],"tr":{}},"italian_wines":{"label":"Italian Wines","icon":"🍷","group":"drink","tags":["italian","wine"],"reach":"global","locale":"it","type":"things","names":[["Barolo","m"],["Chianti","m"],["Prosecco","m"],["Brunello","m"],["Amarone","m"],["Pinot Grigio","m"],["Sangiovese","m"],["Cannonau","m"],["Montepulciano","m"],["Vermentino","m"],["Nebbiolo","m"],["Aglianico","m"],["Barbera","f"],["Dolcetto","m"],["Primitivo","m"],["Gavi","m"],["Moscato","m"],["Cortese","m"],["Lambrusco","m"],["Fiano","m"],["Greco","m"],["Torgiano","m"],["Sassicaia","m"],["Barbaresco","m"],["Ghemme","m"],["Valpolicella","f"],["Frascati","m"],["Trebbiano","m"],["Verdeca","f"],["Lugana","f"],["Vernaccia","f"],["Custoza","f"],["Lacrima","f"],["Ribolla","f"],["Negroamaro","m"],["Falanghina","f"],["Schiava","f"],["Carignano","m"],["Maremma","f"],["Cerasuolo","m"],["Verdicchio","m"],["Passito","m"],["Pecorino","m"],["Grillo","m"],["Sorbara","m"],["Pignoletto","m"],["Syrah","m"],["Cabernet","m"],["Merlot","m"],["Chardonnay","m"],["Malvasia","f"],["Bianchello","m"],["Gaglioppo","m"],["Ciliegiolo","m"],["Lagrein","m"],["Montefalco","m"],["Bardolino","m"],["Etna","m"],["Colli Euganei","m"],["Franciacorta","m"]],"en":["Aromatic","Austere","Balanced","Balsamic","Complex","Creamy","Exotic","Floral","Fragrant","Fruity","Fullbodied","Harmonic","Mature","Mineral","Persistent","Plush","Soft","Sweet","Tasteful","Warm"],"it":[["Armonica","f"],["Armonico","m"],["Aromatica","f"],["Aromatico","m"],["Austera","f"],["Austero","m"],["Balsamica","f"],["Balsamico","m"],["Bilanciata","f"],["Bilanciato","m"],["Calda","f"],["Caldo","m"],["Complessa","f"],["Complesso","m"],["Corposa","f"],["Corposo","m"],["Cremosa","f"],["Cremoso","m"],["Dolce","n"],["Esotica","f"],["Esotico","m"],["Floreale","n"],["Fragrante","n"],["Fruttata","f"],["Fruttato","m"],["Gusto","m"],["Mature","n"],["Minerale","n"],["Morbidissima","f"],["Morbidissimo","m"],["Morbido","m"],["Persistente","n"]],"tr":{}},"italian_cities":{"label":"Italian Cities","icon":"🏙️","group":"places","tags":["geography","italian"],"reach":"global","locale":"it","type":"things","names":[["Roma","f"],["Milano","m"],["Napoli","f"],["Torino","m"],["Firenze","f"],["Venezia","f"],["Genova","f"],["Bologna","f"],["Palermo","m"],["Verona","f"],["Padova","f"],["Siena","f"],["Pisa","f"],["Trieste","f"],["Mantova","f"]],"en":["Academic","Ancient","Baroque","Bustling","Chaotic","Evocative","Foggy","Fortified","Genteel","Hardworking","Hilly","Historic","Maritime","Medieval","Monumental","Panoramic","Picturesque","Renaissance","Sunlit","Welcoming"],"it":[["Accogliente","n"],["Antica","f"],["Antico","m"],["Barocca","f"],["Barocco","m"],["Caotica","f"],["Caotico","m"],["Collinare","n"],["Fortificata","f"],["Fortificato","m"],["Marittima","f"],["Marittimo","m"],["Medievale","n"],["Monumentale","n"],["Nebbiosa","f"],["Nebbioso","m"],["Operosa","f"],["Operoso","m"],["Panoramica","f"],["Panoramico","m"],["Pittoresca","f"],["Pittoresco","m"],["Rinascimentale","n"],["Signorile","n"],["Soleggiata","f"],["Soleggiato","m"],["Storica","f"],["Storico","m"],["Suggestiva","f"],["Suggestivo","m"],["Trafficata","f"],["Trafficato","m"],["Universitaria","f"],["Universitario","m"]],"tr":{"Firenze":"Florence","Genova":"Genoa","Mantova":"Mantua","Milano":"Milan","Napoli":"Naples","Padova":"Padua","Roma":"Rome","Torino":"Turin","Venezia":"Venice"}},"world_capitals":{"label":"World Capitals","icon":"🌍","group":"places","tags":["geography","cities"],"reach":"global","locale":"en","type":"things","names":[["London","f"],["Paris","f"],["Moscow","f"],["Beijing","f"],["Lisbon","f"],["Copenhagen","f"],["Cairo","m"],["Athens","f"],["Warsaw","f"],["Stockholm","f"],["Prague","f"],["Dublin","f"],["Belgrade","f"],["Berlin","f"],["Bucharest","f"]],"en":["Ancient","Central","Chaotic","Distant","Elegant","Fascinating","Historic","Imperial","Lively","Luminous","Metropolitan","Modern","Monumental","Nordic","Populous","Touristic"],"it":[["metropolitano","m"],["metropolitana","f"],["storico","m"],["storica","f"],["caotico","m"],["caotica","f"],["luminoso","m"],["luminosa","f"],["popoloso","m"],["popolosa","f"],["monumentale","n"],["turistico","m"],["turistica","f"],["elegante","n"],["lontano","m"],["lontana","f"],["vivace","n"],["imperiale","n"],["nordico","m"],["nordica","f"],["affascinante","n"],["moderno","m"],["moderna","f"],["antico","m"],["antica","f"],["centrale","n"]],"tr":{"London":"Londra","Paris":"Parigi","Moscow":"Mosca","Beijing":"Pechino","Lisbon":"Lisbona","Copenhagen":"Copenaghen","Cairo":"Il Cairo","Athens":"Atene","Warsaw":"Varsavia","Stockholm":"Stoccolma","Prague":"Praga","Dublin":"Dublino","Belgrade":"Belgrado","Berlin":"Berlino","Bucharest":"Bucarest"}},"italian_volcanoes":{"label":"Italian Volcanoes","icon":"🌋","group":"nature","tags":["geography","italian"],"reach":"global","locale":"it","type":"things","names":[["Etna","m"],["Vesuvio","m"],["Stromboli","m"],["Vulcano","m"],["Solfatara","f"],["Campi Flegrei","m"],["Marsili","m"],["Vulture","m"],["Roccamonfina","f"],["Colli Albani","m"],["Pantelleria","f"],["Linosa","f"],["Ferdinandea","f"],["Empedocle","m"],["Ischia","f"],["Amiata","m"],["Vulsini","m"],["Cimini","m"]],"en":["Ancient","Ardent","Explosive","Fiery","Imposing","Incandescent","Infernal","Legendary","Majestic","Menacing","Powerful","Primordial","Seething","Steaming","Wild"],"it":[["ardente","n"],["infuocato","m"],["infuocata","f"],["esplosivo","m"],["esplosiva","f"],["imponente","n"],["minaccioso","m"],["minacciosa","f"],["fumante","n"],["ribollente","n"],["incandescente","n"],["antico","m"],["antica","f"],["potente","n"],["maestoso","m"],["maestosa","f"],["leggendario","m"],["leggendaria","f"],["selvaggio","m"],["selvaggia","f"],["infernale","n"],["primordiale","n"]],"tr":{"Campi Flegrei":"Phlegraean Fields","Colli Albani":"Alban Hills","Vesuvio":"Vesuvius"}},"constellations":{"label":"Constellations","icon":"✨","group":"nature","tags":["astronomy","sky"],"reach":"global","locale":"en","type":"things","names":[["Orion","m"],["Cassiopeia","f"],["Pegasus","m"],["Lyra","f"],["Andromeda","f"],["Cygnus","m"],["Scorpius","m"],["Auriga","m"],["Perseus","m"],["Hydra","f"],["Draco","m"],["Cepheus","m"],["Phoenix","f"],["Centaurus","m"],["Aquarius","m"]],"en":["Ancient","Austral","Boreal","Brilliant","Celestial","Circumpolar","Infinite","Luminous","Mythical","Nebulous","Nocturnal","Remote","Shining","Sidereal","Starry","Zodiacal"],"it":[["stellato","m"],["stellata","f"],["boreale","n"],["australe","n"],["luminoso","m"],["luminosa","f"],["celeste","n"],["zodiacale","n"],["remoto","m"],["remota","f"],["notturno","m"],["notturna","f"],["mitico","m"],["mitica","f"],["splendente","n"],["circumpolare","n"],["nebuloso","m"],["nebulosa","f"],["antico","m"],["antica","f"],["brillante","n"],["infinito","m"],["infinita","f"],["siderale","n"]],"tr":{"Orion":"Orione","Cassiopeia":"Cassiopea","Pegasus":"Pegaso","Lyra":"Lira","Cygnus":"Cigno","Scorpius":"Scorpione","Perseus":"Perseo","Hydra":"Idra","Draco":"Drago","Cepheus":"Cefeo","Phoenix":"Fenice","Centaurus":"Centauro","Aquarius":"Acquario"}},"gemstones":{"label":"Gemstones","icon":"💎","group":"nature","tags":["minerals","jewels"],"reach":"global","locale":"en","type":"things","names":[["Sapphire","m"],["Amber","f"],["Jade","f"],["Opal","m"],["Turquoise","m"],["Garnet","m"],["Topaz","m"],["Peridot","m"],["Emerald","m"],["Ruby","m"],["Diamond","m"],["Amethyst","f"],["Lapis Lazuli","m"],["Aquamarine","f"],["Onyx","f"]],"en":["Colorful","Crystalline","Faceted","Glittering","Inset","Iridescent","Opaque","Polished","Precious","Pure","Rare","Raw","Shining","Sparkling","Tough","Translucent"],"it":[["sfaccettato","m"],["sfaccettata","f"],["luccicante","n"],["prezioso","m"],["preziosa","f"],["raro","m"],["rara","f"],["lucente","n"],["grezzo","m"],["grezza","f"],["incastonato","m"],["incastonata","f"],["cristallino","m"],["cristallina","f"],["iridescente","n"],["levigato","m"],["levigata","f"],["opaco","m"],["opaca","f"],["puro","m"],["pura","f"],["scintillante","n"],["duro","m"],["dura","f"],["colorato","m"],["colorata","f"],["translucido","m"],["translucida","f"]],"tr":{"Sapphire":"Zaffiro","Amber":"Ambra","Jade":"Giada","Opal":"Opale","Turquoise":"Turchese","Garnet":"Granato","Topaz":"Topazio","Peridot":"Peridoto","Emerald":"Smeraldo","Ruby":"Rubino","Diamond":"Diamante","Amethyst":"Ametista","Lapis Lazuli":"Lapislazzuli","Aquamarine":"Acquamarina","Onyx":"Onice"}},"world_rivers":{"label":"World Rivers","icon":"🌊","group":"nature","tags":["geography","water"],"reach":"global","locale":"en","type":"things","names":[["Nile","m"],["Amazon","m"],["Danube","m"],["Ganges","m"],["Mekong","m"],["Yangtze","m"],["Volga","m"],["Rhine","m"],["Thames","m"],["Zambezi","m"],["Congo","m"],["Mississippi","m"],["Euphrates","m"],["Tigris","m"],["Indus","m"]],"en":["Icy","Impetuous","Limpid","Long","Majestic","Muddy","Murky","Navigable","Perennial","Placid","Sacred","Silty","Sinuous","Tropical","Wide","Winding"],"it":[["sinuoso","m"],["sinuosa","f"],["navigabile","n"],["torbido","m"],["torbida","f"],["sacro","m"],["sacra","f"],["impetuoso","m"],["impetuosa","f"],["perenne","n"],["fangoso","m"],["fangosa","f"],["largo","m"],["larga","f"],["placido","m"],["placida","f"],["tropicale","n"],["melmoso","m"],["melmosa","f"],["gelido","m"],["gelida","f"],["maestoso","m"],["maestosa","f"],["lungo","m"],["lunga","f"],["tortuoso","m"],["tortuosa","f"],["limpido","m"],["limpida","f"]],"tr":{"Nile":"Nilo","Amazon":"Rio delle Amazzoni","Danube":"Danubio","Ganges":"Gange","Rhine":"Reno","Thames":"Tamigi","Zambezi":"Zambesi","Euphrates":"Eufrate","Tigris":"Tigri","Indus":"Indo"}},"greek_mythology":{"label":"Greek Mythology","icon":"🏺","group":"myth","tags":["classical","greek"],"reach":"global","locale":"en","type":"people","names":[["Zeus","m"],["Poseidon","m"],["Hades","m"],["Apollo","m"],["Ares","m"],["Hephaestus","m"],["Hermes","m"],["Dionysus","m"],["Prometheus","m"],["Achilles","m"],["Odysseus","m"],["Heracles","m"],["Theseus","m"],["Perseus","m"],["Jason","m"],["Orpheus","m"],["Oedipus","m"],["Paris","m"],["Hector","m"],["Agamemnon","m"],["Menelaus","m"],["Daedalus","m"],["Icarus","m"],["Narcissus","m"],["Adonis","m"],["Aeneas","m"],["Ajax","m"],["Nestor","m"],["Tantalus","m"],["Sisyphus","m"],["Minos","m"],["Peleus","m"],["Priam","m"],["Cronus","m"],["Titan","m"],["Atlas","m"],["Morpheus","m"],["Hypnos","m"],["Boreas","m"],["Hera","f"],["Aphrodite","f"],["Artemis","f"],["Athena","f"],["Demeter","f"],["Hestia","f"],["Persephone","f"],["Circe","f"],["Medea","f"],["Calypso","f"],["Helen","f"],["Penelope","f"],["Antigone","f"],["Iphigenia","f"],["Medusa","f"],["Arachne","f"],["Ariadne","f"],["Cassandra","f"],["Andromache","f"],["Hecuba","f"],["Clytemnestra","f"],["Selene","f"],["Nyx","f"],["Eos","f"],["Iris","f"],["Europa","f"],["Danae","f"],["Io","f"],["Callisto","f"]],"en":["Brave","Celestial","Cunning","Divine","Epic","Fearsome","Glorious","Heroic","Immortal","Invincible","Legendary","Magnificent","Mysterious","Mythical","Olympic","Powerful","Venerable","Wise"],"it":[["olimpico","m"],["olimpica","f"],["divino","m"],["divina","f"],["eroico","m"],["eroica","f"],["leggendario","m"],["leggendaria","f"],["immortale","n"],["epico","m"],["epica","f"],["mitico","m"],["mitica","f"],["potente","n"],["temibile","n"],["saggio","m"],["saggia","f"],["astuto","m"],["astuta","f"],["coraggioso","m"],["coraggiosa","f"],["magnifico","m"],["magnifica","f"],["glorioso","m"],["gloriosa","f"],["invincibile","n"],["misterioso","m"],["misteriosa","f"],["venerabile","n"],["celestiale","n"]],"tr":{"Poseidon":"Poseidone","Hades":"Ade","Hephaestus":"Efesto","Hermes":"Ermes","Dionysus":"Dioniso","Prometheus":"Prometeo","Achilles":"Achille","Odysseus":"Ulisse","Heracles":"Eracle","Theseus":"Teseo","Perseus":"Perseo","Jason":"Giasone","Orpheus":"Orfeo","Oedipus":"Edipo","Paris":"Paride","Hector":"Ettore","Agamemnon":"Agamennone","Menelaus":"Menelao","Daedalus":"Dedalo","Icarus":"Icaro","Narcissus":"Narciso","Adonis":"Adone","Aeneas":"Enea","Ajax":"Aiace","Nestor":"Nestore","Tantalus":"Tantalo","Sisyphus":"Sisifo","Minos":"Minosse","Peleus":"Peleo","Priam":"Priamo","Cronus":"Crono","Titan":"Titano","Atlas":"Atlante","Morpheus":"Morfeo","Hypnos":"Ipnos","Boreas":"Borea","Hera":"Era","Aphrodite":"Afrodite","Artemis":"Artemide","Athena":"Atena","Demeter":"Demetra","Hestia":"Estia","Persephone":"Persefone","Calypso":"Calipso","Helen":"Elena","Iphigenia":"Ifigenia","Arachne":"Aracne","Ariadne":"Arianna","Andromache":"Andromaca","Hecuba":"Ecuba","Clytemnestra":"Clitemnestra","Iris":"Iride"}},"japanese_mythology":{"label":"Japanese Mythology","icon":"⛩️","group":"myth","tags":["japanese","gods"],"reach":"global","locale":"en","type":"people","names":[["Amaterasu","f"],["Susanoo","m"],["Tsukuyomi","m"],["Izanagi","m"],["Izanami","f"],["Raijin","m"],["Fujin","m"],["Inari","m"],["Hachiman","m"],["Ebisu","m"],["Benzaiten","f"],["Bishamonten","m"],["Tengu","m"],["Kitsune","f"],["Kappa","m"]],"en":["Ancestral","Ancient","Benevolent","Celestial","Cunning","Divine","Imperial","Luminous","Mysterious","Ritual","Sacred","Spiritual","Stormy","Thundering","Venerated","Warlike"],"it":[["divino","m"],["divina","f"],["ancestrale","n"],["sacro","m"],["sacra","f"],["spirituale","n"],["tempestoso","m"],["tempestosa","f"],["luminoso","m"],["luminosa","f"],["imperiale","n"],["misterioso","m"],["misteriosa","f"],["astuto","m"],["astuta","f"],["tonante","n"],["benevolo","m"],["benevola","f"],["antico","m"],["antica","f"],["guerriero","m"],["guerriera","f"],["celeste","n"],["rituale","n"],["venerato","m"],["venerata","f"]],"tr":{}},"celtic_mythology":{"label":"Celtic Mythology","icon":"🍀","group":"myth","tags":["celtic","gods"],"reach":"global","locale":"en","type":"people","names":[["Morrigan","f"],["Brigid","f"],["Lugh","m"],["Cernunnos","m"],["Dagda","m"],["Danu","f"],["Epona","f"],["Nuada","m"],["Manannan","m"],["Ogma","m"],["Aengus","m"],["Cu Chulainn","m"],["Taranis","m"],["Belenus","m"],["Arawn","m"]],"en":["Ancestral","Corvine","Dark","Druidic","Enchanted","Foggy","Immortal","Legendary","Magical","Prophetic","Runic","Sylvan","Valiant","Verdant","Wild","Wooded"],"it":[["druidico","m"],["druidica","f"],["magico","m"],["magica","f"],["silvestre","n"],["nebbioso","m"],["nebbiosa","f"],["ancestrale","n"],["profetico","m"],["profetica","f"],["corvino","m"],["corvina","f"],["verdeggiante","n"],["selvaggio","m"],["selvaggia","f"],["runico","m"],["runica","f"],["leggendario","m"],["leggendaria","f"],["fatato","m"],["fatata","f"],["oscuro","m"],["oscura","f"],["valoroso","m"],["valorosa","f"],["boschivo","m"],["boschiva","f"],["immortale","n"]],"tr":{}},"nato_alphabet":{"label":"Nato Alphabet","icon":"📻","group":"culture","tags":["communication","standard"],"reach":"global","locale":"en","type":"things","names":[["Alfa","m"],["Bravo","m"],["Charlie","m"],["Delta","m"],["Echo","m"],["Foxtrot","m"],["Golf","m"],["Hotel","m"],["India","m"],["Juliett","m"],["Kilo","m"],["Lima","m"],["Mike","m"],["November","m"],["Oscar","m"],["Papa","m"],["Quebec","m"],["Romeo","m"],["Sierra","m"],["Tango","m"],["Uniform","m"],["Victor","m"],["Whiskey","m"],["Xray","m"],["Yankee","m"],["Zulu","m"]],"en":["Audible","Clear","Concise","Conventional","Crisp","Encoded","Enunciated","Military","Operational","Orderly","Punctual","Radiophonic","Sharp","Sonorous","Standardised","Syllabic","Transmitted","Unambiguous"],"it":[["Chiara","f"],["Chiaro","m"],["Codificata","f"],["Codificato","m"],["Concisa","f"],["Conciso","m"],["Convenzionale","n"],["Inequivocabile","n"],["Militare","n"],["Netta","f"],["Netto","m"],["Nitida","f"],["Nitido","m"],["Operativa","f"],["Operativo","m"],["Ordinata","f"],["Ordinato","m"],["Puntuale","n"],["Radiofonica","f"],["Radiofonico","m"],["Scandita","f"],["Scandito","m"],["Sillabica","f"],["Sillabico","m"],["Sonora","f"],["Sonoro","m"],["Standardizzata","f"],["Standardizzato","m"],["Trasmessa","f"],["Trasmesso","m"],["Udibile","n"]],"tr":{}},"italian_carnival_masks":{"label":"Italian Carnival Masks","icon":"🎭","group":"culture","tags":["italian","tradition"],"reach":"italian","locale":"it","type":"things","names":[["Arlecchino","m"],["Pulcinella","m"],["Balanzone","m"],["Brighella","m"],["Colombina","f"],["Pantalone","m"],["Gianduja","m"],["Capitan Spaventa","m"],["Dottor Balanzone","m"],["Meneghino","m"],["Rugantino","m"],["Stenterello","m"],["Sandrone","m"],["Beppe Nappa","m"],["Tartaglia","m"],["Scaramuccia","m"],["Coviello","m"],["Smeraldina","f"],["Rosaura","f"],["Mirandolina","f"]],"en":["Bizarre","Cheerful","Comic","Eccentric","Flamboyant","Funny","Hilarious","Iconic","Jocular","Lively","Masked","Popular","Prankish","Sly","Traditional"],"it":[["buffo","m"],["buffa","f"],["burlone","m"],["burlona","f"],["comico","m"],["comica","f"],["stravagante","n"],["eccentrico","m"],["eccentrica","f"],["bizzarro","m"],["bizzarra","f"],["allegro","m"],["allegra","f"],["vivace","n"],["spassoso","m"],["spassosa","f"],["scherzoso","m"],["scherzosa","f"],["furbo","m"],["furba","f"],["popolare","n"],["tradizionale","n"],["iconico","m"],["iconica","f"],["mascherato","m"],["mascherata","f"]],"tr":{}},"chemical_elements":{"label":"Chemical Elements","icon":"⚗️","group":"science","tags":["chemistry","periodic-table"],"reach":"global","locale":"en","type":"things","names":[["Hydrogen","m"],["Helium","m"],["Carbon","m"],["Nitrogen","m"],["Oxygen","m"],["Neon","m"],["Sulfur","m"],["Iron","m"],["Copper","m"],["Silver","m"],["Gold","m"],["Platinum","m"],["Titanium","m"],["Uranium","m"],["Krypton","m"]],"en":["Atomic","Conductive","Corrosive","Flammable","Gaseous","Inert","Isotopic","Metallic","Molecular","Noble","Pure","Radioactive","Reactive","Soluble","Unstable","Volatile"],"it":[["reattivo","m"],["reattiva","f"],["volatile","n"],["radioattivo","m"],["radioattiva","f"],["inerte","n"],["metallico","m"],["metallica","f"],["gassoso","m"],["gassosa","f"],["nobile","n"],["molecolare","n"],["atomico","m"],["atomica","f"],["instabile","n"],["corrosivo","m"],["corrosiva","f"],["conduttivo","m"],["conduttiva","f"],["isotopico","m"],["isotopica","f"],["solubile","n"],["infiammabile","n"],["puro","m"],["pura","f"]],"tr":{"Hydrogen":"Idrogeno","Helium":"Elio","Carbon":"Carbonio","Nitrogen":"Azoto","Oxygen":"Ossigeno","Sulfur":"Zolfo","Iron":"Ferro","Copper":"Rame","Silver":"Argento","Gold":"Oro","Platinum":"Platino","Titanium":"Titanio","Uranium":"Uranio","Krypton":"Kripton"}},"dinosaurs":{"label":"Dinosaurs","icon":"🦖","group":"science","tags":["paleontology","prehistory"],"reach":"global","locale":"en","type":"things","names":[["Tyrannosaurus","m"],["Triceratops","m"],["Velociraptor","m"],["Stegosaurus","m"],["Brachiosaurus","m"],["Ankylosaurus","m"],["Diplodocus","m"],["Allosaurus","m"],["Spinosaurus","m"],["Apatosaurus","m"],["Iguanodon","m"],["Pteranodon","m"],["Giganotosaurus","m"],["Megalosaurus","m"],["Carnotaurus","m"]],"en":["Armored","Biped","Carnivorous","Cretaceous","Extinct","Feathered","Ferocious","Fossilized","Gigantic","Herbivorous","Horned","Huge","Jurassic","Predatory","Prehistoric","Scaly"],"it":[["preistorico","m"],["preistorica","f"],["gigantesco","m"],["gigantesca","f"],["corazzato","m"],["corazzata","f"],["carnivoro","m"],["carnivora","f"],["erbivoro","m"],["erbivora","f"],["fossilizzato","m"],["fossilizzata","f"],["estinto","m"],["estinta","f"],["squamoso","m"],["squamosa","f"],["cornuto","m"],["cornuta","f"],["feroce","n"],["enorme","n"],["piumato","m"],["piumata","f"],["bipede","n"],["predatorio","m"],["predatoria","f"],["giurassico","m"],["giurassica","f"],["cretaceo","m"],["cretacea","f"]],"tr":{"Tyrannosaurus":"Tirannosauro","Triceratops":"Triceratopo","Stegosaurus":"Stegosauro","Brachiosaurus":"Brachiosauro","Ankylosaurus":"Anchilosauro","Diplodocus":"Diplodoco","Allosaurus":"Allosauro","Spinosaurus":"Spinosauro","Apatosaurus":"Apatosauro","Iguanodon":"Iguanodonte","Pteranodon":"Pteranodonte","Giganotosaurus":"Giganotosauro","Megalosaurus":"Megalosauro","Carnotaurus":"Carnotauro"}},"italian_scientists":{"label":"Italian Scientists","icon":"🔬","group":"science","tags":["italian"],"reach":"global","locale":"it","type":"people","names":[["Galileo Galilei","m"],["Alessandro Volta","m"],["Guglielmo Marconi","m"],["Enrico Fermi","m"],["Evangelista Torricelli","m"],["Luigi Galvani","m"],["Antonio Meucci","m"],["Amedeo Avogadro","m"],["Lazzaro Spallanzani","m"],["Giovanni Battista Morgagni","m"],["Marcello Malpighi","m"],["Giambattista della Porta","m"],["Giovanni Alfonso Borelli","m"],["Francesco Redi","m"],["Gian Domenico Cassini","m"],["Girolamo Cardano","m"],["Niccolo Tartaglia","m"],["Gerolamo Fracastoro","m"],["Carlo Matteucci","m"],["Stanislao Cannizzaro","m"],["Ascanio Sobrero","m"],["Angelo Mosso","m"],["Filippo Pacini","m"],["Camillo Golgi","m"],["Antonio Scarpa","m"],["Ruggero Boscovich","m"],["Paolo Frisi","m"],["Giovanni Virginio Schiaparelli","m"],["Lorenzo Respighi","m"],["Angelo Secchi","m"],["Giovanni Battista Amici","m"],["Leopoldo Nobili","m"],["Macedonio Melloni","m"],["Ottavio Fabrizio Mossotti","m"],["Augusto Righi","m"],["Quirino Majorana","m"],["Ettore Majorana","m"],["Bruno Pontecorvo","m"],["Edoardo Amaldi","m"],["Franco Rasetti","m"],["Giuseppe Occhialini","m"],["Carlo Rubbia","m"],["Giorgio Parisi","m"],["Riccardo Giacconi","m"],["Giulio Natta","m"],["Daniel Bovet","m"],["Ernesto Illy","m"],["Bernardino Ramazzini","m"],["Leonardo da Vinci","m"],["Federico Faggin","m"],["Vito Volterra","m"],["Giuseppe Peano","m"],["Giuseppe Piazzi","m"],["Felice Fontana","m"],["Renato Dulbecco","m"],["Tullio Regge","m"],["Bruno Rossi","m"],["Margherita Hack","f"],["Laura Bassi","f"],["Maria Gaetana Agnesi","f"],["Fabiola Gianotti","f"],["Samantha Cristoforetti","f"],["Ersilia Caetani Lovatelli","f"],["Giuseppina Pizzigoni","f"],["Maria Montessori","f"],["Elena Cornaro Piscopia","f"],["Trotula de Ruggiero","f"],["Enrica Calabresi","f"]],"en":["Analytical","Brilliant","Curious","Determined","Enlightened","Extraordinary","Immortal","Ingenious","Innovative","Methodical","Perceptive","Pioneering","Precise","Revolutionary","Sharp","Systematic","Tireless","Visionary"],"it":[["brillante","n"],["curioso","m"],["curiosa","f"],["rivoluzionario","m"],["rivoluzionaria","f"],["innovativo","m"],["innovativa","f"],["geniale","n"],["preciso","m"],["precisa","f"],["metodico","m"],["metodica","f"],["visionario","m"],["visionaria","f"],["analitico","m"],["analitica","f"],["illuminato","m"],["illuminata","f"],["pioniere","n"],["acuto","m"],["acuta","f"],["perspicace","n"],["straordinario","m"],["straordinaria","f"],["instancabile","n"],["sistematico","m"],["sistematica","f"],["determinato","m"],["determinata","f"],["immortale","n"]],"tr":{}},"roman_emperors":{"label":"Roman Emperors","icon":"🏛️","group":"history","tags":["classical","roman"],"reach":"global","locale":"en","type":"people","names":[["Augustus","m"],["Tiberius","m"],["Caligula","m"],["Claudius","m"],["Nero","m"],["Galba","m"],["Otho","m"],["Vitellius","m"],["Vespasian","m"],["Titus","m"],["Domitian","m"],["Nerva","m"],["Trajan","m"],["Hadrian","m"],["Antoninus Pius","m"],["Marcus Aurelius","m"],["Commodus","m"],["Septimius Severus","m"],["Caracalla","m"],["Elagabalus","m"],["Severus Alexander","m"],["Diocletian","m"],["Constantine","m"],["Julian","m"],["Theodosius","m"]],"en":["Ambitious","Ancient","Glorious","Imperial","Legendary","Martial","Merciless","Powerful","Regal","Severe","Strategic","Triumphant","Tyrannical","Valiant","Wise"],"it":[["imperiale","n"],["potente","n"],["ambizioso","m"],["ambiziosa","f"],["spietato","m"],["spietata","f"],["saggio","m"],["saggia","f"],["severo","m"],["severa","f"],["glorioso","m"],["gloriosa","f"],["tirannico","m"],["tirannica","f"],["leggendario","m"],["leggendaria","f"],["regale","n"],["antico","m"],["antica","f"],["marziale","n"],["valoroso","m"],["valorosa","f"],["stratega","n"],["trionfante","n"]],"tr":{"Augustus":"Augusto","Tiberius":"Tiberio","Caligula":"Caligola","Claudius":"Claudio","Nero":"Nerone","Otho":"Otone","Vitellius":"Vitellio","Vespasian":"Vespasiano","Titus":"Tito","Domitian":"Domiziano","Trajan":"Traiano","Hadrian":"Adriano","Antoninus Pius":"Antonino Pio","Marcus Aurelius":"Marco Aurelio","Commodus":"Commodo","Septimius Severus":"Settimio Severo","Elagabalus":"Eliogabalo","Severus Alexander":"Alessandro Severo","Diocletian":"Diocleziano","Constantine":"Costantino","Julian":"Giuliano","Theodosius":"Teodosio"}},"world_explorers":{"label":"World Explorers","icon":"🧭","group":"history","tags":["exploration","adventure"],"reach":"global","locale":"en","type":"people","names":[["Ferdinand Magellan","m"],["Roald Amundsen","m"],["James Cook","m"],["Ernest Shackleton","m"],["Vasco da Gama","m"],["Robert Falcon Scott","m"],["David Livingstone","m"],["Hernan Cortes","m"],["Francisco Pizarro","m"],["Henry Hudson","m"],["Jacques Cartier","m"],["Fridtjof Nansen","m"],["Zheng He","m"],["Ibn Battuta","m"],["Edmund Hillary","m"]],"en":["Adventurous","Audacious","Bold","Brave","Hardy","Indomitable","Intrepid","Legendary","Nomadic","Pioneering","Polar","Remote","Solitary","Tenacious","Tireless","Wandering"],"it":[["audace","n"],["coraggioso","m"],["coraggiosa","f"],["intrepido","m"],["intrepida","f"],["temerario","m"],["temeraria","f"],["avventuroso","m"],["avventurosa","f"],["pionieristico","m"],["pionieristica","f"],["nomade","n"],["errante","n"],["resistente","n"],["indomito","m"],["indomita","f"],["solitario","m"],["solitaria","f"],["leggendario","m"],["leggendaria","f"],["tenace","n"],["polare","n"],["remoto","m"],["remota","f"],["instancabile","n"]],"tr":{"Ferdinand Magellan":"Ferdinando Magellano"}},"egyptian_pharaohs":{"label":"Egyptian Pharaohs","icon":"👑","group":"history","tags":["egyptian","rulers"],"reach":"global","locale":"en","type":"people","names":[["Tutankhamun","m"],["Ramesses","m"],["Cleopatra","f"],["Hatshepsut","f"],["Akhenaten","m"],["Khufu","m"],["Khafre","m"],["Menkaure","m"],["Nefertiti","f"],["Thutmose","m"],["Djoser","m"],["Seti","m"],["Amenhotep","m"],["Narmer","m"],["Sneferu","m"]],"en":["Ancient","Divine","Embalmed","Eternal","Glorious","Golden","Immortal","Majestic","Monumental","Mummified","Mysterious","Pharaonic","Pyramidal","Regal","Sacred","Solemn"],"it":[["divino","m"],["divina","f"],["eterno","m"],["eterna","f"],["dorato","m"],["dorata","f"],["regale","n"],["sacro","m"],["sacra","f"],["immortale","n"],["monumentale","n"],["antico","m"],["antica","f"],["misterioso","m"],["misteriosa","f"],["solenne","n"],["maestoso","m"],["maestosa","f"],["piramidale","n"],["mummificato","m"],["mummificata","f"],["faraonico","m"],["faraonica","f"],["imbalsamato","m"],["imbalsamata","f"],["glorioso","m"],["gloriosa","f"]],"tr":{"Tutankhamun":"Tutankhamon","Ramesses":"Ramesse","Akhenaten":"Akhenaton","Khufu":"Cheope","Khafre":"Chefren","Menkaure":"Micerino","Sneferu":"Snefru"}},"nba_hall_of_fame":{"label":"Nba Hall Of Fame","icon":"🏀","group":"sport","tags":["basketball","usa"],"reach":"global","locale":"en","type":"people","names":[["Michael Jordan","m"],["Kobe Bryant","m"],["Shaquille ONeal","m"],["Magic Johnson","m"],["Larry Bird","m"],["Kareem Abdul Jabbar","m"],["Wilt Chamberlain","m"],["Bill Russell","m"],["Tim Duncan","m"],["Kevin Garnett","m"],["Allen Iverson","m"],["Hakeem Olajuwon","m"],["Charles Barkley","m"],["Scottie Pippen","m"],["Dennis Rodman","m"]],"en":["Acrobatic","Agile","Athletic","Competitive","Dominant","Explosive","Gritty","Historic","Legendary","Muscular","Olympic","Powerful","Precise","Snappy","Swift","Talented","Tireless","Towering","Unbeatable","Winning"],"it":[["Acrobatica","f"],["Acrobatico","m"],["Agile","n"],["Atletica","f"],["Atletico","m"],["Competitiva","f"],["Competitivo","m"],["Dominante","n"],["Esplosiva","f"],["Esplosivo","m"],["Grintosa","f"],["Grintoso","m"],["Imbattibile","n"],["Instancabile","n"],["Leggendaria","f"],["Leggendario","m"],["Muscolosa","f"],["Muscoloso","m"],["Olimpica","f"],["Olimpico","m"],["Potente","n"],["Precisa","f"],["Preciso","m"],["Scattante","n"],["Storica","f"],["Storico","m"],["Talentuosa","f"],["Talentuoso","m"],["Torreggiante","n"],["Veloce","n"],["Vincente","n"]],"tr":{}},"italian_cyclists":{"label":"Italian Cyclists","icon":"🚴","group":"sport","tags":["cycling","italian"],"reach":"global","locale":"it","type":"people","names":[["Fausto Coppi","m"],["Gino Bartali","m"],["Marco Pantani","m"],["Mario Cipollini","m"],["Vincenzo Nibali","m"],["Paolo Bettini","m"],["Felice Gimondi","m"],["Francesco Moser","m"],["Giuseppe Saronni","m"],["Ivan Basso","m"],["Damiano Cunego","m"],["Gilberto Simoni","m"],["Claudio Chiappucci","m"],["Moreno Argentin","m"],["Gianni Bugno","m"],["Franco Bitossi","m"],["Vittorio Adorni","m"],["Costante Girardengo","m"],["Alfredo Binda","m"],["Learco Guerra","m"],["Ottavio Bottecchia","m"],["Giovanni Brunero","m"],["Gastone Nencini","m"],["Ercole Baldini","m"],["Italo Zilioli","m"],["Vito Taccone","m"],["Imerio Massignan","m"],["Antonio Carboni","m"],["Michele Bartoli","m"],["Maurizio Fondriest","m"],["Claudio Corti","m"],["Davide Rebellin","m"],["Stefano Garzelli","m"],["Rinaldo Nocentini","m"],["Enrico Gasparotto","m"],["Diego Ulissi","m"],["Elia Viviani","m"],["Sonny Colbrelli","m"],["Giulio Ciccone","m"],["Filippo Ganna","m"],["Alberto Bettiol","m"],["Gianni Moscon","m"],["Davide Formolo","m"],["Marta Bastianelli","f"],["Elisa Longo Borghini","f"],["Tatiana Guderzo","f"],["Giorgia Bronzini","f"],["Barbara Guarischi","f"],["Alessandra Cappellotto","f"],["Valentina Scandolara","f"]],"en":["Agile","Brave","Champion","Climbing","Determined","Dominant","Explosive","Extraordinary","Formidable","Glorious","Gritty","Hardy","Legendary","Lightning","Powerful","Snappy","Tireless","Unbeatable","Unstoppable"],"it":[["agile","n"],["campione","n"],["coraggiosa","f"],["coraggioso","m"],["determinata","f"],["determinato","m"],["dominante","n"],["esplosiva","f"],["esplosivo","m"],["formidabile","n"],["fulminea","f"],["fulmineo","m"],["gloriosa","f"],["glorioso","m"],["grintosa","f"],["grintoso","m"],["imbattibile","n"],["inarrestabile","n"],["instancabile","n"],["leggendaria","f"],["leggendario","m"],["potente","n"],["resistente","n"],["scalatore","m"],["scalatrice","f"],["scattante","n"],["straordinaria","f"],["straordinario","m"]],"tr":{}},"car_brands":{"label":"Car Brands","icon":"🚘","group":"vehicles","tags":["brands","cars"],"reach":"global","locale":"en","type":"things","names":[["Ferrari","f"],["Lamborghini","f"],["Porsche","f"],["BMW","f"],["Audi","f"],["Tesla","f"],["Ford","f"],["Chevrolet","f"],["Toyota","f"],["Honda","f"],["Nissan","f"],["Jaguar","f"],["Aston Martin","f"],["Maserati","f"],["Bentley","f"],["Bugatti","f"],["McLaren","f"],["Alfa Romeo","f"],["Land Rover","f"],["Volvo","f"],["Subaru","f"],["Mazda","f"],["Fiat","f"],["Peugeot","f"],["Renault","f"],["Kia","f"],["Hyundai","f"],["Chrysler","f"],["Dodge","f"],["Jeep","f"],["Ram","f"],["Buick","f"],["GMC","f"],["Cadillac","f"],["Lincoln","f"],["Infiniti","f"],["Acura","f"],["Lexus","f"],["Genesis","f"],["Mitsubishi","f"],["Suzuki","f"],["Hummer","f"],["Opel","f"],["Seat","f"],["Smart","f"],["Pagani","f"],["Koenigsegg","f"],["Rimac","f"],["Volkswagen","f"]],"en":["Adaptable","Agile","Compact","Confident","Cozy","Ecological","Economical","Electric","Elegant","Famous","Fascinating","Fastest","Futuristic","Handy","Luxurious","Practical","Reliable","Sporty","Strapping","Technological"],"it":[["adattabile","f"],["affascinante","f"],["affidabile","f"],["agile","f"],["compatta","f"],["confortevole","f"],["ecologica","f"],["economica","f"],["elegante","f"],["elettrica","f"],["famosa","f"],["futuristica","f"],["lussuosa","f"],["maneggevole","f"],["pratica","f"],["prestante","f"],["sicura","f"],["sportiva","f"],["tecnologica","f"],["velocissima","f"]],"tr":{}},"bicycle_brands":{"label":"Bicycle Brands","icon":"🚲","group":"vehicles","tags":["cycling","brands"],"reach":"global","locale":"en","type":"things","names":[["Colnago","f"],["Pinarello","f"],["Bianchi","f"],["Cannondale","f"],["Trek","f"],["Specialized","f"],["Wilier","f"],["De Rosa","f"],["Basso","f"],["Giant","f"],["Merida","f"],["Scott","f"],["Orbea","f"],["Canyon","f"],["Ridley","f"]],"en":["Aerodynamic","Agile","Artisanal","Compact","Elegant","Hardy","Light","Prestigious","Professional","Reliable","Robust","Silent","Snappy","Sporty","Swift","Technical"],"it":[["leggero","m"],["leggera","f"],["aerodinamico","m"],["aerodinamica","f"],["scattante","n"],["resistente","n"],["veloce","n"],["agile","n"],["artigianale","n"],["sportivo","m"],["sportiva","f"],["elegante","n"],["tecnico","m"],["tecnica","f"],["professionale","n"],["robusto","m"],["robusta","f"],["silenzioso","m"],["silenziosa","f"],["affidabile","n"],["compatto","m"],["compatta","f"],["prestigioso","m"],["prestigiosa","f"]],"tr":{}},"rock_bands_70s":{"label":"Rock Bands 70s","icon":"🎸","group":"arts","tags":["music","rock","seventies"],"reach":"global","locale":"en","type":"things","names":[["Led Zeppelin","m"],["Pink Floyd","m"],["Queen","m"],["Black Sabbath","m"],["Deep Purple","m"],["Fleetwood Mac","m"],["ACDC","m"],["Aerosmith","m"],["Ramones","m"],["Eagles","m"],["The Clash","m"],["Sex Pistols","m"],["Lynyrd Skynyrd","m"],["Rush","m"],["Kiss","m"]],"en":["Baroque","Dark","Distorted","Epic","Grand","Grandiloquent","Heavy","Majestic","Mammoth","Monumental","Progressive","Sulfurous","Symphonic","Telluric","Theatrical","Thundering","Thunderous","Virtuoso"],"it":[["Barocca","f"],["Barocco","m"],["Distorta","f"],["Distorto","m"],["Epica","f"],["Epico","m"],["Grandiosa","f"],["Grandioso","m"],["Maestosa","f"],["Maestoso","m"],["Magniloquente","n"],["Mastodontica","f"],["Mastodontico","m"],["Monumentale","n"],["Oscura","f"],["Oscuro","m"],["Pesante","n"],["Progressiva","f"],["Progressivo","m"],["Roboante","n"],["Sinfonica","f"],["Sinfonico","m"],["Sulfurea","f"],["Sulfureo","m"],["Teatrale","n"],["Tellurica","f"],["Tellurico","m"],["Tonante","n"],["Virtuosa","f"],["Virtuoso","m"]],"tr":{}},"italian_opera_composers":{"label":"Italian Opera Composers","icon":"🎼","group":"arts","tags":["classical","italian","music"],"reach":"global","locale":"it","type":"people","names":[["Giuseppe Verdi","m"],["Giacomo Puccini","m"],["Gioachino Rossini","m"],["Gaetano Donizetti","m"],["Vincenzo Bellini","m"],["Claudio Monteverdi","m"],["Giovanni Paisiello","m"],["Domenico Cimarosa","m"],["Luigi Cherubini","m"],["Gaspare Spontini","m"],["Saverio Mercadante","m"],["Amilcare Ponchielli","m"],["Ruggero Leoncavallo","m"],["Pietro Mascagni","m"],["Umberto Giordano","m"],["Francesco Cilea","m"],["Riccardo Zandonai","m"],["Arrigo Boito","m"],["Alfredo Catalani","m"],["Giovanni Pacini","m"],["Antonio Salieri","m"],["Alessandro Scarlatti","m"],["Giovanni Battista Pergolesi","m"],["Nicola Porpora","m"],["Francesco Cavalli","m"],["Antonio Vivaldi","m"],["Luigi Dallapiccola","m"],["Gian Carlo Menotti","m"],["Nino Rota","m"],["Franco Alfano","m"],["Italo Montemezzi","m"],["Alberto Franchetti","m"],["Nicola Vaccai","m"],["Stefano Pavesi","m"],["Carlo Coccia","m"],["Filippo Marchetti","m"],["Lauro Rossi","m"],["Giovanni Bottesini","m"],["Antonio Cagnoni","m"],["Enrico Petrella","m"],["Pietro Generali","m"],["Valentino Fioravanti","m"],["Simon Mayr","m"],["Carlo Soliva","m"],["Antonio Smareglia","m"],["Francesca Caccini","f"],["Barbara Strozzi","f"],["Maddalena Casulana","f"]],"en":["Brilliant","Classic","Divine","Dramatic","Elegant","Grand","Harmonious","Immortal","Ingenious","Inspired","Lyric","Majestic","Melodious","Passionate","Refined","Romantic","Sublime","Virtuoso"],"it":[["melodioso","m"],["melodiosa","f"],["armonioso","m"],["armoniosa","f"],["appassionato","m"],["appassionata","f"],["virtuoso","m"],["virtuosa","f"],["brillante","m"],["brillante","f"],["maestoso","m"],["maestosa","f"],["raffinato","m"],["raffinata","f"],["sublime","m"],["sublime","f"],["grandioso","m"],["grandiosa","f"],["lirico","m"],["lirica","f"],["drammatico","m"],["drammatica","f"],["elegante","m"],["elegante","f"],["romantico","m"],["romantica","f"],["ispirato","m"],["ispirata","f"],["immortale","n"],["divino","m"],["divina","f"],["classico","m"],["classica","f"],["geniale","n"]],"tr":{}}}};
/* ── PLAYGROUND ─────────────────────────────────────────────── */
/* The package's own assembly and entropy rules, reimplemented in JS so
   every command below runs against real dictionary data rather than a
   canned transcript. */
const D = DATA.dicts, KEYS = Object.keys(D);
const pick = a => a[Math.floor(Math.random() * a.length)];
const pad = (s, n) => String(s) + ' '.repeat(Math.max(0, n - String(s).length));

/* ══ The package's own rules, reimplemented faithfully ══════════ */

/* Basic leetspeak is the 1.x set kept verbatim, not "the single-character
   entries" — 's' maps to '$' and is in, 'c' maps to '<' and is out. */
const LEET = { a:'4', b:'8', c:'<', e:'3', f:'|=', g:'9', h:'#', i:'1', j:'_|', k:'|<', l:'1',
  m:'|V|', n:'|\\|', o:'0', p:'|D', q:'9', r:'2', s:'$', t:'7', u:'|_|', v:'\\/', w:'\\/\\/', x:'%', y:'`/', z:'2' };
const BASIC = ['a','b','e','g','i','l','o','q','r','s','t','z'];

function leet(text, mode) {
  if (mode === 'none') return text;
  const map = mode === 'basic' ? Object.fromEntries(BASIC.map(k => [k, LEET[k]])) : LEET;
  return [...text].map(c => map[c.toLowerCase()] ?? c).join('');
}

/* Names are stripped to letters, digits and the separator; runs of the
   separator collapse and the edges are trimmed. */
function sanitize(v, sep) {
  if (!sep) return v.replace(/[^\p{L}\p{N}]/gu, '');
  const q = sep.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const keep = new RegExp(`${q}|[\\p{L}\\p{N}]`, 'gu');
  v = (v.match(keep) || []).join('');
  return v.replace(new RegExp(`(?:${q})+`, 'gu'), sep).replace(new RegExp(`^(?:${q})+|(?:${q})+$`, 'gu'), '');
}

const DEFAULTS = { count: 1, locale: null, only: [], except: [], type: [], group: [], tag: [], reach: null,
  separator: '-', digits: 6, position: 'end', leet: 'none', numbers: true, report: false, json: false, list: false };
const REACH = { niche: 1, italian: 2, global: 3 };
const BANDS = ['very_weak', 'weak', 'fair', 'strong', 'very_strong'];

function selected(o) {
  return KEYS.filter(k => {
    const d = D[k];
    if (o.except.includes(k)) return false;
    if (o.only.length) return o.only.includes(k);
    if (o.type.length && !o.type.includes(d.type)) return false;
    if (o.group.length && !o.group.includes(d.group)) return false;
    if (o.tag.length && !o.tag.every(t => d.tags.includes(t))) return false;
    if (o.reach && REACH[d.reach] < REACH[o.reach]) return false;
    return true;
  });
}

function generate(o) {
  const pool = selected(o);
  if (!pool.length) throw new Error('No dictionaries are enabled. Check --only, --except, --group, --tag and --reach.');

  /* Uniform across every enabled name, not across files. */
  const flat = pool.flatMap(k => D[k].names.map(n => [k, n]));
  const [key, [rawName, gender]] = flat[Math.floor(Math.random() * flat.length)];
  const d = D[key], loc = o.locale || 'en';

  /* A dictionary already in this language has nothing to translate. */
  const name = (loc !== d.locale && d.tr && d.tr[rawName]) || rawName;

  /* Italian adjectives agree with the name; English ones are all neutral. */
  const agreeing = loc === 'it' ? d.it.filter(a => a[1] === gender || a[1] === 'n') : null;
  // The package runs the adjective through MB_CASE_TITLE, and the Italian
  // packs store some entries lowercase — without this the demo prints
  // "Trofia-autentica" where the package produces "Trofia-Autentica".
  const rawAdj = loc === 'it' ? pick(agreeing)[0] : pick(d.en);
  const adj = rawAdj.charAt(0).toUpperCase() + rawAdj.slice(1);

  const sep = o.separator;
  const nm = sanitize(name.replace(/ /g, sep || ''), sep);
  const aj = sanitize(adj, sep);

  /* Word order is a property of the language: Italian says Goldrake Mitico,
     English says Legendary Goldrake. */
  const [first, second] = loc === 'it' ? [nm, aj] : [aj, nm];

  let out;
  if (o.numbers && o.digits > 0) {
    const lo = 10 ** (o.digits - 1), hi = 10 ** o.digits;
    const num = String(Math.floor(Math.random() * (hi - lo)) + lo);
    out = ({ start: [num, first, second], middle: [first, num, second], end: [first, second, num] })[o.position].join(sep);
  } else {
    out = first + sep + second;
  }
  return { key, locale: loc, password: leet(out, o.leet) };
}

/* Structural model: the space this package can actually produce. */
function report(o) {
  const pool = selected(o);
  const loc = o.locale || 'en';
  const names = pool.reduce((n, k) => n + D[k].names.length, 0);
  const adj = pool.length ? Math.round(pool.reduce((n, k) => n + (loc === 'it' ? D[k].it.length : D[k].en.length), 0) / pool.length) : 0;
  const digits = o.numbers ? o.digits : 0;
  const c = {
    name: names ? Math.log2(names) : 0,
    adjective: adj ? Math.log2(adj) : 0,
    number: digits > 0 ? Math.log2(9 * 10 ** (digits - 1)) : 0,
    /* A deterministic transform cannot enlarge the space an attacker who read
       the config has to search, so it is worth exactly nothing. */
    leetspeak_bonus: 0,
  };
  c.total = c.name + c.adjective + c.number;
  const band = BANDS[c.total < 28 ? 0 : c.total < 36 ? 1 : c.total < 60 ? 2 : c.total < 128 ? 3 : 4];
  return { c, band, score: BANDS.indexOf(band), names, adj, pool };
}

/* Charset model: length over the alphabet in play, with the repetition
   penalty the package applies. */
function charsetBits(pw) {
  const chars = [...pw];
  const flags = { lower: /\p{Ll}/u.test(pw), upper: /\p{Lu}/u.test(pw), digits: /\d/u.test(pw), symbols: /[^\p{L}\d]/u.test(pw) };
  let set = 0;
  if (flags.lower) set += 26; if (flags.upper) set += 26;
  if (flags.digits) set += 10; if (flags.symbols) set += 32;
  if (!chars.length || !set) return { bits: 0, flags, length: 0 };
  let bits = chars.length * Math.log2(set);
  if (/(.)\1{2,}/u.test(pw)) bits *= 0.6;
  else if (/(?:abc|bcd|cde|def|123|234|345|456|567|678|789|qwe|wer|ert)/iu.test(pw)) bits *= 0.7;
  else if (new Set(chars.map(c => c.toLowerCase())).size / chars.length < 0.4) bits *= 0.7;
  return { bits, flags, length: chars.length };
}

/* Crack time in log space: 2 ** bits overflows well inside the range a long
   password produces, which is why the package never computes it directly. */
function crack(bits, rate) {
  const log10s = bits * Math.log10(2) - Math.log10(rate);
  return log10s > 308 ? Infinity : 10 ** log10s;
}

function human(s) {
  if (!isFinite(s)) return 'eternity';
  if (s < 1) return 'instant';
  const U = [['century', 3155760000], ['year', 31557600], ['day', 86400], ['hour', 3600], ['minute', 60], ['second', 1]];
  for (const [u, span] of U) {
    if (s >= span) {
      const n = s / span;
      if (u === 'century' && n >= 1e6) return `${(n / 1e6).toPrecision(3)}M centuries`;
      const v = n >= 100 ? Math.round(n).toLocaleString('en') : n >= 10 ? Math.round(n) : n.toFixed(1);
      return `${v} ${u}${(n >= 2 || Math.round(n) !== 1) ? (u === 'century' ? 'ies'.replace('ies', '') + 'ies' : 's') : ''}`.replace('centurys', 'centuries');
    }
  }
  return 'instant';
}

function spaceOf(bits) {
  if (bits <= 0) return '0';
  const e = bits * Math.log10(2);
  return e < 15 ? Math.round(10 ** e).toLocaleString('en') : `10^${Math.round(e)}`;
}

/* ══ 1 · TERMINAL ══════════════════════════════════════════════ */
const OUT = $('#pg-out'), IN = $('#pg-in');
const hist = []; let hp = 0;
function say(html, cls) { const p = document.createElement('pre'); if (cls) p.className = cls; p.innerHTML = html; OUT.append(p); }
const flush = () => { OUT.scrollTop = OUT.scrollHeight; };

const HELP = `<span class="t-cmd">password-toolkit:generate</span> <span class="t-dim">[count]</span>
  <span class="t-dim">--report</span>              score, entropy band and offline crack time
  <span class="t-dim">--json</span>                machine-readable output
  <span class="t-dim">--list</span>                show the dictionaries that resolve, and stop
  <span class="t-dim">--locale=</span>en|it        adjective language; also flips word order
  <span class="t-dim">--only=</span>a,b            restrict to these dictionary keys
  <span class="t-dim">--except=</span>a,b          exclude these keys
  <span class="t-dim">--type=</span>people|things  structural filter
  <span class="t-dim">--group=</span>food          thematic group, twelve of them
  <span class="t-dim">--tag=</span>italian,sweet   must carry every tag
  <span class="t-dim">--reach=</span>global        how widely the names are recognised
  <span class="t-dim">--separator=</span>_         any string, or empty
  <span class="t-dim">--digits=</span>6            length of the numeric segment
  <span class="t-dim">--position=</span>start      start | middle | end
  <span class="t-dim">--leet=</span>basic          none | basic | advanced
  <span class="t-dim">--no-numbers</span>          drop the numeric segment

<span class="t-cmd">password-toolkit:make-dictionary</span> <span class="t-dim">&lt;key&gt; [--type=] [--locale=] [--path=]</span>
  scaffold a dictionary of your own

<span class="t-cmd">help</span> · <span class="t-cmd">clear</span> · <span class="t-cmd">groups</span>

<span class="t-dim">Naming a dictionary with --only beats every other filter.</span>`;

function parse(argv) {
  const o = structuredClone(DEFAULTS), bad = [];
  for (const a of argv) {
    if (/^\d+$/.test(a)) { o.count = Math.min(50, Math.max(1, +a)); continue; }
    if (!a.startsWith('--')) { bad.push(a); continue; }
    const i = a.indexOf('='), flag = i < 0 ? a.slice(2) : a.slice(2, i), raw = i < 0 ? '' : a.slice(i + 1);
    const list = raw.split(',').map(s => s.trim()).filter(Boolean);
    switch (flag) {
      case 'report': o.report = true; break;
      case 'json': o.json = true; break;
      case 'list': o.list = true; break;
      case 'no-numbers': o.numbers = false; break;
      case 'only': o.only.push(...list); break;
      case 'except': o.except.push(...list); break;
      case 'type': o.type.push(...list); break;
      case 'group': o.group.push(...list); break;
      case 'tag': o.tag.push(...list); break;
      case 'reach': o.reach = raw; break;
      case 'locale': o.locale = raw; break;
      case 'separator': o.separator = raw; break;
      case 'digits': o.digits = Math.min(12, Math.max(1, +raw || 4)); break;
      case 'position': o.position = raw; break;
      case 'leet': o.leet = raw === 'no' ? 'none' : raw; break;
      default: bad.push(a);
    }
  }
  if (bad.length) throw new Error(`Unknown option ${bad[0]}. Run help for the full list.`);
  if (!['start', 'middle', 'end'].includes(o.position)) throw new Error(`Unknown numbers position [${o.position}]. Expected start, middle or end.`);
  if (!['none', 'basic', 'advanced'].includes(o.leet)) throw new Error(`Unknown leetspeak mode [${o.leet}]. Expected none, basic or advanced.`);
  if (o.reach && !REACH[o.reach]) throw new Error(`Unknown reach [${o.reach}]. Expected global, italian or niche.`);
  if (o.locale && !/^[A-Za-z0-9]+(?:[_-][A-Za-z0-9]+)*$/.test(o.locale)) throw new Error(`[${o.locale}] is not a valid locale.`);
  for (const k of [...o.only, ...o.except]) if (!/^[A-Za-z0-9_][A-Za-z0-9_-]*$/.test(k)) throw new Error(`[${k}] is not a valid dictionary key.`);
  for (const g of o.group) if (!DATA.meta.groups[g]) throw new Error(`Unknown dictionary group [${g}]. Run groups for the list.`);
  return o;
}

function runList(o) {
  const pool = selected(o);
  if (!pool.length) { say('  No dictionaries resolve with the current selection.', 't-warn'); return; }
  if (o.json) { say(esc(JSON.stringify(pool.map(k => ({ key: k, label: D[k].label, group: D[k].group, reach: D[k].reach, locale: D[k].locale, count: D[k].names.length, tags: D[k].tags })), null, 2)), 't-dim'); return; }
  say(`  <span class="t-dim">${pad('KEY', 26)}${pad('LABEL', 27)}${pad('GROUP', 10)}${pad('REACH', 9)}${pad('LANG', 6)}${pad('N', 4)}TAGS</span>`);
  pool.forEach(k => { const d = D[k];
    say(`  ${d.icon} ${pad(k, 24)}${pad(d.label, 27)}${pad(d.group, 10)}${pad(d.reach, 9)}${pad(d.locale, 6)}${pad(d.names.length, 4)}<span class="t-dim">${d.tags.join(', ')}</span>`); });
  const r = report(o);
  say(`\n  <span class="t-dim">${pool.length} dictionaries, ${r.names} names, ~${r.adj} adjectives each.</span>`);
  say(`  <span class="t-dim">This demo carries ${DATA.meta.sample} of the ${DATA.meta.dictionaries} the package ships.</span>`);
}

function runGenerate(o) {
  if (o.list) return runList(o);
  const rows = [];
  for (let i = 0; i < o.count; i++) rows.push(generate(o));
  const r = report(o);
  if (o.json) {
    say(esc(JSON.stringify(rows.map(x => o.report
      ? { password: x.password, report: { entropy_bits: +r.c.total.toFixed(2), label: r.band, score: r.score, crack_time_human: human(crack(r.c.total, 1e10)) } }
      : x.password), null, 2)), 't-dim');
    return;
  }
  if (!o.report) { rows.forEach(x => say('  ' + esc(x.password))); return; }
  const w = Math.max(...rows.map(x => x.password.length), 8) + 2;
  say(`  <span class="t-dim">${pad('PASSWORD', w)}${pad('SCORE', 7)}${pad('BAND', 13)}${pad('ENTROPY', 12)}CRACK TIME</span>`);
  rows.forEach(x => say(`  ${pad(esc(x.password), w)}${pad(r.score, 7)}${pad(r.band, 13)}${pad(r.c.total.toFixed(1) + ' bits', 12)}${human(crack(r.c.total, 1e10))}`));
  say(`\n  <span class="t-dim">name ${r.c.name.toFixed(1)} + adjective ${r.c.adjective.toFixed(1)} + digits ${r.c.number.toFixed(1)} + leetspeak ${r.c.leetspeak_bonus.toFixed(1)} = ${r.c.total.toFixed(1)} bits over ${r.pool.length} dictionaries</span>`);
  if (o.leet !== 'none') say('  leetspeak is a deterministic transform and is credited zero bits.', 't-warn');
}

function runMake(argv) {
  const key = argv.find(a => !a.startsWith('--'));
  if (!key) return say('  Not enough arguments (missing: "key").', 't-warn');
  if (!/^[a-z0-9_]+$/.test(key)) return say(`  [${esc(key)}] is not a valid key. Use lowercase letters, digits and underscores.`, 't-warn');
  const type = (argv.find(a => a.startsWith('--type=')) || '--type=things').split('=')[1];
  if (!['people', 'things'].includes(type)) return say(`  [${esc(type)}] is not a valid type. Use people or things.`, 't-warn');
  const loc = (argv.find(a => a.startsWith('--locale=')) || '').split('=')[1];
  const path = (argv.find(a => a.startsWith('--path=')) || '').split('=')[1] || 'resources/password-dictionaries';
  say(`  INFO  Dictionary scaffolded at ${esc(path)}/${esc(key)}.json`);
  if (loc) say(`  INFO  Adjectives scaffolded at ${esc(path)}/${esc(loc)}/${esc(key)}.json`);
  say(`\n{\n    "key": "${esc(key)}",\n    "type": "${esc(type)}",\n    "values": [\n        { "name": "Example One", "gender": "neutral" },\n        { "name": "Example Two", "gender": "neutral" }\n    ]\n}`, 't-dim');
  say(`\n  ⇂ Add the directory to password-toolkit.dictionaries.paths\n  ⇂ Check it resolves: password-toolkit:generate --list\n  ⇂ Use it alone: password-toolkit:generate --only=${esc(key)}`, 't-dim');
}

function exec(line) {
  say(`<span class="t-dim">$</span> <span class="t-cmd">${esc(line)}</span>`);
  const argv = line.trim().replace(/^php\s+/, '').replace(/^artisan\s+/, '').split(/\s+/).filter(Boolean);
  const cmd = argv.shift();
  try {
    if (!cmd) return;
    if (cmd === 'clear') { OUT.innerHTML = ''; return; }
    if (cmd === 'help' || cmd === '--help') return say(HELP);
    if (cmd === 'groups') {
      Object.entries(DATA.meta.groups).forEach(([g, label]) => {
        const n = KEYS.filter(k => D[k].group === g).length;
        say(`  ${pad(g, 12)}<span class="t-dim">${pad(label, 12)}${n} in this demo</span>`);
      });
      return;
    }
    if (cmd === 'password-toolkit:make-dictionary') return runMake(argv);
    // A joke answer beats "not defined" for a command somebody typed by habit.
    const bare = line.trim().toLowerCase();
    if (bare.startsWith('rm ')) return say('  ' + RM_RF, 't-dim');
    if (EGGS[cmd]) return say('  ' + EGGS[cmd], 't-dim');
    if (cmd !== 'password-toolkit:generate') return say(`  Command "${esc(cmd)}" is not defined. Try help.`, 't-warn');
    runGenerate(parse(argv));
  } catch (e) {
    say('  ' + esc(e.message), 't-warn');
  } finally { flush(); }
}

const CHIPS = ['password-toolkit:generate 5', 'password-toolkit:generate 3 --report',
  'password-toolkit:generate --locale=it', 'password-toolkit:generate --group=food --reach=global',
  'password-toolkit:generate --only=star_wars,dune --digits=6', 'password-toolkit:generate --leet=advanced --separator=_',
  'password-toolkit:generate --position=start --no-numbers', 'password-toolkit:generate --tag=italian --type=things',
  'password-toolkit:generate 2 --report --json', 'password-toolkit:generate --list --group=screen',
  'password-toolkit:make-dictionary my_team --type=people --locale=it', 'groups', 'help'];
CHIPS.forEach(c => { const b = document.createElement('button'); b.textContent = c.replace('password-toolkit:', ''); b.title = c;
  b.onclick = () => { IN.value = c; submit(); }; $('#pg-chips').append(b); });

$('#pg-clear').onclick = () => { exec('clear'); IN.focus(); };

function submit() {
  const line = IN.value.trim(); if (!line) return;
  hist.push(line); hp = hist.length; exec(line); IN.value = ''; IN.focus();
}
IN.addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); submit(); }
  else if (e.key === 'ArrowUp') { e.preventDefault(); if (hp > 0) IN.value = hist[--hp]; }
  else if (e.key === 'ArrowDown') { e.preventDefault(); hp = Math.min(hist.length, hp + 1); IN.value = hist[hp] ?? ''; }
});
$('#pg-meta').textContent = `${DATA.meta.sample} of ${DATA.meta.dictionaries} dictionaries`;


/* Commands a developer types out of muscle memory. None of them do anything
   here, so the terminal may as well be honest about that with some grace. */
const EGGS = {
  exit: 'There is no shell to leave. Close the tab, or keep going — nobody is timing you.',
  quit: 'Quitters never generate memorable passwords.',
  logout: 'You were never logged in. This is a page.',
  ':q': 'Not vim. Nothing here will hold you hostage.',
  ':q!': 'Not vim, and nothing is unsaved. You are free.',
  ':wq': 'Nothing to write. Nothing to quit. A rare double no-op.',
  vim: 'No editor here. Try <span class="t-cmd">help</span>, which at least tells you how to leave.',
  vi: 'No editor here. Try <span class="t-cmd">help</span>, which at least tells you how to leave.',
  nano: 'Nano would have been the sensible choice, yes.',
  emacs: 'A fine operating system. Sadly this is a text box.',
  sudo: 'You already have every permission this page can grant. Which is none.',
  ls: 'One terminal. One package. Two languages. Nothing else in this directory.',
  cd: 'There is nowhere to go. Everything is on this page.',
  pwd: '<span class="t-dim">~/app — you are inside a documentation site pretending to be a shell</span>',
  whoami: 'Someone about to email a temporary password to a real person. Choose your digits accordingly.',
  clearall: 'Try <span class="t-cmd">clear</span>.',
  man: 'No man pages. <span class="t-cmd">help</span> is shorter and was written this decade.',
  git: 'The repository is on GitHub, and it would rather you starred it than cloned it blind.',
  npm: 'Wrong ecosystem, friend. This one uses <span class="t-cmd">composer</span>.',
  yarn: 'Wrong ecosystem, friend. This one uses <span class="t-cmd">composer</span>.',
  composer: 'Real install: <span class="t-cmd">composer require gabrielesbaiz/password-toolkit</span>. Not from here, though.',
  php: 'There is no PHP runtime in your browser. The maths below is JavaScript wearing a convincing hat.',
  artisan: 'Close. The command is <span class="t-cmd">password-toolkit:generate</span> — no <span class="t-dim">php artisan</span> prefix needed here.',
  top: 'One process. It is idle. It is waiting for you to type a real command.',
  htop: 'One process, still idle, now in colour.',
  date: 'Later than you think. Your temporary passwords should have expired by now.',
  coffee: 'Out of scope, and frankly out of stock.',
  hello: 'Hello. Try <span class="t-cmd">password-toolkit:generate 5 --report</span>.',
  ping: '<span class="t-dim">pong — 0.0ms, because nothing left this page</span>',
};

const RM_RF = 'Nothing here is yours to delete, which is the only reason that command is funny.';

const SHELF = [["a_clockwork_orange","A Clockwork Orange","🍊","screen","p","g",["cult","film","seventies"],13,["Alex DeLarge","Georgie","Dim"],"en"],["alien","Alien","👽","screen","p","g",["eighties","film","sciencefiction"],15,["Ellen Ripley","Dallas","Kane"],"en"],["apocalypse_now","Apocalypse Now","🚁","screen","p","g",["film","seventies","war"],10,["Benjamin Willard","Walter Kurtz","Bill Kilgore"],"en"],["arthurian_legend","Arthurian Legend","⚔️","myth","p","g",["arthurian","medieval"],15,["Arthur","Lancelot","Guinevere"],"en"],["back_to_the_future","Back To The Future","🚗","screen","p","g",["eighties","film"],17,["Marty McFly","Doc Brown","Jennifer Parker"],"en"],["barbie","Barbie","💗","screen","p","g",["comedy","film","twentytwenties"],15,["Barbie","Ken","Gloria"],"en"],["blade_runner","Blade Runner","🌧️","screen","p","g",["eighties","film","sciencefiction"],10,["Rick Deckard","Roy Batty","Rachael"],"en"],["cartoons","Cartoons","📺","screen","p","g",["animation","childhood"],146,["Mickey Mouse","Donald Duck","Minnie"],"en"],["celtic_mythology","Celtic Mythology","🍀","myth","p","g",["celtic","gods"],15,["Morrigan","Brigid","Lugh"],"en"],["deadpool","Deadpool","🗡️","screen","p","g",["comics","film","twentytens"],10,["Wade Wilson","Vanessa Carlysle","Ajax"],"en"],["die_hard","Die Hard","🏢","screen","p","g",["action","eighties","film"],12,["John McClane","Hans Gruber","Karl Vreski"],"en"],["disney_characters","Disney Characters","🏰","screen","p","g",["animation","disney"],60,["Mickey Mouse","Donald Duck","Goofy"],"en"],["disney_villains","Disney Villains","😈","screen","p","g",["animation","disney","villains"],20,["Maleficent","Cruella De Vil","Jafar"],"en"],["django_unchained","Django Unchained","🤠","screen","p","g",["film","twentytens","western"],10,["Django Freeman","King Schultz","Calvin Candie"],"en"],["dune","Dune","🪱","screen","p","g",["film","sciencefiction","twentytwenties"],15,["Paul Atreides","Lady Jessica","Leto Atreides"],"en"],["egyptian_mythology","Egyptian Mythology","🐈","myth","p","g",["egyptian","gods"],15,["Ra","Osiris","Isis"],"en"],["egyptian_pharaohs","Egyptian Pharaohs","👑","history","p","g",["egyptian","rulers"],15,["Tutankhamun","Ramesses","Cleopatra"],"en"],["encanto","Encanto","🕯️","screen","p","g",["animation","film","twentytwenties"],14,["Mirabel Madrigal","Abuela Alma","Bruno Madrigal"],"en"],["everything_everywhere","Everything Everywhere","🥯","screen","p","g",["cult","film","twentytwenties"],12,["Evelyn Wang","Waymond Wang","Joy Wang"],"en"],["game_of_thrones","Game Of Thrones","🐉","screen","p","g",["fantasy","television"],54,["Jon Snow","Daenerys Targaryen","Tyrion Lannister"],"en"],["ghostbusters","Ghostbusters","👻","screen","p","g",["comedy","eighties","film"],13,["Peter Venkman","Ray Stantz","Egon Spengler"],"en"],["grease","Grease","🕺","screen","p","g",["film","musical","seventies"],10,["Danny Zuko","Sandy Olsson","Kenickie"],"en"],["greek_mythology","Greek Mythology","🏺","myth","p","g",["classical","greek"],68,["Zeus","Poseidon","Hades"],"en"],["guardians_of_the_galaxy","Guardians Of The Galaxy","🌌","screen","p","g",["comics","film","space","twentytens"],13,["Peter Quill","Gamora","Drax the Destroyer"],"en"],["harry_potter","Harry Potter","🧙","screen","p","g",["books","fantasy"],71,["Harry Potter","Hermione Granger","Ron Weasley"],"en"],["hayao_miyazaki","Hayao Miyazaki","🌸","screen","p","g",["animation","anime"],20,["Totoro","Satsuki","Mei"],"en"],["home_alone","Home Alone","🏠","screen","p","g",["comedy","film","nineties"],14,["Kevin McCallister","Harry","Marv"],"en"],["inception","Inception","🌀","screen","p","g",["film","sciencefiction","twentytens"],10,["Dom Cobb","Arthur","Ariadne"],"en"],["interstellar","Interstellar","🪐","screen","p","g",["film","space","twentytens"],12,["Joseph Cooper","Murph Cooper","Amelia Brand"],"en"],["italian_actors","Italian Actors","🎭","screen","p","i",["cinema","italian"],69,["Roberto Benigni","Sophia Loren","Marcello Mastroianni"],"it"],["italian_architects","Italian Architects","📐","arts","p","g",["architecture","italian"],43,["Renzo Piano","Carlo Scarpa","Aldo Rossi"],"it"],["italian_basketball_legends","Italian Basketball Legends","🏀","sport","p","i",["basketball","italian"],71,["Dino Meneghin","Sandro Gamba","Cesare Rubini"],"it"],["italian_chefs","Italian Chefs","👨‍🍳","food","p","i",["cuisine","italian"],44,["Gualtiero Marchesi","Massimo Bottura","Carlo Cracco"],"it"],["italian_comedians","Italian Comedians","😂","screen","p","i",["comedy","italian"],40,["Roberto Benigni","Paolo Villaggio","Alessandro Siani"],"it"],["italian_cyclists","Italian Cyclists","🚴","sport","p","g",["cycling","italian"],50,["Fausto Coppi","Gino Bartali","Marco Pantani"],"it"],["italian_dj_producers","Italian Dj Producers","🎧","arts","p","n",["electronic","italian","music"],20,["Benny Benassi","Gabry Ponte","Joe T Vannelli"],"it"],["italian_explorers","Italian Explorers","🧭","history","p","g",["exploration","italian"],42,["Cristoforo Colombo","Amerigo Vespucci","Giovanni Caboto"],"it"],["italian_fashion_designers","Italian Fashion Designers","👗","arts","p","g",["fashion","italian"],15,["Giorgio Armani","Gianni Versace","Donatella Versace"],"it"],["italian_film_directors","Italian Film Directors","🎬","arts","p","g",["cinema","italian"],56,["Federico Fellini","Luchino Visconti","Michelangelo Antonioni"],"it"],["italian_football_legends","Italian Football Legends","⚽","sport","p","g",["football","italian"],15,["Giuseppe Meazza","Valentino Mazzola","Gianni Rivera"],"it"],["italian_inventors","Italian Inventors","💡","science","p","g",["invention","italian"],20,["Antonio Meucci","Guglielmo Marconi","Alessandro Volta"],"it"],["italian_journalists","Italian Journalists","📰","screen","p","n",["italian","media"],20,["Indro Montanelli","Enzo Biagi","Oriana Fallaci"],"it"],["italian_mathematicians","Italian Mathematicians","📐","science","p","g",["italian","mathematics"],20,["Leonardo Fibonacci","Gerolamo Cardano","Giuseppe Lagrange"],"it"],["italian_motogp_legends","Italian Motogp Legends","🏍️","sport","p","g",["italian","motorcycling"],20,["Valentino Rossi","Giacomo Agostini","Loris Capirossi"],"it"],["italian_musicians","Italian Musicians","🎵","arts","p","i",["italian","music"],56,["Lucio Battisti","Pino Daniele","Vasco Rossi"],"it"],["italian_nobel_prize_winners","Italian Nobel Prize Winners","🏅","science","p","i",["awards","italian"],14,["Guglielmo Marconi","Enrico Fermi","Renato Dulbecco"],"it"],["italian_olympic_legends","Italian Olympic Legends","🥇","sport","p","i",["italian","olympics"],20,["Alberto Tomba","Federica Pellegrini","Gianmarco Tamberi"],"it"],["italian_opera_composers","Italian Opera Composers","🎼","arts","p","g",["classical","italian","music"],48,["Giuseppe Verdi","Giacomo Puccini","Gioachino Rossini"],"it"],["italian_painters","Italian Painters","🖼️","arts","p","g",["italian","painting"],50,["Amedeo Modigliani","Giorgio de Chirico","Giorgio Morandi"],"it"],["italian_poets","Italian Poets","✒️","arts","p","g",["italian","literature"],47,["Dante Alighieri","Francesco Petrarca","Ludovico Ariosto"],"it"],["italian_presidents_of_the_republic","Italian Presidents Of The Republic","🇮🇹","history","p","i",["italian","politics"],11,["Enrico De Nicola","Giovanni Gronchi","Antonio Segni"],"it"],["italian_racing_drivers","Italian Racing Drivers","🏁","sport","p","g",["italian","motorsport"],15,["Tazio Nuvolari","Alberto Ascari","Giuseppe Farina"],"it"],["italian_rappers","Italian Rappers","🎤","arts","p","n",["italian","modern","music"],20,["Fabri Fibra","Marracash","Salmo"],"it"],["italian_renaissance_artists","Italian Renaissance Artists","🎨","arts","p","g",["italian","renaissance"],64,["Leonardo da Vinci","Michelangelo Buonarroti","Raffaello Sanzio"],"it"],["italian_scientists","Italian Scientists","🔬","science","p","g",["italian"],68,["Galileo Galilei","Alessandro Volta","Guglielmo Marconi"],"it"],["italian_singers_classic","Italian Singers Classic","🎙️","arts","p","i",["italian","music"],20,["Mina","Lucio Battisti","Adriano Celentano"],"it"],["italian_singers_modern","Italian Singers Modern","🎙️","arts","p","i",["italian","modern","music"],20,["Marco Mengoni","Tiziano Ferro","Laura Pausini"],"it"],["italian_superheroes","Italian Superheroes","🦸","screen","p","i",["comics","italian"],35,["Diabolik","Tex Willer","Dylan Dog"],"it"],["italian_television_personalities","Italian Television Personalities","📺","screen","p","i",["italian","television"],35,["Maria De Filippi","Gerry Scotti","Teo Mammucari"],"it"],["italian_tennis_players","Italian Tennis Players","🎾","sport","p","g",["italian","tennis"],18,["Jannik Sinner","Matteo Berrettini","Flavia Pennetta"],"it"],["italian_voice_actors","Italian Voice Actors","🎙️","screen","p","n",["dubbing","italian"],20,["Ferruccio Amendola","Luca Ward","Pino Insegno"],"it"],["italian_volleyball_legends","Italian Volleyball Legends","🏐","sport","p","i",["italian","volleyball"],20,["Ivan Zaytsev","Paola Egonu","Lorenzo Bernardi"],"it"],["italian_writers","Italian Writers","📖","arts","p","i",["italian","literature"],66,["Dante Alighieri","Giovanni Boccaccio","Francesco Petrarca"],"it"],["italian_youtubers","Italian Youtubers","▶️","screen","p","n",["internet","italian","modern"],15,["Favij","Frank Matano","Fabio Rovazzi"],"it"],["james_bond","James Bond","🕴️","screen","p","g",["film","franchise","spy"],14,["James Bond","M","Q"],"en"],["japanese_mythology","Japanese Mythology","⛩️","myth","p","g",["japanese","gods"],15,["Amaterasu","Susanoo","Tsukuyomi"],"en"],["jaws","Jaws","🦈","screen","p","g",["film","sea","seventies"],12,["Martin Brody","Quint","Matt Hooper"],"en"],["john_wick","John Wick","🐕","screen","p","g",["action","film","twentytens"],15,["John Wick","Helen Wick","Viggo Tarasov"],"en"],["jurassic_park","Jurassic Park","🦖","screen","p","g",["dinosaurs","film","nineties"],15,["Alan Grant","Ellie Sattler","Ian Malcolm"],"en"],["knives_out","Knives Out","🔪","screen","p","g",["film","mystery","twentytwenties"],15,["Benoit Blanc","Marta Cabrera","Harlan Thrombey"],"en"],["lupin_iii_characters","Lupin Iii Characters","🕵️","screen","p","i",["anime","seventies"],18,["Lupin","Jigen","Goemon"],"en"],["mad_max","Mad Max","🏜️","screen","p","g",["film","postapocalyptic","seventies"],15,["Max Rockatansky","Jessie Rockatansky","Toecutter"],"en"],["men_in_black","Men In Black","🕶️","screen","p","g",["film","nineties","sciencefiction"],13,["Agent K","Agent J","Laurel Weaver"],"en"],["monty_python_holy_grail","Monty Python Holy Grail","🥥","screen","p","g",["comedy","film","seventies"],13,["Arthur","Lancelot","Galahad"],"en"],["nba_hall_of_fame","Nba Hall Of Fame","🏀","sport","p","g",["basketball","usa"],15,["Michael Jordan","Kobe Bryant","Shaquille ONeal"],"en"],["norse_mythology","Norse Mythology","🔨","myth","p","g",["gods","norse"],15,["Odin","Thor","Loki"],"en"],["oppenheimer","Oppenheimer","⚛️","screen","p","g",["film","history","twentytwenties"],15,["Robert Oppenheimer","Kitty Oppenheimer","Leslie Groves"],"en"],["philosophers","Philosophers","🤔","science","p","g",["classical","thought"],35,["Socrates","Plato","Aristotle"],"en"],["pixar_characters","Pixar Characters","💡","screen","p","g",["animation","pixar"],45,["Woody","Buzz Lightyear","Rex"],"en"],["poor_things","Poor Things","🧠","screen","p","g",["cult","film","twentytwenties"],11,["Bella Baxter","Godwin Baxter","Duncan Wedderburn"],"en"],["pulp_fiction","Pulp Fiction","🍔","screen","p","g",["crime","film","nineties"],15,["Vincent Vega","Jules Winnfield","Mia Wallace"],"en"],["rocky","Rocky","🥊","screen","p","g",["film","seventies","sport"],10,["Rocky Balboa","Adrian Pennino","Paulie Pennino"],"en"],["roman_emperors","Roman Emperors","🏛️","history","p","g",["classical","roman"],25,["Augustus","Tiberius","Caligula"],"en"],["roman_mythology","Roman Mythology","⚡","myth","p","g",["classical","roman"],30,["Jupiter","Juno","Mars"],"en"],["saturday_night_fever","Saturday Night Fever","🪩","screen","p","g",["film","musical","seventies"],12,["Tony Manero","Stephanie Mangano","Bobby C"],"en"],["spider_verse","Spider Verse","🕸️","screen","p","g",["animation","comics","film","twentytwenties"],15,["Miles Morales","Gwen Stacy","Peter Parker"],"en"],["star_wars","Star Wars","🚀","screen","p","g",["film","science-fiction"],15,["Luke Skywalker","Leia Organa","Han Solo"],"en"],["superman","Superman","🦸","screen","p","g",["comics","film","seventies"],15,["Superman","Clark Kent","Lois Lane"],"en"],["terminator","Terminator","🤖","screen","p","g",["film","nineties","sciencefiction"],15,["The Terminator","Sarah Connor","Kyle Reese"],"en"],["the_avengers","The Avengers","🛡️","screen","p","g",["comics","film","twentytens"],11,["Tony Stark","Steve Rogers","Bruce Banner"],"en"],["the_big_lebowski","The Big Lebowski","🎳","screen","p","g",["comedy","film","nineties"],14,["The Dude","Walter Sobchak","Maude Lebowski"],"en"],["the_fifth_element","The Fifth Element","🚕","screen","p","g",["film","nineties","sciencefiction"],14,["Korben Dallas","Leeloo","Vito Cornelius"],"en"],["the_godfather","The Godfather","🎩","screen","p","g",["crime","film","seventies"],15,["Vito Corleone","Michael Corleone","Sonny Corleone"],"en"],["the_goonies","The Goonies","🗺️","screen","p","g",["adventure","eighties","film"],11,["Mikey","Brand","Chunk"],"en"],["the_grand_budapest_hotel","The Grand Budapest Hotel","🛎️","screen","p","g",["comedy","film","twentytens"],13,["Monsieur Gustave","Zero Moustafa","Agatha"],"en"],["the_hunger_games","The Hunger Games","🏹","screen","p","g",["dystopia","film","twentytens"],15,["Katniss Everdeen","Peeta Mellark","Gale Hawthorne"],"en"],["the_martian","The Martian","🥔","screen","p","g",["film","space","twentytens"],11,["Mark Watney","Melissa Lewis","Vincent Kapoor"],"en"],["the_matrix","The Matrix","💊","screen","p","g",["film","nineties","sciencefiction"],14,["Neo","Morpheus","Trinity"],"en"],["the_silence_of_the_lambs","The Silence Of The Lambs","🦋","screen","p","g",["film","nineties","thriller"],14,["Clarice Starling","Hannibal Lecter","Jack Crawford"],"en"],["top_gun","Top Gun","✈️","screen","p","g",["aviation","eighties","film"],15,["Maverick","Goose","Iceman"],"en"],["trainspotting","Trainspotting","💉","screen","p","g",["cult","film","nineties"],12,["Mark Renton","Spud","Sick Boy"],"en"],["wicked","Wicked","💚","screen","p","g",["film","musical","twentytwenties"],14,["Elphaba Thropp","Glinda Upland","Fiyero Tigelaar"],"en"],["world_explorers","World Explorers","🧭","history","p","g",["exploration","adventure"],15,["Ferdinand Magellan","Roald Amundsen","James Cook"],"en"],["bicycle_brands","Bicycle Brands","🚲","vehicles","t","g",["cycling","brands"],15,["Colnago","Pinarello","Bianchi"],"en"],["car_brands","Car Brands","🚘","vehicles","t","g",["brands","cars"],49,["Ferrari","Lamborghini","Porsche"],"en"],["chemical_elements","Chemical Elements","⚗️","science","t","g",["chemistry","periodic-table"],15,["Hydrogen","Helium","Carbon"],"en"],["cocktails","Cocktails","🍸","drink","t","g",["bar","mixology"],15,["Negroni","Bellini","Margarita"],"en"],["constellations","Constellations","✨","nature","t","g",["astronomy","sky"],15,["Orion","Cassiopeia","Pegasus"],"en"],["dinosaurs","Dinosaurs","🦖","science","t","g",["paleontology","prehistory"],15,["Tyrannosaurus","Triceratops","Velociraptor"],"en"],["electronic_acts_2000s","Electronic Acts 2000s","🎛️","arts","t","g",["electronic","music","twothousands"],15,["Daft Punk","Justice","LCD Soundsystem"],"en"],["electronic_acts_2010s","Electronic Acts 2010s","🎛️","arts","t","g",["electronic","music","twentytens"],15,["Disclosure","Swedish House Mafia","Major Lazer"],"en"],["electronic_acts_2020s","Electronic Acts 2020s","🎛️","arts","t","g",["electronic","music","twentytwenties"],13,["Overmono","Two Shell","Anotr"],"en"],["electronic_acts_70s","Electronic Acts 70s","🎛️","arts","t","g",["electronic","music","seventies"],14,["Kraftwerk","Tangerine Dream","Can"],"en"],["electronic_acts_80s","Electronic Acts 80s","🎛️","arts","t","g",["eighties","electronic","music"],15,["Depeche Mode","New Order","Pet Shop Boys"],"en"],["electronic_acts_90s","Electronic Acts 90s","🎛️","arts","t","g",["electronic","music","nineties"],15,["The Prodigy","The Chemical Brothers","Underworld"],"en"],["football_clubs","Football Clubs","⚽","sport","t","g",["clubs","football"],15,["Real Madrid","Barcelona","Atletico Madrid"],"en"],["gemstones","Gemstones","💎","nature","t","g",["minerals","jewels"],15,["Sapphire","Amber","Jade"],"en"],["greek_letters","Greek Letters","🔤","science","t","g",["greek","notation"],24,["Alpha","Beta","Gamma"],"en"],["hip_hop_groups_2000s","Hip Hop Groups 2000s","🎤","arts","t","g",["hiphop","music","twothousands"],15,["Outkast","Black Eyed Peas","D12"],"en"],["hip_hop_groups_2010s","Hip Hop Groups 2010s","🎤","arts","t","g",["hiphop","music","twentytens"],15,["Migos","Run The Jewels","Rae Sremmurd"],"en"],["hip_hop_groups_2020s","Hip Hop Groups 2020s","🎤","arts","t","g",["hiphop","music","twentytwenties"],10,["Griselda","Paris Texas","Armand Hammer"],"en"],["hip_hop_groups_80s","Hip Hop Groups 80s","🎤","arts","t","g",["eighties","hiphop","music"],15,["Run DMC","NWA","Public Enemy"],"en"],["hip_hop_groups_90s","Hip Hop Groups 90s","🎤","arts","t","g",["hiphop","music","nineties"],15,["Wu Tang Clan","Cypress Hill","Mobb Deep"],"en"],["italian_aperitivi","Italian Aperitivi","🍹","drink","t","g",["cocktails","italian"],18,["Spritz","Negroni","Americano"],"it"],["italian_breads","Italian Breads","🥖","food","t","g",["cuisine","italian"],20,["Ciabatta","Coppia Ferrarese","Pane Carasau"],"it"],["italian_card_games","Italian Card Games","🃏","culture","t","i",["games","italian"],18,["Scopa","Briscola","Tressette"],"it"],["italian_carnival_masks","Italian Carnival Masks","🎭","culture","t","i",["italian","tradition"],20,["Arlecchino","Pulcinella","Balanzone"],"it"],["italian_cars","Italian Cars","🚗","vehicles","t","i",["cars","italian"],20,["Cinquecento","Panda","Stelvio"],"it"],["italian_castles","Italian Castles","🏯","places","t","i",["architecture","history","italian"],20,["Castel del Monte","Castello Sforzesco","Castello Estense"],"it"],["italian_cheeses","Italian Cheeses","🧀","food","t","g",["cuisine","italian"],24,["Parmigiano Reggiano","Mozzarella","Gorgonzola"],"it"],["italian_children_games_2000s","Italian Children Games 2000s","💾","culture","t","i",["childhood","italian","twothousands"],28,["Beyblade","Yu Gi Oh","Bratz"],"it"],["italian_children_games_70s","Italian Children Games 70s","🧸","culture","t","n",["childhood","italian","seventies"],27,["Subbuteo","Allegro Chirurgo","Risiko"],"it"],["italian_children_games_80s","Italian Children Games 80s","🕹️","culture","t","i",["childhood","eighties","italian"],28,["He Man","Skeletor","Big Jim"],"it"],["italian_children_games_90s","Italian Children Games 90s","🎮","culture","t","i",["childhood","italian","nineties"],28,["Tamagotchi","Furby","Pokemon"],"it"],["italian_circus_terms","Italian Circus Terms","🎪","culture","t","n",["circus","italian"],20,["Saltimbanco","Giocoliere","Trapezista"],"it"],["italian_cities","Italian Cities","🏙️","places","t","g",["geography","italian"],15,["Roma","Milano","Napoli"],"it"],["italian_coffee_brands","Italian Coffee Brands","☕","drink","t","i",["brands","coffee","italian"],25,["Lavazza","Illy","Segafredo"],"it"],["italian_cryptids_legends","Italian Cryptids Legends","👻","culture","t","i",["folklore","italian"],20,["Befana","Mazzamurello","Badalisc"],"it"],["italian_cured_meats","Italian Cured Meats","🥓","food","t","g",["cuisine","italian"],20,["Prosciutto","Bresaola","Speck"],"it"],["italian_dance_styles","Italian Dance Styles","💃","culture","t","i",["dance","folk","italian"],18,["Tarantella","Pizzica","Saltarello"],"it"],["italian_design_objects","Italian Design Objects","🪑","culture","t","n",["design","italian"],21,["Arco","Tolomeo","Sacco"],"it"],["italian_desserts","Italian Desserts","🍰","food","t","g",["cuisine","italian","sweet"],20,["Tiramisu","Cannolo","Panettone"],"it"],["italian_dialect_words","Italian Dialect Words","🗣️","culture","t","n",["italian","language","regional"],20,["Guaglione","Belin","Bischero"],"it"],["italian_folk_instruments","Italian Folk Instruments","🪕","culture","t","n",["folk","italian","music"],20,["Mandolino","Zampogna","Organetto"],"it"],["italian_football_clubs","Italian Football Clubs","🇮🇹","sport","t","i",["clubs","football","italian"],15,["Juventus","Milan","Inter"],"it"],["italian_grape_varieties","Italian Grape Varieties","🍇","drink","t","i",["italian","wine"],15,["Sangiovese","Nebbiolo","Primitivo"],"it"],["italian_icecream_flavors","Italian Icecream Flavors","🍨","food","t","g",["cuisine","italian","sweet"],18,["Stracciatella","Pistacchio","Fiordilatte"],"it"],["italian_invented_words","Italian Invented Words","💬","culture","t","n",["italian","language"],19,["Petaloso","Apericena","Spritzino"],"it"],["italian_islands","Italian Islands","🏝️","nature","t","i",["geography","italian","sea"],23,["Capri","Ischia","Elba"],"it"],["italian_lakes","Italian Lakes","🏞️","nature","t","i",["geography","italian"],20,["Garda","Como","Maggiore"],"it"],["italian_liqueurs","Italian Liqueurs","🥃","drink","t","g",["italian","spirits"],22,["Limoncello","Amaro","Sambuca"],"it"],["italian_monuments","Italian Monuments","🏛️","places","t","g",["architecture","history","italian"],41,["Colosseo","Torre di Pisa","Pantheon"],"it"],["italian_motorcycles","Italian Motorcycles","🛵","vehicles","t","i",["italian","motorcycles"],20,["Vespa","Monster","Panigale"],"it"],["italian_mountains","Italian Mountains","🏔️","nature","t","g",["geography","italian"],20,["Cervino","Gran Sasso","Marmolada"],"it"],["italian_old_currencies","Italian Old Currencies","🪙","culture","t","n",["history","italian","money"],21,["Lira","Soldo","Ducato"],"it"],["italian_old_jobs","Italian Old Jobs","🔨","culture","t","n",["history","italian","trades"],25,["Arrotino","Lustrascarpe","Lattaio"],"it"],["italian_operas","Italian Operas","🎭","arts","t","g",["classical","italian","opera"],15,["Aida","Tosca","Rigoletto"],"it"],["italian_pasta_shapes","Italian Pasta Shapes","🍝","food","t","g",["cuisine","italian"],20,["Fusillo","Orecchietta","Rigatone"],"it"],["italian_pizza_types","Italian Pizza Types","🍕","food","t","g",["cuisine","italian"],20,["Margherita","Marinara","Capricciosa"],"it"],["italian_progressive_rock_bands","Italian Progressive Rock Bands","🎸","arts","t","n",["italian","music","seventies"],20,["PFM","Banco Mutuo Soccorso","Area"],"it"],["italian_regional_foods","Italian Regional Foods","🍲","food","t","i",["cuisine","italian","regional"],57,["Cacciucco","Frittura","Bresaola"],"it"],["italian_regions","Italian Regions","🗺️","places","t","g",["geography","italian"],15,["Toscana","Lombardia","Puglia"],"it"],["italian_rivers","Italian Rivers","🌊","nature","t","i",["geography","italian"],22,["Po","Tevere","Arno"],"it"],["italian_sea_creatures","Italian Sea Creatures","🐙","food","t","i",["cuisine","italian","sea"],26,["Polpo","Seppia","Triglia"],"it"],["italian_street_foods","Italian Street Foods","🥪","food","t","i",["cuisine","italian","street"],19,["Arancino","Suppli","Panzerotto"],"it"],["italian_train_stations_classic","Italian Train Stations Classic","🚉","places","t","n",["italian","railway"],21,["Roma Termini","Milano Centrale","Santa Maria Novella"],"it"],["italian_volcanoes","Italian Volcanoes","🌋","nature","t","g",["geography","italian"],18,["Etna","Vesuvio","Stromboli"],"it"],["italian_wine_regions","Italian Wine Regions","🍇","drink","t","g",["italian","regional","wine"],22,["Chianti","Barolo","Franciacorta"],"it"],["italian_wines","Italian Wines","🍷","drink","t","g",["italian","wine"],60,["Barolo","Chianti","Prosecco"],"it"],["metal_bands_2000s","Metal Bands 2000s","🤘","arts","t","g",["metal","music","twothousands"],15,["Slipknot","Lamb Of God","Killswitch Engage"],"en"],["metal_bands_2010s","Metal Bands 2010s","🤘","arts","t","g",["metal","music","twentytens"],15,["Sabaton","Baroness","Deafheaven"],"en"],["metal_bands_2020s","Metal Bands 2020s","🤘","arts","t","n",["metal","music","twentytwenties"],15,["Lorna Shore","Code Orange","Blood Incantation"],"en"],["metal_bands_70s","Metal Bands 70s","🤘","arts","t","g",["metal","music","seventies"],15,["Motorhead","Rainbow","Blue Oyster Cult"],"en"],["metal_bands_80s","Metal Bands 80s","🤘","arts","t","g",["eighties","metal","music"],15,["Megadeth","Slayer","Anthrax"],"en"],["metal_bands_90s","Metal Bands 90s","🤘","arts","t","g",["metal","music","nineties"],15,["Sepultura","Death","Cannibal Corpse"],"en"],["nato_alphabet","Nato Alphabet","📻","culture","t","g",["communication","standard"],26,["Alfa","Bravo","Charlie"],"en"],["planets_and_moons","Planets And Moons","🪐","nature","t","g",["astronomy","space"],15,["Mercury","Venus","Mars"],"en"],["pop_groups_2000s","Pop Groups 2000s","✨","arts","t","g",["music","pop","twothousands"],15,["Destinys Child","Westlife","Sugababes"],"en"],["pop_groups_2010s","Pop Groups 2010s","✨","arts","t","g",["music","pop","twentytens"],14,["One Direction","Little Mix","Fifth Harmony"],"en"],["pop_groups_2020s","Pop Groups 2020s","✨","arts","t","g",["music","pop","twentytwenties"],15,["Stray Kids","Seventeen","NewJeans"],"en"],["pop_groups_60s","Pop Groups 60s","✨","arts","t","g",["music","pop","sixties"],15,["The Supremes","The Temptations","The Four Tops"],"en"],["pop_groups_70s","Pop Groups 70s","✨","arts","t","g",["music","pop","seventies"],15,["ABBA","The Bee Gees","The Jackson 5"],"en"],["pop_groups_80s","Pop Groups 80s","✨","arts","t","g",["eighties","music","pop"],10,["Wham","Duran Duran","Culture Club"],"en"],["pop_groups_90s","Pop Groups 90s","✨","arts","t","g",["music","nineties","pop"],15,["Spice Girls","Backstreet Boys","Take That"],"en"],["punk_bands_2000s","Punk Bands 2000s","🧷","arts","t","g",["music","punk","twothousands"],15,["Rise Against","AFI","Anti Flag"],"en"],["punk_bands_2010s","Punk Bands 2010s","🧷","arts","t","n",["music","punk","twentytens"],15,["Joyce Manor","The Menzingers","Modern Baseball"],"en"],["punk_bands_2020s","Punk Bands 2020s","🧷","arts","t","n",["music","punk","twentytwenties"],14,["Gel","Zulu","Soul Glo"],"en"],["punk_bands_70s","Punk Bands 70s","🧷","arts","t","g",["music","punk","seventies"],15,["Buzzcocks","Dead Boys","Television"],"en"],["punk_bands_80s","Punk Bands 80s","🧷","arts","t","g",["eighties","music","punk"],15,["Dead Kennedys","Black Flag","Minor Threat"],"en"],["punk_bands_90s","Punk Bands 90s","🧷","arts","t","g",["music","nineties","punk"],15,["NOFX","Rancid","Fugazi"],"en"],["rock_bands_2000s","Rock Bands 2000s","🎸","arts","t","g",["music","rock","twothousands"],15,["The White Stripes","The Strokes","Coldplay"],"en"],["rock_bands_2010s","Rock Bands 2010s","🎸","arts","t","g",["music","rock","twentytens"],15,["Imagine Dragons","The Black Keys","Tame Impala"],"en"],["rock_bands_2020s","Rock Bands 2020s","🎸","arts","t","g",["music","rock","twentytwenties"],11,["Maneskin","Sleep Token","Turnstile"],"en"],["rock_bands_60s","Rock Bands 60s","🎸","arts","t","g",["music","rock","sixties"],15,["The Beatles","The Rolling Stones","The Beach Boys"],"en"],["rock_bands_70s","Rock Bands 70s","🎸","arts","t","g",["music","rock","seventies"],15,["Led Zeppelin","Pink Floyd","Queen"],"en"],["rock_bands_80s","Rock Bands 80s","🎸","arts","t","g",["eighties","music","rock"],15,["U2","Van Halen","Bon Jovi"],"en"],["rock_bands_90s","Rock Bands 90s","🎸","arts","t","g",["music","nineties","rock"],15,["Nirvana","Pearl Jam","Soundgarden"],"en"],["space_missions","Space Missions","🚀","science","t","g",["exploration","space"],15,["Apollo","Voyager","Cassini"],"en"],["world_capitals","World Capitals","🌍","places","t","g",["geography","cities"],15,["London","Paris","Moscow"],"en"],["world_rivers","World Rivers","🌊","nature","t","g",["geography","water"],15,["Nile","Amazon","Danube"],"en"]];

/* ── SHELF ──────────────────────────────────────────────────── */
/* [key, label, icon, group, type, reach, tags, count, samples, locale] —
   positional because 200 rows of named keys is 27KB of repeated field names. */
const [K, L, I, G, TY, R, TG, N, S, LO] = [0,1,2,3,4,5,6,7,8,9];
const TYPES = { p: 'people', t: 'things' };
const REACH_LABEL = { g: 'global', i: 'italian', n: 'niche' };

const shQ = $('#sh-q'), shGrid = $('#sh-grid'), shTally = $('#sh-tally');
const picked = { group: null, type: null, reach: null, tag: null };

const tally = (idx, map) => {
  const c = new Map();
  SHELF.forEach(d => (Array.isArray(d[idx]) ? d[idx] : [d[idx]])
    .forEach(v => c.set(v, (c.get(v) ?? 0) + 1)));
  return [...c.entries()].sort((a, b) => b[1] - a[1])
    .map(([v, n]) => [v, map ? map[v] : v, n]);
};

function chips(el, facet, list) {
  el.innerHTML = list
    .map(([v, label, n]) => `<button type="button" data-facet="${facet}" data-v="${esc(v)}" aria-pressed="false">${esc(label)}<i>${n}</i></button>`)
    .join('');
}
chips($('#sh-group'), 'group', tally(G));
chips($('#sh-type'), 'type', tally(TY, TYPES));
chips($('#sh-reach'), 'reach', tally(R, REACH_LABEL));
chips($('#sh-tag'), 'tag', tally(TG));

shGrid.innerHTML = SHELF.map((d, i) => `<article class="dict" data-i="${i}">
  <div class="dict-top"><em>${d[I]}</em><b>${esc(d[L])}</b><span>${d[N]}</span></div>
  <div class="dict-key">${esc(d[K])}</div>
  <div class="dict-sample">${esc(d[S].join(' · '))}</div>
  <div class="dict-meta">
    <span class="is-group">${esc(d[G])}</span>
    <span>${TYPES[d[TY]]}</span>
    <span>${REACH_LABEL[d[R]]}</span>
    <span>${d[LO]}</span>
  </div>
</article>`).join('');

/* A row is searched over its key, label, tags and sample names, so typing a
   character you half-remember finds the dictionary it came from. */
const HAY = SHELF.map(d => (d[K] + ' ' + d[L] + ' ' + d[TG].join(' ') + ' ' + d[S].join(' ')).toLowerCase());

function shelfDraw() {
  const term = shQ.value.trim().toLowerCase();
  let shown = 0, names = 0;
  SHELF.forEach((d, i) => {
    const hit = (!term || HAY[i].includes(term))
      && (!picked.group || d[G] === picked.group)
      && (!picked.type || d[TY] === picked.type)
      && (!picked.reach || d[R] === picked.reach)
      && (!picked.tag || d[TG].includes(picked.tag));
    shGrid.children[i].hidden = !hit;
    if (hit) { shown++; names += d[N]; }
  });
  shTally.innerHTML = shown
    ? `<b>${shown}</b> of ${SHELF.length} · <b>${names.toLocaleString('en')}</b> names`
    : 'nothing matches';
  let empty = shGrid.querySelector('.shelf-empty');
  if (!shown && !empty) {
    empty = document.createElement('div');
    empty.className = 'shelf-empty';
    empty.textContent = 'No dictionary matches those filters. Clear one and try again.';
    shGrid.append(empty);
  } else if (shown && empty) empty.remove();
}

$('.shelf-filters').addEventListener('click', e => {
  const b = e.target.closest('button');
  if (!b) return;
  const { facet, v } = b.dataset;
  // A second press on the same chip clears that facet — one press, one idea.
  picked[facet] = picked[facet] === v ? null : v;
  $$(`button[data-facet="${facet}"]`)
    .forEach(x => x.setAttribute('aria-pressed', String(x.dataset.v === picked[facet])));
  shelfDraw();
});
shQ.addEventListener('input', shelfDraw);
shelfDraw();

/* ── CONFIG BUILDER ─────────────────────────────────────────── */
/* One declarative table drives the controls, the generated file and the live
   sample. Adding a setting to the package means adding a row here, not
   touching three separate places that then disagree. */
const CFG = [
  { g: 'Dictionaries', k: 'dictionaries.enabled', t: 'text', d: '*', ph: '* or star_wars, dune',
    h: 'Which dictionaries are in play. A comma-separated list, or * for all of them.' },
  { g: 'Dictionaries', k: 'dictionaries.except', t: 'text', d: '', ph: 'cartoons, italian_youtubers',
    h: 'Removed after enabled is applied.' },
  { g: 'Dictionaries', k: 'dictionaries.types', t: 'select', d: 'both',
    o: [['both', 'people + things'], ['people', 'people'], ['things', 'things']],
    h: 'Limit the pool to one kind of dictionary.' },
  { g: 'Dictionaries', k: 'dictionaries.groups', t: 'text', d: '', ph: 'food, drink',
    h: 'Thematic buckets. Twelve exist; run --list to see which resolve.' },
  { g: 'Dictionaries', k: 'dictionaries.tags', t: 'text', d: '', ph: 'italian, sweet',
    h: 'A dictionary must carry every tag listed, not just one.' },
  { g: 'Dictionaries', k: 'dictionaries.paths', t: 'text', d: '', ph: 'resource_path(...)',
    h: 'Directories of your own JSON dictionaries. One per line in the file; comma-separated here.' },
  { g: 'Dictionaries', k: 'dictionaries.reach', t: 'select', d: 'null',
    o: [['null', 'any'], ['global', 'global'], ['italian', 'italian'], ['niche', 'niche']],
    h: 'Minimum recognisability. Global names travel; niche ones need the right audience.' },

  { g: 'Locale and words', k: 'locale', t: 'select', d: 'null',
    o: [['null', 'follow the app'], ['en', 'en'], ['it', 'it']],
    h: 'Which language the adjectives come from. Null follows the application locale.' },
  { g: 'Locale and words', k: 'fallback_locale', t: 'select', d: 'en', o: [['en', 'en'], ['it', 'it']],
    h: 'Used when a locale has no resources of its own.' },
  { g: 'Locale and words', k: 'separator_symbol', t: 'text', d: '-', ph: '- or _ or empty',
    h: 'Any string, or empty for none.' },
  { g: 'Locale and words', k: 'name_separator', t: 'bool', d: true,
    h: 'On: Luke-Skywalker. Off: LukeSkywalker.' },
  { g: 'Locale and words', k: 'word_count', t: 'select', d: '2', o: [['2', 'two words'], ['3', 'three words']],
    h: 'A third word adds a second adjective — four to eight bits, and easier to say than more digits.' },
  { g: 'Locale and words', k: 'case', t: 'select', d: 'title',
    o: [['title', 'Title-Case'], ['lower', 'lower'], ['upper', 'UPPER'], ['preserve', 'preserve']],
    h: 'Worth zero bits. Lower case is the easiest to dictate; upper survives some legacy forms.' },
  { g: 'Locale and words', k: 'adjective_position', t: 'select', d: 'null',
    o: [['null', 'follow the locale'], ['before', 'before'], ['after', 'after']],
    h: 'Leave it alone. Word order is a property of the language, not a preference.' },

  { g: 'Numbers', k: 'add_numbers', t: 'bool', d: true,
    h: 'Off costs you every digit of entropy the number was carrying.' },
  { g: 'Numbers', k: 'numbers_digits', t: 'number', d: 6, min: 1, max: 18,
    h: 'The setting that matters most: each digit is worth about 3.3 bits.' },
  { g: 'Numbers', k: 'numbers_position', t: 'select', d: 'end',
    o: [['end', 'end'], ['start', 'start'], ['middle', 'middle']],
    h: 'Where the digits sit relative to the words.' },

  { g: 'Numbers', k: 'numbers_allow_leading_zero', t: 'bool', d: false,
    h: 'Off, 042 can never be drawn and six digits are 900,000 values, not a million. The report knows.' },

  { g: 'Leetspeak', k: 'leetspeak_conversion', t: 'select', d: 'none',
    o: [['none', 'none'], ['basic', 'basic'], ['advanced', 'advanced']],
    h: 'Worth exactly zero bits. Use it to satisfy a policy, never to claim strength.' },

  { g: 'Strength', k: 'strength.guesses_per_second', t: 'select', d: '1e10',
    o: [['1e9', '1e9 — modest'], ['1e10', '1e10 — one offline GPU'], ['1e12', '1e12 — well funded']],
    h: 'The attacker every reported crack time assumes.' },
  { g: 'Strength', k: 'strength.rule_model', t: 'select', d: 'charset',
    o: [['charset', 'charset'], ['structural', 'structural']],
    h: 'Which model the StrongPassword rule scores with. Charset is right for a password a user chose.' },
  { g: 'Strength', k: 'strength.thresholds.weak', t: 'number', d: 28, min: 1, max: 200,
    h: 'Below this a password is very weak. Four bands, and they must ascend.' },
  { g: 'Strength', k: 'strength.thresholds.fair', t: 'number', d: 36, min: 1, max: 200,
    h: 'Where "fair" begins. The four bands must ascend; this is the one most worth moving.' },
  { g: 'Strength', k: 'strength.thresholds.strong', t: 'number', d: 60, min: 1, max: 300,
    h: 'Where "strong" begins — what StrongPassword accepts by default.' },

  { g: 'Strength', k: 'strength.thresholds.very_strong', t: 'number', d: 128, min: 1, max: 400,
    h: 'The top band. 128 bits is where brute force stops being a strategy.' },

  { g: 'Batch', k: 'unique_attempts_multiplier', t: 'number', d: 10, min: 1, max: 100,
    h: 'How hard generateUnique() tries before giving up. Raise it for a narrow pool.' },
];

const CATALOG = {"dicts":["a_clockwork_orange","alien","apocalypse_now","arthurian_legend","back_to_the_future","barbie","bicycle_brands","blade_runner","car_brands","cartoons","celtic_mythology","chemical_elements","cocktails","constellations","deadpool","die_hard","dinosaurs","disney_characters","disney_villains","django_unchained","dune","egyptian_mythology","egyptian_pharaohs","electronic_acts_2000s","electronic_acts_2010s","electronic_acts_2020s","electronic_acts_70s","electronic_acts_80s","electronic_acts_90s","encanto","everything_everywhere","football_clubs","game_of_thrones","gemstones","ghostbusters","grease","greek_letters","greek_mythology","guardians_of_the_galaxy","harry_potter","hayao_miyazaki","hip_hop_groups_2000s","hip_hop_groups_2010s","hip_hop_groups_2020s","hip_hop_groups_80s","hip_hop_groups_90s","home_alone","inception","interstellar","italian_actors","italian_aperitivi","italian_architects","italian_basketball_legends","italian_breads","italian_card_games","italian_carnival_masks","italian_cars","italian_castles","italian_cheeses","italian_chefs","italian_children_games_2000s","italian_children_games_70s","italian_children_games_80s","italian_children_games_90s","italian_circus_terms","italian_cities","italian_coffee_brands","italian_comedians","italian_cryptids_legends","italian_cured_meats","italian_cyclists","italian_dance_styles","italian_design_objects","italian_desserts","italian_dialect_words","italian_dj_producers","italian_explorers","italian_fashion_designers","italian_film_directors","italian_folk_instruments","italian_football_clubs","italian_football_legends","italian_grape_varieties","italian_icecream_flavors","italian_invented_words","italian_inventors","italian_islands","italian_journalists","italian_lakes","italian_liqueurs","italian_mathematicians","italian_monuments","italian_motogp_legends","italian_motorcycles","italian_mountains","italian_musicians","italian_nobel_prize_winners","italian_old_currencies","italian_old_jobs","italian_olympic_legends","italian_opera_composers","italian_operas","italian_painters","italian_pasta_shapes","italian_pizza_types","italian_poets","italian_presidents_of_the_republic","italian_progressive_rock_bands","italian_racing_drivers","italian_rappers","italian_regional_foods","italian_regions","italian_renaissance_artists","italian_rivers","italian_scientists","italian_sea_creatures","italian_singers_classic","italian_singers_modern","italian_street_foods","italian_superheroes","italian_television_personalities","italian_tennis_players","italian_train_stations_classic","italian_voice_actors","italian_volcanoes","italian_volleyball_legends","italian_wine_regions","italian_wines","italian_writers","italian_youtubers","james_bond","japanese_mythology","jaws","john_wick","jurassic_park","knives_out","lupin_iii_characters","mad_max","men_in_black","metal_bands_2000s","metal_bands_2010s","metal_bands_2020s","metal_bands_70s","metal_bands_80s","metal_bands_90s","monty_python_holy_grail","nato_alphabet","nba_hall_of_fame","norse_mythology","oppenheimer","philosophers","pixar_characters","planets_and_moons","poor_things","pop_groups_2000s","pop_groups_2010s","pop_groups_2020s","pop_groups_60s","pop_groups_70s","pop_groups_80s","pop_groups_90s","pulp_fiction","punk_bands_2000s","punk_bands_2010s","punk_bands_2020s","punk_bands_70s","punk_bands_80s","punk_bands_90s","rock_bands_2000s","rock_bands_2010s","rock_bands_2020s","rock_bands_60s","rock_bands_70s","rock_bands_80s","rock_bands_90s","rocky","roman_emperors","roman_mythology","saturday_night_fever","space_missions","spider_verse","star_wars","superman","terminator","the_avengers","the_big_lebowski","the_fifth_element","the_godfather","the_goonies","the_grand_budapest_hotel","the_hunger_games","the_martian","the_matrix","the_silence_of_the_lambs","top_gun","trainspotting","wicked","world_capitals","world_explorers","world_rivers"],"groups":["screen","arts","culture","sport","food","science","nature","myth","drink","places","history","vehicles"],"tags":["italian","film","music","seventies","nineties","twentytens","twentytwenties","eighties","cuisine","geography","sciencefiction","comedy","electronic","animation","pop","twothousands","rock","comics","classical","punk","metal","space","history","childhood","hiphop","cult","gods","modern","exploration","sea","musical","architecture","football","regional","wine","brands","cinema","adventure","roman","television","basketball","egyptian","anime","action","disney","literature","crime","fantasy","cycling","greek","astronomy","folk","language","cars","clubs","sixties","sweet","internet","celtic","awards","sport","franchise","spy","tennis","motorcycling","arthurian","medieval","war","usa","olympics","western","norse","rulers","dinosaurs","mystery","postapocalyptic","pixar","motorsport","dystopia","volleyball","invention","science-fiction","fashion","dubbing","media","books","mathematics","thriller","politics","painting","japanese","aviation","thought","renaissance","villains","notation","communication","standard","paleontology","prehistory","chemistry","periodic-table","minerals","jewels","sky","circus","design","water","railway","opera","games","street","tradition","bar","mixology","cities","coffee","money","folklore","cocktails","spirits","dance","motorcycles","trades"]};

/* Which settings have a knowable set of values, and where that set comes from. */
const CFG_SETS = {
  'dictionaries.enabled': CATALOG.dicts,
  'dictionaries.except': CATALOG.dicts,
  'dictionaries.groups': CATALOG.groups,
  'dictionaries.tags': CATALOG.tags,
  'dictionaries.paths': null,
};

const cfgState = Object.fromEntries(CFG.map(r => [r.k, r.d]));
const cfgRows = $('#cfg-rows'), cfgFile = $('#cfg-file'), cfgQ = $('#cfg-q');

function cfgControl(r) {
  const id = 'cfg_' + r.k.replace(/\W/g, '_');
  if (r.t === 'bool') return `<input type="checkbox" id="${id}" data-k="${r.k}"${r.d ? ' checked' : ''}>`;
  if (r.t === 'number') return `<input type="number" id="${id}" data-k="${r.k}" value="${r.d}" min="${r.min}" max="${r.max}">`;
  if (r.t === 'select') return `<select id="${id}" data-k="${r.k}">` +
    r.o.map(([v, l]) => `<option value="${esc(v)}"${v === r.d ? ' selected' : ''}>${esc(l)}</option>`).join('') + '</select>';
  return `<input type="text" id="${id}" data-k="${r.k}" value="${esc(String(r.d))}" placeholder="${esc(r.ph || '')}">`;
}

const CFG_GROUPS = [...new Set(CFG.map(r => r.g))];
let cfgTab = CFG_GROUPS[0];

$('#cfg-tabs').innerHTML = CFG_GROUPS
  .map(g => `<button role="tab" data-g="${esc(g)}" aria-selected="${g === cfgTab}">${esc(g)}<b>${CFG.filter(r => r.g === g).length}</b></button>`)
  .join('');

cfgRows.innerHTML = CFG.map(r =>
  `<div class="cfg-row" data-k="${r.k}" data-g="${esc(r.g)}" data-search="${esc((r.k + ' ' + r.g + ' ' + r.h).toLowerCase())}">
    <code>${esc(r.k)}</code>${cfgControl(r)}
    <p>${esc(r.h)}</p>
    <p class="cfg-was">default: ${esc(String(r.d))}</p>
    ${CFG_SETS[r.k] ? `<details class="cfg-multi" data-k="${r.k}">
      <summary>pick from ${CFG_SETS[r.k].length} <b class="cfg-n"></b></summary>
      <div class="cfg-picker">
        <input type="search" placeholder="filter&hellip;" aria-label="Filter values" data-pick="${r.k}">
        <div class="cfg-opts" data-opts="${r.k}"></div>
      </div>
    </details>` : ''}
  </div>`).join('');

/* Chips, not a <select multiple>: the value is a comma-separated list either
   way, and a list of 200 keys needs a filter more than it needs a dropdown. */
const cfgChosen = k => String(cfgState[k] || '').split(',').map(s => s.trim()).filter(Boolean);

function cfgPaint(k, term = '') {
  const box = cfgRows.querySelector(`[data-opts="${k}"]`);
  if (!box) return;
  const chosen = new Set(cfgChosen(k));
  const all = CFG_SETS[k];
  // Anything already chosen stays visible, so a filter cannot hide your own
  // selection and make it look lost.
  const list = all.filter(v => chosen.has(v) || !term || v.includes(term));
  box.innerHTML = list.length
    ? list.map(v => `<button type="button" data-v="${esc(v)}" aria-pressed="${chosen.has(v)}">${esc(v)}</button>`).join('')
    : '<span class="cfg-empty">Nothing matches.</span>';
  const n = cfgRows.querySelector(`.cfg-multi[data-k="${k}"] .cfg-n`);
  if (n) n.textContent = chosen.size ? `· ${chosen.size} selected` : '';
}

cfgRows.addEventListener('click', e => {
  const chip = e.target.closest('.cfg-opts button');
  if (!chip) return;
  const k = chip.parentElement.dataset.opts;
  const chosen = cfgChosen(k);
  const v = chip.dataset.v;
  const next = chosen.includes(v) ? chosen.filter(x => x !== v) : [...chosen, v];
  cfgState[k] = next.join(', ');
  const field = cfgRows.querySelector(`input[data-k="${k}"]`);
  if (field) field.value = cfgState[k];
  cfgPaint(k, cfgRows.querySelector(`input[data-pick="${k}"]`)?.value.trim().toLowerCase() || '');
  cfgRender();
});

cfgRows.addEventListener('input', e => {
  if (!e.target.dataset.pick) return;
  cfgPaint(e.target.dataset.pick, e.target.value.trim().toLowerCase());
});

Object.keys(CFG_SETS).forEach(k => CFG_SETS[k] && cfgPaint(k));

/* Search looks across every group; clearing it returns you to the tab. */
function cfgFilter() {
  const term = cfgQ.value.trim().toLowerCase();
  let shown = 0;
  cfgRows.querySelectorAll('.cfg-row').forEach(row => {
    const hit = term ? row.dataset.search.includes(term) : row.dataset.g === cfgTab;
    row.hidden = !hit;
    if (hit) shown++;
  });
  $$('#cfg-tabs button').forEach(b => b.setAttribute('aria-selected', String(!term && b.dataset.g === cfgTab)));
  let none = cfgRows.querySelector('.cfg-none');
  if (!shown && !none) {
    none = document.createElement('div');
    none.className = 'cfg-none';
    none.textContent = 'No setting matches that search.';
    cfgRows.append(none);
  } else if (shown && none) none.remove();
}

$('#cfg-tabs').addEventListener('click', e => {
  const b = e.target.closest('button');
  if (!b) return;
  cfgTab = b.dataset.g;
  cfgQ.value = '';
  cfgFilter();
});

/* PHP literals, not JSON: this output is meant to be pasted into a real file. */
const phpList = s => '[' + s.split(',').map(x => x.trim()).filter(Boolean)
  .map(x => `'${x}'`).join(', ') + ']';

function cfgValue(r) {
  const v = cfgState[r.k];
  if (r.t === 'bool') return v ? 'true' : 'false';
  if (r.t === 'number') return String(v);
  if (v === 'null') return 'null';
  if (r.k === 'dictionaries.types') return v === 'both' ? "['people', 'things']" : `['${v}']`;
  if (r.k === 'strength.guesses_per_second') return v;
  if (r.t === 'text' && r.k.startsWith('dictionaries.') && r.k !== 'dictionaries.enabled') return phpList(v);
  if (r.k === 'dictionaries.enabled') return v.trim() === '*' ? "'*'" : phpList(v);
  return `'${v}'`;
}

function cfgRender() {
  // Only what differs. A file restating the defaults is a file nobody reads.
  const changed = CFG.filter(r => String(cfgState[r.k]) !== String(r.d));
  $('#cfg-count').textContent = changed.length ? `${changed.length} changed` : '';
  CFG.forEach(r => cfgRows.querySelector(`.cfg-row[data-k="${r.k}"]`)
    ?.classList.toggle('changed', String(cfgState[r.k]) !== String(r.d)));

  let src;
  if (!changed.length) {
    src = "<?php\n\nreturn [\n    // Every setting is at its default.\n    // Publish the file only when you need to change one.\n];";
  } else {
    const flat = changed.filter(r => !r.k.includes('.'));
    const nested = {};
    // strength.thresholds.fair is two levels deep, so the writer nests as far
    // as the key does rather than assuming one dot.
    changed.filter(r => r.k.includes('.')).forEach(r => {
      const parts = r.k.split('.');
      let node = nested;
      parts.slice(0, -1).forEach(seg => { node = (node[seg] ??= {}); });
      node[parts.at(-1)] = cfgValue(r);
    });
    const write = (obj, depth) => Object.entries(obj).flatMap(([k, v]) => {
      const pad = ' '.repeat(depth * 4);
      return typeof v === 'string'
        ? [`${pad}'${k}' => ${v},`]
        : [`${pad}'${k}' => [`, ...write(v, depth + 1), `${pad}],`];
    });
    const lines = [...flat.map(r => `    '${r.k}' => ${cfgValue(r)},`), ...write(nested, 1)];
    src = "<?php\n\nreturn [\n" + lines.join('\n') + "\n];";
  }
  cfgFile.innerHTML = highlightPhp(src);
  cfgFile.dataset.src = src;

  // The same options, run through the playground's generator, so the sample
  // is the real thing rather than an illustration.
  const o = { ...DEFAULTS };
  const raw = k => String(cfgState[k] ?? '');
  const list = k => raw(k).split(',').map(x => x.trim()).filter(Boolean);
  o.only = raw('dictionaries.enabled').trim() === '*' ? [] : list('dictionaries.enabled');
  o.except = list('dictionaries.except');
  o.group = list('dictionaries.groups');
  o.tag = list('dictionaries.tags');
  o.type = cfgState['dictionaries.types'] === 'both' ? [] : [cfgState['dictionaries.types']];
  o.reach = cfgState['dictionaries.reach'] === 'null' ? null : cfgState['dictionaries.reach'];
  o.locale = cfgState.locale === 'null' ? null : cfgState.locale;
  o.separator = raw('separator_symbol');
  o.numbers = cfgState.add_numbers;
  o.digits = Number(cfgState.numbers_digits) || 6;
  o.position = cfgState.numbers_position;
  o.leet = cfgState.leetspeak_conversion;
  o.words = Number(cfgState.word_count) || 2;
  o.case = cfgState.case;
  o.leadingZero = cfgState.numbers_allow_leading_zero;

  try {
    const g = generate(o);
    const r = report(o);
    $('#cfg-pw').textContent = g.password;
    $('#cfg-meta').textContent =
      `${r.pool.length} dictionaries · ${r.names} names · ${r.c.total.toFixed(1)} bits · ${r.band.replace('_', ' ')}`;
  } catch (e) {
    $('#cfg-pw').textContent = '—';
    $('#cfg-meta').textContent = e.message;
  }
}

cfgRows.addEventListener('input', e => {
  const k = e.target.dataset.k;
  if (!k) return;
  cfgState[k] = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
  cfgFilter();
cfgRender();
});

cfgQ.addEventListener('input', cfgFilter);

$('#cfg-again').onclick = cfgRender;
$('#cfg-reset').onclick = () => {
  CFG.forEach(r => { cfgState[r.k] = r.d; });
  cfgRows.querySelectorAll('[data-k]').forEach(el => {
    const r = CFG.find(x => x.k === el.dataset.k);
    if (!r || !el.tagName.match(/INPUT|SELECT/)) return;
    if (r.t === 'bool') el.checked = r.d; else el.value = r.d;
  });
  // The chip pickers hold their own rendered state, and their filter boxes
  // hold typed text — a reset that leaves either behind is not a reset.
  cfgRows.querySelectorAll('input[data-pick]').forEach(f => { f.value = ''; });
  cfgRows.querySelectorAll('.cfg-multi').forEach(d => { d.open = false; });
  Object.keys(CFG_SETS).forEach(k => CFG_SETS[k] && cfgPaint(k));
  cfgRender();
};

$('#cfg-copy').onclick = async () => {
  const btn = $('#cfg-copy');
  try { await navigator.clipboard.writeText(cfgFile.dataset.src); } catch (e) { /* blocked */ }
  btn.classList.add('done'); btn.textContent = 'copied';
  setTimeout(() => { btn.classList.remove('done'); btn.textContent = 'copy'; }, 1400);
};

cfgFilter();
cfgRender();

/* One command already run, so the panel is never an empty box. */
exec('password-toolkit:generate 5 --report');
flush();


/* ── SEARCH PALETTE ─────────────────────────────────────────── */
/* The index is read off the page itself, so a section added to the document
   is searchable without anybody remembering to list it twice. The landing
   page's own stops are listed by hand, because its sections are laid out
   rather than titled and their headings are sentences, not labels. */
const HOME_STOPS = [
  ['Home', '#/home', 'page'],
  ['Why it exists', '#/home/why', 'Home'],
  ['The whole integration', '#/home/how', 'Home'],
  ['The dictionary shelf', '#/home/dictionary-shelf', 'Home'],
  ['Two languages', '#/home/lang', 'Home'],
  ['Configuration at a glance', '#/home/bench', 'Home'],
  ['What it costs you', '#/home/cost', 'Home'],
  ['By the numbers', '#/home/home-numbers', 'Home'],
  ['Sponsor', '#/home/sponsor', 'Home'],
];
const INDEX = HOME_STOPS.concat(PAGES.flatMap(sec => {
  const title = titleOf(sec);
  return [[title, '#/' + sec.id, 'page']].concat(
    [...sec.querySelectorAll('h2:not(.page-title), h3')]
      .map(h => [h.dataset.label, `#/${sec.id}/${h.id}`, title]));
})).concat([
  ['Upgrading from 1.x on GitHub', 'https://github.com/gabrielesbaiz/password-toolkit/blob/main/UPGRADE.md', 'github'],
  ['Changelog on GitHub', 'https://github.com/gabrielesbaiz/password-toolkit/blob/main/CHANGELOG.md', 'github'],
  ['Security policy', 'https://github.com/gabrielesbaiz/password-toolkit/blob/main/SECURITY.md', 'github'],
]);

const scrim = $('#scrim'), q = $('#q'), hits = $('#hits');
let cursor = 0, shown = [];

function draw() {
  const term = q.value.trim().toLowerCase();
  shown = term ? INDEX.filter(r => r[0].toLowerCase().includes(term)) : INDEX;
  cursor = Math.min(cursor, Math.max(0, shown.length - 1));
  hits.innerHTML = shown.length
    ? shown.map(([label, href, where], i) =>
        `<li role="option" aria-selected="${i === cursor}"><a href="${href}">${esc(label)}<span class="ph">${esc(where)}</span></a></li>`).join('')
    : `<li class="none">Nothing here by that name.</li>`;
  hits.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
}
const openPal = () => { scrim.hidden = false; q.value = ''; cursor = 0; draw(); q.focus(); };
const closePal = () => { scrim.hidden = true; };

$('#openk').onclick = openPal;
q.oninput = () => { cursor = 0; draw(); };
scrim.onclick = e => { if (e.target === scrim) closePal(); };

addEventListener('keydown', e => {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); scrim.hidden ? openPal() : closePal(); return; }
  if (scrim.hidden) return;
  if (e.key === 'Escape') closePal();
  if (e.key === 'ArrowDown') { e.preventDefault(); cursor = (cursor + 1) % shown.length; draw(); }
  if (e.key === 'ArrowUp') { e.preventDefault(); cursor = (cursor - 1 + shown.length) % shown.length; draw(); }
  if (e.key === 'Enter' && shown[cursor]) { closePal(); hits.querySelectorAll('a')[cursor]?.click(); }
});


/* ── THEME ──────────────────────────────────────────────────── */
/* Auto leaves the root unstamped so prefers-color-scheme decides; Light and
   Dark stamp it so the choice beats the OS in either direction. */
/* The page starts unstamped so the OS decides. The first press stamps the
   opposite of whatever is on screen, which is what a single switch has to do
   to be predictable from either starting point. */
const themeBtn = $('#theme');
const darkQuery = matchMedia('(prefers-color-scheme: dark)');
const effective = () => document.documentElement.dataset.theme || (darkQuery.matches ? 'dark' : 'light');
const paintTheme = () => { themeBtn.textContent = effective() === 'dark' ? 'Light' : 'Dark'; };
themeBtn.onclick = () => {
  document.documentElement.dataset.theme = effective() === 'dark' ? 'light' : 'dark';
  paintTheme();
};
darkQuery.addEventListener('change', paintTheme);
paintTheme();

/* ══════════════════════════════════════════════════════════════
   THE LANDING PAGE
   Everything below belongs to #/home and nothing above it depends
   on any of it. It runs inside a closure because its dictionary
   sample data, its pick() and its SHELF are a different shape from
   the guide's constants of the same name — shadowing them here is
   the whole point, rather than renaming one set of honest names.
   ══════════════════════════════════════════════════════════════ */
(() => {

const HOME_DATA = {"dicts":{"italian_pasta_shapes":{"label":"Italian Pasta Shapes","icon":"\ud83c\udf5d","locale":"it","group":"food","names":[["Fusillo","m"],["Orecchietta","f"],["Rigatone","m"],["Farfalla","f"],["Penna","f"],["Spaghetto","m"],["Linguina","f"],["Tagliatella","f"],["Lasagna","f"],["Gnocco","m"],["Raviolo","m"],["Tortellino","m"],["Cannellone","m"],["Conchiglia","f"],["Bucatino","m"],["Pappardella","f"],["Trofia","f"],["Picio","m"],["Maccherone","m"],["Zito","m"]],"en":["Authentic","Creamy","Delicious","Flavorful","Fragrant","Genuine","Greedy","Irresistible","Rustic","Steaming","Succulent","Tasty","Traditional"],"it":[["gustoso","m"],["gustosa","f"],["saporito","m"],["saporita","f"],["cremoso","m"],["cremosa","f"],["fumante","n"],["delizioso","m"],["deliziosa","f"],["goloso","m"],["golosa","f"],["fragrante","n"],["succulento","m"],["succulenta","f"],["tradizionale","n"],["autentico","m"],["autentica","f"],["genuino","m"],["genuina","f"],["irresistibile","n"],["rustico","m"],["rustica","f"]],"tr":{}},"star_wars":{"label":"Star Wars","icon":"\ud83d\ude80","locale":"en","group":"screen","names":[["Luke Skywalker","m"],["Leia Organa","f"],["Han Solo","m"],["Darth Vader","m"],["Yoda","m"],["Chewbacca","m"],["Obi Wan Kenobi","m"],["Anakin Skywalker","m"],["Palpatine","m"],["Lando Calrissian","m"],["Boba Fett","m"],["Darth Maul","m"],["Count Dooku","m"],["Rey","f"],["Kylo Ren","m"]],"en":["Affectionate","Aggressive","Altruistic","Clearest","Committed","Corrosive","Diplomatic","Dominant","Elusive","Empathetic","Enthusiastic","Erudite","Fascinating","Furious","Gritty","Hardened","Hungry","Selfish","Sly","Undaunted"],"it":[["Affamata","f"],["Affamato","m"],["Affascinante","n"],["Affettuosa","f"],["Affettuoso","m"],["Aggressiva","f"],["Aggressivo","m"],["Agguerrita","f"],["Agguerrito","m"],["Altruista","n"],["Chiarissima","f"],["Chiarissimo","m"],["Corrosiva","f"],["Corrosivo","m"],["Diplomatica","f"],["Diplomatico","m"],["Dominante","n"],["Egoista","n"],["Elusiva","f"],["Elusivo","m"],["Empatica","f"],["Empatico","m"],["Entusiasta","n"],["Erudita","f"],["Erudito","m"],["Furba","f"],["Furbo","m"],["Furiosa","f"],["Furioso","m"],["Grintosa","f"],["Grintoso","m"],["Impavida","f"],["Impavido","m"],["Impegnata","f"],["Impegnato","m"]],"tr":{"Count Dooku":"Conte Dooku"}},"italian_wines":{"label":"Italian Wines","icon":"\ud83c\udf77","locale":"it","group":"drink","names":[["Barolo","m"],["Chianti","m"],["Prosecco","m"],["Brunello","m"],["Amarone","m"],["Pinot Grigio","m"],["Sangiovese","m"],["Cannonau","m"],["Montepulciano","m"],["Vermentino","m"],["Nebbiolo","m"],["Aglianico","m"],["Barbera","f"],["Dolcetto","m"],["Primitivo","m"],["Gavi","m"],["Moscato","m"],["Cortese","m"],["Lambrusco","m"],["Fiano","m"],["Greco","m"],["Torgiano","m"],["Sassicaia","m"],["Barbaresco","m"],["Ghemme","m"],["Valpolicella","f"],["Frascati","m"],["Trebbiano","m"],["Verdeca","f"],["Lugana","f"],["Vernaccia","f"],["Custoza","f"],["Lacrima","f"],["Ribolla","f"],["Negroamaro","m"],["Falanghina","f"],["Schiava","f"],["Carignano","m"],["Maremma","f"],["Cerasuolo","m"],["Verdicchio","m"],["Passito","m"],["Pecorino","m"],["Grillo","m"],["Sorbara","m"],["Pignoletto","m"],["Syrah","m"],["Cabernet","m"],["Merlot","m"],["Chardonnay","m"],["Malvasia","f"],["Bianchello","m"],["Gaglioppo","m"],["Ciliegiolo","m"],["Lagrein","m"],["Montefalco","m"],["Bardolino","m"],["Etna","m"],["Colli Euganei","m"],["Franciacorta","m"]],"en":["Aromatic","Austere","Balanced","Balsamic","Complex","Creamy","Exotic","Floral","Fragrant","Fruity","Fullbodied","Harmonic","Mature","Mineral","Persistent","Plush","Soft","Sweet","Tasteful","Warm"],"it":[["Armonica","f"],["Armonico","m"],["Aromatica","f"],["Aromatico","m"],["Austera","f"],["Austero","m"],["Balsamica","f"],["Balsamico","m"],["Bilanciata","f"],["Bilanciato","m"],["Calda","f"],["Caldo","m"],["Complessa","f"],["Complesso","m"],["Corposa","f"],["Corposo","m"],["Cremosa","f"],["Cremoso","m"],["Dolce","n"],["Esotica","f"],["Esotico","m"],["Floreale","n"],["Fragrante","n"],["Fruttata","f"],["Fruttato","m"],["Gusto","m"],["Mature","n"],["Minerale","n"],["Morbidissima","f"],["Morbidissimo","m"],["Morbido","m"],["Persistente","n"]],"tr":{}},"nato_alphabet":{"label":"NATO Alphabet","icon":"\ud83d\udcfb","locale":"en","group":"culture","names":[["Alfa","m"],["Bravo","m"],["Charlie","m"],["Delta","m"],["Echo","m"],["Foxtrot","m"],["Golf","m"],["Hotel","m"],["India","m"],["Juliett","m"],["Kilo","m"],["Lima","m"],["Mike","m"],["November","m"],["Oscar","m"],["Papa","m"],["Quebec","m"],["Romeo","m"],["Sierra","m"],["Tango","m"],["Uniform","m"],["Victor","m"],["Whiskey","m"],["Xray","m"],["Yankee","m"],["Zulu","m"]],"en":["Audible","Clear","Concise","Conventional","Crisp","Encoded","Enunciated","Military","Operational","Orderly","Punctual","Radiophonic","Sharp","Sonorous","Standardised","Syllabic","Transmitted","Unambiguous"],"it":[["Chiara","f"],["Chiaro","m"],["Codificata","f"],["Codificato","m"],["Concisa","f"],["Conciso","m"],["Convenzionale","n"],["Inequivocabile","n"],["Militare","n"],["Netta","f"],["Netto","m"],["Nitida","f"],["Nitido","m"],["Operativa","f"],["Operativo","m"],["Ordinata","f"],["Ordinato","m"],["Puntuale","n"],["Radiofonica","f"],["Radiofonico","m"],["Scandita","f"],["Scandito","m"],["Sillabica","f"],["Sillabico","m"],["Sonora","f"],["Sonoro","m"],["Standardizzata","f"],["Standardizzato","m"],["Trasmessa","f"],["Trasmesso","m"],["Udibile","n"]],"tr":{}},"greek_mythology":{"label":"Greek Mythology","icon":"\ud83c\udffa","locale":"en","group":"myth","names":[["Zeus","m"],["Poseidon","m"],["Hades","m"],["Apollo","m"],["Ares","m"],["Hephaestus","m"],["Hermes","m"],["Dionysus","m"],["Prometheus","m"],["Achilles","m"],["Odysseus","m"],["Heracles","m"],["Theseus","m"],["Perseus","m"],["Jason","m"],["Orpheus","m"],["Oedipus","m"],["Paris","m"],["Hector","m"],["Agamemnon","m"],["Menelaus","m"],["Daedalus","m"],["Icarus","m"],["Narcissus","m"],["Adonis","m"],["Aeneas","m"],["Ajax","m"],["Nestor","m"],["Tantalus","m"],["Sisyphus","m"],["Minos","m"],["Peleus","m"],["Priam","m"],["Cronus","m"],["Titan","m"],["Atlas","m"],["Morpheus","m"],["Hypnos","m"],["Boreas","m"],["Hera","f"],["Aphrodite","f"],["Artemis","f"],["Athena","f"],["Demeter","f"],["Hestia","f"],["Persephone","f"],["Circe","f"],["Medea","f"],["Calypso","f"],["Helen","f"],["Penelope","f"],["Antigone","f"],["Iphigenia","f"],["Medusa","f"],["Arachne","f"],["Ariadne","f"],["Cassandra","f"],["Andromache","f"],["Hecuba","f"],["Clytemnestra","f"],["Selene","f"],["Nyx","f"],["Eos","f"],["Iris","f"],["Europa","f"],["Danae","f"],["Io","f"],["Callisto","f"]],"en":["Brave","Celestial","Cunning","Divine","Epic","Fearsome","Glorious","Heroic","Immortal","Invincible","Legendary","Magnificent","Mysterious","Mythical","Olympic","Powerful","Venerable","Wise"],"it":[["olimpico","m"],["olimpica","f"],["divino","m"],["divina","f"],["eroico","m"],["eroica","f"],["leggendario","m"],["leggendaria","f"],["immortale","n"],["epico","m"],["epica","f"],["mitico","m"],["mitica","f"],["potente","n"],["temibile","n"],["saggio","m"],["saggia","f"],["astuto","m"],["astuta","f"],["coraggioso","m"],["coraggiosa","f"],["magnifico","m"],["magnifica","f"],["glorioso","m"],["gloriosa","f"],["invincibile","n"],["misterioso","m"],["misteriosa","f"],["venerabile","n"],["celestiale","n"]],"tr":{"Poseidon":"Poseidone","Hades":"Ade","Hephaestus":"Efesto","Hermes":"Ermes","Dionysus":"Dioniso","Prometheus":"Prometeo","Achilles":"Achille","Odysseus":"Ulisse","Heracles":"Eracle","Theseus":"Teseo","Perseus":"Perseo","Jason":"Giasone","Orpheus":"Orfeo","Oedipus":"Edipo","Paris":"Paride","Hector":"Ettore","Agamemnon":"Agamennone","Menelaus":"Menelao","Daedalus":"Dedalo","Icarus":"Icaro","Narcissus":"Narciso","Adonis":"Adone","Aeneas":"Enea","Ajax":"Aiace","Nestor":"Nestore","Tantalus":"Tantalo","Sisyphus":"Sisifo","Minos":"Minosse","Peleus":"Peleo","Priam":"Priamo","Cronus":"Crono","Titan":"Titano","Atlas":"Atlante","Morpheus":"Morfeo","Hypnos":"Ipnos","Boreas":"Borea","Hera":"Era","Aphrodite":"Afrodite","Artemis":"Artemide","Athena":"Atena","Demeter":"Demetra","Hestia":"Estia","Persephone":"Persefone","Calypso":"Calipso","Helen":"Elena","Iphigenia":"Ifigenia","Arachne":"Aracne","Ariadne":"Arianna","Andromache":"Andromaca","Hecuba":"Ecuba","Clytemnestra":"Clitemnestra","Iris":"Iride"}},"top_gun":{"label":"Top Gun","icon":"\u2708\ufe0f","locale":"en","group":"screen","names":[["Maverick","m"],["Goose","m"],["Iceman","m"],["Viper","m"],["Jester","m"],["Slider","m"],["Charlie","f"],["Rooster","m"],["Hangman","m"],["Phoenix","f"],["Bob","m"],["Payback","m"],["Fanboy","m"],["Coyote","m"],["Cyclone","m"]],"en":["Acrobatic","Audacious","Bold","Brazen","Competitive","Dizzying","Flying","Glacial","Gritty","Invincible","Legendary","Lightning","Military","Reckless","Roaring","Snappy","Supersonic","Swift","Tactical","Undaunted"],"it":[["Acrobatica","f"],["Acrobatico","m"],["Audace","n"],["Competitiva","f"],["Competitivo","m"],["Fulminea","f"],["Fulmineo","m"],["Glaciale","n"],["Grintosa","f"],["Grintoso","m"],["Impavida","f"],["Impavido","m"],["Invincibile","n"],["Leggendaria","f"],["Leggendario","m"],["Militare","n"],["Ruggente","n"],["Scattante","n"],["Sfrontata","f"],["Sfrontato","m"],["Spericolata","f"],["Spericolato","m"],["Supersonica","f"],["Supersonico","m"],["Tattica","f"],["Tattico","m"],["Temeraria","f"],["Temerario","m"],["Veloce","n"],["Vertiginosa","f"],["Vertiginoso","m"],["Volante","n"]],"tr":{}},"italian_cities":{"label":"Italian Cities","icon":"\ud83c\udfd9\ufe0f","locale":"it","group":"places","names":[["Roma","f"],["Milano","m"],["Napoli","f"],["Torino","m"],["Firenze","f"],["Venezia","f"],["Genova","f"],["Bologna","f"],["Palermo","m"],["Verona","f"],["Padova","f"],["Siena","f"],["Pisa","f"],["Trieste","f"],["Mantova","f"]],"en":["Academic","Ancient","Baroque","Bustling","Chaotic","Evocative","Foggy","Fortified","Genteel","Hardworking","Hilly","Historic","Maritime","Medieval","Monumental","Panoramic","Picturesque","Renaissance","Sunlit","Welcoming"],"it":[["Accogliente","n"],["Antica","f"],["Antico","m"],["Barocca","f"],["Barocco","m"],["Caotica","f"],["Caotico","m"],["Collinare","n"],["Fortificata","f"],["Fortificato","m"],["Marittima","f"],["Marittimo","m"],["Medievale","n"],["Monumentale","n"],["Nebbiosa","f"],["Nebbioso","m"],["Operosa","f"],["Operoso","m"],["Panoramica","f"],["Panoramico","m"],["Pittoresca","f"],["Pittoresco","m"],["Rinascimentale","n"],["Signorile","n"],["Soleggiata","f"],["Soleggiato","m"],["Storica","f"],["Storico","m"],["Suggestiva","f"],["Suggestivo","m"],["Trafficata","f"],["Trafficato","m"],["Universitaria","f"],["Universitario","m"]],"tr":{}},"dune":{"label":"Dune","icon":"\ud83e\udeb1","locale":"en","group":"screen","names":[["Paul Atreides","m"],["Lady Jessica","f"],["Leto Atreides","m"],["Duncan Idaho","m"],["Gurney Halleck","m"],["Thufir Hawat","m"],["Chani","f"],["Stilgar","m"],["Vladimir Harkonnen","m"],["Glossu Rabban","m"],["Piter De Vries","m"],["Feyd Rautha","m"],["Princess Irulan","f"],["Liet Kynes","f"],["Shaddam","m"]],"en":["Ancient","Arid","Ascetic","Austere","Fearsome","Feudal","Hereditary","Imperial","Inexorable","Infinite","Mystical","Noble","Oneiric","Primordial","Prophetic","Relentless","Sandy","Torrid","Tribal","Visionary"],"it":[["Antica","f"],["Antico","m"],["Arida","f"],["Arido","m"],["Ascetica","f"],["Ascetico","m"],["Austera","f"],["Austero","m"],["Ereditaria","f"],["Ereditario","m"],["Feudale","n"],["Imperiale","n"],["Implacabile","n"],["Inesorabile","n"],["Infinita","f"],["Infinito","m"],["Mistica","f"],["Mistico","m"],["Nobile","n"],["Onirica","f"],["Onirico","m"],["Primordiale","n"],["Profetica","f"],["Profetico","m"],["Sabbiosa","f"],["Sabbioso","m"],["Temibile","n"],["Torrida","f"],["Torrido","m"],["Tribale","n"],["Visionaria","f"],["Visionario","m"]],"tr":{}}}};
const HOME_D = HOME_DATA.dicts, HOME_KEYS = Object.keys(HOME_D);
const homePick = a => a[Math.floor(Math.random() * a.length)];

/* The package's own assembly rules, so every sample here is real. */
function make(locale = 'en', key = homePick(HOME_KEYS), digits = 4) {
  const d = HOME_D[key];
  const [raw, gender] = homePick(d.names);
  const name = (locale !== d.locale && d.tr && d.tr[raw]) || raw;
  const adj = locale === 'it' ? homePick(d.it.filter(a => a[1] === gender || a[1] === 'n'))[0] : homePick(d.en);
  const parts = locale === 'it' ? [name.replace(/ /g, '-'), adj] : [adj, name.replace(/ /g, '-')];
  const num = digits > 0 ? '-' + String(Math.floor(Math.random() * (10 ** digits - 10 ** (digits - 1))) + 10 ** (digits - 1)) : '';
  return { key, gender, name, adj, password: parts.join('-') + num };
}
const bitsFor = k => Math.log2(HOME_D[k].names.length) + Math.log2(HOME_D[k].en.length) + 4 * Math.log2(10);

/* ── HERO ───────────────────────────────────────────────────── */
/* Characters drop in one at a time, like a stamp machine settling. */
const GLYPHS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
let scrambleId = 0;
function typeset(el, text) {
  el.textContent = '';
  if (reduced) { el.textContent = text; return; }
  const cells = [...text].map((ch, i) => {
    const s = document.createElement('span');
    s.textContent = ch === '-' ? '-' : GLYPHS[(Math.random() * GLYPHS.length) | 0];
    s.style.animationDelay = (i * 14) + 'ms';
    el.append(s);
    return { s, ch, settles: 160 + i * 26 };
  });
  // Each character keeps rolling until its own moment, so the string settles
  // left to right the way a sorting machine would hand it over.
  const run = ++scrambleId, t0 = performance.now();
  (function tick(now) {
    if (run !== scrambleId) return;
    let pending = false;
    for (const c of cells) {
      if (now - t0 >= c.settles) { c.s.textContent = c.ch; continue; }
      pending = true;
      if (c.ch !== '-') c.s.textContent = GLYPHS[(Math.random() * GLYPHS.length) | 0];
    }
    if (pending) requestAnimationFrame(tick);
  })(t0);
}

let current = '';
function hero() {
  const g = make('en', homePick(['star_wars', 'dune', 'top_gun', 'greek_mythology', 'nato_alphabet']));
  current = g.password;
  typeset($('#pw'), g.password);
  $('#meta').textContent = `${HOME_D[g.key].icon} ${g.key} · english · ${bitsFor(g.key).toFixed(1)} bits · change on first sign-in`;
  const m = $('#mark');
  m.classList.remove('hit'); void m.offsetWidth; m.classList.add('hit');
}
$('#gen').onclick = hero;
$('#copy').onclick = async () => {
  try { await navigator.clipboard.writeText(current); } catch (e) { /* clipboard may be blocked; the stamp still tells the truth about the attempt */ }
  const c = $('#copied'); c.classList.add('on'); setTimeout(() => c.classList.remove('on'), 1400);
};

/* ── AGREEMENT ──────────────────────────────────────────────── */
/* The wrong form is derived from the right one: Italian -o/-a adjectives
   inflect, -e ones do not, so only inflecting pairs can show a mistake. */
let agreeKey = 'italian_pasta_shapes';
function agreement() {
  const d = HOME_D[agreeKey];
  const candidates = d.names.filter(([, g]) => g !== 'n');
  const [rawName, gender] = candidates.length ? homePick(candidates) : homePick(d.names);
  const inflecting = d.it.filter(a => a[1] === gender && /[oa]$/.test(a[0]));
  const [right] = inflecting.length ? homePick(inflecting) : homePick(d.it.filter(a => a[1] === gender || a[1] === 'n'));
  const wrong = /[oa]$/.test(right) ? right.slice(0, -1) + (right.endsWith('o') ? 'a' : 'o') : null;

  $('#it-line').innerHTML = `${rawName} <span class="ok">${right}</span>` + (wrong ? ` <span class="no">${wrong}</span>` : '');
  $('#it-g').textContent = gender === 'm' ? 'maschile' : gender === 'f' ? 'femminile' : 'neutro';

  const en = (d.tr && d.tr[rawName]) || rawName;
  $('#en-line').innerHTML = `<span class="ok">${homePick(d.en)}</span> ${en}`;
}
$$('.swap button').forEach(b => b.onclick = () => {
  agreeKey = b.dataset.d;
  $$('.swap button').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
  agreement();
});
$('#again').onclick = agreement;

/* ── REVEAL ─────────────────────────────────────────────────── */
const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { threshold: .12 });
$$('.rv').forEach(el => io.observe(el));


/* ── AIRMAIL SKY ────────────────────────────────────────────── */
/* Chevrons off the envelope's border tape, drifting at the angle the tape is
   printed. Colours come from the tokens, so the canvas re-themes with the
   page instead of holding one palette. */
const sky = document.getElementById('sky');
if (sky && !reduced) {
  const ctx = sky.getContext('2d');
  let w = 0, h = 0, flock = [], ink = ['#000', '#000'];

  const readInk = () => {
    const cs = getComputedStyle(document.documentElement);
    ink = [cs.getPropertyValue('--tape-a').trim(), cs.getPropertyValue('--tape-b').trim()];
  };

  function resize() {
    const dpr = Math.min(devicePixelRatio || 1, 2);
    w = innerWidth; h = innerHeight;
    sky.width = w * dpr; sky.height = h * dpr;
    sky.style.width = w + 'px'; sky.style.height = h + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    // Density follows area, so a wide monitor is not emptier than a phone.
    const want = Math.round(Math.min(46, Math.max(14, (w * h) / 34000)));
    flock = Array.from({ length: want }, (_, i) => flock[i] || {
      x: Math.random() * w, y: Math.random() * h,
      len: 7 + Math.random() * 13, speed: .12 + Math.random() * .38,
      alpha: .05 + Math.random() * .16, c: Math.random() < .5 ? 0 : 1,
    });
  }

  const RAD = (115 - 90) * Math.PI / 180, DX = Math.cos(RAD), DY = Math.sin(RAD);

  function frame() {
    ctx.clearRect(0, 0, w, h);
    ctx.lineCap = 'round';
    ctx.lineWidth = 2;
    for (const p of flock) {
      p.x += DX * p.speed; p.y += DY * p.speed;
      if (p.x > w + 40) { p.x = -40; p.y = Math.random() * h; }
      if (p.y > h + 40) { p.y = -40; p.x = Math.random() * w; }
      ctx.globalAlpha = p.alpha;
      ctx.strokeStyle = ink[p.c];
      ctx.beginPath();
      ctx.moveTo(p.x, p.y);
      ctx.lineTo(p.x + DX * p.len, p.y + DY * p.len);
      ctx.stroke();
    }
    ctx.globalAlpha = 1;
    requestAnimationFrame(frame);
  }

  readInk(); resize(); frame();
  addEventListener('resize', resize);
  // Both routes to a theme change: the switch stamps the root, the OS fires
  // the media query, and the canvas has to follow either one.
  new MutationObserver(readInk).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
  matchMedia('(prefers-color-scheme: dark)').addEventListener('change', readInk);
}

/* ── ENVELOPE TILT ──────────────────────────────────────────── */
const tiltwrap = $('.tiltwrap'), envelope = $('.envelope');
if (tiltwrap && envelope && !reduced) {
  let raf = 0, tx = 0, ty = 0;
  // The lift is repeated here because an inline transform replaces the one
  // :hover sets — leaving it out would trade the lean for the lift.
  const apply = () => {
    raf = 0;
    envelope.style.transform = `translateY(-3px) rotateX(${ty.toFixed(2)}deg) rotateY(${tx.toFixed(2)}deg)`;
  };
  tiltwrap.addEventListener('pointerenter', () => { envelope.style.transition = 'none'; });
  tiltwrap.addEventListener('pointermove', e => {
    const r = tiltwrap.getBoundingClientRect();
    tx = ((e.clientX - r.left) / r.width - .5) * 9;
    ty = (.5 - (e.clientY - r.top) / r.height) * 6;
    if (!raf) raf = requestAnimationFrame(apply);
  });
  // Clearing both hands the element back to the stylesheet, which already
  // knows how to ease it home.
  tiltwrap.addEventListener('pointerleave', () => {
    envelope.style.transition = '';
    envelope.style.transform = '';
  });
}

/* ── THE SHELF ──────────────────────────────────────────────── */
const HOME_SHELF = [["🍊","A Clockwork Orange","screen"],["👽","Alien","screen"],["🚁","Apocalypse Now","screen"],["⚔️","Arthurian Legend","myth"],["🚗","Back To The Future","screen"],["💗","Barbie","screen"],["🌧️","Blade Runner","screen"],["📺","Cartoons","screen"],["🍀","Celtic Mythology","myth"],["🗡️","Deadpool","screen"],["🏢","Die Hard","screen"],["🏰","Disney Characters","screen"],["😈","Disney Villains","screen"],["🤠","Django Unchained","screen"],["🪱","Dune","screen"],["🐈","Egyptian Mythology","myth"],["👑","Egyptian Pharaohs","history"],["🕯️","Encanto","screen"],["🥯","Everything Everywhere","screen"],["🐉","Game Of Thrones","screen"],["👻","Ghostbusters","screen"],["🕺","Grease","screen"],["🏺","Greek Mythology","myth"],["🌌","Guardians Of The Galaxy","screen"],["🧙","Harry Potter","screen"],["🌸","Hayao Miyazaki","screen"],["🏠","Home Alone","screen"],["🌀","Inception","screen"],["🪐","Interstellar","screen"],["🎭","Italian Actors","screen"],["📐","Italian Architects","arts"],["🏀","Italian Basketball Legends","sport"],["👨‍🍳","Italian Chefs","food"],["😂","Italian Comedians","screen"],["🚴","Italian Cyclists","sport"],["🎧","Italian Dj Producers","arts"],["🧭","Italian Explorers","history"],["👗","Italian Fashion Designers","arts"],["🎬","Italian Film Directors","arts"],["⚽","Italian Football Legends","sport"],["💡","Italian Inventors","science"],["📰","Italian Journalists","screen"],["📐","Italian Mathematicians","science"],["🏍️","Italian Motogp Legends","sport"],["🎵","Italian Musicians","arts"],["🏅","Italian Nobel Prize Winners","science"],["🥇","Italian Olympic Legends","sport"],["🎼","Italian Opera Composers","arts"],["🖼️","Italian Painters","arts"],["✒️","Italian Poets","arts"],["🇮🇹","Italian Presidents Of The Republic","history"],["🏁","Italian Racing Drivers","sport"],["🎤","Italian Rappers","arts"],["🎨","Italian Renaissance Artists","arts"],["🔬","Italian Scientists","science"],["🎙️","Italian Singers Classic","arts"],["🎙️","Italian Singers Modern","arts"],["🦸","Italian Superheroes","screen"],["📺","Italian Television Personalities","screen"],["🎾","Italian Tennis Players","sport"],["🎙️","Italian Voice Actors","screen"],["🏐","Italian Volleyball Legends","sport"],["📖","Italian Writers","arts"],["▶️","Italian Youtubers","screen"],["🕴️","James Bond","screen"],["⛩️","Japanese Mythology","myth"],["🦈","Jaws","screen"],["🐕","John Wick","screen"],["🦖","Jurassic Park","screen"],["🔪","Knives Out","screen"],["🕵️","Lupin Iii Characters","screen"],["🏜️","Mad Max","screen"],["🕶️","Men In Black","screen"],["🥥","Monty Python Holy Grail","screen"],["🏀","Nba Hall Of Fame","sport"],["🔨","Norse Mythology","myth"],["⚛️","Oppenheimer","screen"],["🤔","Philosophers","science"],["💡","Pixar Characters","screen"],["🧠","Poor Things","screen"],["🍔","Pulp Fiction","screen"],["🥊","Rocky","screen"],["🏛️","Roman Emperors","history"],["⚡","Roman Mythology","myth"],["🪩","Saturday Night Fever","screen"],["🕸️","Spider Verse","screen"],["🚀","Star Wars","screen"],["🦸","Superman","screen"],["🤖","Terminator","screen"],["🛡️","The Avengers","screen"],["🎳","The Big Lebowski","screen"],["🚕","The Fifth Element","screen"],["🎩","The Godfather","screen"],["🗺️","The Goonies","screen"],["🛎️","The Grand Budapest Hotel","screen"],["🏹","The Hunger Games","screen"],["🥔","The Martian","screen"],["💊","The Matrix","screen"],["🦋","The Silence Of The Lambs","screen"],["✈️","Top Gun","screen"],["💉","Trainspotting","screen"],["💚","Wicked","screen"],["🧭","World Explorers","history"],["🚲","Bicycle Brands","vehicles"],["🚘","Car Brands","vehicles"],["⚗️","Chemical Elements","science"],["🍸","Cocktails","drink"],["✨","Constellations","nature"],["🦖","Dinosaurs","science"],["🎛️","Electronic Acts 2000s","arts"],["🎛️","Electronic Acts 2010s","arts"],["🎛️","Electronic Acts 2020s","arts"],["🎛️","Electronic Acts 70s","arts"],["🎛️","Electronic Acts 80s","arts"],["🎛️","Electronic Acts 90s","arts"],["⚽","Football Clubs","sport"],["💎","Gemstones","nature"],["🔤","Greek Letters","science"],["🎤","Hip Hop Groups 2000s","arts"],["🎤","Hip Hop Groups 2010s","arts"],["🎤","Hip Hop Groups 2020s","arts"],["🎤","Hip Hop Groups 80s","arts"],["🎤","Hip Hop Groups 90s","arts"],["🍹","Italian Aperitivi","drink"],["🥖","Italian Breads","food"],["🃏","Italian Card Games","culture"],["🎭","Italian Carnival Masks","culture"],["🚗","Italian Cars","vehicles"],["🏯","Italian Castles","places"],["🧀","Italian Cheeses","food"],["💾","Italian Children Games 2000s","culture"],["🧸","Italian Children Games 70s","culture"],["🕹️","Italian Children Games 80s","culture"],["🎮","Italian Children Games 90s","culture"],["🎪","Italian Circus Terms","culture"],["🏙️","Italian Cities","places"],["☕","Italian Coffee Brands","drink"],["👻","Italian Cryptids Legends","culture"],["🥓","Italian Cured Meats","food"],["💃","Italian Dance Styles","culture"],["🪑","Italian Design Objects","culture"],["🍰","Italian Desserts","food"],["🗣️","Italian Dialect Words","culture"],["🪕","Italian Folk Instruments","culture"],["🇮🇹","Italian Football Clubs","sport"],["🍇","Italian Grape Varieties","drink"],["🍨","Italian Icecream Flavors","food"],["💬","Italian Invented Words","culture"],["🏝️","Italian Islands","nature"],["🏞️","Italian Lakes","nature"],["🥃","Italian Liqueurs","drink"],["🏛️","Italian Monuments","places"],["🛵","Italian Motorcycles","vehicles"],["🏔️","Italian Mountains","nature"],["🪙","Italian Old Currencies","culture"],["🔨","Italian Old Jobs","culture"],["🎭","Italian Operas","arts"],["🍝","Italian Pasta Shapes","food"],["🍕","Italian Pizza Types","food"],["🎸","Italian Progressive Rock Bands","arts"],["🍲","Italian Regional Foods","food"],["🗺️","Italian Regions","places"],["🌊","Italian Rivers","nature"],["🐙","Italian Sea Creatures","food"],["🥪","Italian Street Foods","food"],["🚉","Italian Train Stations Classic","places"],["🌋","Italian Volcanoes","nature"],["🍇","Italian Wine Regions","drink"],["🍷","Italian Wines","drink"],["🤘","Metal Bands 2000s","arts"],["🤘","Metal Bands 2010s","arts"],["🤘","Metal Bands 2020s","arts"],["🤘","Metal Bands 70s","arts"],["🤘","Metal Bands 80s","arts"],["🤘","Metal Bands 90s","arts"],["📻","Nato Alphabet","culture"],["🪐","Planets And Moons","nature"],["✨","Pop Groups 2000s","arts"],["✨","Pop Groups 2010s","arts"],["✨","Pop Groups 2020s","arts"],["✨","Pop Groups 60s","arts"],["✨","Pop Groups 70s","arts"],["✨","Pop Groups 80s","arts"],["✨","Pop Groups 90s","arts"],["🧷","Punk Bands 2000s","arts"],["🧷","Punk Bands 2010s","arts"],["🧷","Punk Bands 2020s","arts"],["🧷","Punk Bands 70s","arts"],["🧷","Punk Bands 80s","arts"],["🧷","Punk Bands 90s","arts"],["🎸","Rock Bands 2000s","arts"],["🎸","Rock Bands 2010s","arts"],["🎸","Rock Bands 2020s","arts"],["🎸","Rock Bands 60s","arts"],["🎸","Rock Bands 70s","arts"],["🎸","Rock Bands 80s","arts"],["🎸","Rock Bands 90s","arts"],["🚀","Space Missions","science"],["🌍","World Capitals","places"],["🌊","World Rivers","nature"]];

/* Two belts, opposite directions, each list doubled so the loop has no seam
   at the halfway point the keyframe translates to. */
const half = Math.ceil(HOME_SHELF.length / 2);
const tile = ([icon, label, group]) =>
  `<span class="tile"><em>${icon}</em>${esc(label)}<span>${group}</span></span>`;
$('#belt-a').innerHTML = HOME_SHELF.slice(0, half).map(tile).join('').repeat(2);
$('#belt-b').innerHTML = HOME_SHELF.slice(half).map(tile).join('').repeat(2);

/* Group counts are tallied from the same array the belt is built from, so the
   chips cannot drift out of step with what is on the page. */
const homeTally = new Map();
for (const [, , g] of HOME_SHELF) homeTally.set(g, (homeTally.get(g) ?? 0) + 1);
const GICON = {
  food: '🍝', drink: '🍷', nature: '🏔️', places: '🏛️', culture: '🎭', arts: '🎨',
  screen: '🎬', sport: '⚽', science: '🔬', history: '📜', myth: '⚡', vehicles: '🏎️',
};
$('#group-row').innerHTML = [...homeTally.entries()]
  .sort((a, b) => b[1] - a[1])
  .map(([g, n]) => `<span class="gchip"><i>${GICON[g] ?? '📦'}</i>${g}<b>${n}</b></span>`)
  .join('');

/* ── COUNTERS ───────────────────────────────────────────────── */
const ease = t => 1 - Math.pow(1 - t, 3);
function countUp(el) {
  const target = +el.dataset.count;
  if (reduced || target <= 3) { el.textContent = target.toLocaleString('en'); return; }
  const dur = 1100, t0 = performance.now();
  const step = now => {
    const k = Math.min(1, (now - t0) / dur);
    el.textContent = Math.round(target * ease(k)).toLocaleString('en');
    if (k < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}
const counter = new IntersectionObserver(es => es.forEach(e => {
  if (!e.isIntersecting) return;
  counter.unobserve(e.target);
  countUp(e.target);
}), { threshold: .4 });
$$('[data-count]').forEach(el => counter.observe(el));

/* ── INSTALL LINE ───────────────────────────────────────────── */
/* The whole row is the control, because the thing people want is the string
   and aiming at a small icon to get it is a tax. */
const cmd = $('#cmd');
cmd.onclick = async () => {
  try { await navigator.clipboard.writeText(cmd.querySelector('code').textContent); } catch (e) { /* blocked clipboard: the row simply says nothing */ }
  cmd.classList.add('done');
  setTimeout(() => cmd.classList.remove('done'), 1500);
};


hero(); agreement();
})();

/* ── BOOT ───────────────────────────────────────────────────── */
/* Last, so every page is populated before one of them is shown. */
const [bootPage, bootHeading] = routeOf();
show(bootPage, false);
fitRail();
if (bootHeading) document.getElementById(bootHeading)?.scrollIntoView({ block: 'start' });
if (!/Mac|iPhone|iPad/.test(navigator.platform || '')) $('#kbdhint').textContent = 'Ctrl K';
