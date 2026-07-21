// KINAS AUTOMOBILE — Compare tool
// Overlays a "Compare" checkbox on each result card (the card itself is a
// single <a> wrapping everything, so the checkbox stops click propagation
// to avoid triggering navigation) and shows a floating bar once 2+ cars
// are selected. Selection persists in localStorage across pagination/filter
// changes so a user can browse multiple pages before comparing.
(function () {
    const STORAGE_KEY = 'kinasCompareCarIds';
    const MAX_COMPARE = 4;

    function getSelected() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            const ids = raw ? JSON.parse(raw) : [];
            return Array.isArray(ids) ? ids : [];
        } catch (e) {
            return [];
        }
    }

    function setSelected(ids) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
        } catch (e) { /* storage unavailable — degrade silently */ }
    }

    function extractId(href) {
        const m = href.match(/[?&]id=(\d+)/);
        return m ? parseInt(m[1], 10) : null;
    }

    function renderBar() {
        let bar = document.getElementById('compareBar');
        const selected = getSelected();

        if (selected.length < 2) {
            if (bar) bar.remove();
            return;
        }

        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'compareBar';
            bar.style.cssText = 'position:fixed;left:0;right:0;bottom:0;background:#151515;color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:center;gap:16px;z-index:999;box-shadow:0 -4px 16px rgba(0,0,0,.2);';
            document.body.appendChild(bar);
        }

        bar.innerHTML =
            '<span style="font-size:14px;">' + selected.length + ' vehicle' + (selected.length === 1 ? '' : 's') + ' selected' +
            (selected.length >= MAX_COMPARE ? ' (max ' + MAX_COMPARE + ')' : '') + '</span>' +
            '<a href="compare.php?ids=' + selected.join(',') + '" style="background:#C6A43F;color:#151515;padding:8px 20px;border-radius:40px;font-weight:600;font-size:13px;text-decoration:none;">Compare Now</a>' +
            '<button id="compareClearBtn" style="background:none;border:1px solid #555;color:#ccc;padding:8px 16px;border-radius:40px;font-size:13px;cursor:pointer;">Clear</button>';

        document.getElementById('compareClearBtn').addEventListener('click', function () {
            setSelected([]);
            document.querySelectorAll('.compare-checkbox').forEach(cb => cb.checked = false);
            renderBar();
        });
    }

    function injectCheckboxes() {
        const selected = getSelected();
        document.querySelectorAll('.je-listings-grid .je-card').forEach(card => {
            const id = extractId(card.getAttribute('href') || '');
            if (!id || card.querySelector('.compare-checkbox-wrap')) return;

            const wrap = document.createElement('label');
            wrap.className = 'compare-checkbox-wrap';
            wrap.style.cssText = 'position:absolute;top:12px;left:12px;z-index:5;background:rgba(255,255,255,.92);border-radius:6px;padding:5px 9px;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#151515;cursor:pointer;';

            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = 'compare-checkbox';
            cb.style.cssText = 'margin:0;cursor:pointer;';
            cb.checked = selected.includes(id);

            cb.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                let ids = getSelected();
                if (cb.checked) {
                    ids = ids.filter(x => x !== id); // uncheck
                    cb.checked = false;
                } else {
                    if (ids.length >= MAX_COMPARE) {
                        cb.checked = false;
                        alert('You can compare up to ' + MAX_COMPARE + ' vehicles at a time.');
                        return;
                    }
                    ids.push(id);
                    cb.checked = true;
                }
                setSelected(ids);
                renderBar();
            });

            wrap.appendChild(cb);
            wrap.appendChild(document.createTextNode('Compare'));

            // The card element itself needs relative positioning for the
            // absolute-positioned checkbox to anchor correctly.
            const imgWrap = card.querySelector('.je-card-img');
            if (imgWrap) {
                imgWrap.style.position = 'relative';
                imgWrap.appendChild(wrap);
            } else {
                card.style.position = 'relative';
                card.appendChild(wrap);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        injectCheckboxes();
        renderBar();
    });
})();
