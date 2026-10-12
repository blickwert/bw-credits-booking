/* assets/lazy-views.js — fills in the placeholders from includes/lazy-views.php */

/*
 * User- and time-dependent shortcodes (free spots, book button, balance, …)
 * arrive on the page as empty <span class="bw-lazy-view"> placeholders, so a
 * page cache never freezes them. All placeholders of a page are sent in a
 * single POST; the answer comes straight from the server and is never cached.
 * data-bw-view/-sig are signed server-side — the server discards any payload
 * that was altered, so nothing here can change what gets requested.
 */

const LAZY_CHUNK = 30; // max items per request, matches the server limit

function lazyRequest(items) {
  const cfg = getCfg();
  const send = function (withNonce) {
    const headers = { "Content-Type": "application/json" };
    if (withNonce && cfg.nonce) headers["X-WP-Nonce"] = cfg.nonce;
    return fetch(cfg.restUrl + "views", {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: headers,
      body: JSON.stringify({ items: items })
    });
  };

  return send(true).then(async function (res) {
    if (res.status !== 401 && res.status !== 403) return res;
    // Expired nonce: a logged-in user gets a fresh one …
    if (await refreshNonce()) return send(true);
    // … a guest on an old cached page simply goes without (guests need none).
    cfg.nonce = "";
    return send(false);
  });
}

async function hydrateChunk(groups) {
  const items = groups.map(function (g, i) { return { id: "v" + i, p: g.payload, s: g.sig }; });

  let views = null;
  try {
    const res = await lazyRequest(items);
    const json = await res.json().catch(function () { return {}; });
    if (res.ok && json && json.views) views = json.views;
  } catch (e) {
    views = null;
  }

  groups.forEach(function (g, i) {
    const result = views && views["v" + i];
    g.nodes.forEach(function (node) {
      node.removeAttribute("data-bw-loading");
      if (!result || !result.ok) {
        node.dataset.bwError = "1";
        return;
      }
      node.innerHTML = result.html;
      node.removeAttribute("aria-busy");
    });
  });
}

/** Loads all pending placeholders below root (default: the whole page). */
async function hydrateViews(root) {
  const nodes = qsa('.bw-lazy-view[aria-busy="true"]:not([data-bw-loading]):not([data-bw-error])', root);
  if (!nodes.length) return;

  // Identical placeholders (e.g. the same balance twice) are requested only once.
  const bySig = {};
  const groups = [];
  nodes.forEach(function (node) {
    const sig = node.dataset.bwSig || "";
    const payload = node.dataset.bwView || "";
    if (!sig || !payload) return;

    node.dataset.bwLoading = "1";
    if (!bySig[sig]) {
      bySig[sig] = { payload: payload, sig: sig, nodes: [] };
      groups.push(bySig[sig]);
    }
    bySig[sig].nodes.push(node);
  });

  for (let i = 0; i < groups.length; i += LAZY_CHUNK) {
    await hydrateChunk(groups.slice(i, i + LAZY_CHUNK));
  }

  document.dispatchEvent(new CustomEvent("bw:views-loaded", { detail: { root: root || document } }));
}

// Content inserted later (popups, tabs, filter results) is picked up as well.
let lazyTimer = null;
function watchLazyViews() {
  if (typeof MutationObserver === "undefined") return;

  new MutationObserver(function () {
    clearTimeout(lazyTimer);
    lazyTimer = setTimeout(function () { hydrateViews(); }, 60);
  }).observe(document.body, { childList: true, subtree: true });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", function () { hydrateViews(); watchLazyViews(); });
} else {
  hydrateViews();
  watchLazyViews();
}
