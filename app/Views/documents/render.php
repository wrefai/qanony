<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php if (!empty($docStyles)): ?>
<style>
<?= $docStyles ?>
</style>
<?php endif; ?>

<!-- 3-bar document viewer (HTML mode) -->
<div class="rdr-page">

    <!-- Bar 1: dark navy — close | prev/next/counter | title | download -->
    <div class="rdr-bar rdr-bar-1">
        <button type="button" class="rdr-close-btn" id="renderBtnBack" aria-label="إغلاق">
            <i class="bi bi-x-lg"></i>
        </button>
        <div class="rdr-nav-group" id="renderNavGroup" style="display:none;">
            <button type="button" class="rdr-navbtn" id="renderBtnPrev" title="<?= lang('App.prev_match') ?>">
                <i class="bi bi-chevron-right"></i>
            </button>
            <span class="rdr-counter" id="renderMatchCounter">0/0</span>
            <button type="button" class="rdr-navbtn" id="renderBtnNext" title="<?= lang('App.next_match') ?>">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>
        <span class="rdr-title" title="<?= esc($document['title']) ?>"><?= esc($document['title']) ?></span>
        <div class="rdr-bar-actions">
            <a href="<?= site_url('documents/' . $document['id'] . '/download') ?>" class="rdr-btn">
                <i class="bi bi-download"></i>
                <span class="rdr-btn-label"><?= lang('App.download') ?></span>
            </a>
        </div>
    </div>

    <!-- Bar 2: keyword chip bar (shown when highlights exist) -->
    <div class="rdr-bar rdr-bar-2" id="renderKeywordNav" style="display:none;">
        <i class="bi bi-search rdr-bar-icon"></i>
        <div class="rdr-kw-chips" id="renderKwChips"></div>
    </div>

    <!-- Bar 3: meta info -->
    <div class="rdr-bar rdr-bar-3">
        <?php if (!empty($document['document_type'])): ?>
            <span class="rdr-meta-badge"><?= lang('App.type_' . $document['document_type']) ?></span>
        <?php endif; ?>
        <?php if (!empty($document['case_number'])): ?>
            <span><i class="bi bi-hash"></i><?= esc($document['case_number']) ?></span>
        <?php endif; ?>
        <?php if (!empty($document['court_level'])): ?>
            <span><i class="bi bi-bank"></i><?= lang('App.court_' . $document['court_level']) ?></span>
        <?php endif; ?>
        <?php if (!empty($document['file_size'])): ?>
            <span><i class="bi bi-file-earmark"></i><?= number_format($document['file_size'] / 1024, 1) ?> KB</span>
        <?php endif; ?>
    </div>

    <!-- Document content + marker bar -->
    <div class="render-content-wrapper" id="renderContentWrapper">
        <div class="render-doc-content" id="renderDocContent"
             dir="<?= esc($direction) ?>"
             lang="<?= esc($locale === 'ar' ? 'ar' : 'en') ?>">
            <?= $docHtml ?>
        </div>
        <div class="render-marker-bar" id="renderMarkerBar"></div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>
    /* ── Render page: full-screen chrome-free ── */
    .navbar  { display: none !important; }
    footer   { display: none !important; }
    body     { overflow: hidden; margin: 0; padding: 0; }
    main.container-fluid,
    main.container { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }

    /* Page = flex column filling viewport */
    .rdr-page {
        display: flex;
        flex-direction: column;
        height: 100svh;
        overflow: hidden;
    }

    /* ── Shared bar base ── */
    .rdr-bar {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.4rem 0.6rem;
        flex-shrink: 0;
        direction: rtl;
    }

    /* ── Bar 1: dark navy ── */
    .rdr-bar-1 {
        background: #1a2a4a;
        color: #fff;
        min-height: 46px;
        flex-wrap: nowrap;
    }

    .rdr-close-btn {
        background: none;
        border: none;
        color: #fff;
        font-size: 1.15rem;
        padding: 0.2rem 0.38rem;
        line-height: 1;
        cursor: pointer;
        flex-shrink: 0;
        border-radius: 4px;
        order: 10; /* push to LTR-left (RTL end) */
        transition: background 0.15s;
    }
    .rdr-close-btn:hover { background: rgba(255,255,255,0.18); }

    .rdr-nav-group {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        flex-shrink: 0;
    }

    .rdr-navbtn {
        background: rgba(255,255,255,0.15);
        border: none;
        color: #fff;
        border-radius: 4px;
        padding: 0.22rem 0.42rem;
        font-size: 0.88rem;
        cursor: pointer;
        line-height: 1;
        flex-shrink: 0;
        transition: background 0.15s;
    }
    .rdr-navbtn:hover  { background: rgba(255,255,255,0.25); }
    .rdr-navbtn:active { background: rgba(255,255,255,0.35); }

    .rdr-counter {
        font-size: 0.78rem;
        min-width: 40px;
        text-align: center;
        color: rgba(255,255,255,0.88);
        flex-shrink: 0;
    }

    .rdr-title {
        flex: 1;
        font-size: 0.84rem;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-align: right;
        min-width: 0;
        color: #fff;
    }

    .rdr-bar-actions {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        flex-shrink: 0;
    }

    .rdr-btn {
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.3);
        color: #fff !important;
        border-radius: 4px;
        padding: 0.22rem 0.55rem;
        font-size: 0.8rem;
        cursor: pointer;
        line-height: 1.4;
        text-decoration: none !important;
        white-space: nowrap;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        transition: background 0.15s;
    }
    .rdr-btn:hover  { background: rgba(255,255,255,0.25); color: #fff !important; }
    .rdr-btn:active { background: rgba(255,255,255,0.35); }

    /* ── Bar 2: keyword nav (slightly lighter blue) ── */
    .rdr-bar-2 {
        background: #253a60;
        color: #fff;
        min-height: 40px;
        border-top: 1px solid rgba(255,255,255,0.08);
    }

    .rdr-bar-icon {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.7);
        flex-shrink: 0;
    }

    /* ── Keyword chip bar ── */
    .rdr-kw-chips {
        display: flex;
        gap: 0.35rem;
        flex: 1;
        overflow-x: auto;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
        align-items: center;
        min-width: 0;
    }
    .rdr-kw-chips::-webkit-scrollbar { display: none; }
    .rdr-kw-chip {
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.28);
        color: #fff;
        border-radius: 99px;
        padding: 0.15rem 0.7rem;
        font-size: 0.78rem;
        cursor: pointer;
        white-space: nowrap;
        flex-shrink: 0;
        transition: background 0.15s;
        line-height: 1.5;
        font-family: inherit;
    }
    .rdr-kw-chip:hover  { background: rgba(255,255,255,0.2); }
    .rdr-kw-chip.active { background: rgba(255,255,255,0.3); border-color: rgba(255,255,255,0.9); font-weight: 600; }
    .rdr-kw-chip-0 { border-color: #d4a700; color: #ffe082; }
    .rdr-kw-chip-1 { border-color: #fd7e14; color: #ffb76b; }
    .rdr-kw-chip-2 { border-color: #adb5bd; color: #dee2e6; }
    .rdr-kw-chip-0.active, .rdr-kw-chip-1.active, .rdr-kw-chip-2.active { color: #fff; }
    .rdr-kw-count { opacity: 0.75; font-size: 0.85em; }

    /* ── Bar 3: meta info ── */
    .rdr-bar-3 {
        background: var(--bs-tertiary-bg);
        color: var(--bs-secondary-color);
        font-size: 0.72rem;
        min-height: 28px;
        flex-wrap: wrap;
        gap: 0.6rem 1rem;
        border-bottom: 1px solid var(--bs-border-color);
        padding: 0.22rem 0.7rem;
        direction: rtl;
    }
    .rdr-bar-3 span {
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
    }
    .rdr-meta-badge {
        background: #1a2a4a;
        color: #fff !important;
        border-radius: 3px;
        padding: 0.1rem 0.45rem;
        font-size: 0.7rem;
        font-weight: 600;
    }

    /* ── Content wrapper: fills remaining space ── */
    .render-content-wrapper {
        flex: 1;
        display: flex;
        direction: ltr;
        overflow: hidden;
        min-height: 0;
    }

    /* Marker tick colors */
    .marker-tick.marker-color-0     { background-color: #d4a700; }
    .marker-tick.marker-color-1     { background-color: #fd7e14; }
    .marker-tick.marker-color-2     { background-color: #6c757d; }
    .marker-tick.marker-color-indoc { background-color: #0d6efd; }

    /* ── Document content ── */
    .render-doc-content {
        flex: 1;
        min-width: 0;
        overflow-y: auto;
        overflow-x: auto;
        padding: 1.5rem 2rem;
        font-family: Arial, 'Noto Naskh Arabic', 'Cairo', 'Segoe UI', Tahoma, sans-serif;
        font-size: 18px;
        line-height: 2;
        background: #ffffff !important;
        color: #212529 !important;
    }

    /* Force uniform 18px on every element inside the document —
       overrides any inline font-size that PhpWord emits (e.g. <span style="font-size:14pt">) */
    .render-doc-content *,
    .render-doc-content p,
    .render-doc-content span,
    .render-doc-content div,
    .render-doc-content td,
    .render-doc-content th,
    .render-doc-content li {
        font-size: 18px !important;
    }

    /* ── Mobile tweaks ── */
    @media (max-width: 991.98px) {
        .rdr-bar-1 {
            padding: 0.35rem 0.5rem;
            min-height: 42px;
        }
        .rdr-title { font-size: 0.78rem; }
        .rdr-btn   { font-size: 0.75rem; padding: 0.18rem 0.45rem; }
        .rdr-btn-label { display: none; } /* icon-only on small screens */
        .render-doc-content { padding: 0.8rem 1rem; }

        /* Bigger marker bar — easier to tap */
        .render-marker-bar {
            width: 26px !important;
        }
        .render-marker-bar .marker-tick {
            height: 6px !important;
            border-radius: 3px;
            min-height: 6px;
        }
        .render-marker-bar .marker-tick:hover,
        .render-marker-bar .marker-tick:active {
            height: 10px !important;
        }
    }

    /* ── Arabic RTL Document Rendering ── */
    .render-doc-content .rendered-doc {
        direction: rtl;
        text-align: right;
        unicode-bidi: embed;
        word-break: break-word;
        overflow-wrap: break-word;
    }
    .render-doc-content .rendered-doc p,
    .render-doc-content .rendered-doc div:not(.rendered-doc) {
        direction: rtl;
        text-align: right;
        unicode-bidi: embed;
        line-height: 2;
    }
    .render-doc-content .rendered-doc p  { margin-bottom: 0.6em; }
    .render-doc-content .rendered-doc span { unicode-bidi: normal !important; }

    .render-doc-content .rendered-doc table {
        width: 100% !important;
        border-collapse: collapse;
        direction: rtl;
        margin-bottom: 1em;
    }
    .render-doc-content .rendered-doc td,
    .render-doc-content .rendered-doc th {
        padding: 6px 10px;
        vertical-align: top;
        text-align: right;
        direction: rtl;
        unicode-bidi: embed;
        border: 1px solid var(--bs-border-color);
    }
    .render-doc-content .rendered-doc th {
        background-color: var(--bs-tertiary-bg);
        font-weight: 600;
    }
    .render-doc-content .rendered-doc ul,
    .render-doc-content .rendered-doc ol {
        padding-right: 2em;
        padding-left: 0;
        margin-bottom: 0.6em;
    }
    .render-doc-content .rendered-doc li { margin-bottom: 0.3em; line-height: 2; }
    .render-doc-content .rendered-doc img { max-width: 100%; max-height: 180px; height: auto; width: auto; }
    .render-doc-content .rendered-doc p:empty::after { content: "\00a0"; }

    .render-doc-content table    { width: 100% !important; border-collapse: collapse; }
    .render-doc-content td,
    .render-doc-content th       { padding: 4px 8px; vertical-align: top; }
    .render-doc-content p        { margin-bottom: 0.5rem; }

    /* ── Marker bar ── */
    .render-marker-bar {
        width: 20px;
        flex-shrink: 0;
        background: var(--bs-tertiary-bg);
        border-inline-start: 1px solid var(--bs-border-color);
        position: relative;
        cursor: pointer;
        overflow: hidden;
    }
    .render-marker-bar .marker-tick {
        position: absolute;
        right: 2px;
        left: 2px;
        height: 4px;
        border-radius: 2px;
        cursor: pointer;
        opacity: 0.8;
        transition: opacity 0.1s, height 0.1s;
    }
    .render-marker-bar .marker-tick:hover {
        opacity: 1;
        height: 7px;
        right: 1px;
        left: 1px;
    }

    /* Scrollbar */
    .render-doc-content::-webkit-scrollbar       { width: 8px; }
    .render-doc-content::-webkit-scrollbar-track  { background: var(--bs-tertiary-bg); }
    .render-doc-content::-webkit-scrollbar-thumb  { background: var(--bs-secondary-bg); border-radius: 4px; }
    .render-doc-content::-webkit-scrollbar-thumb:hover { background: var(--bs-secondary); }

    /* Highlight classes */
    .render-doc-content .search-highlight {
        padding: 0 2px;
        border-radius: 2px;
        scroll-margin-top: 80px;
    }
    .render-doc-content .search-highlight-0     { background-color: #fff3cd; color: #000; }
    .render-doc-content .search-highlight-1     { background-color: #fd7e14; color: #fff; }
    .render-doc-content .search-highlight-2     { background-color: #adb5bd; color: #000; }
    .render-doc-content .search-highlight-indoc { background-color: #e9ecef; color: #000; }
    .render-doc-content .search-highlight.search-highlight-active {
        box-shadow: 0 0 0 2px #000;
        position: relative;
        z-index: 1;
    }

    [data-bs-theme="dark"] .render-doc-content { color: #212529 !important; background: #fff !important; }
</style>

<script>
(function() {
    'use strict';

    var Q = window.Qanony;
    var query = <?= json_encode($query) ?>;
    var indocQuery = <?= json_encode($indocQuery) ?>;
    var docContent  = document.getElementById('renderDocContent');
    var markerBar   = document.getElementById('renderMarkerBar');
    var keywordNav  = document.getElementById('renderKeywordNav');
    var navGroup    = document.getElementById('renderNavGroup');
    var kwChipsContainer = document.getElementById('renderKwChips');
    var matchCounter  = document.getElementById('renderMatchCounter');
    var btnPrev = document.getElementById('renderBtnPrev');
    var btnNext = document.getElementById('renderBtnNext');

    var HIGHLIGHT_COLORS = ['search-highlight-0', 'search-highlight-1', 'search-highlight-2'];
    var highlights = [];
    var highlightsByWord = {};
    var currentMatchIdx = -1;
    var activeWord = null;

    // Arabic normalization for regex patterns
    function arabicNormalize(pattern) {
        // 1. Strip diacritics / combining marks from the search pattern
        pattern = pattern.replace(/[\u0610-\u061A\u064B-\u065F\u0670\u06D6-\u06DC\u06DF-\u06E4\u06E7\u06E8\u06EA-\u06ED]/g, '');
        // 2. Normalise common Arabic letter variants
        pattern = pattern.replace(/[\u0623\u0625\u0622\u0627]/g, '[\u0623\u0625\u0622\u0627]'); // alef forms
        pattern = pattern.replace(/\u0629/g, '[\u0629\u0647]');                                  // ta marbuta / ha
        pattern = pattern.replace(/\u0649/g, '[\u0649\u064A]');                                  // alef maqsura / ya
        // 3. Allow optional diacritics in the TARGET TEXT after each Arabic char/class.
        var DIAC = '[\u0610-\u061A\u064B-\u065F\u0670\u06D6-\u06DC\u06DF-\u06E4\u06E7\u06E8\u06EA-\u06ED\u200C\u200D]*';
        var result = '';
        var inBracket = false;
        for (var i = 0; i < pattern.length; i++) {
            var c = pattern[i];
            if (c === '[') { inBracket = true;  result += c; continue; }
            if (c === ']') { inBracket = false; result += c + DIAC; continue; }
            var code = pattern.charCodeAt(i);
            var isArabic = (code >= 0x0600 && code <= 0x06FF);
            if (!inBracket && isArabic) {
                result += c + DIAC;
            } else {
                result += c;
            }
        }
        return result;
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escAttr(str) {
        if (!str) return '';
        return str.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function parseSearchTerms(q) {
        if (!q) return [];
        var terms = [];
        var len = q.length;
        var i = 0;
        while (i < len) {
            var ch = q[i];
            if (/\s/.test(ch)) { i++; continue; }
            if (ch === '"' || ch === '\u201C' || ch === '\u201D') {
                i++;
                var phrase = '';
                while (i < len) {
                    var c = q[i];
                    if (c === '"' || c === '\u201C' || c === '\u201D') { i++; break; }
                    phrase += c; i++;
                }
                phrase = phrase.trim();
                if (phrase.length > 0) terms.push(phrase);
            } else {
                var word = '';
                while (i < len) {
                    var c2 = q[i];
                    if (/\s/.test(c2) || c2 === '"' || c2 === '\u201C' || c2 === '\u201D') break;
                    word += c2; i++;
                }
                word = word.trim();
                if (word.length > 0) terms.push(word);
            }
        }
        return terms;
    }

    function highlightHtml(html, mainWords, indocWords) {
        mainWords  = mainWords  || [];
        indocWords = indocWords || [];
        if (mainWords.length === 0 && indocWords.length === 0) return html;

        function dedup(arr) {
            var seen = {}, out = [];
            for (var i = 0; i < arr.length; i++) {
                var lw = arr[i].toLowerCase();
                if (!seen[lw]) { seen[lw] = true; out.push(arr[i]); }
            }
            return out;
        }
        var uniqueMain  = dedup(mainWords);
        var uniqueIndoc = dedup(indocWords);

        var TS = '\x00HL_S_', TM = '\x00HL_M\x00', TE = '\x00HL_E\x00';
        var TWS = '\x00HL_W_', TWE = '\x00HL_WE\x00';

        for (var i = 0; i < uniqueMain.length; i++) {
            var w = uniqueMain[i];
            var escaped   = w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            var normalized = arabicNormalize(escaped).replace(/\s+/g, '\\s+');
            var colorIdx  = i % HIGHLIGHT_COLORS.length;
            var regex = new RegExp('(' + normalized + ')(?![^<]*>)', 'gi');
            html = html.replace(regex, TS + colorIdx + TM + TWS + encodeURIComponent(w) + TWE + '$1' + TE);
        }
        for (var j = 0; j < uniqueIndoc.length; j++) {
            var wi = uniqueIndoc[j];
            var escapedI   = wi.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            var normalizedI = arabicNormalize(escapedI).replace(/\s+/g, '\\s+');
            var regex2 = new RegExp('(' + normalizedI + ')(?![^<]*>)', 'gi');
            html = html.replace(regex2, TS + 'indoc' + TM + TWS + encodeURIComponent(wi) + TWE + '$1' + TE);
        }

        var tokenRegex = /\x00HL_S_(.*?)\x00HL_M\x00\x00HL_W_(.*?)\x00HL_WE\x00([\s\S]*?)\x00HL_E\x00/g;
        html = html.replace(tokenRegex, function(match, colorKey, encodedWord, inner) {
            var word = decodeURIComponent(encodedWord);
            var cssClass = colorKey === 'indoc' ? 'search-highlight-indoc' : HIGHLIGHT_COLORS[parseInt(colorKey)];
            return '<mark class="search-highlight ' + cssClass + '" data-word="' + escAttr(word) + '">' + inner + '</mark>';
        });
        return html;
    }

    function getNavigableHighlights() {
        if (activeWord) return highlightsByWord[activeWord] || [];
        return highlights;
    }

    function updateMatchCounter() {
        var nav = getNavigableHighlights();
        matchCounter.textContent = nav.length === 0 ? '0/0' : (currentMatchIdx + 1) + '/' + nav.length;
    }

    function goToMatch(idx) {
        var navHL = getNavigableHighlights();
        if (navHL.length === 0) return;
        if (currentMatchIdx >= 0 && currentMatchIdx < navHL.length)
            navHL[currentMatchIdx].classList.remove('search-highlight-active');
        if (idx < 0) idx = navHL.length - 1;
        if (idx >= navHL.length) idx = 0;
        currentMatchIdx = idx;
        navHL[idx].classList.add('search-highlight-active');
        var containerRect = docContent.getBoundingClientRect();
        var elRect        = navHL[idx].getBoundingClientRect();
        var scrollTop     = docContent.scrollTop;
        var elTop         = elRect.top - containerRect.top + scrollTop;
        docContent.scrollTo({ top: Math.max(0, elTop - containerRect.height / 2), behavior: 'smooth' });
        updateMatchCounter();
    }

    function resetKwChips() {
        if (!kwChipsContainer) return;
        kwChipsContainer.querySelectorAll('.rdr-kw-chip').forEach(function(c) { c.classList.remove('active'); });
        var firstChip = kwChipsContainer.querySelector('.rdr-kw-chip');
        if (firstChip) firstChip.classList.add('active');
        activeWord = null;
    }

    function buildKeywordChips(words) {
        if (!words || words.length === 0 || !kwChipsContainer) {
            navGroup.style.display   = 'none';
            keywordNav.style.display = 'none';
            return;
        }
        navGroup.style.display   = 'flex';
        keywordNav.style.display = 'flex';

        var allLabel = (Q && Q.locale === 'ar') ? '\u0627\u0644\u0643\u0644' : 'All';
        var html = '<button class="rdr-kw-chip active" data-word="">' + allLabel + ' <span class="rdr-kw-count">(' + highlights.length + ')</span></button>';
        var seen = {}, uniqueWords = [];
        for (var u = 0; u < words.length; u++) {
            var lw = words[u].toLowerCase();
            if (!seen[lw]) { seen[lw] = true; uniqueWords.push(words[u]); }
        }
        for (var i = 0; i < uniqueWords.length; i++) {
            var kw    = uniqueWords[i];
            var count = highlightsByWord[kw] ? highlightsByWord[kw].length : 0;
            html += '<button class="rdr-kw-chip rdr-kw-chip-' + (i % 3) + '" data-word="' + escAttr(kw) + '">' + escHtml(kw) + ' <span class="rdr-kw-count">(' + count + ')</span></button>';
        }
        kwChipsContainer.innerHTML = html;

        kwChipsContainer.querySelectorAll('.rdr-kw-chip').forEach(function(chip) {
            chip.addEventListener('click', function() {
                var word = this.getAttribute('data-word');
                kwChipsContainer.querySelectorAll('.rdr-kw-chip').forEach(function(c) { c.classList.remove('active'); });
                this.classList.add('active');
                var oldNav = getNavigableHighlights();
                if (currentMatchIdx >= 0 && currentMatchIdx < oldNav.length)
                    oldNav[currentMatchIdx].classList.remove('search-highlight-active');
                activeWord = word || null;
                currentMatchIdx = -1;
                updateMatchCounter();
                var nav = getNavigableHighlights();
                if (nav.length > 0) goToMatch(0);
            });
        });
    }

    // ── Marker Bar ──
    function buildMarkerBar() {
        markerBar.innerHTML = '';
        if (highlights.length === 0) return;
        requestAnimationFrame(function() {
            var scrollH = docContent.scrollHeight;
            var barH    = markerBar.clientHeight || markerBar.getBoundingClientRect().height;
            if (window.innerWidth >= 992 && barH <= 0) {
                barH = docContent.clientHeight || docContent.getBoundingClientRect().height;
            }
            if (scrollH <= 0 || barH <= 0) return;
            var fragment = document.createDocumentFragment();
            for (var i = 0; i < highlights.length; i++) {
                var el = highlights[i];
                var elOffsetTop = 0, node = el;
                while (node && node !== docContent) {
                    elOffsetTop += node.offsetTop || 0;
                    node = node.offsetParent;
                }
                var pctTop = (elOffsetTop / scrollH) * barH;
                var colorClass = '';
                var classes = el.className.split(/\s+/);
                for (var c = 0; c < classes.length; c++) {
                    if (classes[c] === 'search-highlight-indoc') { colorClass = 'marker-color-indoc'; break; }
                    if (classes[c].indexOf('search-highlight-') === 0 && classes[c] !== 'search-highlight' && classes[c] !== 'search-highlight-active') {
                        var num = classes[c].replace('search-highlight-', '');
                        if (!isNaN(parseInt(num))) { colorClass = 'marker-color-' + num; break; }
                    }
                }
                var tick = document.createElement('div');
                tick.className = 'marker-tick ' + colorClass;
                tick.style.top = Math.round(pctTop) + 'px';
                tick.setAttribute('data-idx', String(i));
                fragment.appendChild(tick);
            }
            markerBar.appendChild(fragment);
        });
    }

    markerBar.addEventListener('click', function(e) {
        var tick = e.target.closest('.marker-tick');
        if (!tick) return;
        var idx = parseInt(tick.getAttribute('data-idx'));
        if (!isNaN(idx) && idx >= 0 && idx < highlights.length) {
            activeWord = null;
            resetKwChips();
            goToMatch(idx);
        }
    });

    var markerResizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(markerResizeTimer);
        markerResizeTimer = setTimeout(buildMarkerBar, 200);
    });

    function applyHighlights() {
        var mainWords  = query     ? parseSearchTerms(query)     : [];
        var indocWords = indocQuery ? parseSearchTerms(indocQuery) : [];
        var allWords   = mainWords.concat(indocWords);
        if (allWords.length === 0) return;

        docContent.innerHTML = highlightHtml(docContent.innerHTML, mainWords, indocWords);

        highlightsByWord = {};
        highlights = Array.from(docContent.querySelectorAll('.search-highlight'));
        for (var h = 0; h < highlights.length; h++) {
            var word = highlights[h].getAttribute('data-word') || '';
            if (!highlightsByWord[word]) highlightsByWord[word] = [];
            highlightsByWord[word].push(highlights[h]);
        }

        buildKeywordChips(allWords);
        buildMarkerBar();
        if (highlights.length > 0) goToMatch(0);
    }

    // Event bindings
    btnPrev.addEventListener('click', function() {
        var nav = getNavigableHighlights();
        if (nav.length > 0) goToMatch(currentMatchIdx - 1);
    });
    btnNext.addEventListener('click', function() {
        var nav = getNavigableHighlights();
        if (nav.length > 0) goToMatch(currentMatchIdx + 1);
    });

    // Keyboard: F3 / Shift+F3 / Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F3' || (e.ctrlKey && e.key === 'g')) {
            e.preventDefault();
            var nav = getNavigableHighlights();
            if (nav.length > 0) goToMatch(currentMatchIdx + 1);
        }
        if ((e.shiftKey && e.key === 'F3') || (e.ctrlKey && e.shiftKey && e.key === 'G')) {
            e.preventDefault();
            var navB = getNavigableHighlights();
            if (navB.length > 0) goToMatch(currentMatchIdx - 1);
        }
        if (e.key === 'Escape') {
            window.close();
            setTimeout(function() {
                if (window.history.length > 1) window.history.back();
                else window.location.href = (Q && Q.siteUrl ? Q.siteUrl : '') + '/search';
            }, 100);
        }
    });

    // Back/Close button
    document.getElementById('renderBtnBack').addEventListener('click', function(e) {
        e.preventDefault();
        window.close();
        setTimeout(function() {
            if (window.history.length > 1) window.history.back();
            else window.location.href = (Q && Q.siteUrl ? Q.siteUrl : '') + '/search';
        }, 100);
    });

    // Swipe right to close (mobile)
    var touchStartX = 0;
    document.addEventListener('touchstart', function(e) { touchStartX = e.touches[0].clientX; }, { passive: true });
    document.addEventListener('touchend', function(e) {
        if (e.changedTouches[0].clientX - touchStartX > 80) {
            window.close();
            setTimeout(function() {
                if (window.history.length > 1) window.history.back();
                else window.location.href = (Q && Q.siteUrl ? Q.siteUrl : '') + '/search';
            }, 100);
        }
    }, { passive: true });

    // Run after fonts are ready so offsetTop positions are stable
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(applyHighlights).catch(applyHighlights);
    } else {
        applyHighlights();
    }
})();
</script>
<?= $this->endSection() ?>
